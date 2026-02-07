<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("SELECT id, telefone, grupo_origem_jid, status FROM marketing_membros LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($rows);
echo "</pre>";
?>