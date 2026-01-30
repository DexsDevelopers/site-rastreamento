<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketing_campanhas (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nome VARCHAR(100) NOT NULL,
            ativo BOOLEAN DEFAULT 0,
            membros_por_dia_grupo INT DEFAULT 5,
            intervalo_min_minutos INT DEFAULT 30,
            intervalo_max_minutos INT DEFAULT 120,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS marketing_mensagens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            campanha_id INT,
            ordem INT NOT NULL, -- 1 para a primeira, 2 para a segunda...
            tipo ENUM('texto', 'imagem', 'audio') DEFAULT 'texto',
            conteudo TEXT,
            delay_apos_anterior_minutos INT DEFAULT 0, -- Quanto tempo esperar após a mensagem anterior (0 = imediato)
            FOREIGN KEY (campanha_id) REFERENCES marketing_campanhas(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS marketing_membros (
            id INT PRIMARY KEY AUTO_INCREMENT,
            telefone VARCHAR(20) NOT NULL,
            grupo_origem_jid VARCHAR(100),
            nome VARCHAR(100),
            status ENUM('novo', 'em_progresso', 'concluido', 'bloqueado') DEFAULT 'novo',
            ultimo_passo_id INT DEFAULT 0, -- ID da ultima mensagem enviada
            data_proximo_envio DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (telefone, grupo_origem_jid)
        );
        
        -- Inserir campanha padrão se não existir
        INSERT IGNORE INTO marketing_campanhas (id, nome, ativo, membros_por_dia_grupo) VALUES (1, 'Campanha Padrão Grupos', 0, 5);
    ");
    
    echo "Tabelas de Marketing criadas com sucesso!";
} catch (PDOException $e) {
    echo "Erro ao criar tabelas: " . $e->getMessage();
}
