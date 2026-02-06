<?php
/**
 * API Marketing - Backend Brain
 * Handles member synchronization, daily selection, and message dispatching.
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Set Timezone to Brazil/Sao Paulo to ensure daily limits work correctly
date_default_timezone_set('America/Sao_Paulo');

// Sync MySQL Timezone with PHP
try {
    $offset = date('P');
    $pdo->exec("SET time_zone = '$offset'");
} catch (Exception $e) {
    // Fallback or ignore if permission denied, but log it
    error_log("Failed to set MySQL timezone: " . $e->getMessage());
}

// Security check (Basic)
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$token = $_SERVER['HTTP_X_API_TOKEN'] ?? $_GET['token'] ?? '';

// ... (existing token validation)

if ($action === 'save_members') {
    // Receive contacts from bot-extension or import
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['group_jid']) || empty($input['members'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    $groupJid = $input['group_jid'];
    $members = $input['members'];
    $added = 0;

    $stmt = $pdo->prepare("INSERT IGNORE INTO marketing_membros (telefone, grupo_origem_jid, status) VALUES (?, ?, 'novo')");

    foreach ($members as $phone) {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) < 10) continue;

        try {
            $stmt->execute([$phone, $groupJid]);
            $added++;
        } catch (Exception $e) {}
    }

    echo json_encode(['success' => true, 'added' => $added]);

} elseif ($action === 'cron_process') {
    // 1. Load Campaign Settings
    $campanha = fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id = 1 AND ativo = 1");
    if (!$campanha) {
        echo json_encode(['success' => true, 'tasks' => [], 'message' => 'Campanha inativa']);
        exit;
    }

    $tasks = [];
    $membrosPorDiaLimit = intval($campanha['membros_por_dia_grupo']); // Treating as GLOBAL limit
    
    // SAFETY CHECK: Verify Global Daily Usage
    $globalStats = fetchOne($pdo, "
        SELECT COUNT(*) as c 
        FROM marketing_membros 
        WHERE (status = 'em_progresso' OR status = 'concluido')
        AND DATE(data_proximo_envio) = CURDATE()
    ");
    
    $globalTotalToday = intval($globalStats['c']);
    
    $canAddNew = ($globalTotalToday < $membrosPorDiaLimit);

    if (!$canAddNew) {
        error_log("Limite diário global atingido: $globalTotalToday / $membrosPorDiaLimit");
    }

    // 2. DAILY SELECTION
    if ($canAddNew) {
        $groups = fetchData($pdo, "SELECT DISTINCT grupo_origem_jid FROM marketing_membros WHERE status = 'novo'");
        
        foreach ($groups as $g) {
            $currentGlobal = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE (status = 'em_progresso' OR status = 'concluido') AND DATE(data_proximo_envio) = CURDATE()");
            if ($currentGlobal['c'] >= $membrosPorDiaLimit) break;
            
            $gj = $g['grupo_origem_jid'];
            $slotsAvailable = $membrosPorDiaLimit - $currentGlobal['c'];
            if ($slotsAvailable <= 0) break;
            
            $candidates = fetchData($pdo, "SELECT id FROM marketing_membros WHERE grupo_origem_jid = ? AND status = 'novo' ORDER BY RAND() LIMIT $slotsAvailable", [$gj]);
            
            foreach ($candidates as $c) {
                $delayMinutes = rand($campanha['intervalo_min_minutos'], $campanha['intervalo_max_minutos']);
                $sendTime = date('Y-m-d H:i:s', strtotime("+$delayMinutes minutes"));
                executeQuery($pdo, "UPDATE marketing_membros SET status = 'em_progresso', data_proximo_envio = ?, ultimo_passo_id = 0, data_entrada_fluxo = CURDATE() WHERE id = ?", [$sendTime, $c['id']]);
                $slotsAvailable--;
            }
        }
    }

    // 2. BUSCAR TAREFAS PENDENTES (Mensagens agendadas)
    // NOVA LÓGICA VIP: Prioridade TOTAL para quem começou HOJE.
    // Se atingiu o limite diário de novos, NÃO processa antigos. Apenas os de hoje continuam.
    
    // Contar quantos leads começaram hoje
    $hojeCount = fetchOne($pdo, "SELECT COUNT(*) as total FROM marketing_membros WHERE DATE(data_entrada_fluxo) = CURDATE()")['total'];
    $limiteDiario = $campanha['membros_por_dia_grupo'];
    
    // Definir quem pode receber mensagem agora
    // Prioridade 1: Pessoas que já estão no fluxo HOJE (msg 2, 3...)
    // Prioridade 2: Novas pessoas (msg 1), SE couber no limite.
    // Prioridade 3: Pessoas antigas... APENAS SE o usuário permitir (Neste caso, o usuário pediu BLOQUEIO)
    
    // Lógica RIGOROSA: 
    // - Se é tarefa de Msg 1: Só libera se $hojeCount < $limiteDiario
    // - Se é tarefa de Msg > 1:
    //      - Se o lead começou HOJE: Libera SEMPRE (para terminar o funil dele)
    //      - Se o lead é ANTIGO: BLOQUEIA se a regra for estrita "Focar no dia".
    
    // Vamos filtrar na query.
    // Buscamos tarefas agendadas para AGORA ou ANTES
    $tasksSql = "
        SELECT m.*, msg.conteudo, msg.tipo, msg.ordem, msg.delay_apos_anterior_minutos
        FROM marketing_membros m
        JOIN marketing_mensagens msg ON 
            (m.ultimo_passo_id + 1) = msg.ordem
        WHERE 
            m.status = 'em_progresso' 
            AND m.data_proximo_envio <= NOW()
            AND (
                -- CASO 1: É a primeira mensagem (Entrada)
                (msg.ordem = 1 AND (SELECT COUNT(*) FROM marketing_membros WHERE DATE(data_entrada_fluxo) = CURDATE()) < ?)
                
                OR
                
                -- CASO 2: É continuação (Msg 2, 3...) PARA ALGUÉM DE HOJE
                (msg.ordem > 1 AND DATE(m.data_entrada_fluxo) = CURDATE())
                
                -- CASO 3: Antigos? O usuário pediu para focar nos de hoje quando bater o limite.
                -- Então, por enquanto, vamos IGNORAR os antigos se o limite de hoje já estiver cheio.
                -- Na verdade, o pedido foi: "mande mensagem APENAS para a quantidade... não importa se tem antigas"
                -- Então antigos ficam PAUSADOS eternamente até sobrar vaga no dia (o que nunca vai acontecer se todo dia lotar).
                -- Para ser seguro: Só liberamos antigos se HOJE tiver VAZIO (manutenção).
                -- Mas o usuário foi enfático: "Focar em mandar para essas pessoas do dia".
            )
        ORDER BY 
            -- Prioriza terminar os funis de quem já começou HOJE
            (DATE(m.data_entrada_fluxo) = CURDATE()) DESC, 
            msg.ordem DESC,
            m.data_proximo_envio ASC
        LIMIT 5
    ";
    
    $pendingTasks = fetchData($pdo, $tasksSql, [$limiteDiario]);

    if (!empty($pendingTasks)) {
        error_log("[Marketing Cron] Found " . count($pendingTasks) . " ready members.");
    } 

    foreach ($pendingTasks as $member) {
        // The message details are already joined in the query ($member['conteudo'], $member['tipo'], $member['ordem'])
        // So, we don't need to fetch $msg separately.
        
        // Check if message content exists (it should, due to JOIN)
        if ($member['conteudo']) {
            // LOCK TASK IMMEDIATELY (Bump time by 60 min using SQL time to avoid timeout during processing)
            // Using NOW() + INTERVAL ensures we are relative to DB time
            executeQuery($pdo, "UPDATE marketing_membros SET data_proximo_envio = DATE_ADD(NOW(), INTERVAL 60 MINUTE) WHERE id = ?", [$member['id']]);

            // ANTI-BAN: Generate unique ID
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $randomId = substr(str_shuffle($chars), 0, 8);
            $msgContent = $member['conteudo'] . "\n\n_" . $randomId . "_";

            // Task found
            $tasks[] = [
                'type' => 'send_message',
                'phone' => $member['telefone'],
                'message' => $msgContent,
                'message_type' => $member['tipo'] ?? 'texto',
                'member_id' => $member['id'],
                'step_order' => $member['ordem'], // From JOIN
            ];
        } else {
            // No next message? This member is done (or error in logic), mark concluded.
            // Since our query only fetches if JOIN matches, this branch is unlikely unless DB corruption.
            executeQuery($pdo, "UPDATE marketing_membros SET status = 'concluido' WHERE id = ?", [$member['id']]);
        }
    }

    echo json_encode(['success' => true, 'tasks' => $tasks]);

} elseif ($action === 'update_task') {
    // Process success result from bot
    $input = json_decode(file_get_contents('php://input'), true);
    $memberId = $input['member_id'];
    $stepOrder = $input['step_order'];
    $success = $input['success'];
    
    if ($success) {
        // Schedule next
        // Get the NEXT message to see its delay requirement
        $nextMsg = fetchOne($pdo, "SELECT delay_apos_anterior_minutos FROM marketing_mensagens WHERE campanha_id = 1 AND ordem > ? ORDER BY ordem ASC LIMIT 1", [$stepOrder]);
        
        if ($nextMsg) {
            $delay = $nextMsg['delay_apos_anterior_minutos'];
            $nextTime = date('Y-m-d H:i:s', strtotime("+$delay minutes"));
            
            executeQuery($pdo, "UPDATE marketing_membros SET ultimo_passo_id = ?, data_proximo_envio = ?, status = 'em_progresso' WHERE id = ?", [$stepOrder, $nextTime, $memberId]);
        } else {
            // No next message, finish
            executeQuery($pdo, "UPDATE marketing_membros SET ultimo_passo_id = ?, status = 'concluido' WHERE id = ?", [$stepOrder, $memberId]);
        }
    } else {
        // Failed
        $reason = $input['reason'] ?? 'unknown';

        if ($reason === 'invalid_number') {
            // Block invalid number to prevent loops
            executeQuery($pdo, "UPDATE marketing_membros SET status = 'bloqueado' WHERE id = ?", [$memberId]);
        } else {
            // Other failures: Retry later & Reset status to 'em_progresso'
            // For now, retry in 1 hour
            $retryTime = date('Y-m-d H:i:s', strtotime("+60 minutes"));
            executeQuery($pdo, "UPDATE marketing_membros SET data_proximo_envio = ?, status = 'em_progresso' WHERE id = ?", [$retryTime, $memberId]);
        }
    }

    // --- LOGGING FOR ADMIN PANEL (Success & Failure) ---
    try {
        // 1. Get Member details
        $member = fetchOne($pdo, "SELECT telefone, grupo_origem_jid FROM marketing_membros WHERE id = ?", [$memberId]);
        
        // 2. Get Message Content
        $msgContent = fetchOne($pdo, "SELECT conteudo FROM marketing_mensagens WHERE campanha_id = 1 AND ordem = ?", [$stepOrder]);
        $actualContent = $msgContent['conteudo'] ?? 'Mensagem de Marketing';

        // 3. Get Automation ID (Create if not exists)
        $auto = fetchOne($pdo, "SELECT id FROM bot_automations WHERE nome = 'Campanha Marketing' LIMIT 1");
        if (!$auto) {
            executeQuery($pdo, "INSERT INTO bot_automations (nome, descricao, ativo, tipo, gatilho, resposta, prioridade) VALUES ('Campanha Marketing', 'Logs automáticos de marketing', 1, 'mensagem_especifica', 'SYSTEM_MARKETING', 'Dinâmico', -1)");
            $autoId = $pdo->lastInsertId();
        } else {
            $autoId = $auto['id'];
        }

        // 4. Update Usage Counter (only on success)
        if ($success) {
            executeQuery($pdo, "UPDATE bot_automations SET contador_uso = contador_uso + 1, ultimo_uso = NOW() WHERE id = ?", [$autoId]);
        }

        // 5. Insert Log
        $phone = $member['telefone'] ?? 'Desconhecido';
        $jid = $phone . '@s.whatsapp.net';
        $statusMsg = $success ? 'SUCESSO_ENVIO' : 'FALHA_ENVIO: ' . ($input['reason'] ?? 'Erro desconhecido');
        $grupoJid = $member['grupo_origem_jid'] ?? 'N/A';
        
        executeQuery($pdo, "INSERT INTO bot_automation_logs 
            (automation_id, jid_origem, numero_origem, mensagem_recebida, resposta_enviada, grupo_id, grupo_nome) 
            VALUES (?, ?, ?, ?, ?, ?, ?)", 
            [$autoId, $jid, $phone, $statusMsg, $actualContent, $grupoJid, 'Marketing Campaign']
        );

    } catch (Exception $e) {
        error_log("Erro ao salvar log de marketing: " . $e->getMessage());
    }
    // --- END LOGGING ---
    
    echo json_encode(['success' => true]);

} elseif ($action === 'reset_daily_limit') {
    // Ação Manual: Resetar contagem diária
    // Truque: Voltamos a data de "proximo envio" dos que rodaram hoje para "ontem"
    // Assim o contador (WHERE DATE = CURDATE) vai dar zero, liberando o limite.
    
    // Atualiza apenas quem rodou hoje para ontem, liberando a cota
    $ontem = date('Y-m-d H:i:s', strtotime('-1 day'));
    
    // Impacta quem está em progresso ou concluiu hoje
    $sql = "UPDATE marketing_membros 
            SET data_proximo_envio = ? 
            WHERE (status = 'em_progresso' OR status = 'concluido') 
            AND DATE(data_proximo_envio) = CURDATE()";
            
    executeQuery($pdo, $sql, [$ontem]);
    
    echo json_encode(['success' => true, 'message' => 'Limite diário resetado com sucesso! Agora você pode enviar mais.']);
}
?>
