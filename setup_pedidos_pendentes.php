<?php
/**
 * Script para criar tabela de pedidos pendentes
 * Execute este arquivo uma única vez
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Verificar se já foi executado
try {
    $checkTable = "SHOW TABLES LIKE 'pedidos_pendentes'";
    $result = $pdo->query($checkTable);
    
    if ($result->rowCount() > 0) {
        die("✅ A tabela 'pedidos_pendentes' já existe!");
    }
    
    echo "<h1>🚀 Criando Tabela de Pedidos Pendentes</h1>";
    
    // Criar tabela de pedidos pendentes
    $sql = "CREATE TABLE IF NOT EXISTS pedidos_pendentes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        telefone VARCHAR(20) NOT NULL,
        email VARCHAR(255) NULL,
        cep VARCHAR(10) NOT NULL,
        estado VARCHAR(2) NOT NULL,
        cidade VARCHAR(100) NOT NULL,
        bairro VARCHAR(100) NOT NULL,
        rua VARCHAR(255) NOT NULL,
        numero VARCHAR(20) NOT NULL,
        complemento VARCHAR(255) NULL,
        observacoes TEXT NULL,
        status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
        codigo_rastreio VARCHAR(50) NULL,
        data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_data_pedido (data_pedido),
        INDEX idx_telefone (telefone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "<p>✅ Tabela 'pedidos_pendentes' criada com sucesso!</p>";
    
} catch (PDOException $e) {
    die("❌ Erro ao criar tabela: " . $e->getMessage());
}
