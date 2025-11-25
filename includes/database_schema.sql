-- Schema para sistema de indicação com prioridade
-- Execute este SQL no seu banco de dados

-- Tabela de indicações
CREATE TABLE IF NOT EXISTS indicacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_indicador VARCHAR(50) NOT NULL,
    codigo_indicado VARCHAR(50) NOT NULL,
    data_indicacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'confirmada', 'entregue') DEFAULT 'pendente',
    prioridade BOOLEAN DEFAULT TRUE,
    data_entrega_prevista DATE,
    INDEX idx_codigo_indicador (codigo_indicador),
    INDEX idx_codigo_indicado (codigo_indicado),
    INDEX idx_data_indicacao (data_indicacao)
);

-- Tabela de clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    telefone VARCHAR(20),
    cidade VARCHAR(100),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_indicacoes INT DEFAULT 0,
    total_compras INT DEFAULT 0,
    INDEX idx_codigo (codigo),
    INDEX idx_email (email)
);

-- Tabela de compras
CREATE TABLE IF NOT EXISTS compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_cliente VARCHAR(50) NOT NULL,
    codigo_indicador VARCHAR(50),
    valor DECIMAL(10,2) NOT NULL,
    data_compra TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'confirmada', 'entregue') DEFAULT 'pendente',
    prioridade BOOLEAN DEFAULT FALSE,
    data_entrega_prevista DATE,
    FOREIGN KEY (codigo_cliente) REFERENCES clientes(codigo),
    FOREIGN KEY (codigo_indicador) REFERENCES clientes(codigo),
    INDEX idx_codigo_cliente (codigo_cliente),
    INDEX idx_data_compra (data_compra)
);

-- Atualizar tabela rastreios_status para incluir prioridade
ALTER TABLE rastreios_status 
ADD COLUMN IF NOT EXISTS prioridade BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS codigo_indicador VARCHAR(50),
ADD COLUMN IF NOT EXISTS data_entrega_prevista DATE,
ADD INDEX idx_prioridade (prioridade),
ADD INDEX idx_codigo_indicador (codigo_indicador);

-- Contatos WhatsApp vinculados a códigos
CREATE TABLE IF NOT EXISTS whatsapp_contatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(255),
    telefone_original VARCHAR(30),
    telefone_normalizado VARCHAR(20),
    notificacoes_ativas TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_telefone (telefone_normalizado)
);

-- Registro de notificações disparadas
CREATE TABLE IF NOT EXISTS whatsapp_notificacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    status_titulo VARCHAR(255) NOT NULL,
    status_subtitulo VARCHAR(255),
    status_data DATETIME NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    mensagem TEXT NOT NULL,
    resposta_http TEXT,
    http_code INT,
    sucesso TINYINT(1) DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    enviado_em TIMESTAMP NULL,
    UNIQUE KEY uniq_codigo_status (codigo, status_titulo, status_data)
);

-- Tabela de mídias por código de rastreio
CREATE TABLE IF NOT EXISTS rastreios_midias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    arquivo VARCHAR(255) NOT NULL,
    tipo ENUM('foto','documento') DEFAULT 'foto',
    legenda VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_codigo (codigo),
    INDEX idx_codigo (codigo)
);

-- Trigger para atualizar contadores de indicações
DELIMITER //
CREATE TRIGGER IF NOT EXISTS tr_atualizar_indicacoes
AFTER INSERT ON indicacoes
FOR EACH ROW
BEGIN
    UPDATE clientes 
    SET total_indicacoes = total_indicacoes + 1 
    WHERE codigo = NEW.codigo_indicador;
END//
DELIMITER ;

-- Trigger para atualizar contadores de compras
DELIMITER //
CREATE TRIGGER IF NOT EXISTS tr_atualizar_compras
AFTER INSERT ON compras
FOR EACH ROW
BEGIN
    UPDATE clientes 
    SET total_compras = total_compras + 1 
    WHERE codigo = NEW.codigo_cliente;
    
    -- Se foi uma compra por indicação, marcar como prioridade
    IF NEW.codigo_indicador IS NOT NULL THEN
        UPDATE rastreios_status 
        SET prioridade = TRUE, 
            codigo_indicador = NEW.codigo_indicador,
            data_entrega_prevista = DATE_ADD(CURDATE(), INTERVAL 2 DAY)
        WHERE codigo = NEW.codigo_cliente;
    END IF;
END//
DELIMITER ;

