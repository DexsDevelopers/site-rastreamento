<?php
/**
 * API para verificar licença de grupo
 * O bot WhatsApp chama essa API antes de responder em grupos
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-api-token');

// Validar token
$expectedToken = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');
$receivedToken = $_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$receivedToken = str_replace('Bearer ', '', $receivedToken);

if ($receivedToken !== $expectedToken) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized', 'message' => 'Token inválido']);
    exit;
}

// Verificar se tabela existe
try {
    $pdo->query("SELECT 1 FROM bot_group_licenses LIMIT 1");
} catch (PDOException $e) {
    // Tabela não existe, retornar como se tivesse licença (para não quebrar)
    echo json_encode([
        'success' => true,
        'valid' => true,
        'message' => 'Sistema de licenças não configurado',
        'unlimited' => true
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? $_GET;
$groupJid = $input['group_jid'] ?? $input['jid'] ?? '';
$licenseKey = $input['license_key'] ?? $input['key'] ?? '';
$action = $input['action'] ?? 'check';

// Atualizar licenças expiradas
$pdo->exec("UPDATE bot_group_licenses SET status = 'expired' WHERE status = 'active' AND expires_at < NOW()");

switch ($action) {
    case 'check':
        // Verificar se grupo tem licença válida
        if (empty($groupJid)) {
            echo json_encode(['success' => false, 'valid' => false, 'message' => 'JID do grupo não informado']);
            exit;
        }
        
        $license = fetchOne($pdo, 
            "SELECT * FROM bot_group_licenses WHERE group_jid = ? AND status = 'active' AND expires_at > NOW()",
            [$groupJid]
        );
        
        if ($license) {
            $daysLeft = ceil((strtotime($license['expires_at']) - time()) / 86400);
            echo json_encode([
                'success' => true,
                'valid' => true,
                'license_key' => $license['license_key'],
                'group_name' => $license['group_name'],
                'expires_at' => $license['expires_at'],
                'days_left' => $daysLeft,
                'message' => "Licença válida por mais {$daysLeft} dias"
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'valid' => false,
                'message' => 'Grupo sem licença válida. Adquira uma licença para usar o bot.'
            ]);
        }
        break;
        
    case 'activate':
        // Ativar licença via comando do bot
        if (empty($licenseKey) || empty($groupJid)) {
            echo json_encode(['success' => false, 'message' => 'Informe a chave e o JID do grupo']);
            exit;
        }
        
        $licenseKey = strtoupper(trim($licenseKey));
        $groupName = $input['group_name'] ?? '';
        
        $license = fetchOne($pdo, "SELECT * FROM bot_group_licenses WHERE license_key = ?", [$licenseKey]);
        
        if (!$license) {
            echo json_encode(['success' => false, 'message' => '❌ Licença não encontrada. Verifique a chave informada.']);
            exit;
        }
        
        if ($license['status'] === 'active' && $license['group_jid'] && $license['group_jid'] !== $groupJid) {
            echo json_encode(['success' => false, 'message' => '❌ Esta licença já está ativa em outro grupo.']);
            exit;
        }
        
        if ($license['status'] === 'revoked') {
            echo json_encode(['success' => false, 'message' => '❌ Esta licença foi revogada.']);
            exit;
        }
        
        if ($license['status'] === 'expired') {
            echo json_encode(['success' => false, 'message' => '❌ Esta licença expirou. Renove para continuar usando.']);
            exit;
        }
        
        // Ativar licença
        $activatedAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$license['days_purchased']} days"));
        
        executeQuery($pdo, 
            "UPDATE bot_group_licenses SET group_jid = ?, group_name = ?, activated_at = ?, expires_at = ?, status = 'active' WHERE id = ?",
            [$groupJid, $groupName, $activatedAt, $expiresAt, $license['id']]
        );
        
        executeQuery($pdo,
            "INSERT INTO bot_license_history (license_id, action, group_jid, group_name, details) VALUES (?, 'activated', ?, ?, ?)",
            [$license['id'], $groupJid, $groupName, "Ativado via bot por {$license['days_purchased']} dias até {$expiresAt}"]
        );
        
        $daysLeft = $license['days_purchased'];
        echo json_encode([
            'success' => true,
            'message' => "✅ *Licença ativada com sucesso!*\n\n" .
                        "📅 Válida por: {$daysLeft} dias\n" .
                        "⏰ Expira em: " . date('d/m/Y H:i', strtotime($expiresAt)) . "\n\n" .
                        "_Aproveite todas as funcionalidades do bot!_",
            'expires_at' => $expiresAt,
            'days_left' => $daysLeft
        ]);
        break;
        
    case 'info':
        // Informações sobre o sistema de licenças
        echo json_encode([
            'success' => true,
            'message' => "🔑 *SISTEMA DE LICENÇAS*\n\n" .
                        "Para usar o bot neste grupo, é necessário uma licença ativa.\n\n" .
                        "*Como adquirir:*\n" .
                        "Entre em contato com o administrador para obter sua chave de licença.\n\n" .
                        "*Como ativar:*\n" .
                        "Use o comando:\n" .
                        "`\$licenca SUA-CHAVE-AQUI`\n\n" .
                        "*Verificar status:*\n" .
                        "`\$licenca status`"
        ]);
        break;
        
    case 'status':
        // Status da licença do grupo
        if (empty($groupJid)) {
            echo json_encode(['success' => false, 'message' => 'JID do grupo não informado']);
            exit;
        }
        
        $license = fetchOne($pdo, 
            "SELECT * FROM bot_group_licenses WHERE group_jid = ? ORDER BY expires_at DESC LIMIT 1",
            [$groupJid]
        );
        
        if (!$license) {
            echo json_encode([
                'success' => true,
                'message' => "❌ *Este grupo não possui licença*\n\n" .
                            "Adquira uma licença para usar o bot.\n" .
                            "Use `\$licenca info` para mais informações."
            ]);
        } else {
            $statusText = [
                'active' => '🟢 Ativa',
                'pending' => '🟡 Pendente',
                'expired' => '🔴 Expirada',
                'revoked' => '⚫ Revogada'
            ][$license['status']] ?? $license['status'];
            
            $expiresText = $license['expires_at'] 
                ? date('d/m/Y H:i', strtotime($license['expires_at']))
                : 'Não ativada';
            
            $daysLeft = $license['expires_at'] 
                ? ceil((strtotime($license['expires_at']) - time()) / 86400)
                : 0;
            
            $daysText = $daysLeft > 0 ? "{$daysLeft} dias restantes" : "Expirada";
            
            echo json_encode([
                'success' => true,
                'valid' => $license['status'] === 'active' && $daysLeft > 0,
                'message' => "🔑 *STATUS DA LICENÇA*\n\n" .
                            "📋 Chave: `{$license['license_key']}`\n" .
                            "📊 Status: {$statusText}\n" .
                            "📅 Expira: {$expiresText}\n" .
                            "⏳ {$daysText}"
            ]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
}
?>

