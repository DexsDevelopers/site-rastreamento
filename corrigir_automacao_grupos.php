<?php
/**
 * Script para corrigir automações e permitir funcionamento em grupos
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Corrigir Automações para Funcionar em Grupos</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; background: #1a1a1a; color: #fff; padding: 20px; }
    h1 { color: #00ffff; }
    .success { color: #00ff00; font-weight: bold; }
    .error { color: #180F33; font-weight: bold; }
    .warning { color: #ffaa00; font-weight: bold; }
    pre { background: #000; padding: 15px; border: 1px solid #333; border-radius: 5px; margin: 20px 0; }
    .button { 
        display: inline-block; 
        padding: 15px 30px; 
        background: #00ff00; 
        color: #000; 
        text-decoration: none; 
        border-radius: 5px; 
        font-weight: bold; 
        margin: 10px 5px;
        border: none;
        cursor: pointer;
        font-size: 16px;
    }
    .button:hover { background: #00cc00; }
    .button.secondary { background: #00aaff; color: #fff; }
    .button.secondary:hover { background: #0088cc; }
</style>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    echo "<pre>";
    
    if ($_POST['action'] === 'corrigir_todas') {
        echo "🔧 Corrigindo TODAS as automações...\n\n";
        
        try {
            // Desmarcar "apenas_privado" de todas as automações
            $sql = "UPDATE bot_automations SET apenas_privado = 0 WHERE apenas_privado = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $affected = $stmt->rowCount();
            
            echo "<span class='success'>✅ Sucesso! {$affected} automação(ões) atualizada(s)</span>\n";
            echo "\nAgora as automações funcionarão tanto em grupos quanto em privado.\n";
            echo "\n<span class='warning'>⚠️  IMPORTANTE: Reinicie o bot para aplicar as mudanças!</span>\n";
            
        } catch (Exception $e) {
            echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
        }
        
    } elseif ($_POST['action'] === 'corrigir_id14') {
        echo "🔧 Corrigindo apenas a automação ID 14 (E-SIM)...\n\n";
        
        try {
            $sql = "UPDATE bot_automations SET apenas_privado = 0 WHERE id = 14";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo "<span class='success'>✅ Sucesso! Automação ID 14 atualizada</span>\n";
                echo "\nAgora a automação E-SIM funcionará em grupos.\n";
                echo "\n<span class='warning'>⚠️  IMPORTANTE: Reinicie o bot para aplicar as mudanças!</span>\n";
            } else {
                echo "<span class='warning'>⚠️  A automação ID 14 não foi encontrada ou já estava correta.</span>\n";
            }
            
        } catch (Exception $e) {
            echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
        }
    }
    
    echo "</pre>";
    echo "<br><a href='diagnostico_automacoes.php' class='button secondary'>🔍 Ver Diagnóstico</a>";
    echo "<a href='admin_bot_config.php' class='button secondary'>⚙️ Configurações</a>";
    
} else {
    // Mostrar opções
    echo "<pre>";
    
    try {
        $sql = "SELECT id, nome, apenas_privado, apenas_grupo, ativo FROM bot_automations ORDER BY id";
        $stmt = $pdo->query($sql);
        $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "📋 Automações encontradas:\n\n";
        
        foreach ($automations as $auto) {
            $status = $auto['ativo'] ? '✅ ATIVA' : '❌ INATIVA';
            $privado = $auto['apenas_privado'] ? '🔒 Apenas Privado' : '✅ Privado e Grupos';
            $grupo = $auto['apenas_grupo'] ? '👥 Apenas Grupos' : '';
            
            echo "ID {$auto['id']}: {$auto['nome']} - {$status}\n";
            echo "  Configuração atual: {$privado} {$grupo}\n\n";
        }
        
        $countPrivado = $pdo->query("SELECT COUNT(*) FROM bot_automations WHERE apenas_privado = 1")->fetchColumn();
        
        if ($countPrivado > 0) {
            echo "<span class='warning'>⚠️  {$countPrivado} automação(ões) está(ão) configurada(s) como 'Apenas Privado'\n";
            echo "Isso impede que funcionem em grupos!</span>\n\n";
        } else {
            echo "<span class='success'>✅ Nenhuma automação está restrita apenas a privado</span>\n\n";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
    }
    
    echo "</pre>";
    
    echo "<h2>Escolha uma opção:</h2>";
    
    echo "<form method='POST' style='margin: 20px 0;'>";
    echo "<input type='hidden' name='action' value='corrigir_id14'>";
    echo "<button type='submit' class='button'>🔧 Corrigir apenas ID 14 (E-SIM)</button>";
    echo "<p style='margin-left: 20px; color: #aaa;'>Permitir que a automação E-SIM funcione em grupos</p>";
    echo "</form>";
    
    echo "<form method='POST' style='margin: 20px 0;'>";
    echo "<input type='hidden' name='action' value='corrigir_todas'>";
    echo "<button type='submit' class='button'>🔧 Corrigir TODAS as automações</button>";
    echo "<p style='margin-left: 20px; color: #aaa;'>Remover a restrição 'Apenas Privado' de todas as automações</p>";
    echo "</form>";
    
    echo "<br><br>";
    echo "<a href='diagnostico_automacoes.php' class='button secondary'>🔍 Ver Diagnóstico Completo</a>";
    echo "<a href='admin_bot_config.php' class='button secondary'>⚙️ Ir para Configurações</a>";
}
?>

