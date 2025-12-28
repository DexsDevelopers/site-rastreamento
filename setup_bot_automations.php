<?php
/**
 * Script para criar tabela de automações do bot
 * Execute este arquivo uma vez para configurar o banco de dados
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<pre>";
echo "=== Configurando tabelas de automação do Bot ===\n\n";

try {
    // Tabela de automações
    $sql = "CREATE TABLE IF NOT EXISTS bot_automations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT,
        ativo TINYINT(1) DEFAULT 1,
        tipo ENUM('mensagem_especifica', 'palavra_chave', 'regex') DEFAULT 'mensagem_especifica',
        gatilho VARCHAR(500) NOT NULL COMMENT 'Texto ou regex que ativa a automação',
        resposta TEXT NOT NULL COMMENT 'Mensagem de resposta',
        imagem_url VARCHAR(500) DEFAULT NULL COMMENT 'URL da imagem a enviar com a resposta',
        grupo_id VARCHAR(100) DEFAULT NULL COMMENT 'JID do grupo específico ou NULL para todos',
        grupo_nome VARCHAR(255) DEFAULT NULL COMMENT 'Nome do grupo para exibição',
        apenas_privado TINYINT(1) DEFAULT 0 COMMENT 'Se 1, só funciona em chat privado',
        apenas_grupo TINYINT(1) DEFAULT 0 COMMENT 'Se 1, só funciona em grupos',
        delay_ms INT DEFAULT 0 COMMENT 'Delay antes de responder em milissegundos',
        cooldown_segundos INT DEFAULT 0 COMMENT 'Cooldown entre usos por usuário',
        prioridade INT DEFAULT 0 COMMENT 'Ordem de execução (maior = primeiro)',
        contador_uso INT DEFAULT 0 COMMENT 'Quantas vezes foi usado',
        ultimo_uso TIMESTAMP NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ativo (ativo),
        INDEX idx_tipo (tipo),
        INDEX idx_grupo (grupo_id),
        INDEX idx_prioridade (prioridade DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Tabela bot_automations criada/verificada!\n";
    
    // Adicionar coluna imagem_url se não existir (para tabelas já existentes)
    try {
        $pdo->exec("ALTER TABLE bot_automations ADD COLUMN imagem_url VARCHAR(500) DEFAULT NULL COMMENT 'URL da imagem a enviar com a resposta' AFTER resposta");
        echo "✅ Coluna imagem_url adicionada!\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false) {
            // Coluna já existe, tudo ok
        }
    }
    
    // Tabela de logs de uso das automações
    $sql2 = "CREATE TABLE IF NOT EXISTS bot_automation_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        automation_id INT NOT NULL,
        jid_origem VARCHAR(100) NOT NULL COMMENT 'JID de quem enviou',
        numero_origem VARCHAR(20) COMMENT 'Número formatado',
        mensagem_recebida TEXT,
        resposta_enviada TEXT,
        grupo_id VARCHAR(100) DEFAULT NULL,
        grupo_nome VARCHAR(255) DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_automation (automation_id),
        INDEX idx_jid (jid_origem),
        INDEX idx_data (criado_em),
        FOREIGN KEY (automation_id) REFERENCES bot_automations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql2);
    echo "✅ Tabela bot_automation_logs criada/verificada!\n";
    
    // Tabela de grupos conhecidos (cache)
    $sql3 = "CREATE TABLE IF NOT EXISTS bot_grupos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jid VARCHAR(100) NOT NULL UNIQUE,
        nome VARCHAR(255),
        descricao TEXT,
        participantes INT DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_jid (jid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql3);
    echo "✅ Tabela bot_grupos criada/verificada!\n";
    
    // Tabela de configurações gerais do bot
    $sql4 = "CREATE TABLE IF NOT EXISTS bot_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(100) NOT NULL UNIQUE,
        valor TEXT,
        tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
        descricao TEXT,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_chave (chave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql4);
    echo "✅ Tabela bot_settings criada/verificada!\n";
    
    // Inserir configurações padrão
    $defaultSettings = [
        ['bot_enabled', '1', 'boolean', 'Se o bot está ativo'],
        ['auto_reply_enabled', '0', 'boolean', 'Resposta automática a saudações'],
        ['welcome_message', 'Olá! Como posso ajudar?', 'string', 'Mensagem de boas-vindas'],
        ['automations_enabled', '1', 'boolean', 'Se as automações estão ativas'],
        ['max_automations_per_minute', '10', 'number', 'Limite de automações por minuto por usuário'],
        ['log_automations', '1', 'boolean', 'Registrar logs das automações'],
        ['notify_admin_errors', '1', 'boolean', 'Notificar admin em caso de erros'],
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO bot_settings (chave, valor, tipo, descricao) VALUES (?, ?, ?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
    echo "✅ Configurações padrão inseridas!\n";
    
    // Inserir algumas automações de exemplo
    $exemploAutomations = [
        [
            'Boas-vindas',
            'Responde quando alguém diz oi/olá',
            1,
            'palavra_chave',
            'oi|olá|ola|oie|eae',
            "👋 Olá! Seja bem-vindo!\n\nDigite */menu* para ver os comandos disponíveis.",
            null,
            null,
            0,  // apenas_privado = 0 (funciona em todos os chats)
            0,  // apenas_grupo = 0
            500,
            30, // cooldown reduzido para 30 segundos
            10
        ],
        [
            'Horário de Atendimento',
            'Informa horário quando perguntam',
            1,
            'palavra_chave',
            'horário|horario|funcionamento|atendimento|abre|fecha',
            "🕐 *Horário de Atendimento*\n\nSegunda a Sexta: 09h às 18h\nSábado: 09h às 13h\nDomingo: Fechado\n\n_Fora deste horário, deixe sua mensagem que retornaremos assim que possível!_",
            null,
            null,
            0,
            0,
            300,
            120,
            5
        ],
        [
            'Localização',
            'Informa localização quando perguntam',
            1,
            'palavra_chave',
            'endereço|endereco|localização|localizacao|onde fica|como chego',
            "📍 *Nossa Localização*\n\nAv. Principal, 1234 - Centro\nSão Paulo - SP\n\n🗺️ Google Maps: https://maps.google.com\n\n_Estamos esperando sua visita!_",
            null,
            null,
            0,
            0,
            300,
            120,
            5
        ]
    ];
    
    $stmtAuto = $pdo->prepare("INSERT IGNORE INTO bot_automations 
        (nome, descricao, ativo, tipo, gatilho, resposta, grupo_id, grupo_nome, apenas_privado, apenas_grupo, delay_ms, cooldown_segundos, prioridade) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($exemploAutomations as $auto) {
        $stmtAuto->execute($auto);
    }
    echo "✅ Automações de exemplo inseridas!\n";
    
    echo "\n=== CONFIGURAÇÃO CONCLUÍDA! ===\n";
    echo "\nAcesse: admin_bot_config.php para gerenciar o bot.\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";

