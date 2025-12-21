<?php
/**
 * Verificar status das automações do bot
 */
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<pre>";
echo "=== VERIFICAÇÃO DO SISTEMA DE AUTOMAÇÕES ===\n\n";

// 1. Verificar se tabelas existem
echo "1. Verificando tabelas...\n";
try {
    $pdo->query("SELECT 1 FROM bot_automations LIMIT 1");
    echo "   ✅ Tabela bot_automations existe\n";
} catch (PDOException $e) {
    echo "   ❌ Tabela bot_automations NÃO existe!\n";
    echo "   >> Execute: setup_bot_automations.php\n\n";
    echo "</pre>";
    exit;
}

try {
    $pdo->query("SELECT 1 FROM bot_settings LIMIT 1");
    echo "   ✅ Tabela bot_settings existe\n";
} catch (PDOException $e) {
    echo "   ❌ Tabela bot_settings NÃO existe!\n";
}

// 2. Listar automações
echo "\n2. Automações cadastradas:\n";
$automations = $pdo->query("SELECT * FROM bot_automations ORDER BY prioridade DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "   Total: " . count($automations) . "\n\n";

foreach ($automations as $a) {
    $status = $a['ativo'] == 1 ? '✅ ATIVO' : '❌ INATIVO';
    echo "   [{$a['id']}] {$a['nome']}\n";
    echo "       Status: {$status}\n";
    echo "       Tipo: {$a['tipo']}\n";
    echo "       Gatilho: {$a['gatilho']}\n";
    echo "       Resposta: " . substr($a['resposta'], 0, 50) . "...\n";
    echo "       Usos: {$a['contador_uso']}\n\n";
}

// 3. Verificar configurações
echo "3. Configurações do bot:\n";
$settings = $pdo->query("SELECT * FROM bot_settings")->fetchAll(PDO::FETCH_ASSOC);
foreach ($settings as $s) {
    $valor = $s['valor'];
    if ($s['tipo'] === 'boolean') {
        $valor = $valor === '1' ? 'true' : 'false';
    }
    echo "   {$s['chave']}: {$valor}\n";
}

// 4. Testar match com "ola"
echo "\n4. Teste de match com 'ola':\n";
$testText = 'ola';
$found = false;

foreach ($automations as $a) {
    if ($a['ativo'] != 1) continue;
    
    $gatilho = strtolower($a['gatilho']);
    $text = strtolower($testText);
    
    $match = false;
    switch ($a['tipo']) {
        case 'palavra_chave':
            $keywords = array_map('trim', explode('|', $gatilho));
            foreach ($keywords as $kw) {
                if (strpos($text, $kw) !== false || $text === $kw) {
                    $match = true;
                    break;
                }
            }
            break;
        case 'mensagem_especifica':
            $match = ($text === $gatilho);
            break;
    }
    
    if ($match) {
        echo "   ✅ MATCH encontrado: {$a['nome']}\n";
        echo "      Gatilho: {$a['gatilho']}\n";
        $found = true;
    }
}

if (!$found) {
    echo "   ❌ Nenhum match encontrado para 'ola'\n";
    echo "   >> Verifique se a automação de boas-vindas inclui 'ola' no gatilho\n";
}

// 5. Verificar API
echo "\n5. Verificando API de automações...\n";
$apiUrl = getDynamicConfig('WHATSAPP_API_URL', 'http://localhost:3000');
$token = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');

echo "   URL Base: {$apiUrl}\n";
echo "   Token: " . substr($token, 0, 4) . "***\n";

echo "\n=== FIM DA VERIFICAÇÃO ===\n";
echo "</pre>";

