<?php
require_once 'includes/whatsapp_helper.php';

$number = '559846135000';
$apiConfig = whatsappApiConfig();

echo "Testando número: $number\n";
echo "API URL: " . $apiConfig['base_url'] . "\n";
echo "API Token: " . substr($apiConfig['token'], 0, 5) . "...\n";

$ch = curl_init($apiConfig['base_url'] . '/check');
$payload = json_encode(['to' => $number]);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-token: ' . $apiConfig['token']
    ],
    CURLOPT_POSTFIELDS => $payload
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($error) {
    echo "Curl Error: $error\n";
}
