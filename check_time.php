<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/plain');

$phpTime = date('Y-m-d H:i:s');
$mysqlTime = fetchOne($pdo, "SELECT NOW() as n")['n'];

echo "PHP Time: $phpTime\n";
echo "MySQL Time: $mysqlTime\n";

$res = fetchData($pdo, "SELECT id, data_proximo_envio FROM marketing_membros WHERE status = 'em_progresso' AND data_proximo_envio <= NOW() LIMIT 5");
echo "Membros prontos (SQL): " . count($res) . "\n";
print_r($res);
?>
