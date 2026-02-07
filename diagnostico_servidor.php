<?php
/**
 * Diagnóstico do Servidor - Marketing Bot
 * Este script verifica se tudo está configurado corretamente
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnóstico do Servidor</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2d3748;
            margin: 0 0 30px 0;
        }
        .check {
            background: #f8f9fa;
            border-left: 4px solid #6c757d;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .check.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .check.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .check.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .check h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .check p {
            margin: 5px 0;
            font-size: 14px;
        }
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
        pre {
            background: #2d3748;
            color: #fff;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Diagnóstico do Servidor</h1>";

// 1. Verificar arquivos necessários
echo "<div class='check " . (file_exists('api_marketing.php') ? 'success' : 'error') . "'>
    <h3>1. Arquivo api_marketing.php</h3>
    <p>" . (file_exists('api_marketing.php') ? '✅ Arquivo encontrado' : '❌ Arquivo NÃO encontrado') . "</p>
</div>";

echo "<div class='check " . (file_exists('api_marketing_ajax.php') ? 'success' : 'error') . "'>
    <h3>2. Arquivo api_marketing_ajax.php</h3>
    <p>" . (file_exists('api_marketing_ajax.php') ? '✅ Arquivo encontrado' : '❌ Arquivo NÃO encontrado') . "</p>
</div>";

echo "<div class='check " . (file_exists('fix_db_migration.php') ? 'success' : 'error') . "'>
    <h3>3. Arquivo fix_db_migration.php</h3>
    <p>" . (file_exists('fix_db_migration.php') ? '✅ Arquivo encontrado' : '❌ Arquivo NÃO encontrado') . "</p>
</div>";

// 2. Verificar conexão com banco
try {
    require_once 'includes/config.php';
    require_once 'includes/db_connect.php';

    echo "<div class='check success'>
        <h3>4. Conexão com Banco de Dados</h3>
        <p>✅ Conexão estabelecida com sucesso</p>
    </div>";

    // 3. Verificar estrutura da tabela marketing_membros
    $tableCheck = $pdo->query("SHOW COLUMNS FROM marketing_membros WHERE Field = 'telefone'")->fetch();

    if ($tableCheck) {
        $type = $tableCheck['Type'];
        $isLargeEnough = (stripos($type, 'varchar(100)') !== false ||
            stripos($type, 'varchar(150)') !== false ||
            stripos($type, 'varchar(200)') !== false ||
            stripos($type, 'varchar(255)') !== false);

        echo "<div class='check " . ($isLargeEnough ? 'success' : 'error') . "'>
            <h3>5. Estrutura da Tabela marketing_membros</h3>
            <p>Campo 'telefone': <code>{$type}</code></p>
            <p>" . ($isLargeEnough ? '✅ Tamanho adequado para JIDs' : '❌ PROBLEMA: Campo muito pequeno! Execute fix_db_migration.php') . "</p>
        </div>";
    }
    else {
        echo "<div class='check error'>
            <h3>5. Estrutura da Tabela marketing_membros</h3>
            <p>❌ Tabela não encontrada ou campo 'telefone' não existe</p>
        </div>";
    }

    // 4. Verificar configurações do bot
    $botSettings = $pdo->query("SELECT * FROM bot_settings WHERE chave IN ('WHATSAPP_API_URL', 'BOT_API_URL')")->fetchAll();

    if ($botSettings) {
        echo "<div class='check success'>
            <h3>6. Configurações do Bot</h3>";
        foreach ($botSettings as $setting) {
            echo "<p><strong>{$setting['chave']}:</strong> <code>{$setting['valor']}</code></p>";
        }
        echo "</div>";
    }
    else {
        echo "<div class='check warning'>
            <h3>6. Configurações do Bot</h3>
            <p>⚠️ Nenhuma configuração encontrada. Execute update_ngrok_auto.php</p>
        </div>";
    }

    // 5. Testar se api_marketing.php aceita requisições
    echo "<div class='check warning'>
        <h3>7. Teste de Requisição</h3>
        <p>Para testar se o api_marketing.php está funcionando, execute:</p>
        <pre>curl -X POST -H \"Content-Type: application/json\" \\
  -H \"x-api-token: lucastav8012\" \\
  -d '{\"group_jid\":\"test@g.us\",\"members\":[\"5511999999999@s.whatsapp.net\"]}' \\
  \"https://cornflowerblue-fly-883408.hostingersite.com/api_marketing.php?action=save_members\"</pre>
    </div>";

    // 6. Verificar versão do PHP
    $phpVersion = phpversion();
    echo "<div class='check success'>
        <h3>8. Versão do PHP</h3>
        <p>✅ PHP {$phpVersion}</p>
    </div>";

    // 7. Verificar se há membros na tabela
    $memberCount = $pdo->query("SELECT COUNT(*) as total FROM marketing_membros")->fetch();
    echo "<div class='check success'>
        <h3>9. Membros Cadastrados</h3>
        <p>Total de membros: <strong>{$memberCount['total']}</strong></p>
    </div>";

}
catch (Exception $e) {
    echo "<div class='check error'>
        <h3>4. Conexão com Banco de Dados</h3>
        <p>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "
        <div class='check warning'>
            <h3>📋 Próximos Passos</h3>
            <p>Se houver algum erro acima:</p>
            <ol>
                <li>Certifique-se de que todos os arquivos foram enviados para o servidor</li>
                <li>Execute <code>fix_db_migration.php</code> se o campo 'telefone' estiver pequeno</li>
                <li>Execute <code>update_ngrok_auto.php</code> para atualizar o link do bot</li>
                <li>Verifique os logs de erro do servidor (Error Log no cPanel)</li>
            </ol>
        </div>
    </div>
</body>
</html>";
?>