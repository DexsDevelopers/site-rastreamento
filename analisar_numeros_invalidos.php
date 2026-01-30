<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE NÚMEROS DE TELEFONE ===\n\n";

try {
    // 1. Estatísticas de comprimento
    $sql = "SELECT LENGTH(telefone) as tamanho, COUNT(*) as qtd 
            FROM marketing_membros 
            GROUP BY LENGTH(telefone) 
            ORDER BY tamanho DESC";
    
    $stats = fetchData($pdo, $sql);
    
    echo "Distribuição de Tamanho dos Números:\n";
    echo str_pad("Tamanho", 10) . str_pad("Quantidade", 15) . "Status Provável\n";
    echo str_repeat("-", 40) . "\n";
    
    foreach ($stats as $row) {
        $len = $row['tamanho'];
        $qtd = $row['qtd'];
        $status = "OK";
        
        if ($len < 10) $status = "Muito Curto (Inválido)";
        if ($len > 13) $status = "Muito Longo (Suspeito)";
        if ($len == 12 || $len == 13) $status = "Padrão BR";
        
        echo str_pad($len, 10) . str_pad($qtd, 15) . $status . "\n";
    }
    
    echo "\n";
    
    // 2. Listar exemplos dos números "Muito Longos" (>13)
    echo "=== Exemplos de Números Suspeitos (>13 dígitos) ===\n";
    $suspeitos = fetchData($pdo, "SELECT id, telefone, nome, grupo_origem_jid FROM marketing_membros WHERE LENGTH(telefone) > 13 LIMIT 20");
    
    if (empty($suspeitos)) {
        echo "Nenhum número suspeito encontrado.\n";
    } else {
        foreach ($suspeitos as $row) {
            echo "ID: " . $row['id'] . " | Tel: " . $row['telefone'] . " (Len: " . strlen($row['telefone']) . ")\n";
        }
    }
    
    echo "\n=== ANÁLISE ===\n";
    echo "Números brasileiros (com DDI 55) costumam ter 12 ou 13 dígitos.\n";
    echo "Ex: 55 11 99999-8888 (13 dígitos)\n";
    echo "Se você vê números com 16, 17 ou mais dígitos, eles provavelmente foram importados incorretamente.\n";

} catch (Exception $e) {
    echo "Erro ao conectar ao banco: " . $e->getMessage();
}
?>
