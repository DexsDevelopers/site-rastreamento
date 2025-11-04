<?php
require_once __DIR__ . '/includes/config.php';

if (!isset($_GET['debug']) || $_GET['debug'] !== '1') {
    http_response_code(403);
    echo 'Acesso negado. Acrescente ?debug=1.';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

$systemLog = getConfig('LOG_FILE');
$automationLog = getConfig('AUTOMATION_LOG_FILE');

function readLastLines($filePath, $maxLines = 200) {
    if (!$filePath || !file_exists($filePath)) {
        return "Arquivo não encontrado: $filePath";
    }
    $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return "Não foi possível ler: $filePath";
    }
    $total = count($lines);
    $start = max(0, $total - $maxLines);
    $slice = array_slice($lines, $start);
    return htmlspecialchars(implode("\n", $slice));
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Viewer de Logs</title>
    <style>
        body { background:#0b0b0b; color:#eaeaea; font-family: Consolas, ui-monospace, SFMono-Regular, Menlo, Monaco, "Liberation Mono", "Courier New", monospace; padding:20px; }
        h2 { color:#ff6666; }
        .box { background:#121212; border:1px solid #2a2a2a; border-radius:8px; padding:12px; margin-bottom:24px; white-space:pre-wrap; overflow:auto; }
        .meta { color:#aaa; font-size:0.9rem; margin-bottom:8px; }
    </style>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
</head>
<body>
    <h1>Logs do Sistema (debug)</h1>
    <div class="meta">Exibindo as últimas 200 linhas</div>

    <h2>logs/system.log</h2>
    <div class="box"><?php echo readLastLines($systemLog, 200); ?></div>

    <h2>logs/automation.log</h2>
    <div class="box"><?php echo readLastLines($automationLog, 200); ?></div>

    <div class="meta">Atualizado em: <?php echo date('Y-m-d H:i:s'); ?></div>
</body>
</html>


