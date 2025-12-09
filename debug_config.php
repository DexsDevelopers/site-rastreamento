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

// 1. Verificar arquivo config.json
$configPath = __DIR__ . '/config.json';
echo "1. ARQUIVO CONFIG.JSON:\n";
echo "   Caminho: $configPath\n";
echo "   Existe: " . (file_exists($configPath) ? 'SIM' : 'NÃO') . "\n";
echo "   Legível: " . (is_readable($configPath) ? 'SIM' : 'NÃO') . "\n";
echo "   Gravável: " . (is_writable($configPath) ? 'SIM' : 'NÃO') . "\n";

if (file_exists($configPath)) {
    $perms = substr(sprintf('%o', fileperms($configPath)), -4);
    echo "   Permissões: $perms\n";
    $owner = posix_getpwuid(fileowner($configPath));
    echo "   Dono: " . ($owner ? $owner['name'] : 'desconhecido') . "\n";
}

// 2. Conteúdo atual
echo "\n2. CONTEÚDO ATUAL:\n";
if (file_exists($configPath) && is_readable($configPath)) {
    $content = file_get_contents($configPath);
    $data = json_decode($content, true);
    
    if ($data) {
        echo "   JSON válido: SIM\n";
        echo "   Total de chaves: " . count($data) . "\n\n";
        
        // Configurações de Express
        echo "   EXPRESS_FEE_VALUE: " . ($data['EXPRESS_FEE_VALUE'] ?? 'não definido') . "\n";
        echo "   EXPRESS_PIX_KEY: " . ($data['EXPRESS_PIX_KEY'] ?? 'não definido') . "\n\n";
        
        // Mensagens WhatsApp
        $msgKeys = ['WHATSAPP_MSG_POSTADO', 'WHATSAPP_MSG_TRANSITO', 'WHATSAPP_MSG_DISTRIBUICAO', 
                    'WHATSAPP_MSG_ENTREGA', 'WHATSAPP_MSG_ENTREGUE', 'WHATSAPP_MSG_TAXA'];
        echo "   MENSAGENS WHATSAPP:\n";
        foreach ($msgKeys as $key) {
            $exists = isset($data[$key]);
            $length = $exists ? strlen($data[$key]) : 0;
            echo "   - $key: " . ($exists ? "definida ($length caracteres)" : "não definida") . "\n";
        }
    } else {
        echo "   JSON inválido!\n";
        echo "   Conteúdo bruto:\n";
        echo "   " . substr($content, 0, 500) . "...\n";
    }
} else {
    echo "   Arquivo não acessível\n";
}

// 3. Teste de escrita
echo "\n3. TESTE DE ESCRITA:\n";
$testKey = 'TEST_TIMESTAMP';
$testValue = date('Y-m-d H:i:s');
$result = setDynamicConfig($testKey, $testValue);

echo "   Tentando escrever '$testKey' = '$testValue'\n";
echo "   Resultado: " . ($result ? 'SUCESSO' : 'FALHA') . "\n";

// Verificar se foi escrito
$readBack = getDynamicConfig($testKey);
echo "   Lido de volta: " . ($readBack ?? 'null') . "\n";
echo "   Coincide: " . ($readBack === $testValue ? 'SIM' : 'NÃO') . "\n";

// 4. Teste das funções getDynamicConfig
echo "\n4. TESTE DE LEITURA:\n";
$expressVal = getDynamicConfig('EXPRESS_FEE_VALUE', 'padrão');
$expressPix = getDynamicConfig('EXPRESS_PIX_KEY', 'padrão');

echo "   EXPRESS_FEE_VALUE via getDynamicConfig: $expressVal\n";
echo "   EXPRESS_PIX_KEY via getDynamicConfig: $expressPix\n";

// 5. Verificar erro do PHP
echo "\n5. ÚLTIMOS ERROS PHP:\n";
$error = error_get_last();
if ($error) {
    echo "   Tipo: " . $error['type'] . "\n";
    echo "   Mensagem: " . $error['message'] . "\n";
    echo "   Arquivo: " . $error['file'] . "\n";
    echo "   Linha: " . $error['line'] . "\n";
} else {
    echo "   Nenhum erro recente\n";
}

// 6. Verificar diretório pai
echo "\n6. DIRETÓRIO RAIZ:\n";
$rootDir = __DIR__;
echo "   Caminho: $rootDir\n";
echo "   Gravável: " . (is_writable($rootDir) ? 'SIM' : 'NÃO') . "\n";

// 7. PHP Info relevante
echo "\n7. INFORMAÇÕES PHP:\n";
echo "   Usuário PHP: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'desconhecido') . "\n";
echo "   open_basedir: " . (ini_get('open_basedir') ?: 'não definido') . "\n";
echo "   file_uploads: " . (ini_get('file_uploads') ? 'habilitado' : 'desabilitado') . "\n";

echo "\n====== FIM DO DIAGNÓSTICO ======\n";



