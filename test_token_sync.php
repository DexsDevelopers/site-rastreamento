<?php
/**
 * Teste de sincronização de token - Diagnóstico completo
 * Este arquivo verifica se o token está configurado corretamente
 */

require_once 'includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$diagnostico = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check 1: Token no config.json
$tokenJson = (string) getDynamicConfig('WHATSAPP_API_TOKEN', '');
$diagnostico['checks'][] = [
    'check' => 'Token no config.json',
    'status' => !empty($tokenJson) ? 'OK' : 'ERRO',
    'value' => !empty($tokenJson) ? substr($tokenJson, 0, 4) . '***' . substr($tokenJson, -4) : 'NÃO DEFINIDO',
    'full_value' => $tokenJson
];

// Check 2: Arquivo .env existe
$envPath = __DIR__ . '/whatsapp-bot/.env';
$envExists = file_exists($envPath);
$diagnostico['checks'][] = [
    'check' => 'Arquivo .env existe',
    'status' => $envExists ? 'OK' : 'ERRO',
    'path' => $envPath
];

// Check 3: Token no .env
$tokenEnv = null;
if ($envExists) {
    $envContent = file_get_contents($envPath);
    $envLines = explode("\n", $envContent);
    foreach ($envLines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^API_TOKEN\s*=\s*(.+)$/i', $line, $matches)) {
            $tokenEnv = trim($matches[1], " \t\"'");
            break;
        }
    }
}

$diagnostico['checks'][] = [
    'check' => 'Token no .env',
    'status' => !empty($tokenEnv) ? 'OK' : 'ERRO',
    'value' => !empty($tokenEnv) ? substr($tokenEnv, 0, 4) . '***' . substr($tokenEnv, -4) : 'NÃO ENCONTRADO',
    'full_value' => $tokenEnv
];

// Check 4: Tokens correspondem
$tokensMatch = !empty($tokenJson) && !empty($tokenEnv) && $tokenJson === $tokenEnv;
$diagnostico['checks'][] = [
    'check' => 'Tokens correspondem',
    'status' => $tokensMatch ? 'OK' : 'ERRO',
    'token_json' => $tokenJson,
    'token_env' => $tokenEnv,
    'match' => $tokensMatch
];

// Check 5: Configuração da API
$apiConfig = whatsappApiConfig();
$diagnostico['checks'][] = [
    'check' => 'Configuração da API',
    'status' => $apiConfig['enabled'] ? 'OK' : 'AVISO',
    'enabled' => $apiConfig['enabled'],
    'base_url' => $apiConfig['base_url'],
    'token_in_config' => substr($apiConfig['token'], 0, 4) . '***' . substr($apiConfig['token'], -4)
];

// Check 6: Testar conexão com bot
if ($apiConfig['enabled'] && !empty($apiConfig['base_url'])) {
    $statusUrl = rtrim($apiConfig['base_url'], '/') . '/status';
    $ch = curl_init($statusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-api-token: ' . $apiConfig['token'],
            'ngrok-skip-browser-warning: true'
        ],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $responseData = $response ? json_decode($response, true) : null;
    
    $diagnostico['checks'][] = [
        'check' => 'Teste de conexão com bot',
        'status' => $httpCode === 200 ? 'OK' : ($httpCode === 401 ? 'ERRO AUTH' : 'ERRO'),
        'http_code' => $httpCode,
        'curl_error' => $curlError ?: null,
        'response' => $responseData,
        'raw_response' => $response
    ];
    
    if ($httpCode === 401) {
        $diagnostico['error'] = '❌ Erro de autenticação detectado!';
        $diagnostico['solution'] = 'Execute o script scripts/sync_whatsapp_token.ps1 e reinicie o bot Node.js';
    }
} else {
    $diagnostico['checks'][] = [
        'check' => 'Teste de conexão com bot',
        'status' => 'PULADO',
        'reason' => 'API desabilitada ou URL não configurada'
    ];
}

// Resumo
$erros = array_filter($diagnostico['checks'], function($check) {
    return isset($check['status']) && strpos($check['status'], 'ERRO') !== false;
});

$diagnostico['summary'] = [
    'total_checks' => count($diagnostico['checks']),
    'errors' => count($erros),
    'ok' => count($diagnostico['checks']) - count($erros),
    'status' => count($erros) === 0 ? '✅ TUDO OK' : '❌ PROBLEMAS ENCONTRADOS'
];

echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
