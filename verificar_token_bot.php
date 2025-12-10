<?php
/**
 * Verificador de token - Testa conexão e mostra exatamente o que está sendo enviado
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carregar dependências ANTES de qualquer output
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/whatsapp_helper.php';

// Verificar se a função existe
if (!function_exists('whatsappApiConfig')) {
    die("❌ Erro: Função whatsappApiConfig não encontrada. Verifique se includes/whatsapp_helper.php foi carregado corretamente.");
}

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
        // Função já foi verificada no topo do arquivo
        $apiConfig = whatsappApiConfig();
        $token = $apiConfig['token'] ?? '';
        $baseUrl = $apiConfig['base_url'] ?? '';
        
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
                
                // Comparação detalhada
                if ($token === $tokenEnv) {
                    echo "<p class='success'>✅ Tokens correspondem perfeitamente!</p>";
                } else {
                    echo "<p class='error'>❌ Tokens NÃO correspondem!</p>";
                    
                    // Análise detalhada da diferença
                    echo "<div class='info'>";
                    echo "<h3>🔍 Análise da Diferença:</h3>";
                    echo "<ul>";
                    echo "<li>Comprimento JSON: " . strlen($token) . " caracteres</li>";
                    echo "<li>Comprimento .env: " . strlen($tokenEnv) . " caracteres</li>";
                    
                    if (strlen($token) !== strlen($tokenEnv)) {
                        echo "<li class='error'>⚠️ Os tokens têm tamanhos diferentes!</li>";
                    }
                    
                    // Comparar byte a byte
                    $diffPos = [];
                    $minLen = min(strlen($token), strlen($tokenEnv));
                    for ($i = 0; $i < $minLen; $i++) {
                        if ($token[$i] !== $tokenEnv[$i]) {
                            $diffPos[] = $i;
                        }
                    }
                    if (count($diffPos) > 0) {
                        echo "<li>Diferenças encontradas em " . count($diffPos) . " posição(ões)</li>";
                        if (count($diffPos) <= 10) {
                            echo "<li>Posições com diferença: " . implode(', ', $diffPos) . "</li>";
                        }
                    }
                    
                    // Verificar caracteres invisíveis
                    $tokenBytes = unpack('C*', $token);
                    $envBytes = unpack('C*', $tokenEnv);
                    $hasInvisible = false;
                    foreach ($tokenBytes as $byte) {
                        if ($byte < 32 && !in_array($byte, [9, 10, 13])) {
                            $hasInvisible = true;
                            break;
                        }
                    }
                    if ($hasInvisible) {
                        echo "<li class='warning'>⚠️ Token pode conter caracteres invisíveis ou especiais</li>";
                    }
                    
                    echo "</ul>";
                    echo "<p><strong>Solução:</strong></p>";
                    echo "<ol>";
                    echo "<li>Execute: <code>.\scripts\sync_whatsapp_token.ps1</code></li>";
                    echo "<li><strong>REINICIE o bot Node.js</strong> (Ctrl+C e depois <code>npm run dev</code>)</li>";
                    echo "</ol>";
                    echo "</div>";
                }
                
                // Mostrar representação hexadecimal para debug
                if ($token !== $tokenEnv) {
                    echo "<details style='margin-top: 10px;'>";
                    echo "<summary style='cursor: pointer; color: #4fc3f7;'>🔬 Ver representação hexadecimal (debug)</summary>";
                    echo "<pre style='margin-top: 10px;'>";
                    echo "Token JSON:  " . bin2hex($token) . "\n";
                    echo "Token .env:  " . bin2hex($tokenEnv) . "\n";
                    echo "</pre>";
                    echo "</details>";
                }
            } else {
                echo "<p class='error'>❌ Token não encontrado no .env!</p>";
                    echo "<p class='warning'>⚠️ Execute: <code>.\scripts\sync_whatsapp_token.ps1</code> para criar/configurar</p>";
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
                    echo "<p class='error'><strong>❌ ERRO DE AUTENTICAÇÃO (401 Unauthorized)</strong></p>";
                    echo "<p>O bot retornou 401 Unauthorized. Isso significa:</p>";
                    echo "<ul>";
                    echo "<li>Token enviado no header: <code>" . htmlspecialchars($token) . "</code></li>";
                    echo "<li>Token esperado pelo bot: <strong>NÃO CORRESPONDE</strong></li>";
                    echo "<li>O bot Node.js está usando um token diferente do que está no .env OU o .env não foi recarregado</li>";
                    echo "</ul>";
                    echo "<div style='background: #ff5722; padding: 15px; border-radius: 4px; margin-top: 10px;'>";
                    echo "<p><strong>🔴 AÇÃO URGENTE NECESSÁRIA:</strong></p>";
                    echo "<ol>";
                    echo "<li><strong>Execute o script:</strong> <code>.\sync_whatsapp_token.ps1</code></li>";
                    echo "<li><strong>PARAR o bot Node.js:</strong> Pressione <code>Ctrl+C</code> no terminal</li>";
                    echo "<li><strong>REINICIAR o bot:</strong> <code>cd whatsapp-bot && npm run dev</code></li>";
                    echo "<li><strong>Testar novamente:</strong> Clique no botão '🔄 Atualizar Teste' acima</li>";
                    echo "</ol>";
                    echo "<p style='margin-top: 10px;'><strong>⚠️ IMPORTANTE:</strong> O Node.js lê o arquivo .env apenas quando inicia. Mudanças no .env só têm efeito após reiniciar o processo!</p>";
                    echo "</div>";
                    echo "</div>";
                } elseif ($httpCode === 200) {
                    echo "<div style='background: #4caf50; padding: 15px; border-radius: 4px; margin-top: 10px;'>";
                    echo "<p class='success'><strong>✅ Conexão bem-sucedida!</strong></p>";
                    if ($responseData && isset($responseData['ready'])) {
                        if ($responseData['ready']) {
                            echo "<p>✅ Bot está conectado ao WhatsApp e pronto para enviar mensagens</p>";
                        } else {
                            echo "<p>⚠️ Bot está online mas não está conectado ao WhatsApp ainda</p>";
                        }
                    }
                    echo "</div>";
                } elseif ($httpCode === 0 || $curlError) {
                    echo "<div class='warning'>";
                    echo "<p><strong>⚠️ Erro de conexão</strong></p>";
                    echo "<p>Não foi possível conectar ao bot. Verifique:</p>";
                    echo "<ul>";
                    echo "<li>Se o bot Node.js está rodando</li>";
                    echo "<li>Se a URL do ngrok está correta</li>";
                    echo "<li>Se há firewall bloqueando</li>";
                    echo "</ul>";
                    echo "</div>";
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
        echo "<li>Se não corresponder, execute: <code>.\scripts\sync_whatsapp_token.ps1</code></li>";
        echo "<li><strong>IMPORTANTE:</strong> Após sincronizar, você DEVE reiniciar o bot Node.js</li>";
        echo "<li>O bot lê o .env apenas na inicialização, então mudanças no .env só têm efeito após reiniciar</li>";
        echo "<li>Para reiniciar: Pare o bot (Ctrl+C) e execute: <code>cd whatsapp-bot && npm run dev</code></li>";
        echo "</ol>";
        echo "</div>";
        ?>
        
        <div class="card">
            <h3>🔧 Ações Rápidas</h3>
            <button class="test-btn" onclick="location.reload()">🔄 Atualizar Teste</button>
            <button class="test-btn" onclick="window.open('test_token_sync.php', '_blank')">🧪 Teste JSON</button>
            <button class="test-btn" onclick="window.open('test_whatsapp_endpoint.php?codigo=GH56YJ1474BR', '_blank')">📱 Testar Envio Completo</button>
            <button class="test-btn" onclick="if(confirm('Isso abrirá o PowerShell para executar o script de sincronização. Continuar?')) { window.open('powershell://./scripts/sync_whatsapp_token.ps1', '_blank'); }">🔐 Sincronizar Token</button>
        </div>
        
        <div class="card" style="background: #263238; border-left: 4px solid #4fc3f7;">
            <h3>💡 Dica Pro</h3>
            <p>Se você acabou de sincronizar o token mas ainda recebe erro 401:</p>
            <ol>
                <li>Verifique se o processo Node.js do bot foi <strong>completamente encerrado</strong></li>
                <li>No Windows, use o Gerenciador de Tarefas para garantir que não há processos Node.js antigos rodando</li>
                <li>Reinicie o bot e aguarde alguns segundos antes de testar novamente</li>
            </ol>
        </div>
    </div>
</body>
</html>
