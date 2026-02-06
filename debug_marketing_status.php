<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Set Timezone
date_default_timezone_set('America/Sao_Paulo');
$pdo->exec("SET time_zone = '-03:00'");

echo "<h1>Marketing Debug Status</h1>";
echo "<p>Server Time (PHP): " . date('Y-m-d H:i:s') . "</p>";

$now = fetchOne($pdo, "SELECT NOW() as t")['t'];
echo "<p>DB Time (NOW): $now</p>";

$stats = fetchData($pdo, "SELECT status, count(*) as c FROM marketing_membros GROUP BY status");
echo "<h3>Stats by Status:</h3><ul>";
foreach($stats as $s) {
    echo "<li>{$s['status']}: {$s['c']}</li>";
}
echo "</ul>";

$pending = fetchData($pdo, "SELECT id, telefone, data_proximo_envio, TIMESTAMPDIFF(MINUTE, NOW(), data_proximo_envio) as diff_mins FROM marketing_membros WHERE status = 'em_progresso' ORDER BY data_proximo_envio ASC LIMIT 20");

echo "<h3>Top 20 'Em Progresso' (Ordered by Date):</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Phone</th><th>Next Send</th><th>Diff Mins (Neg=Past, Pos=Future)</th></tr>";
foreach($pending as $p) {
    echo "<tr>";
    echo "<td>{$p['id']}</td>";
    echo "<td>{$p['telefone']}</td>";
    echo "<td>{$p['data_proximo_envio']}</td>";
    echo "<td>{$p['diff_mins']}</td>";
    echo "</tr>";
}
echo "</table>";

$logs = fetchData($pdo, "SELECT * FROM bot_automation_logs ORDER BY criado_em DESC LIMIT 10");
echo "<h3>Last 10 Logs:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Time</th><th>Status</th><th>Msg</th></tr>";
foreach($logs as $l) {
    echo "<tr>";
    echo "<td>{$l['criado_em']}</td>";
    echo "<td>{$l['mensagem_recebida']}</td>";
    echo "<td>{$l['resposta_enviada']}</td>";
    echo "</tr>";
}
echo "</table>";
?>
