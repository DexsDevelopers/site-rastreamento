<?php
// Script de Debug do WhatsApp
// Acesse via navegador: http://localhost/site-rastreamento/debug_whatsapp.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';

echo "<h1>🕵️ Debug WhatsApp</h1>";

// 1. Verificar Configuração
$config = whatsappApiConfig();
echo "<h2>1. Configuração</h2>";
echo "<ul>";
echo "<li><strong>URL:</strong> " . htmlspecialchars($config['base_url']) . "</li>";
echo "<li><strong>Token:</strong> " . ($config['token'] ? 'Configurado (oculto)' : '❌ NÃO CONFIGURADO') . "</li>";
echo "<li><strong>Ativo:</strong> " . ($config['enabled'] ? 'Sim' : 'Não') . "</li>";
echo "</ul>";

if (!$config['enabled']) {
    die("<p>❌ API desativada. Verifique config.json.</p>");
}

// 2. Teste de Conexão (Status)
echo "<h2>2. Teste de Conexão (/status)</h2>";
echo "<p>Tentando conectar... (Timeout: 10s)</p>";

$ch = curl_init($config['base_url'] . '/status');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'x-api-token: ' . $config['token'],
        'ngrok-skip-browser-warning: true'
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_VERBOSE => true
]);

$start = microtime(true);
$response = curl_exec($ch);
$end = microtime(true);
$duration = number_format($end - $start, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p><strong>Tempo:</strong> {$duration}s</p>";
echo "<p><strong>HTTP Code:</strong> {$httpCode}</p>";

if ($response === false) {
    echo "<p style='color:red'><strong>❌ Erro cURL:</strong> {$error}</p>";
    echo "<p>Dica: O bot está rodando? O ngrok está ativo?</p>";
} else {
    echo "<p><strong>Resposta:</strong></p>";
    echo "<pre style='background:#f4f4f4;padding:10px;'>" . htmlspecialchars($response) . "</pre>";

    $data = json_decode($response, true);
    if ($data && isset($data['ready']) && $data['ready']) {
        echo "<p style='color:green'><strong>✅ Bot Conectado e Pronto!</strong></p>";
    } else {
        echo "<p style='color:orange'><strong>⚠️ Bot Online mas não pronto.</strong> (Escaneie o QR Code)</p>";
    }
}

// 3. Teste de Envio (Send)
if (isset($_GET['test_send'])) {
    echo "<h2>3. Teste de Envio (/send)</h2>";
    $telefone = $_GET['test_send']; // Número para teste

    echo "<p>Enviando mensagem de teste para: $telefone ...</p>";

    $res = sendWhatsappMessage($telefone, "Teste de conexão do sistema de rastreio - " . date('H:i:s'));

    if ($res['success']) {
        echo "<p style='color:green'><strong>✅ Mensagem enviada com sucesso!</strong></p>";
    } else {
        echo "<p style='color:red'><strong>❌ Falha no envio:</strong> " . htmlspecialchars($res['error']) . "</p>";
    }
    echo "<pre>" . print_r($res, true) . "</pre>";
} else {
    echo "<h2>3. Teste de Envio</h2>";
    echo "<p>Para testar o envio, adicione <code>?test_send=5511999999999</code> na URL deste arquivo.</p>";
}
?>