<?php
/**
 * Limpar Base de Conhecimento
 * Remove todos os conhecimentos para adicionar manualmente
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

requireLogin();

echo "<h2>🗑️ Limpar Base de Conhecimento</h2>";

try {
    // Contar quantos registros existem
    $count = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_ia_knowledge");
    $total = $count['total'] ?? 0;
    
    echo "<p>📊 Total de conhecimentos na base: <strong>{$total}</strong></p>";
    
    if ($total > 0) {
        // Limpar todos os conhecimentos
        $pdo->exec("DELETE FROM bot_ia_knowledge");
        $deleted = $pdo->rowCount();
        
        echo "<p style='color:#22c55e;'>✅ <strong>{$deleted} conhecimentos removidos com sucesso!</strong></p>";
        echo "<p>A base de conhecimento está vazia e pronta para você adicionar manualmente.</p>";
    } else {
        echo "<p style='color:#f59e0b;'>⚠️ A base de conhecimento já está vazia.</p>";
    }
    
    echo "<hr>";
    echo "<h3>Próximos passos:</h3>";
    echo "<ul>";
    echo "<li><a href='admin_bot_ia.php' style='color:#8B5CF6;'>→ Ir para Gerenciamento de Conhecimento</a></li>";
    echo "<li><a href='dashboard.php' style='color:#8B5CF6;'>→ Voltar ao Dashboard</a></li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

