<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    // Modify the enum to include 'enviando'
    $pdo->exec("ALTER TABLE marketing_membros MODIFY COLUMN status ENUM('novo', 'em_progresso', 'concluido', 'bloqueado', 'enviando') DEFAULT 'novo'");
    echo "Tabela marketing_membros atualizada com sucesso! Status 'enviando' adicionado.\n";
    
    // Also reset any stuck 'enviando' or broken status to 'em_progresso' and push time forward to avoid immediate loop
    $pdo->exec("UPDATE marketing_membros SET status = 'em_progresso', data_proximo_envio = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE status = '' OR status IS NULL OR status = 'enviando'");
    echo "Limpeza de status concluída.\n";

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
