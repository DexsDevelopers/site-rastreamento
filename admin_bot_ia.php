<?php
/**
 * Painel de Configuração da IA do Bot
 * Gerencia conhecimento, aprendizado e configurações
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

requireLogin();

// Verificar tabelas
try {
    $pdo->query("SELECT 1 FROM bot_ia_settings LIMIT 1");
} catch (PDOException $e) {
    header('Location: setup_bot_ia.php');
    exit;
}

// Funções auxiliares
function getIASetting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM bot_ia_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => 'Ação não reconhecida'];
    
    try {
        switch ($_POST['action']) {
            case 'get_knowledge':
                $search = sanitizeInput($_POST['search'] ?? '');
                $categoria = sanitizeInput($_POST['categoria'] ?? '');
                
                $sql = "SELECT * FROM bot_ia_knowledge WHERE 1=1";
                $params = [];
                
                if ($search) {
                    $sql .= " AND (pergunta LIKE ? OR resposta LIKE ? OR palavras_chave LIKE ?)";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                }
                
                if ($categoria) {
                    $sql .= " AND categoria = ?";
                    $params[] = $categoria;
                }
                
                $sql .= " ORDER BY prioridade DESC, uso_count DESC";
                
                $knowledge = fetchData($pdo, $sql, $params);
                $response = ['success' => true, 'data' => $knowledge];
                break;
                
            case 'save_knowledge':
                $id = (int)($_POST['id'] ?? 0);
                $pergunta = sanitizeInput($_POST['pergunta'] ?? '');
                $resposta = $_POST['resposta'] ?? '';
                $categoria = sanitizeInput($_POST['categoria'] ?? 'geral');
                $palavrasChave = sanitizeInput($_POST['palavras_chave'] ?? '');
                $prioridade = (int)($_POST['prioridade'] ?? 50);
                $ativo = isset($_POST['ativo']) ? 1 : 0;
                
                if (empty($pergunta) || empty($resposta)) {
                    $response = ['success' => false, 'message' => 'Pergunta e resposta são obrigatórios'];
                    break;
                }
                
                if ($id > 0) {
                    executeQuery($pdo, 
                        "UPDATE bot_ia_knowledge SET pergunta = ?, resposta = ?, categoria = ?, palavras_chave = ?, prioridade = ?, ativo = ? WHERE id = ?",
                        [$pergunta, $resposta, $categoria, $palavrasChave, $prioridade, $ativo, $id]
                    );
                    $response = ['success' => true, 'message' => 'Conhecimento atualizado!'];
                } else {
                    executeQuery($pdo,
                        "INSERT INTO bot_ia_knowledge (pergunta, resposta, categoria, palavras_chave, prioridade, ativo) VALUES (?, ?, ?, ?, ?, ?)",
                        [$pergunta, $resposta, $categoria, $palavrasChave, $prioridade, $ativo]
                    );
                    $response = ['success' => true, 'message' => 'Conhecimento adicionado!', 'id' => $pdo->lastInsertId()];
                }
                break;
                
            case 'delete_knowledge':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    executeQuery($pdo, "DELETE FROM bot_ia_knowledge WHERE id = ?", [$id]);
                    $response = ['success' => true, 'message' => 'Conhecimento excluído'];
                }
                break;
                
            case 'get_feedback':
                $status = $_POST['status'] ?? 'all';
                
                $sql = "SELECT * FROM bot_ia_feedback WHERE 1=1";
                $params = [];
                
                if ($status === 'pending') {
                    $sql .= " AND aprovado = 0";
                } elseif ($status === 'approved') {
                    $sql .= " AND aprovado = 1";
                }
                
                $sql .= " ORDER BY criado_em DESC LIMIT 100";
                
                $feedback = fetchData($pdo, $sql, $params);
                $response = ['success' => true, 'data' => $feedback];
                break;
                
            case 'approve_feedback':
                $id = (int)($_POST['id'] ?? 0);
                $correcao = $_POST['correcao'] ?? '';
                $salvarConhecimento = isset($_POST['salvar_conhecimento']);
                
                if ($id > 0) {
                    executeQuery($pdo, "UPDATE bot_ia_feedback SET correcao = ?, aprovado = 1, processado = 1 WHERE id = ?", [$correcao, $id]);
                    
                    if ($salvarConhecimento && !empty($correcao)) {
                        $feedback = fetchOne($pdo, "SELECT pergunta_original FROM bot_ia_feedback WHERE id = ?", [$id]);
                        if ($feedback) {
                            executeQuery($pdo,
                                "INSERT INTO bot_ia_knowledge (pergunta, resposta, categoria, prioridade, criado_por) VALUES (?, ?, 'aprendizado', 60, 'feedback')",
                                [$feedback['pergunta_original'], $correcao]
                            );
                        }
                    }
                    
                    $response = ['success' => true, 'message' => 'Feedback processado!'];
                }
                break;
                
            case 'delete_feedback':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    executeQuery($pdo, "DELETE FROM bot_ia_feedback WHERE id = ?", [$id]);
                    $response = ['success' => true, 'message' => 'Feedback excluído'];
                }
                break;
                
            case 'get_settings':
                $settings = fetchData($pdo, "SELECT * FROM bot_ia_settings ORDER BY setting_key");
                $response = ['success' => true, 'data' => $settings];
                break;
                
            case 'save_settings':
                $settings = $_POST['settings'] ?? [];
                foreach ($settings as $key => $value) {
                    executeQuery($pdo, 
                        "INSERT INTO bot_ia_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                        [$key, $value, $value]
                    );
                }
                $response = ['success' => true, 'message' => 'Configurações salvas!'];
                break;
                
            case 'get_stats':
                $stats = [
                    'knowledge_total' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_knowledge")['c'] ?? 0,
                    'knowledge_active' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_knowledge WHERE ativo = 1")['c'] ?? 0,
                    'feedback_pending' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_feedback WHERE aprovado = 0")['c'] ?? 0,
                    'conversations_today' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_conversations WHERE DATE(created_at) = CURDATE()")['c'] ?? 0,
                    'unique_users' => fetchOne($pdo, "SELECT COUNT(DISTINCT phone_number) as c FROM bot_ia_conversations")['c'] ?? 0
                ];
                $response = ['success' => true, 'data' => $stats];
                break;
                
            case 'test_ia':
                $message = sanitizeInput($_POST['message'] ?? '');
                if (empty($message)) {
                    $response = ['success' => false, 'message' => 'Mensagem vazia'];
                    break;
                }
                
                // Chamar API interna
                $apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api_bot_ia.php';
                $token = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');
                
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'action' => 'chat',
                    'message' => $message,
                    'phone' => 'test_admin'
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'x-api-token: ' . $token
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $result = curl_exec($ch);
                curl_close($ch);
                
                $data = json_decode($result, true);
                $response = ['success' => true, 'data' => $data];
                break;
                
            case 'get_categories':
                $categories = fetchData($pdo, "SELECT DISTINCT categoria FROM bot_ia_knowledge ORDER BY categoria");
                $response = ['success' => true, 'data' => array_column($categories, 'categoria')];
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Estatísticas
$stats = [
    'knowledge_total' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_knowledge")['c'] ?? 0,
    'feedback_pending' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_feedback WHERE aprovado = 0")['c'] ?? 0
];

$iaEnabled = getIASetting($pdo, 'ia_enabled', '1') === '1';
$apiKeyConfigured = !empty(getIASetting($pdo, 'gemini_api_key', ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IA do Bot - Configurações</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #8B5CF6;
            --primary-dark: #7C3AED;
            --secondary: #06B6D4;
            --dark: #0F0F1A;
            --dark-light: #1A1A2E;
            --light: #FFF;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gradient: linear-gradient(135deg, #8B5CF6 0%, #06B6D4 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0F0F1A 0%, #1A1A2E 50%, #0F172A 100%);
            color: var(--light);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header h1 {
            font-size: 1.8rem;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-links { display: flex; gap: 1rem; flex-wrap: wrap; }
        
        .nav-link {
            padding: 0.6rem 1.2rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(139, 92, 246, 0.15);
            border-color: var(--primary);
        }
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab:hover, .tab.active {
            background: var(--gradient);
            color: white;
            border-color: transparent;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group { margin-bottom: 1rem; }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 8px;
            color: var(--light);
            font-size: 0.95rem;
            font-family: inherit;
        }
        
        .form-group textarea { min-height: 100px; resize: vertical; }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary { background: var(--gradient); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(139, 92, 246, 0.4); }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: #111; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-on { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .status-off { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        
        .table-container { overflow-x: auto; }
        
        table { width: 100%; border-collapse: collapse; }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        th {
            background: rgba(139, 92, 246, 0.1);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        tr:hover { background: rgba(255, 255, 255, 0.03); }
        
        .section { display: none; }
        .section.active { display: block; }
        
        .test-area {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 1rem;
        }
        
        .test-chat {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }
        
        .test-message {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            max-width: 80%;
        }
        
        .test-message.user {
            background: var(--gradient);
            margin-left: auto;
        }
        
        .test-message.assistant {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .test-source {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.3rem;
        }
        
        .switch {
            position: relative;
            width: 50px;
            height: 26px;
        }
        
        .switch input { opacity: 0; width: 0; height: 0; }
        
        .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 26px;
            transition: 0.3s;
        }
        
        .slider:before {
            content: "";
            position: absolute;
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
        }
        
        input:checked + .slider { background: var(--success); }
        input:checked + .slider:before { transform: translateX(24px); }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-warning { background: rgba(245, 158, 11, 0.2); border: 1px solid var(--warning); }
        .alert-success { background: rgba(34, 197, 94, 0.2); border: 1px solid var(--success); }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }
        
        .toast-success { background: var(--success); }
        .toast-error { background: var(--danger); }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; }
            th, td { padding: 0.75rem 0.5rem; font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-brain"></i> IA do Bot</h1>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
                <a href="admin_bot_config.php" class="nav-link"><i class="fas fa-robot"></i> Bot Config</a>
                <a href="admin_bot_licenses.php" class="nav-link"><i class="fas fa-key"></i> Licenças</a>
            </div>
        </div>
        
        <?php if (!$apiKeyConfigured): ?>
        <div class="alert alert-warning" style="margin-bottom: 2rem; padding: 1.5rem; border-left: 4px solid var(--warning);">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div style="flex: 1;">
                    <strong style="font-size: 1.1rem;">⚠️ API Key do Gemini não configurada</strong>
                    <p style="margin: 0.5rem 0 0 0; color: rgba(255,255,255,0.8);">
                        A IA está usando respostas de fallback porque a chave da API não foi configurada.<br>
                        <strong>Obtenha sua chave gratuita em:</strong> 
                        <a href="https://aistudio.google.com/apikey" target="_blank" style="color: var(--secondary); text-decoration: underline;">
                            aistudio.google.com/apikey
                        </a>
                    </p>
                </div>
                <button onclick="document.querySelector('[data-tab=settings]').click()" class="btn btn-warning">
                    <i class="fas fa-cog"></i> Configurar Agora
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Status -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="stat-knowledge"><?= $stats['knowledge_total'] ?></div>
                <div class="stat-label">Base de Conhecimento</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-feedback"><?= $stats['feedback_pending'] ?></div>
                <div class="stat-label">Feedbacks Pendentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-conversations">0</div>
                <div class="stat-label">Conversas Hoje</div>
            </div>
            <div class="stat-card">
                <span class="status-badge <?= $iaEnabled ? 'status-on' : 'status-off' ?>">
                    <i class="fas <?= $iaEnabled ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                    <?= $iaEnabled ? 'IA Ativa' : 'IA Desativada' ?>
                </span>
                <div class="stat-label" style="margin-top:0.5rem">Status</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="knowledge"><i class="fas fa-book"></i> Conhecimento</div>
            <div class="tab" data-tab="feedback"><i class="fas fa-comments"></i> Feedback</div>
            <div class="tab" data-tab="settings"><i class="fas fa-cog"></i> Configurações</div>
            <div class="tab" data-tab="test"><i class="fas fa-flask"></i> Testar IA</div>
        </div>
        
        <!-- Seção: Conhecimento -->
        <section id="section-knowledge" class="section active">
            <div class="card">
                <h2 class="card-title"><i class="fas fa-plus-circle"></i> Adicionar Conhecimento</h2>
                <form id="knowledgeForm">
                    <input type="hidden" name="id" id="knowledge_id">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pergunta / Gatilho *</label>
                            <input type="text" name="pergunta" id="knowledge_pergunta" placeholder="Ex: Qual o horário de funcionamento?" required>
                        </div>
                        <div class="form-group">
                            <label>Categoria</label>
                            <input type="text" name="categoria" id="knowledge_categoria" placeholder="Ex: atendimento, vendas, suporte" value="geral">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Resposta *</label>
                        <textarea name="resposta" id="knowledge_resposta" placeholder="Ex: Nosso horário é de segunda a sexta, das 9h às 18h." required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Palavras-chave (separadas por vírgula)</label>
                            <input type="text" name="palavras_chave" id="knowledge_palavras" placeholder="Ex: horario,funcionamento,abre,fecha">
                        </div>
                        <div class="form-group">
                            <label>Prioridade (0-100)</label>
                            <input type="number" name="prioridade" id="knowledge_prioridade" min="0" max="100" value="50">
                        </div>
                        <div class="form-group">
                            <label>Ativo</label>
                            <label class="switch">
                                <input type="checkbox" name="ativo" id="knowledge_ativo" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                    <button type="button" class="btn btn-warning" onclick="resetKnowledgeForm()"><i class="fas fa-undo"></i> Limpar</button>
                </form>
            </div>
            
            <div class="card">
                <h2 class="card-title"><i class="fas fa-database"></i> Base de Conhecimento</h2>
                <div class="form-row" style="margin-bottom:1rem;">
                    <input type="text" id="searchKnowledge" placeholder="Buscar...">
                    <select id="filterCategory">
                        <option value="">Todas categorias</option>
                    </select>
                    <button class="btn btn-primary btn-sm" onclick="loadKnowledge()"><i class="fas fa-search"></i> Buscar</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Pergunta</th>
                                <th>Resposta</th>
                                <th>Categoria</th>
                                <th>Prioridade</th>
                                <th>Usos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="knowledgeTable">
                            <tr><td colspan="6" style="text-align:center">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!-- Seção: Feedback -->
        <section id="section-feedback" class="section">
            <div class="card">
                <h2 class="card-title"><i class="fas fa-comments"></i> Feedbacks e Correções</h2>
                <p style="color:rgba(255,255,255,0.7);margin-bottom:1rem;">
                    Aqui você vê as perguntas que a IA recebeu e pode corrigir as respostas. Correções podem ser salvas na base de conhecimento para a IA aprender.
                </p>
                <div style="margin-bottom:1rem;">
                    <button class="btn btn-sm" onclick="loadFeedback('pending')">Pendentes</button>
                    <button class="btn btn-sm" onclick="loadFeedback('approved')">Aprovados</button>
                    <button class="btn btn-sm" onclick="loadFeedback('all')">Todos</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Pergunta</th>
                                <th>Resposta IA</th>
                                <th>Correção</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="feedbackTable">
                            <tr><td colspan="5" style="text-align:center">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!-- Seção: Configurações -->
        <section id="section-settings" class="section">
            <div class="card">
                <h2 class="card-title"><i class="fas fa-cog"></i> Configurações da IA</h2>
                <form id="settingsForm">
                    <div class="form-group">
                        <label>🔑 API Key do Gemini</label>
                        <input type="password" name="gemini_api_key" id="setting_gemini_api_key" placeholder="Sua chave da API do Google Gemini">
                        <small style="color:rgba(255,255,255,0.5)">Obtenha em: <a href="https://aistudio.google.com/apikey" target="_blank" style="color:var(--secondary)">aistudio.google.com/apikey</a></small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>IA Habilitada</label>
                            <select name="ia_enabled" id="setting_ia_enabled">
                                <option value="1">Sim</option>
                                <option value="0">Não</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Modelo</label>
                            <select name="ia_model" id="setting_ia_model">
                                <option value="gemini-2.0-flash">Gemini 2.0 Flash (Rápido)</option>
                                <option value="gemini-1.5-flash">Gemini 1.5 Flash</option>
                                <option value="gemini-1.5-pro">Gemini 1.5 Pro (Inteligente)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Temperatura (0-1)</label>
                            <input type="number" name="ia_temperature" id="setting_ia_temperature" min="0" max="1" step="0.1" value="0.7">
                            <small style="color:rgba(255,255,255,0.5)">Maior = mais criativo, Menor = mais preciso</small>
                        </div>
                        <div class="form-group">
                            <label>Máx. Tokens</label>
                            <input type="number" name="ia_max_tokens" id="setting_ia_max_tokens" min="50" max="2000" value="500">
                        </div>
                        <div class="form-group">
                            <label>Msgs de Contexto</label>
                            <input type="number" name="ia_context_messages" id="setting_ia_context_messages" min="0" max="50" value="10">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Prompt do Sistema (Personalidade da IA)</label>
                        <textarea name="ia_system_prompt" id="setting_ia_system_prompt" rows="4" placeholder="Defina a personalidade e instruções da IA..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Resposta de Fallback (quando a IA falha)</label>
                        <textarea name="ia_fallback_response" id="setting_ia_fallback_response" rows="2"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Usar Base de Conhecimento</label>
                            <select name="ia_use_knowledge" id="setting_ia_use_knowledge">
                                <option value="1">Sim</option>
                                <option value="0">Não</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Aprender com Correções</label>
                            <select name="ia_learn_from_corrections" id="setting_ia_learn_from_corrections">
                                <option value="1">Sim</option>
                                <option value="0">Não</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Configurações</button>
                </form>
            </div>
        </section>
        
        <!-- Seção: Testar -->
        <section id="section-test" class="section">
            <div class="card">
                <h2 class="card-title"><i class="fas fa-flask"></i> Testar IA</h2>
                <p style="color:rgba(255,255,255,0.7);margin-bottom:1rem;">
                    Teste a IA antes de usar em produção. As respostas aqui usam as mesmas configurações do bot.
                </p>
                
                <div class="test-area">
                    <div class="test-chat" id="testChat"></div>
                    <div style="display:flex;gap:0.5rem;">
                        <input type="text" id="testInput" placeholder="Digite uma mensagem..." style="flex:1">
                        <button class="btn btn-primary" onclick="sendTestMessage()"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        // Tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('section-' + this.dataset.tab).classList.add('active');
            });
        });
        
        // Init
        document.addEventListener('DOMContentLoaded', () => {
            loadKnowledge();
            loadSettings();
            loadCategories();
            loadStats();
        });
        
        // Stats
        async function loadStats() {
            const fd = new FormData();
            fd.append('action', 'get_stats');
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                document.getElementById('stat-knowledge').textContent = data.data.knowledge_total;
                document.getElementById('stat-feedback').textContent = data.data.feedback_pending;
                document.getElementById('stat-conversations').textContent = data.data.conversations_today;
            }
        }
        
        // Categories
        async function loadCategories() {
            const fd = new FormData();
            fd.append('action', 'get_categories');
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                const select = document.getElementById('filterCategory');
                select.innerHTML = '<option value="">Todas categorias</option>';
                data.data.forEach(cat => {
                    select.innerHTML += `<option value="${cat}">${cat}</option>`;
                });
            }
        }
        
        // Knowledge
        async function loadKnowledge() {
            const fd = new FormData();
            fd.append('action', 'get_knowledge');
            fd.append('search', document.getElementById('searchKnowledge').value);
            fd.append('categoria', document.getElementById('filterCategory').value);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            const tbody = document.getElementById('knowledgeTable');
            if (!data.success || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:rgba(255,255,255,0.5)">Nenhum conhecimento encontrado</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.data.map(k => `
                <tr>
                    <td>${escapeHtml(k.pergunta.substring(0, 50))}${k.pergunta.length > 50 ? '...' : ''}</td>
                    <td>${escapeHtml(k.resposta.substring(0, 60))}${k.resposta.length > 60 ? '...' : ''}</td>
                    <td><span style="background:rgba(139,92,246,0.2);padding:0.2rem 0.5rem;border-radius:4px;font-size:0.8rem">${k.categoria}</span></td>
                    <td>${k.prioridade}</td>
                    <td>${k.uso_count}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editKnowledge(${k.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="deleteKnowledge(${k.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }
        
        document.getElementById('knowledgeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action', 'save_knowledge');
            fd.append('ativo', document.getElementById('knowledge_ativo').checked ? '1' : '0');
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                resetKnowledgeForm();
                loadKnowledge();
                loadStats();
            }
        });
        
        function editKnowledge(id) {
            const fd = new FormData();
            fd.append('action', 'get_knowledge');
            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    const k = data.data.find(x => x.id == id);
                    if (k) {
                        document.getElementById('knowledge_id').value = k.id;
                        document.getElementById('knowledge_pergunta').value = k.pergunta;
                        document.getElementById('knowledge_resposta').value = k.resposta;
                        document.getElementById('knowledge_categoria').value = k.categoria;
                        document.getElementById('knowledge_palavras').value = k.palavras_chave || '';
                        document.getElementById('knowledge_prioridade').value = k.prioridade;
                        document.getElementById('knowledge_ativo').checked = k.ativo == 1;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
        }
        
        async function deleteKnowledge(id) {
            if (!confirm('Excluir este conhecimento?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_knowledge');
            fd.append('id', id);
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) loadKnowledge();
        }
        
        function resetKnowledgeForm() {
            document.getElementById('knowledgeForm').reset();
            document.getElementById('knowledge_id').value = '';
            document.getElementById('knowledge_ativo').checked = true;
        }
        
        // Feedback
        async function loadFeedback(status = 'pending') {
            const fd = new FormData();
            fd.append('action', 'get_feedback');
            fd.append('status', status);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            const tbody = document.getElementById('feedbackTable');
            if (!data.success || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:rgba(255,255,255,0.5)">Nenhum feedback encontrado</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.data.map(f => `
                <tr>
                    <td>${escapeHtml(f.pergunta_original?.substring(0, 40) || '')}...</td>
                    <td>${escapeHtml(f.resposta_ia?.substring(0, 40) || '-')}...</td>
                    <td>${f.correcao ? escapeHtml(f.correcao.substring(0, 40)) + '...' : '-'}</td>
                    <td>${f.aprovado == 1 ? '<span style="color:#22c55e">✓ Aprovado</span>' : '<span style="color:#f59e0b">Pendente</span>'}</td>
                    <td>
                        ${f.aprovado == 0 ? `<button class="btn btn-sm btn-success" onclick="approveFeedback(${f.id}, '${escapeHtml(f.pergunta_original || '')}')"><i class="fas fa-check"></i></button>` : ''}
                        <button class="btn btn-sm btn-danger" onclick="deleteFeedback(${f.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }
        
        function approveFeedback(id, pergunta) {
            const correcao = prompt('Digite a resposta correta para:\n\n"' + pergunta + '"');
            if (correcao === null) return;
            
            const salvar = confirm('Salvar na base de conhecimento para a IA aprender?');
            
            const fd = new FormData();
            fd.append('action', 'approve_feedback');
            fd.append('id', id);
            fd.append('correcao', correcao);
            if (salvar) fd.append('salvar_conhecimento', '1');
            
            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    loadFeedback('pending');
                    loadStats();
                });
        }
        
        async function deleteFeedback(id) {
            if (!confirm('Excluir este feedback?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_feedback');
            fd.append('id', id);
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) loadFeedback();
        }
        
        // Settings
        async function loadSettings() {
            const fd = new FormData();
            fd.append('action', 'get_settings');
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                data.data.forEach(s => {
                    const el = document.getElementById('setting_' + s.setting_key);
                    if (el) {
                        if (el.type === 'checkbox') {
                            el.checked = s.setting_value === '1';
                        } else {
                            el.value = s.setting_value || '';
                        }
                    }
                });
            }
        }
        
        document.getElementById('settingsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData();
            fd.append('action', 'save_settings');
            
            const inputs = this.querySelectorAll('input, select, textarea');
            inputs.forEach(inp => {
                const key = inp.name;
                if (key) {
                    fd.append('settings[' + key + ']', inp.type === 'checkbox' ? (inp.checked ? '1' : '0') : inp.value);
                }
            });
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
        });
        
        // Test IA
        async function sendTestMessage() {
            const input = document.getElementById('testInput');
            const message = input.value.trim();
            if (!message) return;
            
            const chat = document.getElementById('testChat');
            chat.innerHTML += `<div class="test-message user">${escapeHtml(message)}</div>`;
            input.value = '';
            
            const fd = new FormData();
            fd.append('action', 'test_ia');
            fd.append('message', message);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success && data.data) {
                const source = data.data.source || 'unknown';
                const response = data.data.response || 'Sem resposta';
                chat.innerHTML += `
                    <div class="test-message assistant">
                        ${escapeHtml(response)}
                        <div class="test-source">Fonte: ${source}</div>
                    </div>
                `;
            } else {
                chat.innerHTML += `<div class="test-message assistant" style="border:1px solid var(--danger)">Erro: ${data.message || 'Desconhecido'}</div>`;
            }
            
            chat.scrollTop = chat.scrollHeight;
        }
        
        document.getElementById('testInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendTestMessage();
        });
        
        // Helpers
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>
</html>

