<?php
/**
 * Setup - Sistema de IA do Bot com Aprendizado
 * Cria tabelas para memória e aprendizado da IA
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<h2>🤖 Setup - Sistema de IA do Bot</h2>";

try {
    // Tabela de conhecimento/aprendizado da IA
    $sql1 = "CREATE TABLE IF NOT EXISTS bot_ia_knowledge (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pergunta TEXT NOT NULL,
        resposta TEXT NOT NULL,
        categoria VARCHAR(100) DEFAULT 'geral',
        palavras_chave TEXT,
        prioridade INT DEFAULT 0,
        uso_count INT DEFAULT 0,
        ativo TINYINT(1) DEFAULT 1,
        criado_por VARCHAR(100) DEFAULT 'admin',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FULLTEXT KEY ft_pergunta (pergunta),
        FULLTEXT KEY ft_palavras_chave (palavras_chave),
        INDEX idx_categoria (categoria),
        INDEX idx_ativo (ativo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql1);
    echo "<p>✅ Tabela <code>bot_ia_knowledge</code> criada!</p>";
    
    // Tabela de histórico de conversas (contexto)
    $sql2 = "CREATE TABLE IF NOT EXISTS bot_ia_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(50) NOT NULL,
        role ENUM('user', 'assistant') NOT NULL,
        message TEXT NOT NULL,
        tokens_used INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_phone (phone_number),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql2);
    echo "<p>✅ Tabela <code>bot_ia_conversations</code> criada!</p>";
    
    // Tabela de feedback/correções para aprendizado
    $sql3 = "CREATE TABLE IF NOT EXISTS bot_ia_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(50),
        pergunta_original TEXT NOT NULL,
        resposta_ia TEXT,
        correcao TEXT,
        aprovado TINYINT(1) DEFAULT 0,
        processado TINYINT(1) DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_aprovado (aprovado),
        INDEX idx_processado (processado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql3);
    echo "<p>✅ Tabela <code>bot_ia_feedback</code> criada!</p>";
    
    // Tabela de configurações da IA
    $sql4 = "CREATE TABLE IF NOT EXISTS bot_ia_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql4);
    echo "<p>✅ Tabela <code>bot_ia_settings</code> criada!</p>";
    
    // Inserir configurações padrão
    $settings = [
        ['gemini_api_key', '', 'Chave da API do Google Gemini'],
        ['ia_enabled', '1', 'Ativar/desativar IA no chat privado'],
        ['ia_model', 'gemini-2.5-flash', 'Modelo do Gemini a usar (padrão: gemini-2.5-flash)'],
        ['ia_max_tokens', '500', 'Máximo de tokens na resposta'],
        ['ia_temperature', '0.7', 'Temperatura (criatividade) da IA'],
        ['ia_system_prompt', 'Você é um assistente virtual amigável e prestativo. Responda de forma clara, objetiva e em português brasileiro. Use emojis quando apropriado para tornar a conversa mais agradável.', 'Prompt de sistema da IA'],
        ['ia_context_messages', '10', 'Quantidade de mensagens anteriores para contexto'],
        ['ia_use_knowledge', '0', 'Usar base de conhecimento personalizada (0=desabilitado, usar apenas IA)'],
        ['ia_learn_from_corrections', '1', 'Aprender com correções do admin'],
        ['ia_quota_disabled', '0', 'Quota desabilitada (0=ativa, 1=desativada por quota excedida)'],
        ['ia_fallback_response', 'Desculpe, não consegui processar sua mensagem no momento. Por favor, tente novamente em alguns instantes ou entre em contato com um atendente.', 'Resposta quando a IA falha']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO bot_ia_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "<p>✅ Configurações padrão inseridas!</p>";
    
    // Inserir alguns conhecimentos base
    $knowledge = [
        ['Olá', 'Olá! 👋 Como posso ajudar você hoje?', 'saudacao', 'oi,olá,ola,hey,eae,e aí', 100],
        ['Bom dia', 'Bom dia! ☀️ Espero que tenha um ótimo dia! Como posso ajudar?', 'saudacao', 'bom dia,bdia,bomdia', 100],
        ['Boa tarde', 'Boa tarde! 🌤️ Como posso ser útil?', 'saudacao', 'boa tarde,btarde,boatarde', 100],
        ['Boa noite', 'Boa noite! 🌙 Em que posso ajudar?', 'saudacao', 'boa noite,bnoite,boanoite', 100],
        ['Obrigado', 'De nada! 😊 Fico feliz em ajudar! Precisa de mais alguma coisa?', 'agradecimento', 'obrigado,obrigada,vlw,valeu,thanks', 90],
        ['Tchau', 'Até mais! 👋 Se precisar de algo, é só chamar!', 'despedida', 'tchau,bye,até,ate,falou,flw', 90],
        ['Quem é você', 'Sou um assistente virtual inteligente! 🤖 Estou aqui para ajudar com suas dúvidas e conversar com você.', 'identidade', 'quem é você,quem é voce,quem vc é,o que você é,bot', 80],
        ['Como funciona', 'Você pode me perguntar qualquer coisa! 💬 Eu uso inteligência artificial para entender suas perguntas e dar as melhores respostas possíveis.', 'ajuda', 'como funciona,como usar,ajuda,help', 80]
    ];
    
    $checkKnowledge = fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_knowledge");
    if ($checkKnowledge['c'] < 5) {
        $stmt = $pdo->prepare("INSERT INTO bot_ia_knowledge (pergunta, resposta, categoria, palavras_chave, prioridade) VALUES (?, ?, ?, ?, ?)");
        foreach ($knowledge as $k) {
            $stmt->execute($k);
        }
        echo "<p>✅ Base de conhecimento inicial inserida!</p>";
    }
    
    echo "<hr>";
    echo "<h3>✅ Setup da IA concluído!</h3>";
    echo "<p><strong>⚠️ IMPORTANTE:</strong> Configure sua chave da API do Gemini no painel!</p>";
    echo "<p><a href='admin_bot_ia.php'>→ Configurar IA do Bot</a></p>";
    echo "<p><a href='dashboard.php'>→ Voltar ao Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

