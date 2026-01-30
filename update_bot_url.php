<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Atualizar BOT_API_URL para porta 3001
$sql = "INSERT INTO bot_settings (chave, valor) VALUES ('BOT_API_URL', 'http://localhost:3001') ON DUPLICATE KEY UPDATE valor = 'http://localhost:3001'";
$pdo->exec($sql);

echo "Updated BOT_API_URL to 3001";
?>
