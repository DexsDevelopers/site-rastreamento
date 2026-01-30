<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    // Contar total de membros
    $total = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros");
    
    // Contar por status
    $stats = fetchData($pdo, "SELECT status, COUNT(*) as c FROM marketing_membros GROUP BY status");
    
    echo "=== Estatísticas de Membros ===\n";
    echo "Total: " . $total['c'] . "\n";
    foreach ($stats as $s) {
        echo "Status '" . $s['status'] . "': " . $s['c'] . "\n";
    }
    
    // Listar últimos 5 bloqueados
    $blocked = fetchData($pdo, "SELECT * FROM marketing_membros WHERE status = 'bloqueado' ORDER BY id DESC LIMIT 5");
    if (count($blocked) > 0) {
        echo "\n=== Últimos 5 Bloqueados ===\n";
        foreach ($blocked as $b) {
            echo "ID: " . $b['id'] . " - Tel: " . $b['telefone'] . "\n";
        }
    } else {
        echo "\nNenhum membro bloqueado encontrado.\n";
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
