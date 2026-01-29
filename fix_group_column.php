<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<pre>";
echo "=== Atualizando esquema do banco de dados ===\n\n";

try {
    // Alterar bot_automations para suportar múltiplos grupos
    $pdo->exec("ALTER TABLE bot_automations MODIFY COLUMN grupo_id TEXT DEFAULT NULL COMMENT 'JIDs dos grupos (CSV) ou NULL para todos'");
    echo "✅ Coluna grupo_id alterada para TEXT com sucesso!\n";
    
    // Alterar bot_automation_logs para suportar múltiplos grupos (se necessário, mas lá é grupo_id do disparo)
    // No log, grupo_id é onde aconteceu, então é singular. Não precisa mudar.
    
} catch (PDOException $e) {
    echo "ℹ️ Nota: " . $e->getMessage() . "\n";
}

echo "\nConcluído.</pre>";
