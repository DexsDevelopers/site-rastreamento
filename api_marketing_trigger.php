<?php
require_once 'includes/config.php';
require_once 'includes/whatsapp_helper.php'; // Pode ter helpers de config

header('Content-Type: application/json');

// Verificar sessão auth (usando helper)
require_once 'includes/auth_helper.php';
requireLogin(true); // true = retorna JSON 401 se falhar

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'sync_groups') {
    // URL do Bot
    $botUrl = getDynamicConfig('BOT_API_URL', 'http://127.0.0.1:3001');
    
    // Hack: Forçar IP 127.0.0.1 e porta 3001
    // Substitui localhost por 127.0.0.1 para evitar problemas de resolução IPv6
    $botUrl = str_replace('localhost', '127.0.0.1', $botUrl);
    
    // Forçar porta 3001 se estiver 3000
    if (strpos($botUrl, ':3000') !== false) {
        $botUrl = str_replace(':3000', ':3001', $botUrl);
    }

    $token = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');
    
    // Chamar endpoint do bot para iniciar sincronização
    $ch = curl_init("$botUrl/sync-members");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['background' => true]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "x-api-token: $token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout curto, bot roda em background
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo json_encode(['success' => true, 'message' => 'Sincronização iniciada']);
    } else {
        $msg = $httpCode ? "Bot retornou erro: $httpCode" : "Erro de conexão ($curlErrno): $curlError";
        // Tentar sugerir porta correta se falhar na 3001
        if (!$httpCode && strpos($botUrl, '3001') !== false) {
             $msg .= " - Verifique se o bot está rodando na porta 3001.";
        }
        echo json_encode(['success' => false, 'message' => $msg]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}
