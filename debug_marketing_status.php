<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "=== Marketing Messages ===\n";
$msgs = fetchData($pdo, "SELECT * FROM marketing_mensagens ORDER BY ordem ASC");
foreach ($msgs as $m) {
    echo "ID: {$m['id']} | Order: {$m['ordem']} | Type: {$m['tipo']} | Delay: {$m['delay_apos_anterior_minutos']} min\n";
    echo "Content: " . substr($m['conteudo'], 0, 50) . "...\n";
    echo "-------------------------\n";
}

echo "\n=== Marketing Members (Sample) ===\n";
$members = fetchData($pdo, "SELECT * FROM marketing_membros WHERE status = 'em_progresso' LIMIT 10");
foreach ($members as $m) {
    echo "ID: {$m['id']} | Phone: {$m['telefone']} | Status: {$m['status']} | Last Step: {$m['ultimo_passo_id']} | PID (DB): {$m['id']} | Next Send: {$m['data_proximo_envio']}\n";
    // Check if next step exists
    $nextStep = $m['ultimo_passo_id'] + 1;
    $hasMsg = fetchOne($pdo, "SELECT id FROM marketing_mensagens WHERE campanha_id = 1 AND ordem = ?", [$nextStep]);
    echo "Next Message Exists? " . ($hasMsg ? "Yes" : "No") . "\n";
    echo "-------------------------\n";
}

echo "\n=== Confirmed/Completed Members (Sample) ===\n";
$completed = fetchData($pdo, "SELECT count(*) as c FROM marketing_membros WHERE status = 'concluido'");
echo "Total Completed: " . $completed[0]['c'] . "\n";

echo "\n=== Blocked Members (Sample) ===\n";
$blocked = fetchData($pdo, "SELECT count(*) as c FROM marketing_membros WHERE status = 'bloqueado'");
echo "Total Blocked: " . $blocked[0]['c'] . "\n";
?>
