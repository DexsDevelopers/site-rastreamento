<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<h1>🛠️ Corrigindo Tabelas do WhatsApp</h1>";

try {
    // 1. Criar tabela whatsapp_contatos
    echo "<p>📱 Verificando tabela whatsapp_contatos...</p>";
    $sql = "CREATE TABLE IF NOT EXISTS whatsapp_contatos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50) NOT NULL UNIQUE,
        nome VARCHAR(255) NULL,
        telefone_original VARCHAR(30) NULL,
        telefone_normalizado VARCHAR(20) NULL,
        notificacoes_ativas TINYINT(1) DEFAULT 1,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_codigo (codigo),
        INDEX idx_telefone (telefone_normalizado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Tabela 'whatsapp_contatos' verificada/criada com sucesso!<br>";

    // 2. Criar tabela whatsapp_notificacoes
    echo "<p>🔔 Verificando tabela whatsapp_notificacoes...</p>";
    $sql = "CREATE TABLE IF NOT EXISTS whatsapp_notificacoes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50) NOT NULL,
        status_titulo VARCHAR(255) NOT NULL,
        status_subtitulo VARCHAR(255) NULL,
        status_data DATETIME NOT NULL,
        telefone VARCHAR(20) NOT NULL,
        mensagem TEXT NOT NULL,
        resposta_http TEXT NULL,
        http_code INT NULL,
        sucesso TINYINT(1) DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        enviado_em TIMESTAMP NULL,
        UNIQUE KEY uniq_codigo_status (codigo, status_titulo, status_data),
        INDEX idx_codigo_notificacoes (codigo),
        INDEX idx_sucesso (sucesso)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Tabela 'whatsapp_notificacoes' verificada/criada com sucesso!<br>";

    echo "<hr><h2>✅ Correção concluída!</h2>";
    echo "<p>Tente aprovar o pedido novamente.</p>";

} catch (PDOException $e) {
    echo "<h2>❌ Erro: " . $e->getMessage() . "</h2>";
}
?>