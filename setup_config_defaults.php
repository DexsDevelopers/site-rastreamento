<?php
/**
 * Script para popular config.json com valores padrão
 * Execute uma vez para inicializar todas as configurações
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head><meta charset='UTF-8'><title>Configurar config.json</title>";
echo "<style>body{background:#0b0b0b;color:#fff;font-family:monospace;padding:20px;line-height:1.6}";
echo ".success{color:#4ade80}.error{color:#f87171}.info{color:#60a5fa}";
echo "pre{background:#1a1a1a;padding:15px;border-radius:8px;overflow:auto}</style></head>";
echo "<body>";

echo "<h1>🔧 Configuração Inicial do config.json</h1>";

// Valores padrão que precisam estar no config.json
$defaultConfigs = [
    // Entrega Expressa
    'EXPRESS_FEE_VALUE' => 29.90,
    'EXPRESS_PIX_KEY' => 'chave-pix-exemplo@helmer.com',
    
    // Mensagens WhatsApp por Etapa
    'WHATSAPP_MSG_POSTADO' => "Olá {nome}!\n\n📦 *Objeto Postado*\n\nSeu pedido *{codigo}* foi postado e está em processamento.\n\n{link}",
    
    'WHATSAPP_MSG_TRANSITO' => "Olá {nome}!\n\n🚚 *Em Trânsito*\n\nSeu pedido *{codigo}* está a caminho do centro de distribuição.\n\n{link}",
    
    'WHATSAPP_MSG_DISTRIBUICAO' => "Olá {nome}!\n\n🏢 *No Centro de Distribuição*\n\nSeu pedido *{codigo}* chegou ao centro de distribuição e está sendo processado.\n\n{link}",
    
    'WHATSAPP_MSG_ENTREGA' => "Olá {nome}!\n\n🚀 *Saiu para Entrega*\n\nSeu pedido *{codigo}* saiu para entrega e chegará em breve!\n\n{link}",
    
    'WHATSAPP_MSG_ENTREGUE' => "Olá {nome}!\n\n✅ *Pedido Entregue*\n\nSeu pedido *{codigo}* foi entregue com sucesso!\n\nObrigado pela preferência! 🎉",
    
    'WHATSAPP_MSG_TAXA' => "Olá {nome}!\n\n💰 *Taxa de Distribuição Nacional*\n\nSeu pedido *{codigo}* precisa de uma taxa de R$ {taxa_valor} para seguir para entrega.\n\nFaça o pagamento via PIX:\n`{taxa_pix}`\n\nApós o pagamento, a liberação acontece rapidamente.\n\n{link}"
];

echo "<p class='info'>📋 Verificando configurações que precisam ser adicionadas...</p>";

$added = 0;
$skipped = 0;
$errors = [];

foreach ($defaultConfigs as $key => $value) {
    // Verificar se já existe
    $existing = getDynamicConfig($key, null);
    
    if ($existing === null) {
        // Não existe, adicionar
        if (setDynamicConfig($key, $value)) {
            echo "<p class='success'>✅ Adicionado: <strong>$key</strong></p>";
            $added++;
        } else {
            echo "<p class='error'>❌ Erro ao adicionar: <strong>$key</strong></p>";
            $errors[] = $key;
        }
    } else {
        echo "<p style='color:#888'>⏭️ Já existe: <strong>$key</strong></p>";
        $skipped++;
    }
}

echo "<hr style='border:1px solid #333;margin:20px 0'>";

if ($added > 0) {
    echo "<h2 class='success'>✅ Sucesso!</h2>";
    echo "<p><strong>{$added}</strong> configuração(ões) adicionada(s) com sucesso!</p>";
}

if ($skipped > 0) {
    echo "<p><strong>{$skipped}</strong> configuração(ões) já existiam (não modificadas).</p>";
}

if (!empty($errors)) {
    echo "<h2 class='error'>❌ Erros</h2>";
    echo "<p>Falha ao adicionar: " . implode(', ', $errors) . "</p>";
    echo "<p>Verifique as permissões do arquivo config.json</p>";
}

// Mostrar config.json atual
echo "<h2>📄 Conteúdo atual do config.json:</h2>";
$configPath = __DIR__ . '/config.json';
if (file_exists($configPath) && is_readable($configPath)) {
    $content = file_get_contents($configPath);
    $data = json_decode($content, true);
    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</pre>";
} else {
    echo "<p class='error'>Não foi possível ler config.json</p>";
}

echo "<hr style='border:1px solid #333;margin:20px 0'>";
echo "<h2>🎯 Próximos Passos:</h2>";
echo "<ol>";
echo "<li>Vá em <a href='admin_settings.php' style='color:#60a5fa'>Configurações Expressa</a> para personalizar valores</li>";
echo "<li>Vá em <a href='admin_mensagens.php' style='color:#60a5fa'>Mensagens WhatsApp</a> para personalizar mensagens</li>";
echo "<li>Recarregue as páginas (F5) para ver suas configurações salvas</li>";
echo "</ol>";

echo "<p style='margin-top:30px'>";
echo "<a href='admin.php' style='background:#0055FF;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>⬅️ Voltar ao Painel</a>";
echo "</p>";

echo "</body></html>";



