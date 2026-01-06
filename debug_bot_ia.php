<?php
/**
 * Página de Debug da IA do Bot
 * Testa e monitora a API do Google Gemini
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

requireLogin();

// Verificar tabelas
try {
    $pdo->query("SELECT 1 FROM bot_ia_settings LIMIT 1");
} catch (PDOException $e) {
    die("❌ Sistema de IA não configurado. Execute <a href='setup_bot_ia.php'>setup_bot_ia.php</a> primeiro.");
}

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
            case 'test_api':
                $apiKey = trim($_POST['api_key'] ?? getIASetting($pdo, 'gemini_api_key', ''));
                $model = trim($_POST['model'] ?? getIASetting($pdo, 'ia_model', 'gemini-2.0-flash'));
                $message = trim($_POST['message'] ?? 'Olá, você está funcionando?');
                
                if (empty($apiKey)) {
                    $response = ['success' => false, 'message' => 'API Key não fornecida'];
                    break;
                }
                
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                
                $data = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $message]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 500
                    ]
                ];
                
                $startTime = microtime(true);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $curlInfo = curl_getinfo($ch);
                curl_close($ch);
                
                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2); // ms
                
                $debug = [
                    'url' => str_replace($apiKey, '***', $url),
                    'http_code' => $httpCode,
                    'duration_ms' => $duration,
                    'curl_error' => $curlError,
                    'curl_info' => [
                        'total_time' => $curlInfo['total_time'] ?? 0,
                        'connect_time' => $curlInfo['connect_time'] ?? 0,
                        'size_upload' => $curlInfo['size_upload'] ?? 0,
                        'size_download' => $curlInfo['size_download'] ?? 0
                    ]
                ];
                
                if ($curlError) {
                    $response = [
                        'success' => false,
                        'message' => "Erro cURL: {$curlError}",
                        'debug' => $debug
                    ];
                    break;
                }
                
                $apiResponse = json_decode($result, true);
                
                if ($httpCode === 200) {
                    $text = $apiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    $usageMetadata = $apiResponse['usageMetadata'] ?? null;
                    
                    $response = [
                        'success' => true,
                        'message' => 'Teste bem-sucedido!',
                        'response' => $text,
                        'usage' => $usageMetadata,
                        'debug' => $debug,
                        'raw_response' => $apiResponse
                    ];
                } else {
                    $errorMsg = $apiResponse['error']['message'] ?? "HTTP {$httpCode}";
                    $errorCode = $apiResponse['error']['code'] ?? $httpCode;
                    
                    $response = [
                        'success' => false,
                        'message' => $errorMsg,
                        'error_code' => $errorCode,
                        'http_code' => $httpCode,
                        'debug' => $debug,
                        'raw_response' => $apiResponse
                    ];
                }
                break;
                
            case 'test_models':
                $apiKey = trim($_POST['api_key'] ?? getIASetting($pdo, 'gemini_api_key', ''));
                
                if (empty($apiKey)) {
                    $response = ['success' => false, 'message' => 'API Key não fornecida'];
                    break;
                }
                
                $models = [
                    'gemini-2.5-flash',
                    'gemini-1.5-flash',
                    'gemini-1.5-flash-001',
                    'gemini-1.5-pro'
                ];
                
                $results = [];
                foreach ($models as $model) {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                    
                    $data = [
                        'contents' => [['parts' => [['text' => 'Teste']]]],
                        'generationConfig' => ['maxOutputTokens' => 10]
                    ];
                    
                    $startTime = microtime(true);
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $duration = round((microtime(true) - $startTime) * 1000, 2);
                    
                    $apiResponse = json_decode($result, true);
                    $available = ($httpCode === 200);
                    $error = $available ? null : ($apiResponse['error']['message'] ?? "HTTP {$httpCode}");
                    
                    $results[] = [
                        'model' => $model,
                        'available' => $available,
                        'http_code' => $httpCode,
                        'duration_ms' => $duration,
                        'error' => $error
                    ];
                }
                
                $response = ['success' => true, 'models' => $results];
                break;
                
            case 'get_stats':
                $stats = [
                    'total_conversations' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_conversations")['c'] ?? 0,
                    'conversations_today' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_conversations WHERE DATE(created_at) = CURDATE()")['c'] ?? 0,
                    'unique_users' => fetchOne($pdo, "SELECT COUNT(DISTINCT phone_number) as c FROM bot_ia_conversations")['c'] ?? 0,
                    'knowledge_total' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_knowledge")['c'] ?? 0,
                    'knowledge_active' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_knowledge WHERE ativo = 1")['c'] ?? 0,
                    'feedback_pending' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_feedback WHERE aprovado = 0")['c'] ?? 0,
                    'last_conversation' => fetchOne($pdo, "SELECT MAX(created_at) as d FROM bot_ia_conversations")['d'] ?? null
                ];
                
                $response = ['success' => true, 'data' => $stats];
                break;
                
            case 'get_recent_errors':
                // Buscar erros recentes nos logs (se houver tabela de logs)
                // Por enquanto, buscar feedbacks sem resposta
                $errors = fetchData($pdo, "
                    SELECT f.*, COUNT(c.id) as conversation_count
                    FROM bot_ia_feedback f
                    LEFT JOIN bot_ia_conversations c ON c.phone_number = f.phone_number
                    WHERE f.resposta_ia IS NULL OR f.resposta_ia = ''
                    GROUP BY f.id
                    ORDER BY f.criado_em DESC
                    LIMIT 20
                ");
                
                $response = ['success' => true, 'data' => $errors];
                break;
                
            case 'check_api_key':
                $apiKey = trim($_POST['api_key'] ?? getIASetting($pdo, 'gemini_api_key', ''));
                
                if (empty($apiKey)) {
                    $response = ['success' => false, 'message' => 'API Key não configurada'];
                    break;
                }
                
                // Validar formato básico
                $isValidFormat = (strlen($apiKey) > 20 && preg_match('/^[A-Za-z0-9_-]+$/', $apiKey));
                
                // Testar com chamada simples
                $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";
                $data = ['contents' => [['parts' => [['text' => 'test']]]]];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $apiResponse = json_decode($result, true);
                $isValid = ($httpCode === 200);
                $error = $isValid ? null : ($apiResponse['error']['message'] ?? "HTTP {$httpCode}");
                
                $response = [
                    'success' => true,
                    'is_valid' => $isValid,
                    'is_valid_format' => $isValidFormat,
                    'http_code' => $httpCode,
                    'error' => $error,
                    'key_length' => strlen($apiKey),
                    'key_preview' => substr($apiKey, 0, 8) . '...' . substr($apiKey, -4)
                ];
                break;
                
            case 'get_settings':
                $settings = fetchData($pdo, "SELECT * FROM bot_ia_settings ORDER BY setting_key");
                $response = ['success' => true, 'data' => $settings];
                break;
                
            case 'clear_cache':
                // Limpar conversas antigas (manter últimas 1000)
                $pdo->exec("DELETE FROM bot_ia_conversations WHERE id NOT IN (SELECT id FROM (SELECT id FROM bot_ia_conversations ORDER BY created_at DESC LIMIT 1000) tmp)");
                $deleted = $pdo->rowCount();
                $response = ['success' => true, 'message' => "Cache limpo! {$deleted} conversas antigas removidas."];
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

$apiKey = getIASetting($pdo, 'gemini_api_key', '');
$model = getIASetting($pdo, 'ia_model', 'gemini-2.0-flash');
$iaEnabled = getIASetting($pdo, 'ia_enabled', '1') === '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug IA - Gemini</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #8B5CF6;
            --secondary: #06B6D4;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0F0F1A;
            --dark-light: #1A1A2E;
            --light: #FFF;
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
            background: linear-gradient(135deg, #8B5CF6, #06B6D4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
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
        
        .btn-primary { background: linear-gradient(135deg, #8B5CF6, #06B6D4); color: white; }
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
        
        .result-box {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .result-box pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #8B5CF6, #06B6D4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-warning { background: rgba(245, 158, 11, 0.2); border: 1px solid var(--warning); }
        .alert-success { background: rgba(34, 197, 94, 0.2); border: 1px solid var(--success); }
        .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid var(--danger); }
        
        .model-list {
            display: grid;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .model-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .model-item.available { border-left: 4px solid var(--success); }
        .model-item.unavailable { border-left: 4px solid var(--danger); }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-bug"></i> Debug IA - Gemini</h1>
            <div>
                <a href="admin_bot_ia.php" class="nav-link"><i class="fas fa-arrow-left"></i> Voltar</a>
                <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            </div>
        </div>
        
        <?php if (empty($apiKey)): ?>
        <div class="alert alert-warning">
            <strong>⚠️ API Key não configurada!</strong> Configure em <a href="admin_bot_ia.php" style="color: var(--secondary);">admin_bot_ia.php</a>
        </div>
        <?php endif; ?>
        
        <!-- Estatísticas -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-chart-bar"></i> Estatísticas</h2>
            <div class="stats-grid" id="statsGrid">
                <div class="stat-item">
                    <div class="stat-value" id="stat-conversations">-</div>
                    <div class="stat-label">Conversas Hoje</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="stat-total">-</div>
                    <div class="stat-label">Total Conversas</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="stat-users">-</div>
                    <div class="stat-label">Usuários Únicos</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="stat-knowledge">-</div>
                    <div class="stat-label">Base Conhecimento</div>
                </div>
            </div>
        </div>
        
        <!-- Verificar API Key -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-key"></i> Verificar API Key</h2>
            <div class="form-group">
                <label>API Key do Gemini</label>
                <input type="password" id="apiKeyInput" value="<?= htmlspecialchars($apiKey) ?>" placeholder="Cole sua API Key aqui">
            </div>
            <button class="btn btn-primary" onclick="checkAPIKey()">
                <i class="fas fa-check"></i> Verificar
            </button>
            <div id="apiKeyResult"></div>
        </div>
        
        <!-- Testar Modelos -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-list"></i> Testar Modelos Disponíveis</h2>
            <p style="color:rgba(255,255,255,0.7);margin-bottom:1rem;">
                Testa quais modelos do Gemini estão disponíveis com sua API Key.
            </p>
            <button class="btn btn-primary" onclick="testModels()">
                <i class="fas fa-flask"></i> Testar Todos os Modelos
            </button>
            <div id="modelsResult"></div>
        </div>
        
        <!-- Testar API -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-paper-plane"></i> Testar Chamada API</h2>
            <div class="form-group">
                <label>Modelo</label>
                <select id="testModel">
                    <option value="gemini-2.5-flash">Gemini 2.5 Flash (Recomendado)</option>
                    <option value="gemini-1.5-flash">Gemini 1.5 Flash</option>
                    <option value="gemini-1.5-flash-001">Gemini 1.5 Flash 001</option>
                    <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
                </select>
            </div>
            <div class="form-group">
                <label>Mensagem de Teste</label>
                <textarea id="testMessage" placeholder="Digite uma mensagem para testar...">Olá, você está funcionando?</textarea>
            </div>
            <button class="btn btn-primary" onclick="testAPI()">
                <i class="fas fa-play"></i> Testar
            </button>
            <div id="testResult"></div>
        </div>
        
        <!-- Configurações Atuais -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-cog"></i> Configurações Atuais</h2>
            <div id="settingsList"></div>
        </div>
        
        <!-- Ações -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-tools"></i> Ações</h2>
            <button class="btn btn-warning" onclick="clearCache()">
                <i class="fas fa-trash"></i> Limpar Cache de Conversas
            </button>
        </div>
    </div>

    <script>
        // Carregar dados ao iniciar
        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
            loadSettings();
        });
        
        // Stats
        async function loadStats() {
            const fd = new FormData();
            fd.append('action', 'get_stats');
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                document.getElementById('stat-conversations').textContent = data.data.conversations_today || 0;
                document.getElementById('stat-total').textContent = data.data.total_conversations || 0;
                document.getElementById('stat-users').textContent = data.data.unique_users || 0;
                document.getElementById('stat-knowledge').textContent = data.data.knowledge_active || 0;
            }
        }
        
        // Settings
        async function loadSettings() {
            const fd = new FormData();
            fd.append('action', 'get_settings');
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                const list = document.getElementById('settingsList');
                list.innerHTML = data.data.map(s => `
                    <div style="display:flex;justify-content:space-between;padding:0.75rem;background:rgba(0,0,0,0.2);border-radius:8px;margin-bottom:0.5rem;">
                        <div>
                            <strong>${s.setting_key}</strong>
                            <div style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin-top:0.25rem;">
                                ${s.description || ''}
                            </div>
                        </div>
                        <div style="color:rgba(255,255,255,0.8);font-family:monospace;">
                            ${s.setting_key.includes('key') ? '***' + s.setting_value.slice(-4) : (s.setting_value || '-')}
                        </div>
                    </div>
                `).join('');
            }
        }
        
        // Check API Key
        async function checkAPIKey() {
            const key = document.getElementById('apiKeyInput').value.trim();
            if (!key) {
                showToast('Digite uma API Key', 'error');
                return;
            }
            
            const resultDiv = document.getElementById('apiKeyResult');
            resultDiv.innerHTML = '<div class="loading"></div> Verificando...';
            
            const fd = new FormData();
            fd.append('action', 'check_api_key');
            fd.append('api_key', key);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                const status = data.is_valid ? 'success' : 'danger';
                const icon = data.is_valid ? 'fa-check-circle' : 'fa-times-circle';
                const msg = data.is_valid ? 'API Key válida!' : ('Erro: ' + (data.error || 'Inválida'));
                
                resultDiv.innerHTML = `
                    <div class="alert alert-${status}" style="margin-top:1rem;">
                        <strong><i class="fas ${icon}"></i> ${msg}</strong>
                        <div style="margin-top:0.5rem;font-size:0.85rem;">
                            Formato: ${data.is_valid_format ? '✓ Válido' : '✗ Inválido'}<br>
                            Tamanho: ${data.key_length} caracteres<br>
                            Preview: ${data.key_preview}<br>
                            HTTP Code: ${data.http_code}
                        </div>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        }
        
        // Test Models
        async function testModels() {
            const key = document.getElementById('apiKeyInput').value.trim() || '<?= htmlspecialchars($apiKey) ?>';
            if (!key) {
                showToast('Configure a API Key primeiro', 'error');
                return;
            }
            
            const resultDiv = document.getElementById('modelsResult');
            resultDiv.innerHTML = '<div class="loading"></div> Testando modelos...';
            
            const fd = new FormData();
            fd.append('action', 'test_models');
            fd.append('api_key', key);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="model-list" style="margin-top:1rem;">
                        ${data.models.map(m => `
                            <div class="model-item ${m.available ? 'available' : 'unavailable'}">
                                <div>
                                    <strong>${m.model}</strong>
                                    <div style="font-size:0.85rem;color:rgba(255,255,255,0.6);margin-top:0.25rem;">
                                        ${m.available ? '✓ Disponível' : ('✗ ' + (m.error || 'Indisponível'))} | ${m.duration_ms}ms
                                    </div>
                                </div>
                                <span class="status-badge ${m.available ? 'status-on' : 'status-off'}">
                                    <i class="fas ${m.available ? 'fa-check' : 'fa-times'}"></i>
                                    ${m.http_code}
                                </span>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        }
        
        // Test API
        async function testAPI() {
            const key = document.getElementById('apiKeyInput').value.trim() || '<?= htmlspecialchars($apiKey) ?>';
            const model = document.getElementById('testModel').value;
            const message = document.getElementById('testMessage').value.trim();
            
            if (!key) {
                showToast('Configure a API Key primeiro', 'error');
                return;
            }
            
            if (!message) {
                showToast('Digite uma mensagem', 'error');
                return;
            }
            
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '<div class="loading"></div> Testando...';
            
            const fd = new FormData();
            fd.append('action', 'test_api');
            fd.append('api_key', key);
            fd.append('model', model);
            fd.append('message', message);
            
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="result-box" style="margin-top:1rem;">
                        <div style="color:#22c55e;margin-bottom:0.5rem;">
                            <strong>✓ Sucesso!</strong> (${data.debug.duration_ms}ms)
                        </div>
                        <div style="margin-bottom:1rem;padding:0.75rem;background:rgba(34,197,94,0.1);border-radius:8px;">
                            <strong>Resposta:</strong><br>
                            ${escapeHtml(data.response)}
                        </div>
                        <details>
                            <summary style="cursor:pointer;color:rgba(255,255,255,0.7);margin-bottom:0.5rem;">📊 Detalhes Técnicos</summary>
                            <pre>${JSON.stringify({
                                http_code: data.debug.http_code,
                                duration_ms: data.debug.duration_ms,
                                usage: data.usage,
                                curl_info: data.debug.curl_info
                            }, null, 2)}</pre>
                        </details>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="result-box" style="margin-top:1rem;border:1px solid var(--danger);">
                        <div style="color:#ef4444;margin-bottom:0.5rem;">
                            <strong>✗ Erro:</strong> ${escapeHtml(data.message)}
                        </div>
                        <details>
                            <summary style="cursor:pointer;color:rgba(255,255,255,0.7);margin-bottom:0.5rem;">📊 Detalhes do Erro</summary>
                            <pre>${JSON.stringify({
                                error_code: data.error_code,
                                http_code: data.http_code,
                                debug: data.debug,
                                raw_response: data.raw_response
                            }, null, 2)}</pre>
                        </details>
                    </div>
                `;
            }
        }
        
        // Clear Cache
        async function clearCache() {
            if (!confirm('Limpar cache de conversas antigas? (mantém últimas 1000)')) return;
            
            const fd = new FormData();
            fd.append('action', 'clear_cache');
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) loadStats();
        }
        
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

