<?php
require_once 'includes/whatsapp_helper.php';

$config = whatsappApiConfig();
$url = $config['base_url'] . '/status';
$token = $config['token'];

echo "=== DIAGNÓSTICO DE TOKEN ===\n";
echo "URL: $url\n";
echo "Token Local (config.json): '$token'\n";
echo "Comprimento Token Local: " . strlen($token) . "\n\n";

echo "Enviando requisição...\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER => [
        'x-api-token: ' . $token,
        'ngrok-skip-browser-warning: true'
    ],
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($error) {
    echo "ERRO CURL: $error\n";
}

echo "Resposta:\n$response\n";

if ($httpCode == 401) {
    echo "\n\nANÁLISE DE ERRO 401:\n";
    $json = json_decode($response, true);
    if (isset($json['debug'])) {
        print_r($json['debug']);
    }
}
?>