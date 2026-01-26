<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/plain');

try {
    echo "=== Tables ===\n";
    $tables = fetchData($pdo, "SHOW TABLES");
    foreach ($tables as $t) {
        echo current($t) . "\n";
    }

    echo "\n=== Columns in whatsapp_contatos ===\n";
    try {
        $cols = fetchData($pdo, "SHOW COLUMNS FROM whatsapp_contatos");
        foreach ($cols as $c) {
            echo $c['Field'] . " (" . $c['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "Table whatsapp_contatos NOT FOUND or Error: " . $e->getMessage() . "\n";
    }

    echo "\n=== Columns in pedidos_pendentes ===\n";
    try {
        $cols = fetchData($pdo, "SHOW COLUMNS FROM pedidos_pendentes");
        foreach ($cols as $c) {
            echo $c['Field'] . " (" . $c['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "Table pedidos_pendentes NOT FOUND or Error: " . $e->getMessage() . "\n";
    }

    // Check one pending order to see keys
    echo "\n=== First Pending Order Keys ===\n";
    $p = fetchOne($pdo, "SELECT * FROM pedidos_pendentes LIMIT 1");
    if ($p) {
        print_r(array_keys($p));
    } else {
        echo "No pending orders found.\n";
    }

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage();
}
