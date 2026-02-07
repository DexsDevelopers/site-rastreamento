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
if (ob_get_length())
    ob_clean();

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

        case 'clear_all_members':
            // Limpar todos os contatos
            executeQuery($pdo, "DELETE FROM marketing_membros");
            $response = ['success' => true, 'message' => 'Todos os contatos foram removidos com sucesso!'];
            break;

        case 'migrate_db':
            // Migração de banco de dados para suportar JIDs
            try {
                $pdo->exec("ALTER TABLE marketing_membros MODIFY COLUMN telefone VARCHAR(100) NOT NULL");
                $response = ['success' => true, 'message' => 'Banco de dados atualizado para suportar JIDs completos!'];
            }
            catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Erro na migração: ' . $e->getMessage()];
            }
            break;

        case 'get_disparos_tasks':
            // 1. Carregar Campanha e Limites
            $campanha = fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id = 1 AND ativo = 1");
            if (!$campanha) {
                $response = ['success' => false, 'message' => 'Campanha inativa ou não encontrada'];
                break;
            }

            $membrosPorDiaLimit = intval($campanha['membros_por_dia_grupo']);

            // 2. Verificar Cotas de Hoje (Quantos JÁ foram processados)
            $hojeCount = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE (status = 'em_progresso' OR status = 'concluido') AND DATE(data_entrada_fluxo) = CURDATE()")['c'] ?? 0;

            $remainingSlots = $membrosPorDiaLimit - $hojeCount;

            // Se já estourou o limite, não adiciona NINGUÉM novo, mas pode retornar tarefas de quem já está no fluxo de hoje.
            // O usuário pediu "pare ao atingir 10", então se remaining <= 0, não devemos puxar novos.

            // 3. Selecionar NOVOS apenas se houver espaço
            if ($remainingSlots > 0) {
                $novosCount = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE status = 'novo'")['c'];

                if ($novosCount > 0) {
                    $groups = fetchData($pdo, "SELECT DISTINCT grupo_origem_jid FROM marketing_membros WHERE status = 'novo'");
                    foreach ($groups as $g) {
                        if ($remainingSlots <= 0)
                            break;

                        $candidates = fetchData($pdo, "SELECT id FROM marketing_membros WHERE grupo_origem_jid = ? AND status = 'novo' ORDER BY id ASC LIMIT ?", [$g['grupo_origem_jid'], $remainingSlots]);

                        foreach ($candidates as $c) {
                            $sendTime = date('Y-m-d H:i:s'); // Imediato
                            executeQuery($pdo, "UPDATE marketing_membros SET status = 'em_progresso', data_proximo_envio = ?, ultimo_passo_id = 0, data_entrada_fluxo = CURDATE() WHERE id = ?", [$sendTime, $c['id']]);
                            $remainingSlots--;
                        }
                    }
                }
            }
            else {
            // Opcional: Avisar no log que limite foi atingido, mas deixar retornar tarefas pendentes de quem JÁ está no fluxo hoje
            }

            // 4. Buscar FILA de tarefas prontas
            // Pegamos todos que estão 'em_progresso' e com data <= NOW
            // Prioridade para os de HOJE ou os que o cron travou
            $tasksSql = "
                SELECT m.id, m.telefone, m.ultimo_passo_id, msg.conteudo, msg.tipo, msg.ordem
                FROM marketing_membros m
                JOIN marketing_mensagens msg ON (m.ultimo_passo_id + 1) = msg.ordem
                WHERE m.status = 'em_progresso' 
                AND m.data_proximo_envio <= NOW()
                ORDER BY msg.ordem ASC, m.data_proximo_envio ASC
                LIMIT 50
            ";
            $pendingTasks = fetchData($pdo, $tasksSql);

            $tasks = [];
            foreach ($pendingTasks as $t) {
                // Anti-ban id
                $chars = '0123456789abcdef';
                $randomId = substr(str_shuffle($chars), 0, 5);

                // Normalizar apenas se NÃO for um JID/LID/Grupo
                $phone = $t['telefone'];
                if (strpos($phone, '@') === false) {
                    $phone = preg_replace('/\D/', '', $phone);
                }

                $tasks[] = [
                    'member_id' => $t['id'],
                    'phone' => $phone,
                    'message' => $t['conteudo'] . "\n\n_" . $randomId . "_",
                    'step_order' => $t['ordem'],
                    'type' => $t['tipo']
                ];
            }

            $response = [
                'success' => true,
                'tasks' => $tasks,
                'message' => count($tasks) . ' tarefas encontradas.'
            ];
            break;

        case 'execute_disparo_task':
            require_once 'includes/whatsapp_helper.php';

            $memberId = (int)$_POST['member_id'];
            $stepOrder = (int)$_POST['step_order'];
            $phone = $_POST['phone'];
            $message = $_POST['message'];

            if (!$memberId || !$phone) {
                $response = ['success' => false, 'message' => 'Dados incompletos'];
                break;
            }

            // 1. Marcar como "Enviando" (Trava)
            executeQuery($pdo, "UPDATE marketing_membros SET data_proximo_envio = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id = ?", [$memberId]);

            // 2. Enviar via Helper
            $result = sendWhatsappMessage($phone, $message);

            // 3. Processar Resultado (Mesma lógica do update_task da api_marketing.php)
            if ($result['success']) {
                $nextMsg = fetchOne($pdo, "SELECT delay_apos_anterior_minutos FROM marketing_mensagens WHERE campanha_id = 1 AND ordem > ? ORDER BY ordem ASC LIMIT 1", [$stepOrder]);
                if ($nextMsg) {
                    $delay = $nextMsg['delay_apos_anterior_minutos'];
                    $nextTime = date('Y-m-d H:i:s', strtotime("+$delay minutes"));
                    executeQuery($pdo, "UPDATE marketing_membros SET ultimo_passo_id = ?, data_proximo_envio = ?, status = 'em_progresso' WHERE id = ?", [$stepOrder, $nextTime, $memberId]);
                }
                else {
                    executeQuery($pdo, "UPDATE marketing_membros SET ultimo_passo_id = ?, status = 'concluido' WHERE id = ?", [$stepOrder, $memberId]);
                }
            }
            else {
                // Falha
                $error = $result['error'] ?? 'Erro desconhecido';
                if ($error === 'invalid_number') {
                    executeQuery($pdo, "UPDATE marketing_membros SET status = 'bloqueado' WHERE id = ?", [$memberId]);
                }
                else {
                    $retryTime = date('Y-m-d H:i:s', strtotime("+10 minutes"));
                    executeQuery($pdo, "UPDATE marketing_membros SET data_proximo_envio = ?, status = 'em_progresso' WHERE id = ?", [$retryTime, $memberId]);
                }
            }

            // 4. Log Automático
            try {
                $auto = fetchOne($pdo, "SELECT id FROM bot_automations WHERE nome = 'Campanha Marketing' LIMIT 1");
                $autoId = $auto ? $auto['id'] : 0;
                $statusMsg = $result['success'] ? 'SUCESSO_ENVIO (Manual)' : 'FALHA_ENVIO (Manual): ' . ($result['error'] ?? '');

                // Log Automático - Garantir que o JID está correto
                $logJid = strpos($phone, '@') === false ? $phone . '@s.whatsapp.net' : $phone;

                executeQuery($pdo, "INSERT INTO bot_automation_logs 
                    (automation_id, jid_origem, numero_origem, mensagem_recebida, resposta_enviada, grupo_id, grupo_nome) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$autoId, $logJid, $phone, $statusMsg, $message, 'manual', 'Marketing Campaign']
                );
            }
            catch (Exception $e) {
            }

            $response = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Mensagem enviada!' : 'Erro: ' . ($result['error'] ?? 'cURL fail'),
                'result' => $result
            ];
            break;

        default:
            $response = ['success' => false, 'message' => 'Ação não reconhecida: ' . $action];
    }
}
catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
}

// Clean buffer one more time before output
if (ob_get_length())
    ob_clean();

echo json_encode($response);
exit;