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
        ['ia_use_knowledge', '1', 'Usar base de conhecimento personalizada (1=ativado, 0=desabilitado)'],
        ['ia_learn_from_corrections', '1', 'Aprender com correções do admin'],
        ['ia_quota_disabled', '0', 'Quota desabilitada (0=ativa, 1=desativada por quota excedida)'],
        ['ia_fallback_response', 'Desculpe, não consegui processar sua mensagem no momento. Por favor, tente novamente em alguns instantes ou entre em contato com um atendente.', 'Resposta quando a IA falha']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO bot_ia_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "<p>✅ Configurações padrão inseridas!</p>";
    
    // Inserir conhecimentos sobre rastreamento
    $knowledge = [
        // Saudações básicas
        ['Olá', 'Olá! 👋 Sou o assistente da Helmer Logistics. Como posso ajudar com seu rastreamento?', 'saudacao', 'oi,olá,ola,hey,eae,e aí', 100],
        ['Bom dia', 'Bom dia! ☀️ Em que posso ajudar com seu pedido hoje?', 'saudacao', 'bom dia,bdia,bomdia', 100],
        ['Boa tarde', 'Boa tarde! 🌤️ Precisa de ajuda com rastreamento?', 'saudacao', 'boa tarde,btarde,boatarde', 100],
        ['Boa noite', 'Boa noite! 🌙 Como posso ajudar?', 'saudacao', 'boa noite,bnoite,boanoite', 100],
        
        // Rastreamento
        ['Como rastrear meu pedido', 'Para rastrear seu pedido, você precisa do código de rastreamento que recebeu. Acesse nosso site e digite o código no campo de busca, ou use o comando /rastrear no WhatsApp seguido do seu código. Exemplo: /rastrear ABC123BR', 'rastreamento', 'como rastrear,rastrear pedido,rastreamento,verificar pedido,status pedido', 95],
        ['Onde está meu pedido', 'Para verificar onde está seu pedido, preciso do código de rastreamento. Use o comando /rastrear seguido do código, ou acesse nosso site de rastreamento. Se não tiver o código, verifique seu email ou WhatsApp onde recebeu a confirmação da compra.', 'rastreamento', 'onde está,localização pedido,onde meu pedido,pedido onde', 95],
        ['Status do pedido', 'Para verificar o status do seu pedido, use o código de rastreamento. Digite /rastrear seguido do código no WhatsApp, ou acesse nosso site. O sistema mostrará todas as etapas do seu pedido em tempo real.', 'rastreamento', 'status,status pedido,atualização,atualização pedido', 95],
        ['Código de rastreamento', 'O código de rastreamento é uma sequência de letras e números que você recebe após fazer a compra. Ele geralmente vem no formato como ABC123BR ou similar. Você recebe esse código por email ou WhatsApp. Use o comando /rastrear seguido do código para verificar o status.', 'rastreamento', 'código rastreamento,codigo,numero rastreamento,chave rastreamento', 95],
        ['Não tenho código de rastreamento', 'Se você não tem o código de rastreamento, verifique: 1) Seu email de confirmação da compra, 2) Mensagens no WhatsApp, 3) Entre em contato com o vendedor. O código é essencial para rastrear seu pedido. Se não encontrar, entre em contato conosco.', 'rastreamento', 'sem código,não tenho código,perdi código,esqueci código', 90],
        
        // Sistema de Indicações
        ['Como funciona a indicação', 'O sistema de indicações funciona assim: 1) Você indica um amigo no nosso site, 2) Seu amigo faz uma compra no mesmo dia, 3) Você ganha entrega prioritária em apenas 2 dias! Acesse nosso site e clique em "Indicar Amigo" para começar.', 'indicacao', 'como funciona indicação,indicar amigo,sistema indicação,indicação como funciona', 95],
        ['Indicar amigo', 'Para indicar um amigo e ganhar entrega prioritária: 1) Acesse nosso site de rastreamento, 2) Clique no botão "Indicar Amigo", 3) Preencha os dados do seu amigo (nome, telefone e cidade), 4) Aguarde aprovação. Se seu amigo comprar no mesmo dia, você ganha entrega em 2 dias!', 'indicacao', 'indicar,indicar amigo,como indicar,indicação', 95],
        ['Entrega prioritária', 'A entrega prioritária é um benefício do sistema de indicações. Quando você indica um amigo e ele compra no mesmo dia, seu pedido será entregue em apenas 2 dias (ao invés de 5-7 dias normais). Você ganha status VIP e prioridade total no sistema, sem custos adicionais!', 'indicacao', 'entrega prioritária,entrega rápida,2 dias,entrega expressa,prioridade', 95],
        ['Benefícios da indicação', 'Ao indicar um amigo que compra no mesmo dia, você ganha: ✅ Entrega em 2 dias (normal: 5-7 dias), ✅ Status VIP no rastreamento, ✅ Prioridade total no sistema, ✅ Processamento acelerado, ✅ Sem custos adicionais. Seu amigo também ganha desconto especial e frete grátis!', 'indicacao', 'benefícios indicação,vantagens indicação,o que ganho,benefícios', 90],
        
        // Prazos e Entregas
        ['Prazo de entrega', 'O prazo normal de entrega é de 5 a 7 dias úteis. Porém, se você indicar um amigo e ele comprar no mesmo dia, você ganha entrega prioritária em apenas 2 dias! O prazo pode variar conforme a região de destino.', 'entrega', 'prazo entrega,quanto tempo,quando chega,demora entrega', 95],
        ['Quando chega meu pedido', 'O prazo de entrega normal é de 5 a 7 dias úteis. Para saber a data exata, rastreie seu pedido usando o código de rastreamento. Se você tem indicação aprovada, seu pedido chega em 2 dias! Use /rastrear seguido do código para ver o status atualizado.', 'entrega', 'quando chega,data entrega,previsão entrega,quando recebo', 95],
        ['Pedido atrasado', 'Se seu pedido está atrasado, verifique o status usando o código de rastreamento. Entre em contato conosco informando o código e nossa equipe verificará o que aconteceu. Pedidos com indicação têm prioridade e chegam em 2 dias.', 'entrega', 'atrasado,pedido atrasado,demora muito,atraso', 90],
        
        // Informações Gerais
        ['O que é Helmer Logistics', 'Helmer Logistics é um sistema de rastreamento logístico completo. Oferecemos rastreamento de pedidos em tempo real, sistema de indicações com entrega prioritária, notificações automáticas por WhatsApp e painel administrativo completo. Nosso objetivo é entregar seus pedidos de forma rápida e eficiente!', 'geral', 'helmer logistics,quem são,sobre empresa,o que fazem', 90],
        ['Como entrar em contato', 'Você pode entrar em contato conosco através do WhatsApp, email ou pelo site. Use o comando /rastrear no WhatsApp para rastrear seu pedido, ou acesse nosso site de rastreamento para mais informações e suporte.', 'contato', 'contato,telefone,email,suporte,atendimento,como falar', 90],
        ['Ajuda', 'Posso ajudar você com: 📦 Rastreamento de pedidos, 👥 Sistema de indicações, ⏱️ Prazos de entrega, 📱 Como usar o WhatsApp para rastrear. Digite sua dúvida ou use /rastrear seguido do código para rastrear seu pedido!', 'ajuda', 'ajuda,help,suporte,como usar,o que posso fazer', 85],
        
        // Agradecimentos
        ['Obrigado', 'De nada! 😊 Fico feliz em ajudar! Se precisar rastrear seu pedido, use /rastrear seguido do código. Boa entrega! 📦', 'agradecimento', 'obrigado,obrigada,vlw,valeu,thanks', 90],
        ['Tchau', 'Até mais! 👋 Se precisar rastrear seu pedido, é só chamar! Boa entrega! 📦', 'despedida', 'tchau,bye,até,ate,falou,flw', 90]
    ];
    
    $checkKnowledge = fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_ia_knowledge");
    if ($checkKnowledge['c'] < 10) {
        // Limpar conhecimentos antigos genéricos e inserir novos
        $pdo->exec("DELETE FROM bot_ia_knowledge WHERE categoria IN ('saudacao', 'agradecimento', 'despedida', 'identidade', 'ajuda') AND criado_por = 'admin'");
        
        $stmt = $pdo->prepare("INSERT INTO bot_ia_knowledge (pergunta, resposta, categoria, palavras_chave, prioridade, criado_por) VALUES (?, ?, ?, ?, ?, 'admin')");
        foreach ($knowledge as $k) {
            $stmt->execute($k);
        }
        echo "<p>✅ Base de conhecimento sobre rastreamento inserida! (" . count($knowledge) . " itens)</p>";
    } else {
        echo "<p>✅ Base de conhecimento já possui " . $checkKnowledge['c'] . " itens</p>";
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

