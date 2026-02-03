<?php
require_once 'includes/config.php';
require_once 'includes/whatsapp_helper.php';

header('Content-Type: text/plain');

$config = whatsappApiConfig();
echo "URL Configurada: " . $config['base_url'] . "\n";
echo "Token Configurado: " . $config['token'] . "\n\n";

echo "Testando conexão com /status...\n";

$ch = curl_init($config['base_url'] . '/status');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'x-api-token: ' . $config['token'],
        'ngrok-skip-browser-warning: true'
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Resposta: $response\n";
echo "Erro cURL: $error\n";
?>
