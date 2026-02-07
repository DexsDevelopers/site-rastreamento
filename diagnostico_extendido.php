<?php
/**
 * Diagnóstico do Servidor - Extendido
 */

header('Content-Type: text/html; charset=utf-8');

function checkBOM($filename)
{
    if (!file_exists($filename))
        return "Arquivo não existe";
    $content = file_get_contents($filename);
    $bom = pack('H*', 'EFBBBF');
    if (substr($content, 0, 3) == $bom)
        return "DETECTADO (UTF-8 BOM)";
    return "Nenhum BOM detectado";
}

function checkPermissions($filename)
{
    if (!file_exists($filename))
        return "Arquivo não existe";
    $perms = fileperms($filename);
    $info = substr(sprintf('%o', $perms), -4);
    $owner = posix_getpwuid(fileowner($filename))['name'];
    return "Permissões: $info | Dono: $owner";
}

// Lista de arquivos para verificar
$files = [
    'api_marketing.php',
    'includes/config.php',
    'includes/db_connect.php',
    'update_ngrok_auto.php'
];

echo "<h1>Diagnóstico Extendido</h1>";

foreach ($files as $file) {
    echo "<h3>Arquivo: $file</h3>";
    echo "<ul>";
    echo "<li>BOM: " . checkBOM($file) . "</li>";
    echo "<li>Permissões: " . checkPermissions($file) . "</li>";

    // Check syntax via eval (dangerous but useful here locally)
    /* actually executing risky code on server might be blocked */

    echo "</ul>";
}

// Tentar rodar o api_marketing.php via include para capturar erro
echo "<h3>Teste de Include: api_marketing.php</h3>";
echo "<pre>";

// Mock inputs
$_GET['action'] = 'test_syntax';
$_SERVER['HTTP_X_API_TOKEN'] = 'test';

try {
    // Buffer output to catch "headers already sent"
    ob_start();
    include 'api_marketing.php';
    $output = ob_get_clean();
    echo "Include executado com sucesso.\n";
    echo "Output: " . htmlspecialchars($output);
}
catch (Throwable $e) {
    echo "ERRO FATAL AO INCLUIR: " . $e->getMessage();
    echo "\nLine: " . $e->getLine();
    echo "\nFile: " . $e->getFile();
}
echo "</pre>";
?>