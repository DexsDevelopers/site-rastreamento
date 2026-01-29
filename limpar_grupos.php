<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<pre>";
echo "=== Limpando tabela de grupos ===\n\n";

try {
    $pdo->exec("DELETE FROM bot_grupos");
    echo "✅ Tabela 'bot_grupos' limpa com sucesso!\n";
    echo "ℹ️  O bot irá repovoar a lista de grupos assim que reconectar ou executar a sincronização obrigatória.\n";
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\nConcluído.</pre>";
