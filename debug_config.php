<?php
/**
 * Diagnóstico de Configurações
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Verificar login
if (!isset($_SESSION['logado'])) {
    die('Acesso negado');
}

header('Content-Type: text/plain; charset=UTF-8');

echo "====== DIAGNÓSTICO DE CONFIGURAÇÕES ======\n\n";

// 1. Verificar arquivo config_custom.json (PERSONALIZAÇÕES)
$customPath = __DIR__ . '/config_custom.json';
echo "1. ARQUIVO CONFIG_CUSTOM.JSON (PERSONALIZAÇÕES):\n";
echo "   Caminho: $customPath\n";
echo "   Existe: " . (file_exists($customPath) ? 'SIM ✓' : 'NÃO') . "\n";
echo "   Legível: " . (is_readable($customPath) ? 'SIM ✓' : 'NÃO') . "\n";
echo "   Diretório gravável: " . (is_writable(__DIR__) ? 'SIM ✓' : 'NÃO ✗') . "\n";

if (file_exists($customPath)) {
    if (function_exists('posix_getpwuid')) {
        $perms = substr(sprintf('%o', fileperms($customPath)), -4);
        echo "   Permissões: $perms\n";
        $owner = @posix_getpwuid(fileowner($customPath));
        echo "   Dono: " . ($owner ? $owner['name'] : 'desconhecido') . "\n";
    }
    
    $content = file_get_contents($customPath);
    $data = json_decode($content, true);
    
    if ($data) {
        echo "   JSON válido: SIM ✓\n";
        echo "   Total de chaves: " . count($data) . "\n";
        echo "   Conteúdo:\n";
        foreach ($data as $key => $value) {
            $display = is_string($value) ? (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) : json_encode($value);
            echo "   - $key: $display\n";
        }
    } else {
        echo "   JSON inválido! ✗\n";
    }
} else {
    echo "   >>> ARQUIVO NÃO EXISTE - será criado no primeiro salvamento\n";
}

// 2. Verificar arquivo config.json (BASE)
$configPath = __DIR__ . '/config.json';
echo "\n2. ARQUIVO CONFIG.JSON (BASE - não editável):\n";
echo "   Caminho: $configPath\n";
echo "   Existe: " . (file_exists($configPath) ? 'SIM' : 'NÃO') . "\n";
echo "   Legível: " . (is_readable($configPath) ? 'SIM' : 'NÃO') . "\n";

if (file_exists($configPath) && is_readable($configPath)) {
    $content = file_get_contents($configPath);
    $data = json_decode($content, true);
    
    if ($data) {
        echo "   JSON válido: SIM\n";
        echo "   Total de chaves: " . count($data) . "\n";
    } else {
        echo "   JSON inválido!\n";
    }
} else {
    echo "   Arquivo não acessível\n";
}

// 3. Teste de escrita em config_custom.json
echo "\n3. TESTE DE ESCRITA (config_custom.json):\n";
$testKey = 'TEST_TIMESTAMP';
$testValue = date('Y-m-d H:i:s');
echo "   Tentando escrever '$testKey' = '$testValue'...\n";
$result = setDynamicConfig($testKey, $testValue);
echo "   Resultado: " . ($result ? 'SUCESSO ✓' : 'FALHA ✗') . "\n";

// Verificar se foi escrito
clearstatcache(true, $customPath);
$readBack = getDynamicConfig($testKey);
echo "   Lido de volta: " . ($readBack ?? 'null') . "\n";
echo "   Coincide: " . ($readBack === $testValue ? 'SIM ✓' : 'NÃO ✗') . "\n";

// 4. Valores atuais das configurações
echo "\n4. VALORES ATUAIS DAS CONFIGURAÇÕES:\n";
$pedidoPix = getDynamicConfig('PEDIDO_PIX_KEY', '');
$expressVal = getDynamicConfig('EXPRESS_FEE_VALUE', 29.90);
$expressPix = getDynamicConfig('EXPRESS_PIX_KEY', '');

echo "   PEDIDO_PIX_KEY: " . ($pedidoPix ?: '(não definido)') . "\n";
echo "   EXPRESS_FEE_VALUE: $expressVal\n";
echo "   EXPRESS_PIX_KEY: " . ($expressPix ?: '(não definido)') . "\n";

// Mensagens WhatsApp
$msgKeys = ['WHATSAPP_MSG_POSTADO', 'WHATSAPP_MSG_TRANSITO', 'WHATSAPP_MSG_DISTRIBUICAO', 
            'WHATSAPP_MSG_ENTREGA', 'WHATSAPP_MSG_ENTREGUE', 'WHATSAPP_MSG_TAXA'];
echo "\n   MENSAGENS WHATSAPP:\n";
foreach ($msgKeys as $key) {
    $val = getDynamicConfig($key, null);
    if ($val) {
        echo "   - $key: PERSONALIZADA (" . strlen($val) . " caracteres)\n";
    } else {
        echo "   - $key: usando padrão do sistema\n";
    }
}

// 5. Verificar erro do PHP
echo "\n5. ÚLTIMOS ERROS PHP:\n";
$error = error_get_last();
if ($error) {
    echo "   Tipo: " . $error['type'] . "\n";
    echo "   Mensagem: " . $error['message'] . "\n";
    echo "   Arquivo: " . $error['file'] . "\n";
    echo "   Linha: " . $error['line'] . "\n";
} else {
    echo "   Nenhum erro recente ✓\n";
}

// 6. Verificar diretório raiz
echo "\n6. DIRETÓRIO RAIZ:\n";
$rootDir = __DIR__;
echo "   Caminho: $rootDir\n";
echo "   Gravável: " . (is_writable($rootDir) ? 'SIM ✓' : 'NÃO ✗') . "\n";

// 7. PHP Info relevante
echo "\n7. INFORMAÇÕES PHP:\n";
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $userInfo = @posix_getpwuid(posix_geteuid());
    echo "   Usuário PHP: " . ($userInfo['name'] ?? 'desconhecido') . "\n";
} else {
    echo "   Usuário PHP: N/A (Windows)\n";
}
echo "   open_basedir: " . (ini_get('open_basedir') ?: 'não definido') . "\n";

echo "\n====== FIM DO DIAGNÓSTICO ======\n";
echo "\n>>> Personalizações são salvas em config_custom.json\n";
echo ">>> Este arquivo NÃO é sobrescrito por deploys do GitHub!\n";
