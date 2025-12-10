<?php
/**
 * Teste específico do header de autenticação
 */

require_once 'includes/config.php';
require_once 'includes/whatsapp_helper.php';

header('Content-Type: application/json; charset=utf-8');

$apiConfig = whatsappApiConfig();
$token = trim($apiConfig['token']);
$baseUrl = $apiConfig['base_url'];

$resultado = [
    'token_info' => [
        'value' => $token,
        'length' => strlen($token),
        'hex' => bin2hex($token),
        'trimmed_length' => strlen(trim($token))
    ],
    'testes' => []
];

// Teste 1: Status (sem auth antes, agora com auth)
$resultado['testes'][] = '1. Testando /status com auth...';
$ch1 = curl_init(rtrim($baseUrl, '/') . '/status');
curl_setopt_array($ch1, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'x-api-token: ' . $token,
        'ngrok-skip-browser-warning: true'
    ],
    CURLOPT_TIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => false
]);
$response1 = curl_exec($ch1);
$httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

$resultado['teste_status'] = [
    'http_code' => $httpCode1,
    'response' => $response1 ? json_decode($response1, true) : null
];

// Teste 2: Send com token como está
$resultado['testes'][] = '2. Testando /send com token original...';
$ch2 = curl_init(rtrim($baseUrl, '/') . '/send');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-token: ' . $token,
        'ngrok-skip-browser-warning: true'
    ],
    CURLOPT_POSTFIELDS => json_encode(['to' => '5551985922779', 'text' => 'teste']),
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false
]);
$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$resultado['teste_send_original'] = [
    'http_code' => $httpCode2,
    'response' => $response2 ? json_decode($response2, true) : null,
    'raw_response' => $response2
];

// Teste 3: Send com token trimado
$resultado['testes'][] = '3. Testando /send com token trimado...';
$tokenTrimmed = trim($token);
$ch3 = curl_init(rtrim($baseUrl, '/') . '/send');
curl_setopt_array($ch3, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-token: ' . $tokenTrimmed,
        'ngrok-skip-browser-warning: true'
    ],
    CURLOPT_POSTFIELDS => json_encode(['to' => '5551985922779', 'text' => 'teste']),
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false
]);
$response3 = curl_exec($ch3);
$httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

$resultado['teste_send_trimmed'] = [
    'http_code' => $httpCode3,
    'response' => $response3 ? json_decode($response3, true) : null,
    'raw_response' => $response3
];

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
