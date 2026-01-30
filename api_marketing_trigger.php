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
    $botUrl = getDynamicConfig('BOT_API_URL', 'http://localhost:3000');
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
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo json_encode(['success' => true, 'message' => 'Sincronização iniciada']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Bot retornou erro: ' . $httpCode]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}
