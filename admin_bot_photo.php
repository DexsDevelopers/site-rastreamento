<?php
/**
 * Endpoint para receber fotos enviadas via WhatsApp Bot
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/rastreio_media.php';

// Validação de token
$expectedToken = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');
$receivedToken = $_SERVER['HTTP_AUTHORIZATION'] ?? $_POST['token'] ?? '';

if ($receivedToken !== "Bearer $expectedToken" && $receivedToken !== $expectedToken) {
    http_response_code(401);
    die(json_encode(['error' => 'Token inválido']));
}

header('Content-Type: application/json; charset=UTF-8');

// Verificar parâmetros
$codigo = strtoupper($_POST['codigo'] ?? '');
$from = $_POST['from'] ?? '';

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => '❌ Código não fornecido']);
    exit;
}

// Verificar se o código existe
$exists = fetchOne($pdo, "SELECT 1 FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ?", [$codigo]);
if (!$exists) {
    echo json_encode(['success' => false, 'message' => "❌ Código *$codigo* não encontrado no sistema."]);
    exit;
}

// Processar upload da foto
if (!isset($_FILES['foto_pedido']) || $_FILES['foto_pedido']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '❌ Nenhuma foto foi recebida ou houve erro no upload.']);
    exit;
}

try {
    // Usar a mesma função do painel admin
    $uploadResult = handleRastreioFotoUpload($codigo, 'foto_pedido');
    
    if (!$uploadResult['success']) {
        throw new Exception($uploadResult['message'] ?? 'Erro desconhecido no upload');
    }
    
    // Persistir no banco
    persistRastreioFoto($pdo, $codigo, $uploadResult['path']);
    
    // Log
    writeLog("Foto anexada ao código $codigo via WhatsApp Bot de $from", 'INFO');
    
    echo json_encode([
        'success' => true,
        'message' => "✅ Foto anexada com sucesso ao pedido *$codigo*!\n\n" .
                    "📸 A imagem já está disponível para o cliente.\n" .
                    "_Use /status $codigo para ver detalhes_"
    ]);
    
} catch (Exception $e) {
    writeLog("Erro ao processar foto para $codigo via bot: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => '❌ Erro ao processar foto: ' . $e->getMessage()
    ]);
}
