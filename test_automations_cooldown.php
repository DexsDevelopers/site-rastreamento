<?php
/**
 * Script de teste para verificar os valores de cooldown das automações
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Teste de Cooldowns das Automações</h1>";
echo "<pre>";

try {
    // Buscar todas as automações ativas
    $sql = "SELECT id, nome, tipo, gatilho, cooldown_segundos, ativo FROM bot_automations ORDER BY prioridade DESC, id ASC";
    $stmt = $pdo->query($sql);
    $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== AUTOMAÇÕES NO BANCO DE DADOS ===\n\n";
    echo "Total: " . count($automations) . " automações\n\n";
    
    foreach ($automations as $auto) {
        $status = $auto['ativo'] ? '✅ ATIVA' : '❌ INATIVA';
        $cooldown = (int)$auto['cooldown_segundos'];
        
        // Converter cooldown para formato legível
        $cooldownText = '';
        if ($cooldown == 0) {
            $cooldownText = 'SEM COOLDOWN';
        } else if ($cooldown < 60) {
            $cooldownText = $cooldown . ' segundos';
        } else if ($cooldown < 3600) {
            $minutos = floor($cooldown / 60);
            $segundos = $cooldown % 60;
            $cooldownText = $minutos . ' minutos';
            if ($segundos > 0) $cooldownText .= ' e ' . $segundos . ' segundos';
        } else if ($cooldown < 86400) {
            $horas = floor($cooldown / 3600);
            $minutos = floor(($cooldown % 3600) / 60);
            $cooldownText = $horas . ' horas';
            if ($minutos > 0) $cooldownText .= ' e ' . $minutos . ' minutos';
        } else {
            $dias = floor($cooldown / 86400);
            $horas = floor(($cooldown % 86400) / 3600);
            $cooldownText = $dias . ' dias';
            if ($horas > 0) $cooldownText .= ' e ' . $horas . ' horas';
        }
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: {$auto['id']} {$status}\n";
        echo "Nome: {$auto['nome']}\n";
        echo "Tipo: {$auto['tipo']}\n";
        echo "Gatilho: {$auto['gatilho']}\n";
        echo "Cooldown: {$cooldown} segundos ({$cooldownText})\n";
        echo "\n";
    }
    
    echo "=== TESTE CONCLUÍDO ===\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>";

echo "<br><a href='admin_bot_config.php'>← Voltar para Configurações</a>";
?>

