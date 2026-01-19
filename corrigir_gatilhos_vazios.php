<?php
/**
 * Script para corrigir gatilhos com palavras-chave vazias
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Corrigir Gatilhos com Palavras Vazias</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; background: #1a1a1a; color: #fff; padding: 20px; }
    h1 { color: #00ffff; }
    .success { color: #00ff00; font-weight: bold; }
    .error { color: #ff0000; font-weight: bold; }
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
</style>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['corrigir'])) {
    echo "<pre>";
    echo "🔧 Corrigindo gatilhos...\n\n";
    
    try {
        // Buscar todas as automações
        $sql = "SELECT id, nome, gatilho FROM bot_automations";
        $stmt = $pdo->query($sql);
        $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalCorrigidas = 0;
        
        foreach ($automations as $auto) {
            $gatilhoOriginal = $auto['gatilho'];
            
            // Separar por |
            $keywords = explode('|', $gatilhoOriginal);
            
            // Remover palavras vazias e espaços em branco
            $keywordsLimpas = array_filter($keywords, function($k) {
                return trim($k) !== '';
            });
            
            // Remover duplicatas
            $keywordsLimpas = array_unique($keywordsLimpas);
            
            // Trim em cada palavra
            $keywordsLimpas = array_map('trim', $keywordsLimpas);
            
            // Reconstruir gatilho
            $gatilhoNovo = implode('|', $keywordsLimpas);
            
            // Se mudou, atualizar
            if ($gatilhoOriginal !== $gatilhoNovo) {
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "ID {$auto['id']}: {$auto['nome']}\n";
                echo "Antes: {$gatilhoOriginal}\n";
                echo "Depois: {$gatilhoNovo}\n";
                
                // Contar palavras
                $countAntes = count(explode('|', $gatilhoOriginal));
                $countDepois = count($keywordsLimpas);
                echo "Palavras: {$countAntes} → {$countDepois}\n";
                
                // Atualizar no banco
                $updateSql = "UPDATE bot_automations SET gatilho = ? WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$gatilhoNovo, $auto['id']]);
                
                echo "<span class='success'>✅ Atualizado!</span>\n\n";
                $totalCorrigidas++;
            }
        }
        
        if ($totalCorrigidas === 0) {
            echo "<span class='success'>✅ Nenhum gatilho precisou ser corrigido!</span>\n";
        } else {
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "<span class='success'>✅ Total de {$totalCorrigidas} automação(ões) corrigida(s)!</span>\n";
            echo "\n<span class='warning'>⚠️  IMPORTANTE: Reinicie o bot para aplicar as mudanças!</span>\n";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
    }
    
    echo "</pre>";
    echo "<br><a href='diagnostico_automacoes.php' class='button'>🔍 Ver Diagnóstico</a>";
    echo "<a href='admin_bot_config.php' class='button'>⚙️ Configurações</a>";
    
} else {
    // Mostrar análise
    echo "<pre>";
    
    try {
        $sql = "SELECT id, nome, gatilho, tipo FROM bot_automations ORDER BY id";
        $stmt = $pdo->query($sql);
        $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "📋 Analisando gatilhos...\n\n";
        
        $temProblemas = false;
        
        foreach ($automations as $auto) {
            $keywords = explode('|', $auto['gatilho']);
            $hasEmpty = false;
            $hasDuplicates = false;
            $hasSpaces = false;
            
            // Verificar palavras vazias
            foreach ($keywords as $k) {
                if (trim($k) === '') {
                    $hasEmpty = true;
                    break;
                }
            }
            
            // Verificar duplicatas
            if (count($keywords) !== count(array_unique($keywords))) {
                $hasDuplicates = true;
            }
            
            // Verificar espaços extras
            foreach ($keywords as $k) {
                if ($k !== trim($k)) {
                    $hasSpaces = true;
                    break;
                }
            }
            
            if ($hasEmpty || $hasDuplicates || $hasSpaces) {
                $temProblemas = true;
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "ID {$auto['id']}: {$auto['nome']} ({$auto['tipo']})\n";
                echo "Gatilho: " . substr($auto['gatilho'], 0, 80) . "...\n";
                
                if ($hasEmpty) {
                    echo "<span class='error'>❌ TEM PALAVRAS VAZIAS (causa match em tudo!)</span>\n";
                }
                if ($hasDuplicates) {
                    echo "<span class='warning'>⚠️  Tem palavras duplicadas</span>\n";
                }
                if ($hasSpaces) {
                    echo "<span class='warning'>⚠️  Tem espaços extras</span>\n";
                }
                
                echo "Total de palavras-chave: " . count($keywords) . "\n";
                echo "\n";
            }
        }
        
        if (!$temProblemas) {
            echo "<span class='success'>✅ Todos os gatilhos estão corretos!</span>\n";
        } else {
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "<span class='error'>⚠️  Foram encontrados problemas nos gatilhos acima!</span>\n";
            echo "\nClique no botão abaixo para corrigir automaticamente.\n";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
    }
    
    echo "</pre>";
    
    echo "<form method='POST'>";
    echo "<input type='hidden' name='corrigir' value='1'>";
    echo "<button type='submit' class='button'>🔧 CORRIGIR GATILHOS AGORA</button>";
    echo "</form>";
    
    echo "<br><br>";
    echo "<a href='diagnostico_automacoes.php' class='button' style='background: #00aaff; color: #fff;'>🔍 Ver Diagnóstico Completo</a>";
    echo "<a href='admin_bot_config.php' class='button' style='background: #00aaff; color: #fff;'>⚙️ Ir para Configurações</a>";
}
?>

