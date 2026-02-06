<?php
/**
 * Script para adicionar o campo CPF na tabela de pedidos pendentes
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    echo "<h1>🚀 Atualizando Tabela de Pedidos Pendentes</h1>";
    
    // Verificar se a coluna já existe
    $checkColumn = $pdo->query("SHOW COLUMNS FROM pedidos_pendentes LIKE 'cpf'");
    
    if ($checkColumn->rowCount() === 0) {
        $sql = "ALTER TABLE pedidos_pendentes ADD COLUMN cpf VARCHAR(20) NULL AFTER email";
        $pdo->exec($sql);
        echo "<p>✅ Coluna 'cpf' adicionada com sucesso!</p>";
    } else {
        echo "<p>ℹ️ A coluna 'cpf' já existe.</p>";
    }
    
} catch (PDOException $e) {
    die("❌ Erro ao atualizar tabela: " . $e->getMessage());
}
