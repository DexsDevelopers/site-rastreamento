<?php
/**
 * Script de Configuração do Banco de Dados
 * Helmer Logistics - Sistema de Indicação
 * Execute este arquivo uma única vez para criar as tabelas necessárias
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Verificar se já foi executado
$checkTable = "SHOW TABLES LIKE 'indicacoes'";
$result = $pdo->query($checkTable);

if ($result->rowCount() > 0) {
    die("❌ As tabelas já foram criadas! Este script só deve ser executado uma vez.");
}

echo "<h1>🚀 Configurando Banco de Dados - Helmer Logistics</h1>";
echo "<p>Iniciando criação das tabelas...</p>";

try {
    // 1. Criar tabela de clientes
    echo "<p>📋 Criando tabela de clientes...</p>";
    $sql = "CREATE TABLE clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50) UNIQUE NOT NULL,
        nome VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        telefone VARCHAR(20),
        cidade VARCHAR(100),
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        total_indicacoes INT DEFAULT 0,
        total_compras INT DEFAULT 0,
        INDEX idx_codigo (codigo),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'clientes' criada com sucesso!<br>";
    
    // 2. Criar tabela de indicações
    echo "<p>📋 Criando tabela de indicações...</p>";
    $sql = "CREATE TABLE indicacoes (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'indicacoes' criada com sucesso!<br>";
    
    // 3. Criar tabela de compras
    echo "<p>📋 Criando tabela de compras...</p>";
    $sql = "CREATE TABLE compras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo_cliente VARCHAR(50) NOT NULL,
        codigo_indicador VARCHAR(50),
        valor DECIMAL(10,2) NOT NULL,
        data_compra TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pendente', 'confirmada', 'entregue') DEFAULT 'pendente',
        prioridade BOOLEAN DEFAULT FALSE,
        data_entrega_prevista DATE,
        INDEX idx_codigo_cliente (codigo_cliente),
        INDEX idx_data_compra (data_compra)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'compras' criada com sucesso!<br>";
    
    // 4. Verificar se a tabela rastreios_status existe e adicionar colunas
    echo "<p>📋 Verificando tabela rastreios_status...</p>";
    $checkRastreios = "SHOW TABLES LIKE 'rastreios_status'";
    $result = $pdo->query($checkRastreios);
    
    if ($result->rowCount() > 0) {
        echo "✅ Tabela 'rastreios_status' já existe. Adicionando colunas de prioridade...<br>";
        
        // Adicionar colunas de prioridade se não existirem
        $columns = [
            "ADD COLUMN prioridade BOOLEAN DEFAULT FALSE",
            "ADD COLUMN codigo_indicador VARCHAR(50)",
            "ADD COLUMN data_entrega_prevista DATE"
        ];
        
        foreach ($columns as $column) {
            try {
                $sql = "ALTER TABLE rastreios_status $column";
                $pdo->exec($sql);
                echo "✅ Coluna adicionada: $column<br>";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                    echo "⚠️ Coluna já existe: $column<br>";
                } else {
                    echo "❌ Erro ao adicionar coluna $column: " . $e->getMessage() . "<br>";
                }
            }
        }
        
        // Adicionar índices
        try {
            $pdo->exec("ALTER TABLE rastreios_status ADD INDEX idx_prioridade (prioridade)");
            echo "✅ Índice de prioridade criado<br>";
        } catch (PDOException $e) {
            echo "⚠️ Índice de prioridade já existe<br>";
        }
        
        try {
            $pdo->exec("ALTER TABLE rastreios_status ADD INDEX idx_codigo_indicador (codigo_indicador)");
            echo "✅ Índice de código indicador criado<br>";
        } catch (PDOException $e) {
            echo "⚠️ Índice de código indicador já existe<br>";
        }
        
    } else {
        echo "❌ Tabela 'rastreios_status' não encontrada. Criando tabela completa...<br>";
        
        $sql = "CREATE TABLE rastreios_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) NOT NULL,
            cidade VARCHAR(100) NOT NULL,
            status_atual VARCHAR(255) NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            subtitulo TEXT,
            data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            cor VARCHAR(50),
            taxa_valor DECIMAL(10,2),
            taxa_pix TEXT,
            prioridade BOOLEAN DEFAULT FALSE,
            codigo_indicador VARCHAR(50),
            data_entrega_prevista DATE,
            INDEX idx_codigo (codigo),
            INDEX idx_prioridade (prioridade),
            INDEX idx_codigo_indicador (codigo_indicador)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ Tabela 'rastreios_status' criada com sucesso!<br>";
    }
    
    // 5. Criar triggers para atualizar contadores
    echo "<p>📋 Criando triggers...</p>";
    
    // Trigger para atualizar contadores de indicações
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS tr_atualizar_indicacoes");
        $sql = "CREATE TRIGGER tr_atualizar_indicacoes
                AFTER INSERT ON indicacoes
                FOR EACH ROW
                BEGIN
                    UPDATE clientes 
                    SET total_indicacoes = total_indicacoes + 1 
                    WHERE codigo = NEW.codigo_indicador;
                END";
        $pdo->exec($sql);
        echo "✅ Trigger 'tr_atualizar_indicacoes' criado<br>";
    } catch (PDOException $e) {
        echo "❌ Erro ao criar trigger: " . $e->getMessage() . "<br>";
    }
    
    // Trigger para atualizar contadores de compras
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS tr_atualizar_compras");
        $sql = "CREATE TRIGGER tr_atualizar_compras
                AFTER INSERT ON compras
                FOR EACH ROW
                BEGIN
                    UPDATE clientes 
                    SET total_compras = total_compras + 1 
                    WHERE codigo = NEW.codigo_cliente;
                    
                    IF NEW.codigo_indicador IS NOT NULL THEN
                        UPDATE rastreios_status 
                        SET prioridade = TRUE, 
                            codigo_indicador = NEW.codigo_indicador,
                            data_entrega_prevista = DATE_ADD(CURDATE(), INTERVAL 2 DAY)
                        WHERE codigo = NEW.codigo_cliente;
                    END IF;
                END";
        $pdo->exec($sql);
        echo "✅ Trigger 'tr_atualizar_compras' criado<br>";
    } catch (PDOException $e) {
        echo "❌ Erro ao criar trigger: " . $e->getMessage() . "<br>";
    }
    
    // 6. Inserir dados de exemplo (opcional)
    echo "<p>📋 Inserindo dados de exemplo...</p>";
    
    // Cliente exemplo
    try {
        $sql = "INSERT INTO clientes (codigo, nome, email, telefone, cidade) VALUES 
                ('CLIENTE001', 'João Silva', 'joao@email.com', '11999999999', 'São Paulo'),
                ('CLIENTE002', 'Maria Santos', 'maria@email.com', '11888888888', 'Rio de Janeiro')";
        $pdo->exec($sql);
        echo "✅ Dados de exemplo inseridos<br>";
    } catch (PDOException $e) {
        echo "⚠️ Dados de exemplo já existem ou erro: " . $e->getMessage() . "<br>";
    }
    
    // 7. Criar arquivo de configuração para indicar que foi executado
    file_put_contents('database_setup_complete.txt', date('Y-m-d H:i:s') . ' - Database setup completed successfully');
    
    echo "<hr>";
    echo "<h2>🎉 Configuração Concluída com Sucesso!</h2>";
    echo "<p><strong>✅ Todas as tabelas foram criadas com sucesso!</strong></p>";
    echo "<p><strong>📋 Tabelas criadas:</strong></p>";
    echo "<ul>";
    echo "<li>✅ clientes</li>";
    echo "<li>✅ indicacoes</li>";
    echo "<li>✅ compras</li>";
    echo "<li>✅ rastreios_status (atualizada)</li>";
    echo "</ul>";
    
    echo "<p><strong>🔧 Funcionalidades disponíveis:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Sistema de indicação</li>";
    echo "<li>✅ Prioridade de entrega</li>";
    echo "<li>✅ Controle de clientes</li>";
    echo "<li>✅ Relatórios de indicações</li>";
    echo "</ul>";
    
    echo "<p><strong>🚀 Próximos passos:</strong></p>";
    echo "<ol>";
    echo "<li>Acesse <a href='indicacao.php'>indicacao.php</a> para testar o sistema</li>";
    echo "<li>Configure o painel admin para gerenciar indicações</li>";
    echo "<li>Teste o sistema de prioridade</li>";
    echo "</ol>";
    
    echo "<p><strong>⚠️ Importante:</strong> Delete este arquivo (setup_database.php) após a execução por segurança!</p>";
    
} catch (PDOException $e) {
    echo "<h2>❌ Erro na Configuração</h2>";
    echo "<p><strong>Erro:</strong> " . $e->getMessage() . "</p>";
    echo "<p>Verifique as configurações do banco de dados no arquivo includes/config.php</p>";
    echo "<p>Certifique-se de que o usuário do banco tem permissões para criar tabelas e triggers.</p>";
}

echo "<hr>";
echo "<p><small>Helmer Logistics - Sistema de Indicação | " . date('Y-m-d H:i:s') . "</small></p>";
?>
