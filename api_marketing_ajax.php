<?php
/**
 * API exclusiva para ações de Marketing
 * Retorna apenas JSON puro, sem HTML
 */

// Prevent any output before JSON
ob_start();

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

// Verificar autenticação
requireLogin();

// Clean any previous output
if (ob_get_length()) ob_clean();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';
$response = ['success' => false, 'message' => 'Ação não reconhecida'];

try {
    switch ($action) {
        case 'sync_funnel':
            // 1. Reordenar mensagens (1, 2, 3...)
            $msgs = fetchData($pdo, "SELECT id FROM marketing_mensagens ORDER BY ordem ASC, id ASC");
            $i = 1;
            foreach ($msgs as $msg) {
                executeQuery($pdo, "UPDATE marketing_mensagens SET ordem = ? WHERE id = ?", [$i, $msg['id']]);
                $i++;
            }
            
            // 2. Destravar membros presos em passos inexistentes
            $maxStep = $i - 1;
            if ($maxStep > 0) {
                executeQuery($pdo, "UPDATE marketing_membros SET ultimo_passo_id = ? WHERE ultimo_passo_id > ?", [$maxStep, $maxStep]);
            }
            
            $response = ['success' => true, 'message' => 'Funil reordenado e sincronizado!'];
            break;

        case 'get_marketing_stats':
            // Estatísticas Gerais
            $total = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros")['c'] ?? 0;
            $novos = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE status = 'novo'")['c'] ?? 0;
            
            // --- ESTATÍSTICAS DO DIA (VIPs) ---
            $hojeTotal = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE DATE(data_entrada_fluxo) = CURDATE()")['c'] ?? 0;
            $hojeConcluidos = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE DATE(data_entrada_fluxo) = CURDATE() AND status = 'concluido'")['c'] ?? 0;
            $hojeAndamento = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE DATE(data_entrada_fluxo) = CURDATE() AND status = 'em_progresso'")['c'] ?? 0;
            
            // Progresso global do funil
            $totalFunilMsgs = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_mensagens")['c'] ?? 0;
            
            // Média de passo dos VIPs ativos
            $passoMedio = 0;
            if ($hojeAndamento > 0) {
                $somaPassos = fetchOne($pdo, "SELECT SUM(ultimo_passo_id) as s FROM marketing_membros WHERE DATE(data_entrada_fluxo) = CURDATE() AND status = 'em_progresso'")['s'] ?? 0;
                $passoMedio = round($somaPassos / $hojeAndamento, 1);
            }

            // Disparos Reais Hoje
            $disparosHoje = fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_automation_logs WHERE tipo_automacao = 'marketing' AND DATE(data_envio) = CURDATE()")['c'] ?? 0;
            
            // Próximo envio agendado
            $proxEnvio = fetchOne($pdo, "SELECT data_proximo_envio FROM marketing_membros WHERE status = 'em_progresso' AND data_proximo_envio IS NOT NULL ORDER BY data_proximo_envio ASC LIMIT 1");

            // Get campaign settings
            $campanha = fetchOne($pdo, "SELECT membros_por_dia_grupo FROM marketing_campanhas WHERE id = 1");

            $response = [
                'success' => true, 
                'stats' => [
                    'total_leads' => $total,
                    'fila_espera' => $novos,
                    'hoje_iniciados' => $hojeTotal,
                    'hoje_concluidos' => $hojeConcluidos,
                    'hoje_andamento' => $hojeAndamento,
                    'hoje_disparos' => $disparosHoje,
                    'passo_medio' => $passoMedio,
                    'total_msgs_funil' => $totalFunilMsgs,
                    'meta_diaria' => $campanha['membros_por_dia_grupo'] ?? 10,
                    'proximo_envio' => $proxEnvio ? $proxEnvio['data_proximo_envio'] : null
                ]
            ];
            break;

        case 'reset_daily_limit':
            // Resetar contador diário
            executeQuery($pdo, "UPDATE marketing_membros SET data_entrada_fluxo = NULL WHERE DATE(data_entrada_fluxo) = CURDATE()");
            $response = ['success' => true, 'message' => 'Limite diário resetado!'];
            break;

        default:
            $response = ['success' => false, 'message' => 'Ação não reconhecida: ' . $action];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
}

// Clean buffer one more time before output
if (ob_get_length()) ob_clean();

echo json_encode($response);
exit;
