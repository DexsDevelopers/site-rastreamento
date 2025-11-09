<?php
/**
 * Sistema de Automações - Cron Job
 * Este arquivo deve ser executado periodicamente via cron job
 * Exemplo: 0,30 * * * * php /path/to/automation_cron.php
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';

// Verificar se é uma execução via cron
if (!isset($_GET['cron']) || $_GET['cron'] !== 'true') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acesso negado. Use ?cron=true']);
    exit;
}

// Log de execução
$logFile = 'automation_logs.txt';
$timestamp = date('Y-m-d H:i:s');

function writeCronLog($message) {
    global $logFile, $timestamp;
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// 1. Verificar e atualizar status automaticamente
function checkAndUpdateStatus($pdo) {
    writeCronLog("Iniciando verificação de status...");
    // Último registro por código
    $sql = "SELECT rs.codigo, rs.cidade, rs.status_atual, rs.data
            FROM rastreios_status rs
            INNER JOIN (
                SELECT codigo, MAX(data) AS max_data
                FROM rastreios_status
                GROUP BY codigo
            ) t ON t.codigo = rs.codigo AND t.max_data = rs.data
            WHERE rs.status_atual NOT LIKE '%Entregue%'
              AND rs.data < DATE_SUB(NOW(), INTERVAL 2 HOUR)";
    $results = fetchData($pdo, $sql);
    $updated = 0;
    foreach ($results as $row) {
        $codigo = $row['codigo'];
        $cidade = $row['cidade'];
        $ultimo_status = $row['status_atual'];
        $novo_status = '';
        $novo_titulo = '';
        $novo_subtitulo = '';
        $nova_cor = '';
        if (strpos($ultimo_status, 'Objeto postado') !== false) {
            $novo_status = '🚚 Em trânsito';
            $novo_titulo = '🚚 Em trânsito';
            $novo_subtitulo = 'A caminho do centro de distribuição';
            $nova_cor = 'bg-orange-500';
        } elseif (strpos($ultimo_status, 'Em trânsito') !== false) {
            $novo_status = '🏢 No centro de distribuição';
            $novo_titulo = '🏢 No centro de distribuição';
            $novo_subtitulo = 'Processando encaminhamento';
            $nova_cor = 'bg-yellow-500';
        } elseif (strpos($ultimo_status, 'No centro de distribuição') !== false) {
            $novo_status = '🚀 Saiu para entrega';
            $novo_titulo = '🚀 Saiu para entrega';
            $novo_subtitulo = 'Saiu para entrega ao destinatário';
            $nova_cor = 'bg-red-500';
        }
        if ($novo_status) {
            $data_nova = date('Y-m-d H:i:s');
            $ins = "INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor) VALUES (?, ?, ?, ?, ?, ?, ?)";
            try {
                executeQuery($pdo, $ins, [$codigo, $cidade, $novo_status, $novo_titulo, $novo_subtitulo, $data_nova, $nova_cor]);
                notifyWhatsappLatestStatus($pdo, $codigo);
                $updated++;
                writeCronLog("Status atualizado para $codigo: $novo_status");
            } catch (Exception $e) {
                writeCronLog("Erro ao atualizar status para $codigo: " . $e->getMessage());
            }
        }
    }
    writeCronLog("Verificação de status concluída. $updated rastreios atualizados.");
    return $updated;
}

// 2. Verificar taxas pendentes e aplicar alertas
function checkPendingTaxes($pdo) {
    writeCronLog("Verificando taxas pendentes...");
    $sql = "SELECT rs.codigo, rs.cidade, rs.taxa_valor, rs.taxa_pix, rs.data
            FROM rastreios_status rs
            INNER JOIN (
                SELECT codigo, MAX(data) AS max_data
                FROM rastreios_status
                GROUP BY codigo
            ) t ON t.codigo = rs.codigo AND t.max_data = rs.data
            WHERE rs.taxa_valor IS NOT NULL AND rs.taxa_pix IS NOT NULL
              AND rs.data < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $results = fetchData($pdo, $sql);
    $alertas = 0;
    foreach ($results as $row) {
        $codigo = $row['codigo'];
        $cidade = $row['cidade'];
        $taxa_valor = $row['taxa_valor'];
        writeCronLog("ALERTA: Taxa pendente há mais de 24h - Código: $codigo, Cidade: $cidade, Valor: R$ $taxa_valor");
        $alertas++;
    }
    writeCronLog("Verificação de taxas concluída. $alertas alertas gerados.");
    return $alertas;
}

// 3. Verificar rastreios "presos" (sem atualização há muito tempo)
function checkStuckRastreios($pdo) {
    writeCronLog("Verificando rastreios presos...");
    $sql = "SELECT rs.codigo, rs.cidade, rs.data AS ultima_data, rs.status_atual
            FROM rastreios_status rs
            INNER JOIN (
                SELECT codigo, MAX(data) AS max_data
                FROM rastreios_status
                GROUP BY codigo
            ) t ON t.codigo = rs.codigo AND t.max_data = rs.data
            WHERE rs.status_atual NOT LIKE '%Entregue%'
              AND rs.data < DATE_SUB(NOW(), INTERVAL 3 DAY)";
    $results = fetchData($pdo, $sql);
    $presos = 0;
    foreach ($results as $row) {
        $codigo = $row['codigo'];
        $cidade = $row['cidade'];
        $ultima_data = $row['ultima_data'];
        $ultimo_status = $row['status_atual'];
        writeCronLog("ALERTA: Rastreio preso - Código: $codigo, Cidade: $cidade, Última atualização: $ultima_data, Status: $ultimo_status");
        $presos++;
    }
    writeCronLog("Verificação de rastreios presos concluída. $presos rastreios encontrados.");
    return $presos;
}

// 4. Gerar relatório automático (executar apenas aos domingos às 8h)
function generateWeeklyReport($pdo) {
    if (date('N') == 7 && date('H') == 8) { // Domingo às 8h
        writeLog("Gerando relatório semanal...");
        
        try {
            // Estatísticas da semana
            $inicio_semana = date('Y-m-d', strtotime('monday this week'));
            $fim_semana = date('Y-m-d', strtotime('sunday this week'));
            
            $total_rastreios = fetchOne($pdo, "SELECT COUNT(DISTINCT codigo) as total FROM rastreios_status WHERE DATE(data) BETWEEN ? AND ?", [$inicio_semana, $fim_semana])['total'];
            $entregues = fetchOne($pdo, "SELECT COUNT(DISTINCT codigo) as total FROM rastreios_status WHERE status_atual LIKE '%Entregue%' AND DATE(data) BETWEEN ? AND ?", [$inicio_semana, $fim_semana])['total'];
            $com_taxa = fetchOne($pdo, "SELECT COUNT(DISTINCT codigo) as total FROM rastreios_status WHERE taxa_valor IS NOT NULL AND DATE(data) BETWEEN ? AND ?", [$inicio_semana, $fim_semana])['total'];
            
            $relatorio = "RELATÓRIO SEMANAL - $inicio_semana a $fim_semana\n";
            $relatorio .= "Total de rastreios: $total_rastreios\n";
            $relatorio .= "Entregues: $entregues\n";
            $relatorio .= "Com taxa: $com_taxa\n";
            $relatorio .= "Taxa de entrega: " . ($total_rastreios > 0 ? round(($entregues / $total_rastreios) * 100, 2) : 0) . "%\n";
            
            // Salvar relatório
            file_put_contents('relatorio_semanal_' . date('Y-m-d') . '.txt', $relatorio);
            writeLog("Relatório semanal gerado com sucesso.");
            
            return true;
        } catch (Exception $e) {
            writeLog("Erro ao gerar relatório semanal: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    return false;
}

// 5. Limpeza de logs antigos
function cleanupOldLogs() {
    writeLog("Executando limpeza de logs antigos...");
    
    // Manter apenas logs dos últimos 30 dias
    $logFile = 'automation_logs.txt';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        $filtered_lines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                if ($matches[1] >= $cutoff_date) {
                    $filtered_lines[] = $line;
                }
            }
        }
        
        file_put_contents($logFile, implode('', $filtered_lines));
        writeLog("Limpeza de logs concluída.");
    }
}

// Executar todas as automações
try {
    writeCronLog("=== INÍCIO DA EXECUÇÃO DE AUTOMAÇÕES ===");
    
    $status_updated = checkAndUpdateStatus($pdo);
    $taxes_alerted = checkPendingTaxes($pdo);
    $stuck_found = checkStuckRastreios($pdo);
    $report_generated = generateWeeklyReport($pdo);
    cleanupOldLogs();
    
    writeCronLog("=== FIM DA EXECUÇÃO DE AUTOMAÇÕES ===");
    writeCronLog("Resumo: $status_updated status atualizados, $taxes_alerted alertas de taxa, $stuck_found rastreios presos");
    
    // Retornar status para monitoramento
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => $timestamp,
        'status_updated' => $status_updated,
        'taxes_alerted' => $taxes_alerted,
        'stuck_found' => $stuck_found,
        'report_generated' => $report_generated
    ]);
    
} catch (Exception $e) {
    writeCronLog("ERRO: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => $timestamp
    ]);
}
?>
