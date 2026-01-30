<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    $columns = fetchData($pdo, "SHOW COLUMNS FROM marketing_membros");
    echo "Columns in marketing_membros:\n";
    foreach ($columns as $c) {
        echo "- " . $c['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
