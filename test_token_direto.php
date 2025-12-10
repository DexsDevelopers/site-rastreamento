<?php
/**
 * Teste DIRETO de token - Compara byte a byte
 */

require_once 'includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$resultado = [
    'timestamp' => date('Y-m-d H:i:s'),
    'diagnostico' => []
];

// 1. Token do config.json
$tokenJson = (string) getDynamicConfig('WHATSAPP_API_TOKEN', '');

// 2. Token do .env
$envPath = __DIR__ . '/whatsapp-bot/.env';
$tokenEnv = null;
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    $lines = explode("\n", $envContent);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (preg_match('/^API_TOKEN\s*=\s*(.+)$/i', $line, $matches)) {
            $tokenEnv = trim($matches[1], " \t\"'");
            break;
        }
    }
}

// 3. Análise detalhada
$resultado['tokens'] = [
    'json' => [
        'value' => $tokenJson,
        'length' => strlen($tokenJson),
        'hex' => bin2hex($tokenJson),
        'bytes' => array_map('ord', str_split($tokenJson))
    ],
    'env' => [
        'value' => $tokenEnv,
        'length' => $tokenEnv ? strlen($tokenEnv) : 0,
        'hex' => $tokenEnv ? bin2hex($tokenEnv) : null,
        'bytes' => $tokenEnv ? array_map('ord', str_split($tokenEnv)) : null
    ],
    'match' => $tokenJson === $tokenEnv,
    'match_bytes' => $tokenJson === $tokenEnv ? 'SIM' : 'NÃO'
];

// 4. Teste de envio real
if (!empty($tokenJson)) {
    $apiConfig = whatsappApiConfig();
    $baseUrl = $apiConfig['base_url'];
    
    if (!empty($baseUrl)) {
        $statusUrl = rtrim($baseUrl, '/') . '/status';
        
        // Teste 1: Com token do config.json
        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-token: ' . $tokenJson,
                'ngrok-skip-browser-warning: true'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response1 = curl_exec($ch);
        $httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $resultado['teste_com_token_json'] = [
            'http_code' => $httpCode1,
            'response' => $response1 ? json_decode($response1, true) : null,
            'success' => $httpCode1 === 200
        ];
        
        // Teste 2: Se o token do .env for diferente, testar com ele também
        if ($tokenEnv && $tokenEnv !== $tokenJson) {
            $ch2 = curl_init($statusUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'x-api-token: ' . $tokenEnv,
                    'ngrok-skip-browser-warning: true'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            
            $resultado['teste_com_token_env'] = [
                'http_code' => $httpCode2,
                'response' => $response2 ? json_decode($response2, true) : null,
                'success' => $httpCode2 === 200
            ];
        }
    }
}

// 5. Recomendações
$resultado['recomendacoes'] = [];

if (!$tokenEnv) {
    $resultado['recomendacoes'][] = '❌ Token não encontrado no .env - Execute scripts/sync_whatsapp_token.ps1';
}

if ($tokenJson !== $tokenEnv) {
    $resultado['recomendacoes'][] = '❌ Tokens não correspondem - Execute scripts/sync_whatsapp_token.ps1';
}

if (isset($resultado['teste_com_token_json']['http_code']) && $resultado['teste_com_token_json']['http_code'] === 401) {
    $resultado['recomendacoes'][] = '❌ Token do config.json NÃO funciona - O bot está usando um token diferente';
    $resultado['recomendacoes'][] = '⚠️ REINICIE o bot Node.js após sincronizar o token';
}

if (isset($resultado['teste_com_token_json']['http_code']) && $resultado['teste_com_token_json']['http_code'] === 200) {
    $resultado['recomendacoes'][] = '✅ TUDO FUNCIONANDO! O token está correto.';
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
