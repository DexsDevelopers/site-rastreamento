<?php
/**
 * Painel de Configuração do Bot WhatsApp
 * Gerencia automações, configurações e monitoramento
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';
require_once 'includes/auth_helper.php';

// Verificar autenticação
requireLogin();

// Verificar conexão com banco
if (!isset($pdo) || $pdo === null) {
    die("❌ Erro: Não foi possível conectar ao banco de dados.");
}

// Verificar se tabelas existem
try {
    $pdo->query("SELECT 1 FROM bot_automations LIMIT 1");
} catch (PDOException $e) {
    header('Location: setup_bot_automations.php');
    exit;
}

// ===== PROCESSAMENTO AJAX =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Ação não reconhecida'];
    
    try {
        switch ($action) {
            case 'get_automations':
                $automations = fetchData($pdo, "SELECT * FROM bot_automations ORDER BY prioridade DESC, criado_em DESC");
                $response = ['success' => true, 'data' => $automations];
                break;
                
            case 'save_automation':
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                $nome = sanitizeInput($_POST['nome'] ?? '');
                $descricao = sanitizeInput($_POST['descricao'] ?? '');
                $tipo = sanitizeInput($_POST['tipo'] ?? 'mensagem_especifica');
                $gatilho = sanitizeInput($_POST['gatilho'] ?? '');
                $resposta = $_POST['resposta'] ?? ''; // Não sanitizar para manter formatação
                $imagemUrl = sanitizeInput($_POST['imagem_url'] ?? '') ?: null;
                $grupoIdData = $_POST['grupo_id'] ?? null;
                if (is_array($grupoIdData)) {
                    $grupoIdData = array_filter($grupoIdData); // Remove vazios
                    $grupoId = !empty($grupoIdData) ? implode(',', $grupoIdData) : null;
                } else {
                    $grupoId = sanitizeInput($grupoIdData) ?: null;
                }
                
                $grupoNome = sanitizeInput($_POST['grupo_nome'] ?? '') ?: null;
                $apenasPrivado = isset($_POST['apenas_privado']) ? 1 : 0;
                $apenasGrupo = isset($_POST['apenas_grupo']) ? 1 : 0;
                // Delay - já vem em milissegundos do select
                $delayMs = (int)($_POST['delay_ms'] ?? 0);
                
                // Cooldown - já vem em segundos do select
                $cooldown = (int)($_POST['cooldown_segundos'] ?? 0);
                
                $prioridade = (int)($_POST['prioridade'] ?? 0);
                $ativo = isset($_POST['ativo']) ? 1 : 0;
                
                if (empty($nome) || empty($gatilho) || empty($resposta)) {
                    $response = ['success' => false, 'message' => 'Preencha todos os campos obrigatórios'];
                    break;
                }
                
                if ($id > 0) {
                    // Atualizar
                    $sql = "UPDATE bot_automations SET 
                            nome = ?, descricao = ?, tipo = ?, gatilho = ?, resposta = ?, imagem_url = ?,
                            grupo_id = ?, grupo_nome = ?, apenas_privado = ?, apenas_grupo = ?,
                            delay_ms = ?, cooldown_segundos = ?, prioridade = ?, ativo = ?
                            WHERE id = ?";
                    executeQuery($pdo, $sql, [$nome, $descricao, $tipo, $gatilho, $resposta, $imagemUrl,
                        $grupoId, $grupoNome, $apenasPrivado, $apenasGrupo, 
                        $delayMs, $cooldown, $prioridade, $ativo, $id]);
                    $response = ['success' => true, 'message' => 'Automação atualizada!', 'id' => $id];
                } else {
                    // Inserir
                    $sql = "INSERT INTO bot_automations 
                            (nome, descricao, tipo, gatilho, resposta, imagem_url, grupo_id, grupo_nome, 
                             apenas_privado, apenas_grupo, delay_ms, cooldown_segundos, prioridade, ativo)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($pdo, $sql, [$nome, $descricao, $tipo, $gatilho, $resposta, $imagemUrl,
                        $grupoId, $grupoNome, $apenasPrivado, $apenasGrupo, 
                        $delayMs, $cooldown, $prioridade, $ativo]);
                    $newId = $pdo->lastInsertId();
                    $response = ['success' => true, 'message' => 'Automação criada!', 'id' => $newId];
                }
                break;
                
            case 'delete_automation':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    executeQuery($pdo, "DELETE FROM bot_automations WHERE id = ?", [$id]);
                    $response = ['success' => true, 'message' => 'Automação excluída!'];
                }
                break;
                
            case 'clone_automation':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    // Buscar automação original
                    $original = fetchOne($pdo, "SELECT * FROM bot_automations WHERE id = ?", [$id]);
                    
                    if ($original) {
                        // Criar cópia com nome modificado
                        $novoNome = $original['nome'] . ' (Cópia)';
                        
                        $sql = "INSERT INTO bot_automations 
                                (nome, descricao, tipo, gatilho, resposta, imagem_url, grupo_id, grupo_nome, 
                                 apenas_privado, apenas_grupo, delay_ms, cooldown_segundos, prioridade, ativo)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        executeQuery($pdo, $sql, [
                            $novoNome,
                            $original['descricao'],
                            $original['tipo'],
                            $original['gatilho'],
                            $original['resposta'],
                            $original['imagem_url'],
                            $original['grupo_id'],
                            $original['grupo_nome'],
                            $original['apenas_privado'],
                            $original['apenas_grupo'],
                            $original['delay_ms'],
                            $original['cooldown_segundos'],
                            $original['prioridade'],
                            0  // Deixar inativa por padrão
                        ]);
                        
                        $newId = $pdo->lastInsertId();
                        $response = ['success' => true, 'message' => 'Automação clonada com sucesso!', 'id' => $newId];
                    } else {
                        $response = ['success' => false, 'message' => 'Automação não encontrada'];
                    }
                }
                break;
                
            case 'toggle_automation':
                $id = (int)($_POST['id'] ?? 0);
                $ativo = (int)($_POST['ativo'] ?? 0);
                if ($id > 0) {
                    executeQuery($pdo, "UPDATE bot_automations SET ativo = ? WHERE id = ?", [$ativo, $id]);
                    $response = ['success' => true, 'message' => $ativo ? 'Automação ativada!' : 'Automação desativada!'];
                }
                break;
                
            case 'get_settings':
                $settings = fetchData($pdo, "SELECT * FROM bot_settings ORDER BY chave");
                $settingsObj = [];
                foreach ($settings as $s) {
                    $settingsObj[$s['chave']] = $s;
                }
                $response = ['success' => true, 'data' => $settingsObj];
                break;
                
            case 'save_setting':
                $chave = sanitizeInput($_POST['chave'] ?? '');
                $valor = $_POST['valor'] ?? '';
                if ($chave) {
                    executeQuery($pdo, "UPDATE bot_settings SET valor = ? WHERE chave = ?", [$valor, $chave]);
                    $response = ['success' => true, 'message' => 'Configuração salva!'];
                }
                break;
                
            case 'get_logs':
                $limit = (int)($_POST['limit'] ?? 50);
                $logs = fetchData($pdo, 
                    "SELECT l.*, a.nome as automation_nome 
                     FROM bot_automation_logs l 
                     LEFT JOIN bot_automations a ON l.automation_id = a.id 
                     ORDER BY l.criado_em DESC 
                     LIMIT ?", [$limit]);
                $response = ['success' => true, 'data' => $logs];
                break;
                
            case 'get_stats':
                $totalAutomations = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_automations")['total'];
                $activeAutomations = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_automations WHERE ativo = 1")['total'];
                $totalUsos = fetchOne($pdo, "SELECT SUM(contador_uso) as total FROM bot_automations")['total'] ?? 0;
                $logsHoje = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_automation_logs WHERE DATE(criado_em) = CURDATE()")['total'];
                
                $response = ['success' => true, 'data' => [
                    'total_automations' => $totalAutomations,
                    'active_automations' => $activeAutomations,
                    'total_usos' => $totalUsos,
                    'logs_hoje' => $logsHoje
                ]];
                break;
                
            case 'get_bot_status':
                $apiConfig = whatsappApiConfig();
                $status = ['online' => false, 'ready' => false, 'uptime' => 0];
                
                if ($apiConfig['enabled']) {
                    try {
                        $ch = curl_init($apiConfig['base_url'] . '/status');
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER => ['x-api-token: ' . $apiConfig['token'], 'ngrok-skip-browser-warning: true'],
                            CURLOPT_TIMEOUT => 5,
                            CURLOPT_SSL_VERIFYPEER => false
                        ]);
                        $result = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode === 200 && $result) {
                            $data = json_decode($result, true);
                            $status = [
                                'online' => true,
                                'ready' => $data['ready'] ?? false,
                                'uptime' => $data['uptimeFormatted'] ?? 'N/A',
                                'memory' => $data['memoryMB'] ?? 0,
                                'reconnects' => $data['reconnectAttempts'] ?? 0
                            ];
                        }
                    } catch (Exception $e) {
                        // Bot offline
                    }
                }
                
                $response = ['success' => true, 'data' => $status];
                break;
                
            case 'get_grupos':
                $grupos = fetchData($pdo, "SELECT * FROM bot_grupos ORDER BY nome");
                $response = ['success' => true, 'data' => $grupos];
                break;
                
            case 'upload_image':
                // Upload de imagem para automação
                if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    $response = ['success' => false, 'message' => 'Nenhuma imagem enviada'];
                    break;
                }
                
                $file = $_FILES['image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($file['type'], $allowedTypes)) {
                    $response = ['success' => false, 'message' => 'Tipo de arquivo não permitido'];
                    break;
                }
                
                if ($file['size'] > 5 * 1024 * 1024) {
                    $response = ['success' => false, 'message' => 'Arquivo muito grande (máx 5MB)'];
                    break;
                }
                
                // Criar diretório se não existir
                $uploadDir = 'uploads/bot_images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Gerar nome único
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'auto_' . uniqid() . '_' . time() . '.' . $ext;
                $filepath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Gerar URL completa
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/';
                    $imageUrl = $baseUrl . $filepath;
                    
                    $response = ['success' => true, 'url' => $imageUrl, 'path' => $filepath];
                } else {
                    $response = ['success' => false, 'message' => 'Erro ao salvar arquivo'];
                }
                break;

            // ===== MARKETING LOGIC =====
            case 'save_campaign':
                $ativo = isset($_POST['ativo']) ? 1 : 0;
                $membros_dia = (int)$_POST['membros_dia'];
                $intervalo_min = (int)$_POST['intervalo_min'];
                $intervalo_max = (int)$_POST['intervalo_max'];
                
                $sql = "UPDATE marketing_campanhas SET ativo = ?, membros_por_dia_grupo = ?, intervalo_min_minutos = ?, intervalo_max_minutos = ? WHERE id = 1";
                executeQuery($pdo, $sql, [$ativo, $membros_dia, $intervalo_min, $intervalo_max]);
                
                $response = ['success' => true, 'message' => 'Configurações de campanha salvas!'];
                break;
                
            case 'add_marketing_msg':
                $conteudo = trim($_POST['conteudo']);
                $delay = (int)$_POST['delay'];
                
                $lastOrder = fetchOne($pdo, "SELECT MAX(ordem) as max_ordem FROM marketing_mensagens WHERE campanha_id = 1");
                $ordem = ($lastOrder['max_ordem'] ?? 0) + 1;
                
                if (!empty($conteudo)) {
                    $sql = "INSERT INTO marketing_mensagens (campanha_id, ordem, conteudo, delay_apos_anterior_minutos) VALUES (1, ?, ?, ?)";
                    executeQuery($pdo, $sql, [$ordem, $conteudo, $delay]);
                    $response = ['success' => true, 'message' => 'Mensagem adicionada com sucesso!'];
                } else {
                    $response = ['success' => false, 'message' => 'Conteúdo não pode ser vazio'];
                }
                break;
            
            case 'delete_marketing_msg':
                $id = (int)$_POST['id'];
                if ($id > 0) {
                    executeQuery($pdo, "DELETE FROM marketing_mensagens WHERE id = ?", [$id]);
                    $response = ['success' => true, 'message' => 'Mensagem removida!'];
                }
                break;

            case 'get_marketing_msgs':
                $msgs = fetchData($pdo, "SELECT * FROM marketing_mensagens WHERE campanha_id = 1 ORDER BY ordem ASC");
                $response = ['success' => true, 'data' => $msgs];
                break;

            // ===== MESSAGES CONFIG LOGIC =====
            case 'save_messages':
                $etapas = [
                    'postado' => 'WHATSAPP_MSG_POSTADO',
                    'transito' => 'WHATSAPP_MSG_TRANSITO',
                    'distribuicao' => 'WHATSAPP_MSG_DISTRIBUICAO',
                    'entrega' => 'WHATSAPP_MSG_ENTREGA',
                    'entregue' => 'WHATSAPP_MSG_ENTREGUE',
                    'taxa' => 'WHATSAPP_MSG_TAXA'
                ];
                
                $saved = 0;
                foreach ($etapas as $key => $configKey) {
                    if (isset($_POST[$configKey])) {
                        if (setDynamicConfig($configKey, $_POST[$configKey])) {
                            $saved++;
                        }
                    }
                }
                
                // Limpar cache local se necessário
                if (function_exists('opcache_reset')) opcache_reset();
                clearstatcache(true, __DIR__ . '/config_custom.json');
                
                $response = ['success' => true, 'message' => "{$saved} mensagens salvas com sucesso!"];
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Carregar dados iniciais (Automações)
$automations = fetchData($pdo, "SELECT * FROM bot_automations ORDER BY prioridade DESC, criado_em DESC");
$settings = fetchData($pdo, "SELECT * FROM bot_settings");
$settingsObj = [];
foreach ($settings as $s) {
    $settingsObj[$s['chave']] = $s['valor'];
}

// Carregar dados (Marketing)
try {
    // Check tables first to avoid crashes
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketing_campanhas (id INT PRIMARY KEY AUTO_INCREMENT, nome VARCHAR(100), ativo BOOLEAN DEFAULT 0, membros_por_dia_grupo INT DEFAULT 5, intervalo_min_minutos INT DEFAULT 30, intervalo_max_minutos INT DEFAULT 120, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketing_mensagens (id INT PRIMARY KEY AUTO_INCREMENT, campanha_id INT, ordem INT, tipo ENUM('texto', 'imagem', 'audio') DEFAULT 'texto', conteudo TEXT, delay_apos_anterior_minutos INT DEFAULT 0, FOREIGN KEY (campanha_id) REFERENCES marketing_campanhas(id) ON DELETE CASCADE)");
    // Ensure default campaign exists
    $pdo->exec("INSERT IGNORE INTO marketing_campanhas (id, nome, ativo, membros_por_dia_grupo) VALUES (1, 'Campanha Grupos', 0, 5)");

    $mktCampanha = fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id = 1");
    $mktMensagens = fetchData($pdo, "SELECT * FROM marketing_mensagens WHERE campanha_id = 1 ORDER BY ordem ASC");
    $mktStats = fetchOne($pdo, "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'novo' THEN 1 ELSE 0 END) as novos,
        SUM(CASE WHEN status = 'em_progresso' THEN 1 ELSE 0 END) as progresso,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos
        FROM marketing_membros") ?: ['total'=>0,'novos'=>0,'progresso'=>0,'concluidos'=>0];
} catch (Exception $e) {
    // Silently fail or log (tables might not exist yet if setup wasnt run)
    $mktCampanha = ['ativo'=>0, 'membros_por_dia_grupo'=>5, 'intervalo_min_minutos'=>30, 'intervalo_max_minutos'=>120];
    $mktMensagens = [];
    $mktStats = ['total'=>0,'novos'=>0,'progresso'=>0,'concluidos'=>0];
}

// Carregar dados (Mensagens Personalizadas)
$msgEtapas = [
    'postado' => ['nome' => 'Objeto Postado', 'icon' => '📦', 'key' => 'WHATSAPP_MSG_POSTADO', 'default' => "Olá {nome}!\n\n📦 *Objeto Postado*\n\nSeu pedido *{codigo}* foi postado e está em processamento.\n\n{link}"],
    'transito' => ['nome' => 'Em Trânsito', 'icon' => '🚚', 'key' => 'WHATSAPP_MSG_TRANSITO', 'default' => "Olá {nome}!\n\n🚚 *Em Trânsito*\n\nSeu pedido *{codigo}* está a caminho do centro de distribuição.\n\n{link}"],
    'distribuicao' => ['nome' => 'No Centro de Distribuição', 'icon' => '🏢', 'key' => 'WHATSAPP_MSG_DISTRIBUICAO', 'default' => "Olá {nome}!\n\n🏢 *No Centro de Distribuição*\n\nSeu pedido *{codigo}* chegou ao centro de distribuição e está sendo processado.\n\n{link}"],
    'entrega' => ['nome' => 'Saiu para Entrega', 'icon' => '🚀', 'key' => 'WHATSAPP_MSG_ENTREGA', 'default' => "Olá {nome}!\n\n🚀 *Saiu para Entrega*\n\nSeu pedido *{codigo}* saiu para entrega e chegará em breve!\n\n{link}"],
    'entregue' => ['nome' => 'Entregue', 'icon' => '✅', 'key' => 'WHATSAPP_MSG_ENTREGUE', 'default' => "Olá {nome}!\n\n✅ *Pedido Entregue*\n\nSeu pedido *{codigo}* foi entregue com sucesso!\n\nObrigado pela preferência! 🎉"],
    'taxa' => ['nome' => 'Taxa Pendente', 'icon' => '💰', 'key' => 'WHATSAPP_MSG_TAXA', 'default' => "Olá {nome}!\n\n💰 *Taxa de Distribuição Nacional*\n\nSeu pedido *{codigo}* precisa de uma taxa de R$ {taxa_valor} para seguir para entrega.\n\nFaça o pagamento via PIX:\n`{taxa_pix}`\n\nApós o pagamento, a liberação acontece rapidamente.\n\n{link}"]
];
$msgConfig = [];
foreach ($msgEtapas as $k => $v) {
    // Assuming getDynamicConfig is available via config.php included at top
    $msgConfig[$k] = getDynamicConfig($v['key'], $v['default']);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Configuração do Bot | Helmer Logistics</title>
    <meta name="theme-color" content="#FF3333">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/images/whatsapp-1.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-mobile.css">
    <style>
        :root {
            --primary: #FF3333;
            --primary-dark: #E02020;
            --primary-gradient: linear-gradient(135deg, #FF3333 0%, #FF6B6B 100%);
            --accent: #f59e0b;
            --bg-dark: #0F0F0F;
            --bg-card: #1A1A1A;
            --bg-input: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.1);
            --text: #FFFFFF;
            --text-muted: rgba(255, 255, 255, 0.7);
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: var(--text);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .mono { font-family: 'JetBrains Mono', monospace; }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #1A1A1A 0%, #0F0F0F 100%);
            border-right: 1px solid var(--border);
        }
        
        .sidebar-item {
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-item:hover, .sidebar-item.active {
            background: rgba(255, 51, 51, 0.1);
            border-left-color: var(--primary);
        }
        
        .sidebar-item.active {
            background: rgba(255, 51, 51, 0.15);
        }
        
        /* Cards */
        .card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            transition: all 0.2s;
        }
        
        .card:hover {
            border-color: rgba(255, 51, 51, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .card-header {
            border-bottom: 1px solid var(--border);
            padding: 16px 20px;
        }
        
        /* Inputs */
        .input-field {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text);
            transition: all 0.2s;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }
        
        .input-field::placeholder {
            color: var(--text-muted);
        }
        
        /* Buttons */
        .btn {
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(255, 51, 51, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 51, 51, 0.5);
        }
        
        .btn-primary:active {
            transform: scale(0.97);
        }
        
        .btn-secondary {
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text);
        }
        
        .btn-secondary:hover {
            background: #3f3f46;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            width: 48px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 26px;
            transition: 0.3s;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }
        
        /* Automation Card */
        .automation-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            transition: all 0.2s;
        }
        
        .automation-card:hover {
            border-color: var(--primary);
        }
        
        .automation-card.inactive {
            opacity: 0.6;
        }
        
        /* Badge */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-green {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }
        
        .badge-yellow {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
        }
        
        .badge-red {
            background: rgba(220, 38, 38, 0.15);
            color: #f87171;
        }
        
        .badge-blue {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
        }
        
        /* Status Indicator */
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-online {
            background: #22c55e;
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
        }
        
        .status-offline {
            background: #ef4444;
            animation: none;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: all 0.3s;
        }
        
        .modal-overlay.active .modal-content {
            transform: scale(1);
        }
        
        /* Tab Navigation */
        .tab-btn {
            padding: 12px 24px;
            border-bottom: 2px solid transparent;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        
        .tab-btn:hover {
            color: var(--text);
        }
        
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        /* Stat Card */
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 51, 51, 0.05) 100%);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            border-color: rgba(255, 51, 51, 0.3);
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-dark);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #52525b;
        }
        
        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 16px 24px;
            border-radius: 12px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
            z-index: 1001;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast.success { border-left: 4px solid #22c55e; }
        .toast.error { border-left: 4px solid #ef4444; }
        .toast.warning { border-left: 4px solid #f59e0b; }
        
        /* ===== RESPONSIVO MOBILE ===== */
        @media screen and (max-width: 768px) {
            /* Menu Hambúrguer */
            .menu-toggle {
                display: flex !important;
                position: fixed !important;
                top: max(16px, env(safe-area-inset-top) + 8px) !important;
                left: 16px !important;
                z-index: 999999 !important;
                width: 52px !important;
                height: 52px !important;
                background: var(--primary-gradient) !important;
                border: none !important;
                border-radius: 14px !important;
                box-shadow: 0 4px 16px rgba(255, 51, 51, 0.4), 0 0 20px rgba(255, 51, 51, 0.3) !important;
                cursor: pointer !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 5px !important;
                padding: 0 !important;
            }
            
            .menu-toggle span {
                display: block !important;
                width: 26px !important;
                height: 3px !important;
                background: #FFFFFF !important;
                border-radius: 3px !important;
                transition: all 0.2s !important;
            }
            
            /* Sidebar Mobile */
            .sidebar {
                position: fixed !important;
                top: 0 !important;
                left: -100% !important;
                width: 85% !important;
                max-width: 320px !important;
                height: 100vh !important;
                height: 100dvh !important;
                z-index: 10000 !important;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                box-shadow: 8px 0 40px rgba(0, 0, 0, 0.6) !important;
            }
            
            .sidebar.active {
                left: 0 !important;
            }
            
            /* Overlay */
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.7);
                z-index: 9999;
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                opacity: 0;
                transition: opacity 0.3s;
            }
            
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
            }
            
            /* Main Content Mobile */
            main {
                margin-left: 0 !important;
                padding: 16px !important;
                padding-top: 80px !important;
            }
            
            /* Header Mobile */
            header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 16px !important;
                margin-bottom: 20px !important;
            }
            
            /* Stats Grid Mobile */
            .grid {
                grid-template-columns: 1fr !important;
                gap: 16px !important;
            }
            
            /* Cards Mobile */
            .card {
                margin-bottom: 16px !important;
            }
            
            /* Buttons Mobile */
            .btn {
                width: 100% !important;
                justify-content: center !important;
                margin-bottom: 12px !important;
            }
            
            /* Modal Mobile */
            .modal-content {
                width: 95% !important;
                max-width: 95% !important;
                margin: 10px !important;
                max-height: 90vh !important;
            }
            
            /* Form Grid Mobile */
            .grid.grid-cols-2,
            .grid.grid-cols-3 {
                grid-template-columns: 1fr !important;
            }
            
            /* Table Mobile */
            .overflow-x-auto {
                -webkit-overflow-scrolling: touch;
            }
            
            table {
                font-size: 0.875rem !important;
                min-width: 600px;
            }
            
            /* Toast Mobile */
            .toast {
                left: 16px !important;
                right: 16px !important;
                bottom: 80px !important;
                width: auto !important;
            }
        }
        
        /* Desktop - manter sidebar visível */
        @media (min-width: 769px) {
            .menu-toggle {
                display: none !important;
            }
            
            .sidebar-overlay {
                display: none !important;
            }
        }
    </style>
</head>
<body class="flex">
    <!-- Menu Hambúrguer Mobile -->
    <button class="menu-toggle hidden" id="menuToggle" onclick="toggleSidebar()" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
    
    <!-- Overlay para fechar sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <!-- Sidebar -->
    <aside class="sidebar w-64 min-h-screen flex flex-col fixed left-0 top-0" id="sidebar">
        <div class="p-6 border-b border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: var(--primary-gradient);">
                    <i class="fas fa-robot text-white"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg">Bot Config</h1>
                    <p class="text-xs text-zinc-500">WhatsApp Automation</p>
                </div>
                <!-- Botão fechar no mobile -->
                <button onclick="closeSidebar()" class="ml-auto text-zinc-500 hover:text-white md:hidden">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <nav class="flex-1 py-4">
            <div class="px-4 mb-2 text-xs text-zinc-500 uppercase tracking-wider">Menu</div>
            
            <a href="#dashboard" class="sidebar-item active flex items-center gap-3 px-6 py-3 text-zinc-300" data-section="dashboard">
                <i class="fas fa-chart-line w-5"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="#automations" class="sidebar-item flex items-center gap-3 px-6 py-3 text-zinc-300" data-section="automations">
                <i class="fas fa-bolt w-5"></i>
                <span>Automações</span>
            </a>
            
            <a href="#settings" class="sidebar-item flex items-center gap-3 px-6 py-3 text-zinc-300" data-section="settings">
                <i class="fas fa-cog w-5"></i>
                <span>Configurações</span>
            </a>
            
            <a href="#logs" class="sidebar-item flex items-center gap-3 px-6 py-3 text-zinc-300" data-section="logs">
                <i class="fas fa-history w-5"></i>
                <span>Logs</span>
            </a>
            
            <a href="admin_bot_licenses.php" class="sidebar-item flex items-center gap-3 px-6 py-3 text-zinc-300" style="background: linear-gradient(135deg, rgba(255,51,51,0.1), rgba(255,102,0,0.1)); border-left: 3px solid #FF3333;">
                <i class="fas fa-key w-5" style="color: #FF3333;"></i>
                <span>Licenças de Grupos</span>
            </a>
            
            <a href="admin_bot_ia.php" class="sidebar-item flex items-center gap-3 px-6 py-3 text-zinc-300" style="background: linear-gradient(135deg, rgba(139,92,246,0.1), rgba(6,182,212,0.1)); border-left: 3px solid #8B5CF6;">
                <i class="fas fa-brain w-5" style="color: #8B5CF6;"></i>
                <span>IA do Bot</span>
            </a>

            <a href="#marketing" class="sidebar-item flex items-center gap-3 px-6 py-3 text-zinc-300" data-section="marketing">
                <i class="fas fa-bullhorn w-5"></i>
                <span>Marketing</span>
            </a>

            <a href="#messages" class="sidebar-item flex items-center gap-3 px-6 py-3 text-zinc-300" data-section="messages">
                <i class="fas fa-comment-dots w-5"></i>
                <span>Mensagens</span>
            </a>
            
            <div class="px-4 mt-6 mb-2 text-xs text-zinc-500 uppercase tracking-wider">Links</div>
            
            <a href="dashboard.php" class="sidebar-item flex items-center gap-3 px-6 py-3 text-zinc-300">
                <i class="fas fa-home w-5"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="admin.php" class="sidebar-item flex items-center gap-3 px-6 py-3 text-zinc-300">
                <i class="fas fa-arrow-left w-5"></i>
                <span>Painel Rastreamento</span>
            </a>
        </nav>
        
        <!-- Bot Status -->
        <div class="p-4 border-t border-zinc-800">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div id="botStatusDot" class="status-dot status-offline"></div>
                    <span id="botStatusText" class="text-sm text-zinc-400">Verificando...</span>
                </div>
                <button onclick="checkBotStatus()" class="text-zinc-500 hover:text-zinc-300">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="flex-1 ml-64 p-8">
        <!-- Header -->
        <header class="flex items-center justify-between mb-8">
            <div>
                <h2 id="pageTitle" class="text-2xl font-bold">Dashboard</h2>
                <p id="pageSubtitle" class="text-zinc-500">Visão geral do bot e automações</p>
            </div>
            <button id="btnNewAutomation" onclick="openModal()" class="btn btn-primary" style="display: none;">
                <i class="fas fa-plus"></i>
                Nova Automação
            </button>
        </header>
        
        <!-- Dashboard Section -->
        <section id="section-dashboard" class="section">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-zinc-400 text-sm">Automações</span>
                        <i class="fas fa-bolt" style="color: #FF3333;"></i>
                    </div>
                    <div class="stat-value" id="statTotal">0</div>
                    <p class="text-xs text-zinc-500 mt-1"><span id="statActive">0</span> ativas</p>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-zinc-400 text-sm">Usos Total</span>
                        <i class="fas fa-chart-bar text-blue-500"></i>
                    </div>
                    <div class="stat-value" id="statUsos">0</div>
                    <p class="text-xs text-zinc-500 mt-1">desde o início</p>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-zinc-400 text-sm">Hoje</span>
                        <i class="fas fa-calendar-day text-yellow-500"></i>
                    </div>
                    <div class="stat-value" id="statHoje">0</div>
                    <p class="text-xs text-zinc-500 mt-1">execuções</p>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-zinc-400 text-sm">Status Bot</span>
                        <i class="fas fa-robot" style="color: #FF3333;"></i>
                    </div>
                    <div id="statBotStatus" class="text-2xl font-bold" style="color: #FF3333;">--</div>
                    <p class="text-xs text-zinc-500 mt-1" id="statUptime">uptime: --</p>
                </div>
            </div>
            
            <!-- Recent Automations -->
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h3 class="font-semibold">Automações Recentes</h3>
                    <a href="#automations" class="text-sm hover:underline" style="color: #FF3333;" onclick="showSection('automations')">Ver todas</a>
                </div>
                <div class="p-4">
                    <div id="recentAutomations" class="space-y-3">
                        <p class="text-zinc-500 text-center py-8">Carregando...</p>
                    </div>
                </div>
            </div>
        </section>
        
        </section>

        <!-- Marketing Section -->
        <section id="section-marketing" class="section hidden">
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="stat-card p-4">
                    <div class="text-xs text-zinc-400 mb-1">Total Leads</div>
                    <div class="text-xl font-bold text-white"><?= $mktStats['total'] ?? 0 ?></div>
                </div>
                <div class="stat-card p-4">
                    <div class="text-xs text-zinc-400 mb-1">Na Fila</div>
                    <div class="text-xl font-bold text-yellow-500"><?= $mktStats['novos'] ?? 0 ?></div>
                </div>
                <div class="stat-card p-4">
                    <div class="text-xs text-zinc-400 mb-1">Em Andamento</div>
                    <div class="text-xl font-bold text-blue-500"><?= $mktStats['progresso'] ?? 0 ?></div>
                </div>
                <div class="stat-card p-4">
                    <div class="text-xs text-zinc-400 mb-1">Finalizados</div>
                    <div class="text-xl font-bold text-green-500"><?= $mktStats['concluidos'] ?? 0 ?></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Configuração -->
                <div class="card h-fit">
                    <div class="card-header">
                        <h3 class="font-semibold">⚙️ Configuração da Campanha</h3>
                    </div>
                    <div class="p-6">
                        <form id="marketingConfigForm" onsubmit="saveMarketingConfig(event)">
                            <div class="flex items-center justify-between mb-6 p-4 bg-zinc-900 rounded-lg border border-zinc-800">
                                <div>
                                    <h4 class="font-medium">Campanha Ativa</h4>
                                    <p class="text-sm text-zinc-500">O bot enviará mensagens automáticas</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="ativo" <?= ($mktCampanha['ativo'] ?? 0) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-zinc-400 mb-2">Membros por Dia (por Grupo)</label>
                                <input type="number" name="membros_dia" value="<?= $mktCampanha['membros_por_dia_grupo'] ?? 5 ?>" min="1" max="50" class="input-field w-full">
                                <p class="text-xs text-zinc-600 mt-1">Recomendado: 5-10 para evitar banimento.</p>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-zinc-400 mb-2">Intervalo entre Envios (Minutos)</label>
                                <div class="flex gap-4">
                                    <div class="flex-1">
                                        <input type="number" name="intervalo_min" value="<?= $mktCampanha['intervalo_min_minutos'] ?? 30 ?>" placeholder="Min" class="input-field w-full">
                                        <div class="text-xs text-zinc-600 mt-1">Mínimo</div>
                                    </div>
                                    <div class="flex-1">
                                        <input type="number" name="intervalo_max" value="<?= $mktCampanha['intervalo_max_minutos'] ?? 120 ?>" min="5" class="input-field w-full">
                                        <p class="text-xs text-zinc-600 mt-1">Máximo</p>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="w-full btn btn-primary justify-center mb-3">
                                Salvar Configurações
                            </button>
                            
                            <button type="button" onclick="resetDailyLimit()" class="w-full btn btn-secondary justify-center text-sm">
                                <i class="fas fa-undo mr-2"></i> Zerar Limite Hoje (Emergência)
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Funil -->
                <div class="card h-fit">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="font-semibold">💬 Funil de Mensagens</h3>
                        <span class="text-xs bg-zinc-800 px-2 py-1 rounded text-zinc-400"><?= count($mktMensagens) ?> msgs</span>
                    </div>
                    <div class="p-4 space-y-4">
                        <?php if (empty($mktMensagens)): ?>
                            <div class="text-center py-8 text-zinc-500 dashed border border-zinc-800 rounded-lg">
                                Nenhuma mensagem configurada.<br>Adicione a primeira abaixo.
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($mktMensagens as $msg): ?>
                                    <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-4 relative group">
                                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition">
                                            <button onclick="deleteMarketingMsg(<?= $msg['id'] ?>)" class="text-zinc-500 hover:text-red-500 p-1"><i class="fas fa-trash"></i></button>
                                        </div>
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="bg-zinc-800 text-xs px-2 py-0.5 rounded text-zinc-400">#<?= $msg['ordem'] ?></span>
                                            <span class="text-xs text-zinc-500">
                                                <?= $msg['delay_apos_anterior_minutos'] == 0 ? 'Imediato (1º msg)' : 'Aguarda ' . $msg['delay_apos_anterior_minutos'] . ' min após anterior' ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-zinc-300 whitespace-pre-line"><?= htmlspecialchars($msg['conteudo']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <hr class="border-zinc-800">

                        <form id="addMktMsgForm" onsubmit="addMarketingMsg(event)">
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-zinc-500 mb-1">Nova Mensagem</label>
                                <textarea name="conteudo" rows="3" required placeholder="Digite a mensagem..." class="input-field w-full text-sm"></textarea>
                            </div>
                            <div class="flex gap-3 items-end">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-zinc-500 mb-1">Delay (min)</label>
                                    <input type="number" name="delay" value="60" required class="input-field w-full text-sm">
                                </div>
                                <button type="submit" class="btn btn-secondary text-sm h-[42px]">
                                    <i class="fas fa-plus mr-1"></i> Adicionar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Personalized Messages Section -->
        <section id="section-messages" class="section hidden">
            <div class="card mb-6">
                <div class="p-4 bg-zinc-900/50 border-b border-zinc-800">
                    <p class="text-sm text-zinc-400"><i class="fas fa-info-circle mr-2"></i>Estas são as mensagens enviadas automaticamente quando o status de um rastreio é atualizado.</p>
                </div>
                <div class="p-6">
                    <form id="messagesConfigForm" onsubmit="saveMessagesConfig(event)">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($msgEtapas as $k => $v): ?>
                                <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="text-xl"><?= $v['icon'] ?></span>
                                        <h4 class="font-medium text-zinc-200"><?= $v['nome'] ?></h4>
                                    </div>
                                    <textarea name="<?= $v['key'] ?>" rows="5" class="input-field w-full text-sm font-mono leading-relaxed" spellcheck="false"><?= htmlspecialchars($msgConfig[$k]) ?></textarea>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <span class="text-[10px] bg-zinc-800 text-zinc-500 px-1 rounded">{nome}</span>
                                        <span class="text-[10px] bg-zinc-800 text-zinc-500 px-1 rounded">{codigo}</span>
                                        <span class="text-[10px] bg-zinc-800 text-zinc-500 px-1 rounded">{link}</span>
                                        <?php if ($k === 'taxa'): ?>
                                            <span class="text-[10px] bg-zinc-800 text-orange-500/50 px-1 rounded">{taxa_valor}</span>
                                            <span class="text-[10px] bg-zinc-800 text-orange-500/50 px-1 rounded">{taxa_pix}</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-8 flex justify-end sticky bottom-4">
                            <button type="submit" class="btn btn-primary shadow-lg shadow-red-900/20">
                                <i class="fas fa-save mr-2"></i> Salvar Todas as Mensagens
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        
        <!-- Automations Section -->
        <section id="section-automations" class="section hidden">
            <div id="automationsList" class="grid gap-4">
                <p class="text-zinc-500 text-center py-12">Carregando automações...</p>
            </div>
        </section>
        
        <!-- Settings Section -->
        <section id="section-settings" class="section hidden">
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold">Configurações Gerais do Bot</h3>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Bot Enabled -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium">Bot Ativo</h4>
                            <p class="text-sm text-zinc-500">Ativar ou desativar o bot completamente</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_bot_enabled" onchange="saveSetting('bot_enabled', this.checked ? '1' : '0')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <!-- Automations Enabled -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium">Automações Ativas</h4>
                            <p class="text-sm text-zinc-500">Ativar ou desativar todas as automações</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_automations_enabled" onchange="saveSetting('automations_enabled', this.checked ? '1' : '0')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <!-- Auto Reply -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium">Resposta Automática</h4>
                            <p class="text-sm text-zinc-500">Responder automaticamente a saudações</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_auto_reply_enabled" onchange="saveSetting('auto_reply_enabled', this.checked ? '1' : '0')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <!-- Log Automations -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium">Registrar Logs</h4>
                            <p class="text-sm text-zinc-500">Salvar histórico de execução das automações</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_log_automations" onchange="saveSetting('log_automations', this.checked ? '1' : '0')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Auto Join Groups -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium">Entrar Automaticamente em Grupos</h4>
                            <p class="text-sm text-zinc-500">Entrar em grupos quando receber link no privado (após validação)</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_auto_join_groups" onchange="saveSetting('auto_join_groups', this.checked ? '1' : '0')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Warming Mode -->
                    <div class="flex items-center justify-between mt-4 p-3 bg-orange-500/10 border border-orange-500/20 rounded-lg">
                        <div>
                            <h4 class="font-medium text-orange-400">🔥 Modo Aquecimento</h4>
                            <p class="text-sm text-zinc-400">Simula comportamento humano (digita, varia tempo) para evitar banimento.</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_warming_mode" onchange="saveSetting('warming_mode', this.checked ? '1' : '0')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <hr class="border-zinc-800">
                    
                    <!-- Welcome Message -->
                    <div>
                        <h4 class="font-medium mb-2">Mensagem de Boas-Vindas</h4>
                        <textarea id="setting_welcome_message" class="input-field w-full h-24" 
                            placeholder="Olá! Como posso ajudar?"
                            onblur="saveSetting('welcome_message', this.value)"></textarea>
                    </div>
                    
                    <!-- Rate Limit -->
                    <div>
                        <h4 class="font-medium mb-2">Limite de Automações por Minuto (por usuário)</h4>
                        <input type="number" id="setting_max_automations_per_minute" class="input-field w-32"
                            min="1" max="100" value="10"
                            onblur="saveSetting('max_automations_per_minute', this.value)">
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Logs Section -->
        <section id="section-logs" class="section hidden">
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h3 class="font-semibold">Histórico de Execuções</h3>
                    <button onclick="loadLogs()" class="btn btn-secondary text-sm">
                        <i class="fas fa-sync-alt"></i>
                        Atualizar
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-zinc-800">
                            <tr class="text-left text-zinc-500 text-sm">
                                <th class="px-4 py-3">Data</th>
                                <th class="px-4 py-3">Automação</th>
                                <th class="px-4 py-3">Origem</th>
                                <th class="px-4 py-3">Mensagem</th>
                                <th class="px-4 py-3">Grupo</th>
                            </tr>
                        </thead>
                        <tbody id="logsTable" class="text-sm">
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500">Carregando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
    
    <!-- Modal -->
    <div id="modal" class="modal-overlay" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-zinc-800 flex items-center justify-between">
                <h3 id="modalTitle" class="text-xl font-semibold">Nova Automação</h3>
                <button onclick="closeModal()" class="text-zinc-500 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="automationForm" class="p-6 space-y-5" onsubmit="saveAutomation(event)">
                <input type="hidden" id="automationId" name="id" value="">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-zinc-400 mb-2">Nome *</label>
                        <input type="text" name="nome" id="autoNome" class="input-field w-full" required
                            placeholder="Ex: Boas-vindas">
                    </div>
                    <div>
                        <label class="block text-sm text-zinc-400 mb-2">Tipo</label>
                        <select name="tipo" id="autoTipo" class="input-field w-full">
                            <option value="mensagem_especifica">Mensagem Específica</option>
                            <option value="palavra_chave">Palavra-Chave</option>
                            <option value="regex">Expressão Regular (Regex)</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm text-zinc-400 mb-2">Descrição</label>
                    <input type="text" name="descricao" id="autoDescricao" class="input-field w-full"
                        placeholder="Breve descrição da automação">
                </div>
                
                <div>
                    <label class="block text-sm text-zinc-400 mb-2">Gatilho (Texto que ativa) *</label>
                    <input type="text" name="gatilho" id="autoGatilho" class="input-field w-full mono" required
                        placeholder="oi|olá|hello (use | para múltiplas opções)">
                    <p class="text-xs text-zinc-500 mt-1">
                        <span class="text-yellow-500">Dica:</span> Use | para múltiplas palavras. Ex: oi|olá|hey
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm text-zinc-400 mb-2">Resposta *</label>
                    <textarea name="resposta" id="autoResposta" class="input-field w-full h-32" required
                        placeholder="Olá! Como posso ajudar?&#10;&#10;Use *negrito*, _itálico_ e ~tachado~"></textarea>
                    <p class="text-xs text-zinc-500 mt-1">
                        Suporta formatação WhatsApp: *negrito*, _itálico_, ~tachado~, ```código```
                    </p>
                </div>
                
                <!-- Campo de Imagem -->
                <div>
                    <label class="block text-sm text-zinc-400 mb-2">
                        <i class="fas fa-image mr-1"></i> Imagem (opcional)
                    </label>
                    <div class="flex gap-3">
                        <input type="text" name="imagem_url" id="autoImagemUrl" class="input-field flex-1"
                            placeholder="https://exemplo.com/imagem.jpg" 
                            onchange="previewImage(this.value)">
                        <button type="button" onclick="document.getElementById('imageUpload').click()" 
                            class="btn btn-secondary" title="Upload de imagem">
                            <i class="fas fa-upload"></i>
                        </button>
                    </div>
                    <input type="file" id="imageUpload" class="hidden" accept="image/*" onchange="uploadImage(this)">
                    <p class="text-xs text-zinc-500 mt-1">
                        Cole uma URL de imagem ou faça upload. Formatos: JPG, PNG, GIF, WebP
                    </p>
                    <!-- Preview da imagem -->
                    <div id="imagePreview" class="mt-3 hidden">
                        <div class="relative inline-block">
                            <img id="imagePreviewImg" src="" alt="Preview" 
                                class="max-h-32 rounded-lg border border-zinc-700">
                            <button type="button" onclick="clearImage()" 
                                class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center hover:bg-red-600">
                                <i class="fas fa-times text-xs text-white"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm text-zinc-400">Grupos Específicos (opcional)</label>
                            <a href="#" onclick="purgeGroups(); return false;" class="text-xs text-red-500 hover:text-red-400" title="Limpar lista antiga">Limpar cache</a>
                        </div>
                        <div class="text-xs text-zinc-500 mb-1">Segure Ctrl (ou Cmd) para selecionar vários</div>
                        <select name="grupo_id[]" id="autoGrupoId" class="input-field w-full" multiple size="4" onchange="updateGrupoNome()">
                            <option value="">Todos os chats</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-zinc-400 mb-2">Prioridade</label>
                        <input type="number" name="prioridade" id="autoPrioridade" class="input-field w-full"
                            value="0" min="0" max="100">
                    </div>
                </div>
                <input type="hidden" name="grupo_nome" id="autoGrupoNome">
                
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-zinc-400 mb-2">Delay antes de responder</label>
                        <select name="delay_ms" id="autoDelay" class="input-field w-full">
                            <option value="0">Sem delay (instantâneo)</option>
                            <option value="1000">1 segundo</option>
                            <option value="2000">2 segundos</option>
                            <option value="3000">3 segundos</option>
                            <option value="5000">5 segundos</option>
                            <option value="7000">7 segundos</option>
                            <option value="10000">10 segundos</option>
                            <option value="15000">15 segundos</option>
                            <option value="30000">30 segundos</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-zinc-400 mb-2">Cooldown (tempo entre usos)</label>
                        <select name="cooldown_segundos" id="autoCooldown" class="input-field w-full">
                            <option value="0">Sem cooldown</option>
                            <option value="10">10 segundos</option>
                            <option value="30">30 segundos</option>
                            <option value="60">1 minuto</option>
                            <option value="120">2 minutos</option>
                            <option value="300">5 minutos</option>
                            <option value="600">10 minutos</option>
                            <option value="1800">30 minutos</option>
                            <option value="3600">1 hora</option>
                            <option value="7200">2 horas</option>
                            <option value="21600">6 horas</option>
                            <option value="43200">12 horas</option>
                            <option value="86400">1 dia</option>
                            <option value="172800">2 dias</option>
                            <option value="604800">7 dias (1 semana)</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-4 pb-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="apenas_privado" id="autoApenasPrivado" class="w-4 h-4 rounded">
                            <span class="text-sm">Só Privado</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="apenas_grupo" id="autoApenasGrupo" class="w-4 h-4 rounded">
                            <span class="text-sm">Só Grupo</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <label class="toggle-switch">
                        <input type="checkbox" name="ativo" id="autoAtivo" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span>Automação Ativa</span>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-zinc-800">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar
                    </button>
                </div>
            </form>

        </div>
    </div>
    
    <!-- Toast -->
    <div id="toast" class="toast">
        <span id="toastMessage"></span>
    </div>
    
    <script>
        // ===== VARIÁVEIS GLOBAIS =====
        let automations = <?= json_encode($automations) ?>;
        let settings = <?= json_encode($settingsObj) ?>;
        let grupos = [];
        const API_TOKEN = '<?= whatsappApiConfig()['token'] ?? '' ?>';
        
        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
            loadSettings();
            loadGrupos();
            renderAutomations();
            checkBotStatus();
            
            // Auto-refresh status a cada 30s
            setInterval(checkBotStatus, 30000);
        });
        
        // ===== NAVEGAÇÃO =====
        document.querySelectorAll('.sidebar-item[data-section]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const section = item.dataset.section;
                showSection(section);
            });
        });
        
        function showSection(section) {
            // Update sidebar
            document.querySelectorAll('.sidebar-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-section="${section}"]`)?.classList.add('active');
            
            // Update sections
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(`section-${section}`)?.classList.remove('hidden');
            
            // Update header
            const titles = {
                dashboard: ['Dashboard', 'Visão geral do bot e automações'],
                automations: ['Automações', 'Gerencie respostas automáticas'],
                settings: ['Configurações', 'Ajuste o comportamento do bot'],
                logs: ['Logs', 'Histórico de execuções'],
                marketing: ['Marketing', 'Disparos e campanhas'],
                licencas: ['Licenças', 'Gerencie acesso de grupos'],
                ai: ['IA do Bot', 'Configuração de inteligência'],
                messages: ['Mensagens', 'Personalize textos de rastreamento']
            };
            
            document.getElementById('pageTitle').textContent = titles[section]?.[0] || section;
            document.getElementById('pageSubtitle').textContent = titles[section]?.[1] || '';
            
            // Show/hide new automation button
            document.getElementById('btnNewAutomation').style.display = 
                section === 'automations' ? 'flex' : 'none';
            
            // Load section data
            if (section === 'logs') loadLogs();
        }
        
        // ===== MODAL =====
        function openModal(automation = null) {
            const modal = document.getElementById('modal');
            const form = document.getElementById('automationForm');
            
            if (automation) {
                document.getElementById('modalTitle').textContent = 'Editar Automação';
                document.getElementById('automationId').value = automation.id;
                document.getElementById('autoNome').value = automation.nome;
                document.getElementById('autoDescricao').value = automation.descricao || '';
                document.getElementById('autoTipo').value = automation.tipo;
                document.getElementById('autoGatilho').value = automation.gatilho;
                document.getElementById('autoResposta').value = automation.resposta;
                document.getElementById('autoImagemUrl').value = automation.imagem_url || '';
                const grupoIdSelect = document.getElementById('autoGrupoId');
                const selectedIds = (automation.grupo_id || '').split(',');
                Array.from(grupoIdSelect.options).forEach(opt => {
                    opt.selected = selectedIds.includes(opt.value);
                });
                document.getElementById('autoGrupoNome').value = automation.grupo_nome || '';
                document.getElementById('autoPrioridade').value = automation.prioridade || 0;
                
                // Delay - selecionar a opção mais próxima
                const delayMs = parseInt(automation.delay_ms || 0);
                const delaySelect = document.getElementById('autoDelay');
                if (delaySelect) {
                    // Encontrar opção exata ou mais próxima
                    let selectedDelay = '0';
                    const delayOptions = [0, 1000, 2000, 3000, 5000, 7000, 10000, 15000, 30000];
                    for (const opt of delayOptions) {
                        if (delayMs >= opt) {
                            selectedDelay = opt.toString();
                        } else {
                            break;
                        }
                    }
                    delaySelect.value = selectedDelay;
                }
                
                // Cooldown - selecionar a opção exata ou mais próxima
                const cooldownSegundos = parseInt(automation.cooldown_segundos || 0);
                const cooldownSelect = document.getElementById('autoCooldown');
                if (cooldownSelect) {
                    // Encontrar opção exata ou mais próxima
                    let selectedCooldown = '0';
                    const cooldownOptions = [0, 10, 30, 60, 120, 300, 600, 1800, 3600, 7200, 21600, 43200, 86400, 172800, 604800];
                    for (const opt of cooldownOptions) {
                        if (cooldownSegundos >= opt) {
                            selectedCooldown = opt.toString();
                        } else {
                            break;
                        }
                    }
                    cooldownSelect.value = selectedCooldown;
                }
                
                document.getElementById('autoApenasPrivado').checked = automation.apenas_privado == 1;
                document.getElementById('autoApenasGrupo').checked = automation.apenas_grupo == 1;
                document.getElementById('autoAtivo').checked = automation.ativo == 1;
                
                // Preview da imagem se existir
                if (automation.imagem_url) {
                    previewImage(automation.imagem_url);
                } else {
                    document.getElementById('imagePreview').classList.add('hidden');
                }
            } else {
                document.getElementById('modalTitle').textContent = 'Nova Automação';
                form.reset();
                document.getElementById('automationId').value = '';
                document.getElementById('autoAtivo').checked = true;
                document.getElementById('imagePreview').classList.add('hidden');
                
                // Resetar campos
                document.getElementById('autoDelay').value = '0';
                document.getElementById('autoCooldown').value = '0';
                document.getElementById('autoPrioridade').value = '0';
                
                // Clear group selection
                const grupoSelect = document.getElementById('autoGrupoId');
                Array.from(grupoSelect.options).forEach(opt => opt.selected = false);
            }
            
            modal.classList.add('active');
        }
        
        function closeModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('modal').classList.remove('active');
        }
        
        // ===== AUTOMAÇÕES =====
        function renderAutomations() {
            const container = document.getElementById('automationsList');
            const recent = document.getElementById('recentAutomations');
            
            if (!automations.length) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <i class="fas fa-robot text-6xl text-zinc-700 mb-4"></i>
                        <h3 class="text-xl text-zinc-400 mb-2">Nenhuma automação criada</h3>
                        <p class="text-zinc-500 mb-6">Crie sua primeira automação para começar!</p>
                        <button onclick="openModal()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nova Automação
                        </button>
                    </div>
                `;
                recent.innerHTML = container.innerHTML;
                return;
            }
            
            // Full list
            container.innerHTML = automations.map(a => automationCard(a)).join('');
            
            // Recent (top 5)
            recent.innerHTML = automations.slice(0, 5).map(a => automationCardMini(a)).join('');
        }
        
        function automationCard(a) {
            const tipoLabels = {
                'mensagem_especifica': { text: 'Mensagem', class: 'badge-blue' },
                'palavra_chave': { text: 'Palavra-Chave', class: 'badge-yellow' },
                'regex': { text: 'Regex', class: 'badge-red' }
            };
            const tipo = tipoLabels[a.tipo] || { text: a.tipo, class: 'badge-blue' };
            
            return `
                <div class="automation-card ${a.ativo == 0 ? 'inactive' : ''}" id="auto-${a.id}">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-semibold">${escapeHtml(a.nome)}</h4>
                                <span class="badge ${tipo.class}">${tipo.text}</span>
                                ${a.ativo == 1 ? '<span class="badge badge-green">Ativo</span>' : '<span class="badge badge-red">Inativo</span>'}
                            </div>
                            <p class="text-sm text-zinc-500">${escapeHtml(a.descricao || 'Sem descrição')}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="toggle-switch" style="transform: scale(0.8)">
                                <input type="checkbox" ${a.ativo == 1 ? 'checked' : ''} 
                                    onchange="toggleAutomation(${a.id}, this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div class="bg-zinc-900 rounded-lg p-3">
                            <div class="text-xs text-zinc-500 mb-1">Gatilho</div>
                            <code class="mono text-sm" style="color: #FF3333;">${escapeHtml(a.gatilho)}</code>
                        </div>
                        <div class="bg-zinc-900 rounded-lg p-3">
                            <div class="text-xs text-zinc-500 mb-1">Resposta</div>
                            <p class="text-sm text-zinc-300 line-clamp-2">${escapeHtml(a.resposta.substring(0, 100))}${a.resposta.length > 100 ? '...' : ''}</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between text-xs text-zinc-500">
                        <div class="flex items-center gap-4">
                            ${a.grupo_nome ? `<span><i class="fas fa-users mr-1"></i>${escapeHtml(a.grupo_nome)}</span>` : '<span><i class="fas fa-globe mr-1"></i>Todos os chats</span>'}
                            <span><i class="fas fa-chart-bar mr-1"></i>${a.contador_uso || 0} usos</span>
                            ${a.imagem_url ? '<span style="color: #FF3333;"><i class="fas fa-image mr-1"></i>Imagem</span>' : ''}
                            ${a.delay_ms > 0 ? `<span><i class="fas fa-clock mr-1"></i>${a.delay_ms}ms</span>` : ''}
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick='openModal(${JSON.stringify(a)})' class="p-2 hover:bg-zinc-800 rounded-lg transition" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="cloneAutomation(${a.id}, '${escapeHtml(a.nome)}')" class="p-2 hover:bg-blue-900/50 rounded-lg transition text-blue-400" title="Clonar">
                                <i class="fas fa-clone"></i>
                            </button>
                            <button onclick="deleteAutomation(${a.id}, '${escapeHtml(a.nome)}')" class="p-2 hover:bg-red-900/50 rounded-lg transition text-red-400" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function automationCardMini(a) {
            return `
                <div class="flex items-center justify-between p-3 bg-zinc-900 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg ${a.ativo == 1 ? 'bg-red-500/20' : 'bg-zinc-800'} flex items-center justify-center">
                            <i class="fas fa-bolt ${a.ativo == 1 ? 'text-red-400' : 'text-zinc-600'}"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-sm">${escapeHtml(a.nome)}</h4>
                            <p class="text-xs text-zinc-500">${a.contador_uso || 0} usos</p>
                        </div>
                    </div>
                    <code class="mono text-xs max-w-[150px] truncate" style="color: #FF3333;">${escapeHtml(a.gatilho)}</code>
                </div>
            `;
        }
        
        async function saveAutomation(e) {
            e.preventDefault();
            const form = document.getElementById('automationForm');
            const formData = new FormData(form);
            formData.append('action', 'save_automation');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal();
                    await loadAutomations();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Erro ao salvar: ' + err.message, 'error');
            }
        }
        
        async function toggleAutomation(id, ativo) {
            const formData = new FormData();
            formData.append('action', 'toggle_automation');
            formData.append('id', id);
            formData.append('ativo', ativo ? 1 : 0);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadAutomations();
                }
            } catch (err) {
                showToast('Erro: ' + err.message, 'error');
            }
        }
        
        async function cloneAutomation(id, nome) {
            if (!confirm(`Clonar a automação "${nome}"?\n\nUma cópia será criada com o nome "${nome} (Cópia)" e ficará INATIVA por padrão.`)) return;
            
            const formData = new FormData();
            formData.append('action', 'clone_automation');
            formData.append('id', id);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast(data.message + ' Edite para personalizar.', 'success');
                    await loadAutomations();
                    
                    // Abrir modal de edição da nova automação clonada
                    if (data.id) {
                        // Aguardar lista carregar e então abrir o modal
                        setTimeout(async () => {
                            const automations = await fetchAutomations();
                            const cloned = automations.find(a => a.id == data.id);
                            if (cloned) {
                                openModal(cloned);
                            }
                        }, 500);
                    }
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Erro ao clonar: ' + err.message, 'error');
            }
        }
        
        async function deleteAutomation(id, nome) {
            if (!confirm(`Excluir a automação "${nome}"?`)) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_automation');
            formData.append('id', id);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadAutomations();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Erro: ' + err.message, 'error');
            }
        }
        
        async function fetchAutomations() {
            const formData = new FormData();
            formData.append('action', 'get_automations');
            
            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                return data.data;
            }
            return [];
        }
        
        async function loadAutomations() {
            try {
                automations = await fetchAutomations();
                renderAutomations();
                loadStats();
            } catch (err) {
                console.error('Erro ao carregar automações:', err);
            }
        }
        
        // ===== CONFIGURAÇÕES =====
        function loadSettings() {
            document.getElementById('setting_bot_enabled').checked = settings.bot_enabled === '1';
            document.getElementById('setting_automations_enabled').checked = settings.automations_enabled === '1';
            document.getElementById('setting_auto_reply_enabled').checked = settings.auto_reply_enabled === '1';
            document.getElementById('setting_log_automations').checked = settings.log_automations === '1';
            document.getElementById('setting_auto_join_groups').checked = settings.auto_join_groups === '1';
            document.getElementById('setting_warming_mode').checked = settings.warming_mode === '1';
            document.getElementById('setting_welcome_message').value = settings.welcome_message || '';
            document.getElementById('setting_max_automations_per_minute').value = settings.max_automations_per_minute || 10;
        }

        async function purgeGroups() {
            if (!confirm('Tem certeza? Isso apagará a lista de grupos do banco. O bot precisará ser reiniciado para sincronizar novamente.')) return;
            
            try {
                // Usando o script separado que criamos
                const res = await fetch('limpar_grupos.php');
                const text = await res.text();
                if (text.includes('Concluído')) {
                    showToast('Grupos limpos! Reinicie o bot agora.', 'success');
                    // Recarregar lista (vai ficar vazia)
                    loadGrupos();
                } else {
                    showToast('Erro ao limpar grupos. Verifique console.', 'error');
                    console.log(text);
                }
            } catch (err) {
                showToast('Erro ao limpar: ' + err.message, 'error');
            }
        }
        // ===== MARKETING JS =====
        async function resetDailyLimit() {
            if (!confirm('Tem certeza? Isso vai zerar a contagem do dia e o bot enviará mensagens para MAIS PESSOAS hoje, ignorando o limite já atingido.')) return;
            
            try {
                const response = await fetch('api_marketing.php?action=reset_daily_limit&token=' + API_TOKEN, {
                    method: 'GET',
                    headers: {
                        'x-api-token': API_TOKEN
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                } else {
                    showToast('Erro ao resetar: ' + (result.message || 'Erro desconhecido'), 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('Erro de conexão ou resposta inválida', 'error');
            }
        }
        
    // ...

        function showSection(section) {
            // ...
            
            // Update header
            const titles = {
                dashboard: ['Dashboard', 'Visão geral do bot e automações'],
                automations: ['Automações', 'Gerencie respostas automáticas'],
                settings: ['Configurações', 'Ajuste o comportamento do bot'],
                logs: ['Logs', 'Histórico de execuções'],
                marketing: ['Marketing', 'Disparos e campanhas'],
                licencas: ['Licenças', 'Gerencie acesso de grupos'],
                ai: ['IA do Bot', 'Configuração de inteligência'],
                messages: ['Mensagens', 'Personalize textos de rastreamento'] // Corrigido de 'mensagens' para 'messages' para bater com o data-section
            };
            
            // ...

        async function saveMarketingConfig(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'save_campaign');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Erro ao salvar: ' + err.message, 'error');
            }
        }

        async function addMarketingMsg(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'add_marketing_msg');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    form.reset(); // Limpa o formulário
                    await loadMarketingMsgs(); // Recarrega lista sem refresh
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Erro: ' + err.message, 'error');
            }
        }

        async function deleteMarketingMsg(id) {
            if(!confirm('Tem certeza?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_marketing_msg');
            formData.append('id', id);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadMarketingMsgs(); // Recarrega lista sem refresh
                }
            } catch (err) {
                showToast('Erro: ' + err.message, 'error');
            }
        }

        async function loadMarketingMsgs() {
            const formData = new FormData();
            formData.append('action', 'get_marketing_msgs');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    renderMarketingMsgs(data.data);
                }
            } catch (err) {
                console.error('Erro ao recarregar mensagens:', err);
            }
        }

        function renderMarketingMsgs(msgs) {
            // Encontrar o container da lista nas abas
            // O container é o div com class "space-y-3" dentro da section marketing
            // Vamos precisar adicionar um ID ao container no PHP ou usar seletor robusto
            // Como não posso editar o HTML do PHP aqui facilmente sem ver o código, vou usar seletor relativo ao form
            // O form é addMktMsgForm. O container de msgs está acima dele.
            
            // Mas espera, eu tenho acesso ao container via DOM se eu der um ID pra ele.
            // Vou assumir que o usuário pode atualizar o PHP container.
            // Melhor: Vou recriar o container no JS.
            
            // Mas espera, o HTML atual é:
            /*
            <div class="p-4 space-y-4">
                <?php if (empty($mktMensagens)): ?>
                    ...
                <?php else: ?>
                    <div class="space-y-3">
                        ... loop ...
                    </div>
                <?php endif; ?>
                <hr ...>
                <form ...>
            */
            
            // Eu preciso identificar o pai dos items.
            // Vou usar o seletor: #section-marketing .card:nth-child(2) .p-4
            
            const container = document.querySelector('#section-marketing .card:nth-child(2) .p-4');
            if(!container) return;
            
            // Remover conteúdo antes do HR
            // A estrutura é complexa. O ideal é ter um container explícito.
            // Como não tenho ID, vou limpar tudo exceto o form e o HR e recriar.
            
            // Pegar o form e o hr para preservar
            const form = document.getElementById('addMktMsgForm');
            const hr = container.querySelector('hr');
            
            // Limpar container
            container.innerHTML = '';
            
            // Recriar lista
            if (!msgs || msgs.length === 0) {
                container.innerHTML = `
                    <div id="mktMsgsList" class="text-center py-8 text-zinc-500 dashed border border-zinc-800 rounded-lg">
                        Nenhuma mensagem configurada.<br>Adicione a primeira abaixo.
                    </div>
                `;
            } else {
                const listDiv = document.createElement('div');
                listDiv.className = 'space-y-3';
                listDiv.id = 'mktMsgsList';
                
                msgs.forEach(msg => {
                    const item = document.createElement('div');
                    item.className = 'bg-zinc-900 border border-zinc-800 rounded-lg p-4 relative group';
                    
                    // Escapar conteúdo HTML para segurança
                    const conteudoEscapado = escapeHtml(msg.conteudo);
                    const delayText = msg.delay_apos_anterior_minutos == 0 ? 'Imediato (1º msg)' : `Aguarda ${msg.delay_apos_anterior_minutos} min após anterior`;
                    
                    item.innerHTML = `
                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition">
                            <button onclick="deleteMarketingMsg(${msg.id})" class="text-zinc-500 hover:text-red-500 p-1"><i class="fas fa-trash"></i></button>
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="bg-zinc-800 text-xs px-2 py-0.5 rounded text-zinc-400">#${msg.ordem}</span>
                            <span class="text-xs text-zinc-500">${delayText}</span>
                        </div>
                        <p class="text-sm text-zinc-300 whitespace-pre-line">${conteudoEscapado}</p>
                    `;
                    listDiv.appendChild(item);
                });
                
                container.appendChild(listDiv);
            }
            
            // Re-adicionar HR e Form
            container.appendChild(hr);
            container.appendChild(form);
            
            // Atualizar contador no header do card se possível
            const counter = document.querySelector('#section-marketing .card:nth-child(2) .card-header span');
            if(counter) counter.textContent = `${msgs.length} msgs`;
        }

        async function syncMembers() {
            if(!confirm('Isso ordenará ao Bot varrer os grupos. Continuar?')) return;
            showToast('Enviando comando...', 'warning');
            try {
                const res = await fetch('api_marketing_trigger.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'sync_groups'})
                });
                const data = await res.json();
                if(data.success) {
                    showToast('Comando enviado! Bot está processando em background.', 'success');
                } else {
                    showToast('Erro: ' + data.message, 'error');
                }
            } catch(err) {
                showToast('Falha na requisição: ' + err.message, 'error');
            }
        }

        // ===== MESSAGES CONFIG JS =====
        async function saveMessagesConfig(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'save_messages');
            
            showToast('Salvando...', 'warning');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Erro ao salvar: ' + err.message, 'error');
            }
        }
        
        async function saveSetting(chave, valor) {
            const formData = new FormData();
            formData.append('action', 'save_setting');
            formData.append('chave', chave);
            formData.append('valor', valor);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    settings[chave] = valor;
                    showToast('Configuração salva!', 'success');
                }
            } catch (err) {
                showToast('Erro: ' + err.message, 'error');
            }
        }
        
        // ===== ESTATÍSTICAS =====
        async function loadStats() {
            const formData = new FormData();
            formData.append('action', 'get_stats');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById('statTotal').textContent = data.data.total_automations;
                    document.getElementById('statActive').textContent = data.data.active_automations;
                    document.getElementById('statUsos').textContent = data.data.total_usos;
                    document.getElementById('statHoje').textContent = data.data.logs_hoje;
                }
            } catch (err) {
                console.error('Erro ao carregar stats:', err);
            }
        }
        
        // ===== BOT STATUS =====
        async function checkBotStatus() {
            const formData = new FormData();
            formData.append('action', 'get_bot_status');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                const dot = document.getElementById('botStatusDot');
                const text = document.getElementById('botStatusText');
                const statStatus = document.getElementById('statBotStatus');
                const statUptime = document.getElementById('statUptime');
                
                if (data.success && data.data.ready) {
                    dot.className = 'status-dot status-online';
                    text.textContent = 'Online';
                    statStatus.textContent = 'Online';
                    statStatus.className = 'text-2xl font-bold';
                    statStatus.style.color = '#FF3333';
                    statUptime.textContent = `uptime: ${data.data.uptime}`;
                } else if (data.success && data.data.online) {
                    dot.className = 'status-dot status-offline';
                    text.textContent = 'Conectando...';
                    statStatus.textContent = 'Conectando';
                    statStatus.className = 'text-2xl font-bold text-yellow-400';
                } else {
                    dot.className = 'status-dot status-offline';
                    text.textContent = 'Offline';
                    statStatus.textContent = 'Offline';
                    statStatus.className = 'text-2xl font-bold text-red-400';
                    statUptime.textContent = 'uptime: --';
                }
            } catch (err) {
                console.error('Erro ao verificar status:', err);
            }
        }
        
        // ===== LOGS =====
        async function loadLogs() {
            const formData = new FormData();
            formData.append('action', 'get_logs');
            formData.append('limit', 50);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                const tbody = document.getElementById('logsTable');
                
                if (!data.success || !data.data.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">Nenhum log encontrado</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.data.map(log => `
                    <tr class="border-b border-zinc-800/50 hover:bg-zinc-800/30">
                        <td class="px-4 py-3 text-zinc-400">${formatDate(log.criado_em)}</td>
                        <td class="px-4 py-3 font-medium">${escapeHtml(log.automation_nome || '--')}</td>
                        <td class="px-4 py-3 mono text-xs">${escapeHtml(log.numero_origem || log.jid_origem)}</td>
                        <td class="px-4 py-3 text-zinc-400 max-w-xs truncate">${escapeHtml(log.mensagem_recebida || '--')}</td>
                        <td class="px-4 py-3 text-zinc-500">${escapeHtml(log.grupo_nome || 'Privado')}</td>
                    </tr>
                `).join('');
            } catch (err) {
                console.error('Erro ao carregar logs:', err);
            }
        }
        
        // ===== GRUPOS =====
        async function loadGrupos() {
            const formData = new FormData();
            formData.append('action', 'get_grupos');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    grupos = data.data;
                    const select = document.getElementById('autoGrupoId');
                    
                    // Manter primeira opção (todos os chats)
                    select.innerHTML = '<option value="">Todos os chats</option>';
                    
                    grupos.forEach(g => {
                        select.innerHTML += `<option value="${escapeHtml(g.jid)}">${escapeHtml(g.nome || g.jid)}</option>`;
                    });
                }
            } catch (err) {
                console.error('Erro ao carregar grupos:', err);
            }
        }
        
        function updateGrupoNome() {
            const select = document.getElementById('autoGrupoId');
            const nomeInput = document.getElementById('autoGrupoNome');
            
            const selectedOptions = Array.from(select.selectedOptions);
            
            if (selectedOptions.length === 0 || (selectedOptions.length === 1 && selectedOptions[0].value === '')) {
                nomeInput.value = ''; // Todos os chats
            } else if (selectedOptions.length === 1) {
                nomeInput.value = selectedOptions[0].text;
            } else {
                nomeInput.value = selectedOptions.length + ' grupos selecionados';
            }
        }
        
        // ===== FUNÇÕES DE IMAGEM =====
        function previewImage(url) {
            const preview = document.getElementById('imagePreview');
            const img = document.getElementById('imagePreviewImg');
            
            if (url && url.trim()) {
                img.src = url;
                img.onload = () => preview.classList.remove('hidden');
                img.onerror = () => {
                    preview.classList.add('hidden');
                    showToast('URL de imagem inválida', 'error');
                };
            } else {
                preview.classList.add('hidden');
            }
        }
        
        function clearImage() {
            document.getElementById('autoImagemUrl').value = '';
            document.getElementById('imagePreview').classList.add('hidden');
        }
        
        async function uploadImage(input) {
            const file = input.files[0];
            if (!file) return;
            
            // Validar tamanho (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showToast('Imagem muito grande! Máximo 5MB', 'error');
                return;
            }
            
            // Validar tipo
            if (!file.type.startsWith('image/')) {
                showToast('Arquivo deve ser uma imagem', 'error');
                return;
            }
            
            showToast('Fazendo upload...', 'warning');
            
            const formData = new FormData();
            formData.append('action', 'upload_image');
            formData.append('image', file);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success && data.url) {
                    document.getElementById('autoImagemUrl').value = data.url;
                    previewImage(data.url);
                    showToast('Imagem enviada!', 'success');
                } else {
                    showToast(data.message || 'Erro no upload', 'error');
                }
            } catch (err) {
                showToast('Erro: ' + err.message, 'error');
            }
            
            // Limpar input
            input.value = '';
        }
        
        // ===== UTILIDADES =====
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('pt-BR', { 
                day: '2-digit', month: '2-digit', year: '2-digit',
                hour: '2-digit', minute: '2-digit'
            });
        }
        
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toast.className = `toast ${type}`;
            toastMessage.textContent = message;
            toast.classList.add('show');
            
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
                closeSidebar();
            }
            if (e.key === 'n' && e.ctrlKey) {
                e.preventDefault();
                openModal();
            }
            // Prevenir zoom com teclado (Ctrl + / Ctrl -)
            if ((e.ctrlKey || e.metaKey) && (e.key === '+' || e.key === '-' || e.key === '=' || e.keyCode === 187 || e.keyCode === 189)) {
                e.preventDefault();
            }
        });
        
        // ===== PREVENIR ZOOM COMPLETAMENTE =====
        // Prevenir zoom com gestos de pinça
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
        
        // Prevenir zoom com gestos de pinça (iOS)
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });
        
        document.addEventListener('gesturechange', function(e) {
            e.preventDefault();
        });
        
        document.addEventListener('gestureend', function(e) {
            e.preventDefault();
        });
        
        // Prevenir zoom com duplo toque
        let lastTouch = 0;
        document.addEventListener('touchstart', function(event) {
            const now = Date.now();
            if (now - lastTouch <= 300) {
                event.preventDefault();
            }
            lastTouch = now;
        }, { passive: false });
        
        // Prevenir zoom com wheel (alguns navegadores)
        document.addEventListener('wheel', function(e) {
            if (e.ctrlKey) {
                e.preventDefault();
            }
        }, { passive: false });
        
        // Forçar viewport scale
        const viewport = document.querySelector('meta[name="viewport"]');
        if (viewport) {
            viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover');
        }
        
        // ===== SIDEBAR MOBILE =====
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const isActive = sidebar.classList.contains('active');
            
            if (isActive) {
                closeSidebar();
            } else {
                sidebar.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Fechar sidebar ao clicar em um link
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });
        
        // Mostrar menu hambúrguer no mobile
        function updateMenuVisibility() {
            const menuToggle = document.getElementById('menuToggle');
            if (window.innerWidth <= 768) {
                menuToggle.classList.remove('hidden');
            } else {
                menuToggle.classList.add('hidden');
                closeSidebar();
            }
        }
        
        window.addEventListener('resize', updateMenuVisibility);
        updateMenuVisibility();
    </script>
</body>
</html>

