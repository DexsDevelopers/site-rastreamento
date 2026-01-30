<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Atualizar URLs e Tokens no banco
$sql = "INSERT INTO bot_settings (chave, valor) VALUES 
    ('BOT_API_URL', 'https://leola-formulable-iridescently.ngrok-free.dev'),
    ('WHATSAPP_API_URL', 'https://leola-formulable-iridescently.ngrok-free.dev')
    ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
$pdo->exec($sql);

echo "Updated BOT_API_URL and WHATSAPP_API_URL to Ngrok URL";
?>
