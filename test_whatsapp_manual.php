<?php
/**
 * Script de teste para envio manual de WhatsApp
 * Use este arquivo para testar se o envio está funcionando
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';

// Configuração
$codigo_teste = isset($_GET['codigo']) ? $_GET['codigo'] : 'GH56YJ1474BR';

echo "<h2>Teste de Envio Manual WhatsApp</h2>";
echo "<p><strong>Código:</strong> {$codigo_teste}</p>";

// 1. Verificar configuração da API
echo "<h3>1. Verificando configuração da API...</h3>";
$apiConfig = whatsappApiConfig();
echo "<pre>";
print_r($apiConfig);
echo "</pre>";

if (!$apiConfig['enabled']) {
    echo "<p style='color:red;'><strong>❌ API WhatsApp está DESABILITADA ou configuração incompleta!</strong></p>";
    echo "<p>Verifique:</p>";
    echo "<ul>";
    echo "<li>WHATSAPP_API_URL em config.json: " . getDynamicConfig('WHATSAPP_API_URL', 'NÃO DEFINIDO') . "</li>";
    echo "<li>WHATSAPP_API_TOKEN em config.json: " . (getDynamicConfig('WHATSAPP_API_TOKEN', '') ? 'DEFINIDO' : 'NÃO DEFINIDO') . "</li>";
    echo "<li>WHATSAPP_API_ENABLED em config.json: " . (getDynamicConfig('WHATSAPP_API_ENABLED', true) ? 'true' : 'false') . "</li>";
    echo "</ul>";
    exit;
}

echo "<p style='color:green;'><strong>✅ API WhatsApp está habilitada</strong></p>";

// 2. Verificar contato
echo "<h3>2. Verificando contato WhatsApp...</h3>";
$contato = getWhatsappContact($pdo, $codigo_teste);
if (!$contato) {
    echo "<p style='color:red;'><strong>❌ Contato não encontrado para o código {$codigo_teste}</strong></p>";
    exit;
}

echo "<pre>";
print_r($contato);
echo "</pre>";

if ((int) $contato['notificacoes_ativas'] !== 1) {
    echo "<p style='color:orange;'><strong>⚠️ Notificações estão DESATIVADAS para este código</strong></p>";
}

if (empty($contato['telefone_normalizado'])) {
    echo "<p style='color:red;'><strong>❌ Telefone não cadastrado</strong></p>";
    exit;
}

echo "<p style='color:green;'><strong>✅ Contato encontrado: {$contato['telefone_normalizado']}</strong></p>";

// 3. Verificar status atual
echo "<h3>3. Verificando status atual...</h3>";
$status = fetchOne($pdo, "SELECT codigo, cidade, status_atual, titulo, subtitulo, data
                          FROM rastreios_status
                          WHERE codigo = ?
                          ORDER BY data DESC
                          LIMIT 1", [$codigo_teste]);

if (!$status) {
    echo "<p style='color:red;'><strong>❌ Status não encontrado para o código {$codigo_teste}</strong></p>";
    exit;
}

echo "<pre>";
print_r($status);
echo "</pre>";

// 4. Testar envio
echo "<h3>4. Testando envio...</h3>";
try {
    notifyWhatsappLatestStatus($pdo, $codigo_teste);
    echo "<p style='color:green;'><strong>✅ Função notifyWhatsappLatestStatus executada sem erros</strong></p>";
    echo "<p>Verifique os logs em <code>logs/system.log</code> para ver se a mensagem foi realmente enviada.</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'><strong>❌ Erro ao executar: " . $e->getMessage() . "</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 5. Verificar última notificação
echo "<h3>5. Verificando última notificação enviada...</h3>";
$ultimaNotif = fetchOne($pdo, "SELECT * FROM whatsapp_notificacoes 
                               WHERE codigo = ? 
                               ORDER BY enviado_em DESC 
                               LIMIT 1", [$codigo_teste]);

if ($ultimaNotif) {
    echo "<pre>";
    print_r($ultimaNotif);
    echo "</pre>";
    
    if ((int) $ultimaNotif['sucesso'] === 1) {
        echo "<p style='color:green;'><strong>✅ Última notificação foi enviada com SUCESSO</strong></p>";
    } else {
        echo "<p style='color:red;'><strong>❌ Última notificação FALHOU</strong></p>";
        echo "<p>HTTP Code: {$ultimaNotif['http_code']}</p>";
        echo "<p>Resposta: {$ultimaNotif['resposta_http']}</p>";
    }
} else {
    echo "<p style='color:orange;'><strong>⚠️ Nenhuma notificação registrada ainda</strong></p>";
}

echo "<hr>";
echo "<p><a href='?codigo={$codigo_teste}'>Testar novamente</a> | <a href='admin.php'>Voltar ao Admin</a></p>";
?>

