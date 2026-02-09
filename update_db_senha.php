<?php
try {
    require_once 'includes/config.php';
    require_once 'includes/db_connect.php';

    // Verificar se a coluna já existe antes de tentar adicionar
    $stmt = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'senha'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN senha VARCHAR(255) AFTER email");
        echo "✅ Coluna 'senha' adicionada à tabela 'clientes' com sucesso!";
    }
    else {
        echo "ℹ️ A coluna 'senha' já existe na tabela 'clientes'.";
    }
}
catch (Exception $e) {
    echo "❌ Erro ao atualizar tabela: " . $e->getMessage();
}
?>