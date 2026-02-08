<?php
require_once 'includes/config.php';
require_once 'includes/whatsapp_helper.php';

// Configuração
$config = whatsappApiConfig();
$url = $config['base_url'] . '/health';
$token = $config['token'];

echo "---------------------------------------------------\n";
echo "DIAGNÓSTICO DE STATUS DO BOT WHATSAPP\n";
echo "---------------------------------------------------\n";
echo "URL Base: " . $config['base_url'] . "\n";
echo "Token Configurado: " . ($token ? substr($token, 0, 4) . '***' . substr($token, -4) : 'NÃO DEFINIDO') . "\n";
echo "Status Habilitado: " . ($config['enabled'] ? 'SIM' : 'NÃO') . "\n";
echo "---------------------------------------------------\n";

if (!$config['enabled']) {
    echo "❌ A integração com o bot está desabilitada no config.json ou via variáveis de ambiente.\n";
    exit;
}

// 1. Teste de Conexão Simples (/health)
echo "\n1. Testando endpoint /health...\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'ngrok-skip-browser-warning: true'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo "❌ Falha na conexão cURL: $error\n";
    echo "   Verifique se o bot está rodando e se a URL está correta.\n";
}
else {
    echo "✅ Conexão estabelecida (HTTP $httpCode)\n";
    echo "   Resposta: $response\n";

    $json = json_decode($response, true);
    if ($json) {
        if (isset($json['status']) && $json['status'] === 'healthy') {
            echo "   Status: CUSTOMER READY (O bot reporta 'healthy')\n";
        }
        else {
            echo "   Status: UNHEALTHY (O bot reporta '" . ($json['status'] ?? 'unknown') . "')\n";
        }
    }
    else {
        echo "   ❌ Resposta não é um JSON válido.\n";
    }
}

// 2. Teste de Status Detalhado (/status) - Requer Token se houver middleware, mas no código vi que /status pode ser público ou protegido dependendo da implementação. 
// No index.js analisado, /status é público (não usa middleware 'auth').
echo "\n2. Testando endpoint /status (Detalhes da Sessão)...\n";
$statusUrl = $config['base_url'] . '/status';
$ch = curl_init($statusUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'ngrok-skip-browser-warning: true',
        'x-api-token: ' . $token
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    echo "✅ Status obtido com sucesso:\n";
    $json = json_decode($response, true);
    if ($json) {
        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        if (isset($json['qr'])) {
            echo "\n⚠️  ATENÇÃO: O bot retornou um QR Code. Isso significa que ele NÃO está conectado ao WhatsApp.\n";
            echo "   Acesse " . $config['base_url'] . "/qr para escanear.\n";
        }
    }
    else {
        echo "   Resposta: $response\n";
    }
}
else {
    echo "❌ Falha ao obter status (HTTP $httpCode)\n";
    echo "   Resposta: $response\n";
}