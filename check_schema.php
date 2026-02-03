<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "Table: bot_automation_logs\n";
$cols = fetchData($pdo, "DESCRIBE bot_automation_logs");
print_r($cols);

echo "\nTable: bot_automations\n";
$cols2 = fetchData($pdo, "DESCRIBE bot_automations");
print_r($cols2);
?>
