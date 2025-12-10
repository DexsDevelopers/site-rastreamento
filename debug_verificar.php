<?php
/**
 * Debug simples para verificar_token_bot.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug - Verificador de Token</h1>";
echo "<pre>";

try {
    echo "1. Verificando includes...\n";
    require_once 'includes/config.php';
    echo "   ✅ includes/config.php carregado\n";
    
    echo "\n2. Verificando função whatsappApiConfig...\n";
    if (function_exists('whatsappApiConfig')) {
        echo "   ✅ Função whatsappApiConfig existe\n";
        
        echo "\n3. Chamando whatsappApiConfig()...\n";
        $apiConfig = whatsappApiConfig();
        echo "   ✅ Retornou: ";
        print_r($apiConfig);
        
        echo "\n4. Verificando token...\n";
        $token = $apiConfig['token'] ?? 'NÃO DEFINIDO';
        echo "   Token: " . ($token ? substr($token, 0, 4) . '***' : 'VAZIO') . "\n";
        
        echo "\n5. Verificando base_url...\n";
        $baseUrl = $apiConfig['base_url'] ?? 'NÃO DEFINIDO';
        echo "   URL: $baseUrl\n";
        
        echo "\n6. Testando arquivo .env...\n";
        $envPath = __DIR__ . '/whatsapp-bot/.env';
        if (file_exists($envPath)) {
            echo "   ✅ Arquivo .env existe: $envPath\n";
            $envContent = file_get_contents($envPath);
            echo "   Conteúdo:\n";
            echo "   " . str_replace("\n", "\n   ", $envContent) . "\n";
        } else {
            echo "   ❌ Arquivo .env NÃO existe: $envPath\n";
        }
        
    } else {
        echo "   ❌ Função whatsappApiConfig NÃO existe!\n";
        echo "   Verificando includes/whatsapp_helper.php...\n";
        if (file_exists('includes/whatsapp_helper.php')) {
            require_once 'includes/whatsapp_helper.php';
            echo "   ✅ includes/whatsapp_helper.php carregado\n";
            if (function_exists('whatsappApiConfig')) {
                echo "   ✅ Agora a função existe!\n";
                $apiConfig = whatsappApiConfig();
                print_r($apiConfig);
            } else {
                echo "   ❌ Ainda não existe após carregar helper\n";
            }
        } else {
            echo "   ❌ Arquivo includes/whatsapp_helper.php não encontrado\n";
        }
    }
    
} catch (Throwable $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
