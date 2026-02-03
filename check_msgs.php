<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/plain');

echo "=== Mensagens de Marketing ===\n";
$msgs = fetchData($pdo, "SELECT * FROM marketing_mensagens ORDER BY ordem ASC");
print_r($msgs);

echo "\n=== Campanha ===\n";
print_r(fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id = 1"));
?>
