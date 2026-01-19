<?php
/**
 * Diagnóstico completo do sistema de automações
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Diagnóstico Completo de Automações</h1>";
echo "<style>
    body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
    h1, h2 { color: #00ffff; }
    .success { color: #00ff00; }
    .error { color: #ff0000; }
    .warning { color: #ffaa00; }
    .info { color: #00aaff; }
    pre { background: #000; padding: 15px; border: 1px solid #333; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #333; padding: 10px; text-align: left; }
    th { background: #333; color: #00ffff; }
    .status-ok { color: #00ff00; font-weight: bold; }
    .status-error { color: #ff0000; font-weight: bold; }
</style>";

echo "<pre>";

// 1. Verificar conexão com banco
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  VERIFICANDO CONEXÃO COM BANCO DE DADOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($pdo) {
    echo "<span class='success'>✅ Conexão estabelecida com sucesso</span>\n";
} else {
    echo "<span class='error'>❌ ERRO: Não foi possível conectar ao banco</span>\n";
    exit;
}

// 2. Verificar tabela bot_automations
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  VERIFICANDO TABELA bot_automations\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'bot_automations'");
    if ($stmt->rowCount() > 0) {
        echo "<span class='success'>✅ Tabela existe</span>\n";
        
        // Contar registros
        $count = $pdo->query("SELECT COUNT(*) FROM bot_automations")->fetchColumn();
        echo "📊 Total de automações cadastradas: <span class='info'>{$count}</span>\n";
        
        $countAtivas = $pdo->query("SELECT COUNT(*) FROM bot_automations WHERE ativo = 1")->fetchColumn();
        echo "✅ Automações ATIVAS: <span class='success'>{$countAtivas}</span>\n";
        
        $countInativas = $pdo->query("SELECT COUNT(*) FROM bot_automations WHERE ativo = 0")->fetchColumn();
        echo "❌ Automações INATIVAS: <span class='warning'>{$countInativas}</span>\n";
    } else {
        echo "<span class='error'>❌ ERRO: Tabela não existe</span>\n";
        exit;
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
    exit;
}

// 3. Listar todas as automações
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  LISTANDO TODAS AS AUTOMAÇÕES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $sql = "SELECT * FROM bot_automations ORDER BY prioridade DESC, id ASC";
    $stmt = $pdo->query($sql);
    $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "</pre>";
    echo "<table>";
    echo "<tr>
            <th>ID</th>
            <th>Status</th>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Gatilho</th>
            <th>Cooldown</th>
            <th>Delay</th>
            <th>Prioridade</th>
            <th>Grupo</th>
            <th>Privado</th>
          </tr>";
    
    foreach ($automations as $auto) {
        $statusClass = $auto['ativo'] ? 'status-ok' : 'status-error';
        $statusText = $auto['ativo'] ? '✅ ATIVA' : '❌ INATIVA';
        
        $cooldown = (int)$auto['cooldown_segundos'];
        if ($cooldown == 0) {
            $cooldownText = 'SEM';
        } else if ($cooldown < 60) {
            $cooldownText = $cooldown . 's';
        } else if ($cooldown < 3600) {
            $cooldownText = floor($cooldown / 60) . 'min';
        } else if ($cooldown < 86400) {
            $cooldownText = floor($cooldown / 3600) . 'h';
        } else {
            $cooldownText = floor($cooldown / 86400) . 'd';
        }
        
        $delay = (int)$auto['delay_ms'];
        $delayText = $delay > 0 ? $delay . 'ms' : 'SEM';
        
        $apenasGrupo = $auto['apenas_grupo'] ? '✅' : '❌';
        $apenasPrivado = $auto['apenas_privado'] ? '✅' : '❌';
        
        $grupoInfo = '';
        if ($auto['grupo_id']) {
            $grupoInfo = substr($auto['grupo_nome'] ?: $auto['grupo_id'], 0, 20);
        } else {
            $grupoInfo = 'TODOS';
        }
        
        $gatilho = htmlspecialchars(substr($auto['gatilho'], 0, 50));
        if (strlen($auto['gatilho']) > 50) $gatilho .= '...';
        
        echo "<tr>
                <td>{$auto['id']}</td>
                <td class='{$statusClass}'>{$statusText}</td>
                <td>" . htmlspecialchars($auto['nome']) . "</td>
                <td>{$auto['tipo']}</td>
                <td>{$gatilho}</td>
                <td>{$cooldownText}</td>
                <td>{$delayText}</td>
                <td>{$auto['prioridade']}</td>
                <td>{$grupoInfo}</td>
                <td>{$apenasPrivado}</td>
              </tr>";
    }
    
    echo "</table>";
    echo "<pre>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
}

// 4. Testar API (simulando chamada do bot)
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4️⃣  TESTANDO API (api_bot_automations.php)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    // Simular chamada da API
    $sql = "SELECT id, nome, tipo, gatilho, resposta, imagem_url, grupo_id, grupo_nome, 
                   apenas_privado, apenas_grupo, delay_ms, cooldown_segundos, prioridade
            FROM bot_automations 
            WHERE ativo = 1 
            ORDER BY prioridade DESC, id ASC";
    
    $stmt = $pdo->query($sql);
    $apiAutomations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📡 Automações que o BOT vai receber da API:\n";
    echo "Total: <span class='info'>" . count($apiAutomations) . "</span> automações ativas\n\n";
    
    if (count($apiAutomations) === 0) {
        echo "<span class='error'>⚠️  ATENÇÃO: Nenhuma automação ativa será enviada ao bot!</span>\n";
        echo "<span class='warning'>Solução: Ative pelo menos uma automação no painel admin</span>\n";
    } else {
        foreach ($apiAutomations as $auto) {
            echo "  • ID {$auto['id']}: {$auto['nome']} ({$auto['tipo']})\n";
            echo "    Gatilho: " . substr($auto['gatilho'], 0, 60) . "\n";
            echo "    Cooldown: {$auto['cooldown_segundos']}s\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERRO na API: {$e->getMessage()}</span>\n";
}

// 5. Verificar configurações do bot
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "5️⃣  VERIFICANDO CONFIGURAÇÕES DO BOT\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'bot_settings'");
    if ($stmt->rowCount() > 0) {
        $settings = $pdo->query("SELECT * FROM bot_settings")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($settings as $setting) {
            $value = $setting['valor'];
            if ($setting['tipo'] === 'boolean') {
                $value = ($value === '1' || $value === 'true') ? '✅ ATIVADO' : '❌ DESATIVADO';
            }
            
            echo "{$setting['chave']}: <span class='info'>{$value}</span>\n";
        }
    } else {
        echo "<span class='warning'>⚠️  Tabela bot_settings não existe</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
}

// Resumo final
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 RESUMO DO DIAGNÓSTICO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$countAtivas = $pdo->query("SELECT COUNT(*) FROM bot_automations WHERE ativo = 1")->fetchColumn();

if ($countAtivas > 0) {
    echo "<span class='success'>✅ Sistema OK - {$countAtivas} automação(ões) ativa(s)</span>\n";
    echo "\n<span class='info'>Próximo passo:</span>\n";
    echo "1. Reinicie o bot: Ctrl+C e depois 'node index.js'\n";
    echo "2. Envie uma mensagem com uma das palavras-chave em um grupo\n";
    echo "3. Verifique os logs do bot no terminal\n";
} else {
    echo "<span class='error'>❌ PROBLEMA ENCONTRADO - Nenhuma automação ativa!</span>\n";
    echo "\n<span class='warning'>Solução:</span>\n";
    echo "1. Vá para admin_bot_config.php\n";
    echo "2. Clique em 'Editar' em uma automação\n";
    echo "3. Marque a opção 'Ativo'\n";
    echo "4. Salve e reinicie o bot\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "</pre>";

echo "<br><br><a href='admin_bot_config.php' style='color: #00ffff;'>← Voltar para Configurações</a>";
echo " | ";
echo "<a href='test_automations_cooldown.php' style='color: #00ffff;'>Ver Cooldowns →</a>";
?>

