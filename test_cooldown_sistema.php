<?php
/**
 * Script para testar se o cooldown está funcionando corretamente
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🧪 Teste de Sistema de Cooldown</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; background: #1a1a1a; color: #fff; padding: 20px; }
    h1, h2 { color: #00ffff; }
    .success { color: #00ff00; font-weight: bold; }
    .error { color: #ff0000; font-weight: bold; }
    .warning { color: #ffaa00; font-weight: bold; }
    .info { color: #00aaff; }
    pre { background: #000; padding: 15px; border: 1px solid #333; border-radius: 5px; margin: 20px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; background: #000; }
    th, td { border: 1px solid #333; padding: 12px; text-align: left; }
    th { background: #333; color: #00ffff; }
    tr:hover { background: #222; }
</style>";

echo "<pre>";

// 1. Verificar automações com cooldown
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  AUTOMAÇÕES COM COOLDOWN CONFIGURADO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $sql = "SELECT id, nome, cooldown_segundos, ativo FROM bot_automations WHERE cooldown_segundos > 0 ORDER BY id";
    $stmt = $pdo->query($sql);
    $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($automations) === 0) {
        echo "<span class='warning'>⚠️  Nenhuma automação com cooldown configurado</span>\n";
    } else {
        foreach ($automations as $auto) {
            $status = $auto['ativo'] ? '✅ ATIVA' : '❌ INATIVA';
            $cooldown = (int)$auto['cooldown_segundos'];
            
            $horas = floor($cooldown / 3600);
            $minutos = floor(($cooldown % 3600) / 60);
            $segundos = $cooldown % 60;
            
            $tempoFormatado = '';
            if ($horas > 0) $tempoFormatado .= "{$horas}h ";
            if ($minutos > 0) $tempoFormatado .= "{$minutos}min ";
            if ($segundos > 0) $tempoFormatado .= "{$segundos}s";
            
            echo "ID {$auto['id']}: {$auto['nome']} - {$status}\n";
            echo "  Cooldown: <span class='info'>{$cooldown}s ({$tempoFormatado})</span>\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
}

// 2. Verificar logs de execução na API (últimas 20 execuções)
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  ÚLTIMAS EXECUÇÕES DAS AUTOMAÇÕES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    // Verificar se tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'bot_automation_logs'");
    if ($stmt->rowCount() > 0) {
        $sql = "SELECT l.*, a.nome as automacao_nome 
                FROM bot_automation_logs l
                LEFT JOIN bot_automations a ON l.automation_id = a.id
                ORDER BY l.created_at DESC
                LIMIT 20";
        $stmt = $pdo->query($sql);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($logs) === 0) {
            echo "<span class='warning'>⚠️  Nenhum log de execução encontrado</span>\n";
        } else {
            echo "</pre>";
            echo "<table>";
            echo "<tr>
                    <th>Hora</th>
                    <th>Automação</th>
                    <th>ID Auto</th>
                    <th>Número</th>
                    <th>Grupo/Nome</th>
                    <th>Mensagem</th>
                  </tr>";
            
            $lastExecutions = []; // Rastrear última execução por automação+jid
            
            foreach ($logs as $log) {
                $hora = date('d/m H:i:s', strtotime($log['created_at']));
                $numero = substr($log['numero_origem'], 0, 13);
                $mensagem = htmlspecialchars(substr($log['mensagem_recebida'], 0, 30));
                $grupoNome = htmlspecialchars(substr($log['grupo_nome'] ?: 'Privado', 0, 20));
                
                // Calcular diferença de tempo desde última execução desta automação neste grupo/jid
                $key = $log['automation_id'] . '-' . $log['jid_origem'];
                $tempoDecorrido = '';
                
                if (isset($lastExecutions[$key])) {
                    $diff = strtotime($lastExecutions[$key]) - strtotime($log['created_at']);
                    if ($diff > 0) {
                        $horas = floor($diff / 3600);
                        $minutos = floor(($diff % 3600) / 60);
                        $tempoDecorrido = " <span class='info'>({$horas}h {$minutos}min antes)</span>";
                    }
                }
                $lastExecutions[$key] = $log['created_at'];
                
                echo "<tr>
                        <td>{$hora}</td>
                        <td>{$log['automacao_nome']}</td>
                        <td>{$log['automation_id']}</td>
                        <td>{$numero}</td>
                        <td>{$grupoNome}</td>
                        <td>{$mensagem}{$tempoDecorrido}</td>
                      </tr>";
            }
            
            echo "</table>";
            echo "<pre>";
        }
    } else {
        echo "<span class='warning'>⚠️  Tabela de logs não existe</span>\n";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
}

// 3. Analisar padrão de execuções
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  ANÁLISE DE COOLDOWN (por automação + grupo)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'bot_automation_logs'");
    if ($stmt->rowCount() > 0) {
        // Buscar execuções agrupadas
        $sql = "SELECT 
                    automation_id,
                    jid_origem,
                    grupo_nome,
                    COUNT(*) as total_execucoes,
                    MIN(created_at) as primeira_exec,
                    MAX(created_at) as ultima_exec,
                    a.nome as automacao_nome,
                    a.cooldown_segundos
                FROM bot_automation_logs l
                LEFT JOIN bot_automations a ON l.automation_id = a.id
                WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY automation_id, jid_origem
                HAVING total_execucoes > 1
                ORDER BY ultima_exec DESC";
        
        $stmt = $pdo->query($sql);
        $analises = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($analises) === 0) {
            echo "<span class='success'>✅ Nenhuma automação executou mais de 1 vez no mesmo grupo nas últimas 24h</span>\n";
        } else {
            foreach ($analises as $analise) {
                $cooldownConfig = (int)$analise['cooldown_segundos'];
                $totalExec = $analise['total_execucoes'];
                
                $primeira = new DateTime($analise['primeira_exec']);
                $ultima = new DateTime($analise['ultima_exec']);
                $intervalo = $primeira->diff($ultima);
                
                $tempoTotal = ($intervalo->h * 3600) + ($intervalo->i * 60) + $intervalo->s;
                $intervaloMedio = $totalExec > 1 ? floor($tempoTotal / ($totalExec - 1)) : 0;
                
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "Automação: <span class='info'>{$analise['automacao_nome']}</span> (ID: {$analise['automation_id']})\n";
                echo "Grupo: " . htmlspecialchars($analise['grupo_nome'] ?: 'Privado') . "\n";
                echo "Total de execuções: <span class='warning'>{$totalExec}x</span>\n";
                echo "Primeira: " . $primeira->format('d/m H:i:s') . "\n";
                echo "Última: " . $ultima->format('d/m H:i:s') . "\n";
                echo "Intervalo médio: <span class='info'>" . floor($intervaloMedio/3600) . "h " . floor(($intervaloMedio%3600)/60) . "min</span>\n";
                echo "Cooldown configurado: <span class='info'>" . floor($cooldownConfig/3600) . "h " . floor(($cooldownConfig%3600)/60) . "min</span>\n";
                
                if ($intervaloMedio < $cooldownConfig) {
                    echo "<span class='error'>❌ PROBLEMA: Executando mais rápido que o cooldown!</span>\n";
                } else {
                    echo "<span class='success'>✅ OK: Respeitando o cooldown</span>\n";
                }
                echo "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERRO: {$e->getMessage()}</span>\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "</pre>";

echo "<br><a href='diagnostico_automacoes.php' style='color: #00ffff; text-decoration: none; padding: 10px 20px; background: #333; border-radius: 5px;'>← Ver Diagnóstico Completo</a>";
?>

