<?php
/**
 * Gerar Taxa PIX via PixGhost
 * Chama a API do PixGhost com external_id = codigo_rastreio
 * para que o webhook possa identificar qual rastreio foi pago.
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

requireLogin();
header('Content-Type: application/json');

$codigo = sanitizeInput($_POST['codigo'] ?? '');
$valor  = (float)str_replace(',', '.', $_POST['valor'] ?? '0');

if (!$codigo) {
    echo json_encode(['success' => false, 'error' => 'Código de rastreio obrigatório.']);
    exit;
}
if ($valor < 5) {
    echo json_encode(['success' => false, 'error' => 'Valor mínimo: R$ 5,00.']);
    exit;
}

$apiKey = getDynamicConfig('PIXGHOST_API_KEY', '');
if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'API Key do PixGhost não configurada. Acesse Configurações → PixGhost.']);
    exit;
}

// Monta a URL de callback com o código embutido na query string
$domain      = getDynamicConfig('SITE_DOMAIN', 'https://transloggi.site');
$callbackUrl = rtrim($domain, '/') . '/webhook_pix.php';

$payload = [
    'amount'       => $valor,
    'customer_name'=> 'Taxa Loggi - ' . $codigo,
    'external_id'  => $codigo,
    'callback_url' => $callbackUrl,
];

$ch = curl_init('https://pixghost.site/api.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    writeLog("gerar_taxa_pix: cURL error para {$codigo}: {$curlError}", 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Erro de conexão com PixGhost: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || empty($data['pix_code'])) {
    $errMsg = $data['error'] ?? "HTTP {$httpCode}: " . substr($response, 0, 200);
    writeLog("gerar_taxa_pix: erro PixGhost para {$codigo}: {$errMsg}", 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Erro ao gerar PIX: ' . $errMsg]);
    exit;
}

// Atualiza o rastreio com o código PIX gerado
executeQuery($pdo,
    "UPDATE rastreios_status SET taxa_valor = ?, taxa_pix = ?, taxa_paga = FALSE WHERE codigo = ?",
    [$valor, $data['pix_code'], $codigo]
);

writeLog("gerar_taxa_pix: PIX gerado para {$codigo} | valor={$valor} | tx_id=" . ($data['transaction_id'] ?? '-'), 'INFO');

echo json_encode([
    'success'        => true,
    'pix_code'       => $data['pix_code'],
    'qr_image'       => $data['qr_image'] ?? null,
    'transaction_id' => $data['transaction_id'] ?? null,
]);
