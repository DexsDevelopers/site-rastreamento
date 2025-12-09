<?php
/**
 * Verificador de token - Testa conexão e mostra exatamente o que está sendo enviado
 */

require_once 'includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificador de Token - WhatsApp Bot</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #1e1e1e; color: #e0e0e0; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #4fc3f7; }
        .card { background: #2d2d2d; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .success { color: #4caf50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .info { background: #1565c0; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .test-btn { background: #4fc3f7; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 10px 5px; }
        .test-btn:hover { background: #29b6f6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificador de Token - WhatsApp Bot</h1>
        
        <?php
        $apiConfig = whatsappApiConfig();
        $token = $apiConfig['token'];
        $baseUrl = $apiConfig['base_url'];
        
        echo "<div class='card'>";
        echo "<h2>1. Configuração Atual</h2>";
        echo "<p><strong>URL Base:</strong> " . htmlspecialchars($baseUrl) . "</p>";
        echo "<p><strong>Token (config.json):</strong> " . htmlspecialchars(substr($token, 0, 4) . '***' . substr($token, -4)) . "</p>";
        echo "<p><strong>Token Completo:</strong> <code>" . htmlspecialchars($token) . "</code></p>";
        echo "<p><strong>Comprimento do Token:</strong> " . strlen($token) . " caracteres</p>";
        echo "</div>";
        
        // Ler .env
        $envPath = __DIR__ . '/whatsapp-bot/.env';
        echo "<div class='card'>";
        echo "<h2>2. Arquivo .env do Bot</h2>";
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            echo "<p class='success'>✅ Arquivo .env encontrado</p>";
            echo "<pre>" . htmlspecialchars($envContent) . "</pre>";
            
            // Extrair token do .env
            $tokenEnv = null;
            $envLines = explode("\n", $envContent);
            foreach ($envLines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] === '#') continue;
                if (preg_match('/^API_TOKEN\s*=\s*(.+)$/i', $line, $matches)) {
                    $tokenEnv = trim($matches[1], " \t\"'");
                    break;
                }
            }
            
            if ($tokenEnv) {
                echo "<p><strong>Token no .env:</strong> <code>" . htmlspecialchars($tokenEnv) . "</code></p>";
                echo "<p><strong>Comprimento:</strong> " . strlen($tokenEnv) . " caracteres</p>";
                
                if ($token === $tokenEnv) {
                    echo "<p class='success'>✅ Tokens correspondem!</p>";
                } else {
                    echo "<p class='error'>❌ Tokens NÃO correspondem!</p>";
                    echo "<p class='warning'>⚠️ Execute: <code>.\sync_whatsapp_token.ps1</code></p>";
                }
            } else {
                echo "<p class='error'>❌ Token não encontrado no .env!</p>";
            }
        } else {
            echo "<p class='error'>❌ Arquivo .env não encontrado em: " . htmlspecialchars($envPath) . "</p>";
        }
        echo "</div>";
        
        // Teste de conexão
        if (!empty($baseUrl) && !empty($token)) {
            echo "<div class='card'>";
            echo "<h2>3. Teste de Conexão</h2>";
            
            $statusUrl = rtrim($baseUrl, '/') . '/status';
            
            echo "<p><strong>URL de teste:</strong> " . htmlspecialchars($statusUrl) . "</p>";
            echo "<p><strong>Header enviado:</strong> <code>x-api-token: " . htmlspecialchars($token) . "</code></p>";
            
            $ch = curl_init($statusUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'x-api-token: ' . $token,
                    'ngrok-skip-browser-warning: true'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_VERBOSE => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);
            
            echo "<p><strong>HTTP Status Code:</strong> <span class='" . ($httpCode === 200 ? 'success' : ($httpCode === 401 ? 'error' : 'warning')) . "'>" . $httpCode . "</span></p>";
            
            if ($curlError) {
                echo "<p class='error'><strong>Erro cURL:</strong> " . htmlspecialchars($curlError) . "</p>";
            }
            
            if ($response) {
                $responseData = json_decode($response, true);
                echo "<h3>Resposta do Bot:</h3>";
                echo "<pre>" . htmlspecialchars(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
                
                if ($httpCode === 401) {
                    echo "<div class='info'>";
                    echo "<p class='error'><strong>❌ ERRO DE AUTENTICAÇÃO</strong></p>";
                    echo "<p>O bot retornou 401 Unauthorized. Isso significa:</p>";
                    echo "<ul>";
                    echo "<li>O token enviado: <code>" . htmlspecialchars($token) . "</code></li>";
                    echo "<li>Não corresponde ao token configurado no bot Node.js</li>";
                    echo "<li><strong>Solução:</strong> Execute <code>.\sync_whatsapp_token.ps1</code> e <strong>REINICIE o bot Node.js</strong></li>";
                    echo "</ul>";
                    echo "</div>";
                } elseif ($httpCode === 200) {
                    echo "<p class='success'>✅ Conexão bem-sucedida!</p>";
                }
            } else {
                echo "<p class='error'>❌ Nenhuma resposta recebida do bot</p>";
            }
            
            echo "</div>";
        }
        
        // Instruções
        echo "<div class='card'>";
        echo "<h2>📋 Instruções</h2>";
        echo "<ol>";
        echo "<li>Verifique se o token no config.json corresponde ao token no .env</li>";
        echo "<li>Se não corresponder, execute: <code>.\sync_whatsapp_token.ps1</code></li>";
        echo "<li><strong>IMPORTANTE:</strong> Após sincronizar, você DEVE reiniciar o bot Node.js</li>";
        echo "<li>O bot lê o .env apenas na inicialização, então mudanças no .env só têm efeito após reiniciar</li>";
        echo "<li>Para reiniciar: Pare o bot (Ctrl+C) e execute: <code>cd whatsapp-bot && npm run dev</code></li>";
        echo "</ol>";
        echo "</div>";
        ?>
        
        <div class="card">
            <button class="test-btn" onclick="location.reload()">🔄 Atualizar Teste</button>
            <button class="test-btn" onclick="window.open('test_token_sync.php', '_blank')">🧪 Teste JSON</button>
        </div>
    </div>
</body>
</html>
