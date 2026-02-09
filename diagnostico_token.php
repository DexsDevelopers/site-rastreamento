<?php
require_once 'includes/whatsapp_helper.php';

$config = whatsappApiConfig();
$url = $config['base_url'] . '/status';
$tokenConfig = $config['token'];

echo "=== DIAGNÓSTICO AVANÇADO DE TOKEN ===\n";
echo "URL Alvo: $url\n";
echo "Token Local (config.json): '$tokenConfig'\n\n";

$tokensToTry = [
    'Do Config.json' => $tokenConfig,
    'Padrão Projeto' => 'lucastav8012',
    'Padrão Fábrica' => 'troque-este-token',
    'Financeiro Antigo' => 'site-financeiro-token-2024',
    'Simples' => '123456',
    'Admin' => 'admin',
    'Teste' => 'teste'
];

foreach ($tokensToTry as $label => $token) {
    if (empty($token))
        continue;

    echo "Testando '$label' (Token: $token)... ";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'x-api-token: ' . $token,
            'ngrok-skip-browser-warning: true'
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo "✅ SUCESSO! (HTTP $httpCode)\n";
        echo ">> O TOKEN CORRETO É: $token\n";
        echo ">> Atualize seu arquivo config.json com este valor.\n";
        exit;
    }
    elseif ($httpCode == 401) {
        echo "❌ Falha (401 Unauthorized)\n";
    }
    else {
        echo "⚠️ Erro HTTP $httpCode\n";
    }
}

echo "\n\n=== CONCLUSÃO ===\n";
echo "Nenhum dos tokens testados funcionou.\n";
echo "Possíveis causas:\n";
echo "1. O arquivo .env na Hostinger tem um token totalmente diferente.\n";
echo "2. O bot precisa ser reiniciado para pegar mudanças no .env.\n";
echo "3. Você deve acessar o Gerenciador de Arquivos da Hostinger, abrir a pasta do bot/site, editar o arquivo .env e verificar o valor de API_TOKEN.\n";
?>