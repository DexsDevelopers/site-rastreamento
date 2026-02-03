<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/plain');

try {
    $count = executeQuery($pdo, "UPDATE bot_automations SET ativo = 1 WHERE nome != 'Campanha Marketing'");
    echo "Sucesso! $count automações foram ativadas.\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
