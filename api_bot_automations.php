<?php
/**
 * API para o Bot WhatsApp acessar automações
 * Endpoints:
 *   GET  /api_bot_automations.php?action=get_automations - Lista automações ativas
 *   GET  /api_bot_automations.php?action=get_settings - Configurações do bot
 *   POST /api_bot_automations.php?action=log_execution - Registrar uso
 *   POST /api_bot_automations.php?action=increment_usage - Incrementar contador
 *   POST /api_bot_automations.php?action=save_grupo - Salvar/atualizar grupo
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Headers para CORS e JSON
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-api-token');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Validação de token
$expectedToken = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');
$receivedToken = $_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$receivedToken = str_replace('Bearer ', '', $receivedToken);

if ($receivedToken !== $expectedToken) {
    http_response_code(401);
    die(json_encode(['error' => 'Token inválido', 'received' => substr($receivedToken, 0, 4) . '***']));
}

// Verificar se tabelas existem
try {
    $pdo->query("SELECT 1 FROM bot_automations LIMIT 1");
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Tabelas de automação não configuradas. Execute setup_bot_automations.php']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'error' => 'Ação não especificada'];

try {
    switch ($action) {
        case 'get_automations':
            // Buscar automações ativas
            // Adicionado campo grupos_permitidos
            $automations = fetchData($pdo, 
                "SELECT id, nome, tipo, gatilho, resposta, imagem_url, grupo_id, grupos_permitidos, grupo_nome, 
                        apenas_privado, apenas_grupo, delay_ms, cooldown_segundos, prioridade
                 FROM bot_automations 
                 WHERE ativo = 1 
                 ORDER BY prioridade DESC, id ASC");
            
            // Decodificar grupos_permitidos se for JSON válido
            foreach ($automations as &$auto) {
                if (!empty($auto['grupos_permitidos'])) {
                    $decoded = json_decode($auto['grupos_permitidos'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $auto['grupos_permitidos'] = $decoded;
                    } else {
                        // Se não for JSON, assumir string separada por vírgula
                        $auto['grupos_permitidos'] = array_map('trim', explode(',', $auto['grupos_permitidos']));
                    }
                } else {
                    $auto['grupos_permitidos'] = [];
                    // Fallback para grupo_id antigo se existir
                    if (!empty($auto['grupo_id'])) {
                        $auto['grupos_permitidos'][] = $auto['grupo_id'];
                    }
                }
            }
            
            $response = [
                'success' => true,
                'automations' => $automations,
                'count' => count($automations),
                'timestamp' => date('c')
            ];
            break;

        case 'migrate_schema':
            // Endpoint temporário para migração
            try {
                // Adicionar coluna grupos_permitidos se não existir
                $sql = "SHOW COLUMNS FROM bot_automations LIKE 'grupos_permitidos'";
                $stmt = $pdo->query($sql);
                
                $message = "";
                
                if ($stmt->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE bot_automations ADD COLUMN grupos_permitidos TEXT DEFAULT NULL COMMENT 'JSON array ou lista separada por vírgula de JIDs permitidos' AFTER grupo_id");
                    $message .= "Coluna adicionada. ";
                    
                    // Migrar dados existentes
                    $sqlMigrate = "UPDATE bot_automations SET grupos_permitidos = JSON_ARRAY(grupo_id) WHERE grupo_id IS NOT NULL AND grupo_id != '' AND grupos_permitidos IS NULL";
                    $pdo->exec($sqlMigrate);
                    $message .= "Dados migrados.";
                } else {
                    $message = "Coluna já existe.";
                }
                $response = ['success' => true, 'message' => $message];
            } catch (Exception $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
            
        case 'get_settings':
            // Buscar configurações
            $settings = fetchData($pdo, "SELECT chave, valor, tipo FROM bot_settings");
            $settingsObj = [];
            foreach ($settings as $s) {
                $value = $s['valor'];
                // Converter tipo
                switch ($s['tipo']) {
                    case 'boolean':
                        $value = $value === '1' || $value === 'true';
                        break;
                    case 'number':
                        $value = (int) $value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }
                $settingsObj[$s['chave']] = $value;
            }
            
            $response = [
                'success' => true,
                'settings' => $settingsObj,
                'timestamp' => date('c')
            ];
            break;
            
        case 'log_execution':
            // Registrar execução de automação
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            $automationId = (int) ($input['automation_id'] ?? 0);
            $jidOrigem = sanitizeInput($input['jid_origem'] ?? '');
            $numeroOrigem = sanitizeInput($input['numero_origem'] ?? '');
            $mensagemRecebida = $input['mensagem_recebida'] ?? '';
            $respostaEnviada = $input['resposta_enviada'] ?? '';
            $grupoId = sanitizeInput($input['grupo_id'] ?? '') ?: null;
            $grupoNome = sanitizeInput($input['grupo_nome'] ?? '') ?: null;
            
            if ($automationId && $jidOrigem) {
                $sql = "INSERT INTO bot_automation_logs 
                        (automation_id, jid_origem, numero_origem, mensagem_recebida, resposta_enviada, grupo_id, grupo_nome)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                executeQuery($pdo, $sql, [$automationId, $jidOrigem, $numeroOrigem, $mensagemRecebida, $respostaEnviada, $grupoId, $grupoNome]);
                
                $response = ['success' => true, 'message' => 'Log registrado'];
            } else {
                $response = ['success' => false, 'error' => 'Dados incompletos'];
            }
            break;
            
        case 'increment_usage':
            // Incrementar contador de uso
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $automationId = (int) ($input['automation_id'] ?? 0);
            
            if ($automationId) {
                $sql = "UPDATE bot_automations SET contador_uso = contador_uso + 1, ultimo_uso = NOW() WHERE id = ?";
                executeQuery($pdo, $sql, [$automationId]);
                
                $response = ['success' => true];
            }
            break;
            
        case 'save_grupo':
            // Salvar/atualizar informações de grupo
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            $jid = sanitizeInput($input['jid'] ?? '');
            $nome = sanitizeInput($input['nome'] ?? '');
            $descricao = sanitizeInput($input['descricao'] ?? '');
            $participantes = (int) ($input['participantes'] ?? 0);
            
            if ($jid) {
                $sql = "INSERT INTO bot_grupos (jid, nome, descricao, participantes) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        nome = VALUES(nome), 
                        descricao = VALUES(descricao), 
                        participantes = VALUES(participantes),
                        atualizado_em = NOW()";
                executeQuery($pdo, $sql, [$jid, $nome, $descricao, $participantes]);
                
                $response = ['success' => true, 'message' => 'Grupo salvo'];
            }
            break;
            
        case 'check_cooldown':
            // Verificar se usuário está em cooldown
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $automationId = (int) ($input['automation_id'] ?? 0);
            $jidOrigem = sanitizeInput($input['jid_origem'] ?? '');
            $cooldownSegundos = (int)($input['cooldown_segundos'] ?? 0);
            
            if ($automationId && $jidOrigem) {
                // Usar cooldown passado como parâmetro ou buscar do banco
                if ($cooldownSegundos <= 0) {
                    $automation = fetchOne($pdo, "SELECT cooldown_segundos FROM bot_automations WHERE id = ?", [$automationId]);
                    $cooldownSegundos = (int) ($automation['cooldown_segundos'] ?? 0);
                }
                
                if ($cooldownSegundos > 0) {
                    // Verificar último uso deste usuário para esta automação
                    $lastUse = fetchOne($pdo, 
                        "SELECT criado_em FROM bot_automation_logs 
                         WHERE automation_id = ? AND jid_origem = ? 
                         ORDER BY criado_em DESC LIMIT 1", 
                        [$automationId, $jidOrigem]);
                    
                    if ($lastUse) {
                        $lastTime = strtotime($lastUse['criado_em']);
                        $elapsed = time() - $lastTime;
                        $remaining = max(0, $cooldownSegundos - $elapsed);
                        
                        if ($elapsed < $cooldownSegundos) {
                            $response = [
                                'success' => true, 
                                'in_cooldown' => true,
                                'elapsed_seconds' => $elapsed,
                                'remaining_seconds' => $remaining,
                                'last_use' => $lastUse['criado_em']
                            ];
                            break;
                        }
                        
                        $response = [
                            'success' => true, 
                            'in_cooldown' => false,
                            'elapsed_seconds' => $elapsed,
                            'remaining_seconds' => 0,
                            'last_use' => $lastUse['criado_em']
                        ];
                        break;
                    }
                }
                
                // Primeira execução ou sem cooldown
                $response = [
                    'success' => true, 
                    'in_cooldown' => false,
                    'elapsed_seconds' => 0,
                    'remaining_seconds' => 0,
                    'last_use' => null
                ];
            } else {
                $response = ['success' => false, 'error' => 'Dados incompletos'];
            }
            break;
            
        case 'get_group_settings':
            // Buscar configurações de um grupo específico
            $grupoJid = sanitizeInput($_GET['grupo_jid'] ?? '');
            if ($grupoJid) {
                $settings = fetchOne($pdo, "SELECT * FROM bot_group_settings WHERE grupo_jid = ?", [$grupoJid]);
                $response = ['success' => true, 'data' => $settings];
            } else {
                $response = ['success' => false, 'error' => 'grupo_jid não informado'];
            }
            break;
            
        case 'get_all_group_settings':
            // Buscar configurações de todos os grupos (para inicialização do bot)
            $allSettings = fetchData($pdo, "SELECT * FROM bot_group_settings");
            $response = ['success' => true, 'data' => $allSettings, 'count' => count($allSettings)];
            break;
            
        case 'save_group_settings':
            // Salvar/atualizar configurações de grupo
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            $grupoJid = sanitizeInput($input['grupo_jid'] ?? '');
            $grupoNome = sanitizeInput($input['grupo_nome'] ?? '');
            $antilinkEnabled = isset($input['antilink_enabled']) ? (int)$input['antilink_enabled'] : 0;
            $antilinkAllowAdmins = isset($input['antilink_allow_admins']) ? (int)$input['antilink_allow_admins'] : 1;
            $automationsEnabled = isset($input['automations_enabled']) ? (int)$input['automations_enabled'] : 1;
            
            if ($grupoJid) {
                $sql = "INSERT INTO bot_group_settings 
                        (grupo_jid, grupo_nome, antilink_enabled, antilink_allow_admins, automations_enabled)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        grupo_nome = VALUES(grupo_nome),
                        antilink_enabled = VALUES(antilink_enabled),
                        antilink_allow_admins = VALUES(antilink_allow_admins),
                        automations_enabled = VALUES(automations_enabled),
                        atualizado_em = NOW()";
                executeQuery($pdo, $sql, [$grupoJid, $grupoNome, $antilinkEnabled, $antilinkAllowAdmins, $automationsEnabled]);
                
                $response = ['success' => true, 'message' => 'Configurações salvas'];
            } else {
                $response = ['success' => false, 'error' => 'grupo_jid não informado'];
            }
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Ação não reconhecida: ' . $action];
    }
} catch (Exception $e) {
    writeLog("Erro na API de automações: " . $e->getMessage(), 'ERROR');
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);

