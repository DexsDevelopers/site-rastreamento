<?php
/**
 * Página de debug para verificar configurações de admin WhatsApp
 */

require_once 'includes/config.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "====== DEBUG ADMIN WHATSAPP ======\n\n";

// 1. Verificar números admin no config.json
$adminNumbers = getDynamicConfig('ADMIN_WHATSAPP_NUMBERS', []);
echo "1. Números admin no config.json:\n";
echo "   Tipo: " . gettype($adminNumbers) . "\n";
echo "   Valor bruto: " . json_encode($adminNumbers) . "\n";

// 2. Processar números (mesma lógica da API)
if (!is_array($adminNumbers)) {
    $adminNumbers = array_map('trim', explode(',', $adminNumbers));
}
$adminNumbers = array_filter(array_map(function($num) {
    return preg_replace('/\D/', '', $num);
}, $adminNumbers));

echo "\n2. Números processados:\n";
foreach ($adminNumbers as $num) {
    echo "   - $num\n";
}

// 3. Testar números específicos
$testNumbers = [
    '5551996148568',
    '5537991101425',
    '551996148568',
    '37991101425'
];

echo "\n3. Teste de verificação:\n";
foreach ($testNumbers as $test) {
    $isAdmin = in_array($test, $adminNumbers);
    echo "   $test: " . ($isAdmin ? '✅ ADMIN' : '❌ NÃO ADMIN') . "\n";
}

// 4. Token
$token = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');
echo "\n4. Token configurado: $token\n";

// 5. Últimos logs
echo "\n5. Últimos logs de comando WhatsApp:\n";
$logFile = 'logs/system.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $whatsappLogs = array_filter($lines, function($line) {
        return strpos($line, 'WhatsApp') !== false || strpos($line, 'DEBUG') !== false;
    });
    $last10 = array_slice($whatsappLogs, -10);
    foreach ($last10 as $log) {
        echo "   " . trim($log) . "\n";
    }
} else {
    echo "   Arquivo de log não encontrado\n";
}

echo "\n====== FIM DEBUG ======\n";

