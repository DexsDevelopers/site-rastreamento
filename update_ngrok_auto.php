<?php
/**
 * Atualização Automática do Ngrok URL
 * Este script atualiza automaticamente o link do Ngrok no banco de dados
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

// URL atual do Ngrok (atualize este valor quando o link mudar)
$ngrokUrl = 'https://lazaro-enforceable-finley.ngrok-free.dev';

try {
    // Atualizar no banco de dados
    $sql = "INSERT INTO bot_settings (chave, valor) VALUES 
        ('WHATSAPP_API_URL', :url),
        ('BOT_API_URL', :url)
        ON DUPLICATE KEY UPDATE valor = :url";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['url' => $ngrokUrl]);

    // Verificar se foi atualizado
    $check = $pdo->query("SELECT * FROM bot_settings WHERE chave IN ('WHATSAPP_API_URL', 'BOT_API_URL')")->fetchAll();

    echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Atualização Ngrok - Sucesso</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #2d3748;
            margin: 0 0 20px 0;
            font-size: 28px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info strong {
            color: #495057;
        }
        .url {
            font-family: 'Courier New', monospace;
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 4px;
            display: inline-block;
            margin: 5px 0;
            word-break: break-all;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>✅ Ngrok URL Atualizado com Sucesso!</h1>
        
        <div class='success'>
            <strong>Status:</strong> O link do Ngrok foi atualizado no banco de dados.
        </div>
        
        <div class='info'>
            <strong>URL Configurado:</strong><br>
            <span class='url'>{$ngrokUrl}</span>
        </div>
        
        <div class='info'>
            <strong>Configurações Atualizadas:</strong>
            <table>
                <thead>
                    <tr>
                        <th>Chave</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>";

    foreach ($check as $row) {
        echo "<tr>
                <td>{$row['chave']}</td>
                <td><span class='url'>{$row['valor']}</span></td>
              </tr>";
    }

    echo "      </tbody>
            </table>
        </div>
        
        <div class='info'>
            <strong>Próximos Passos:</strong>
            <ol>
                <li>Reinicie o bot WhatsApp (se estiver rodando)</li>
                <li>Teste o botão 'Sincronizar Grupos' no painel admin</li>
                <li>Quando o link do Ngrok mudar, atualize a variável <code>\$ngrokUrl</code> neste arquivo e execute novamente</li>
            </ol>
        </div>
        
        <a href='admin_bot_marketing.php' class='btn'>Ir para Painel de Marketing</a>
    </div>
</body>
</html>";

}
catch (Exception $e) {
    echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <title>Erro na Atualização</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f8d7da;
            padding: 40px;
        }
        .error {
            background: white;
            border: 2px solid #f5c6cb;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            margin: 0 auto;
        }
        h1 { color: #721c24; }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class='error'>
        <h1>❌ Erro ao Atualizar</h1>
        <p><strong>Mensagem de erro:</strong></p>
        <code>" . htmlspecialchars($e->getMessage()) . "</code>
    </div>
</body>
</html>";
}
?>