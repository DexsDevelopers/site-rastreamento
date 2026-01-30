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

// Security check (Basic)
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$token = $_SERVER['HTTP_X_API_TOKEN'] ?? $_GET['token'] ?? '';

// ... (existing token validation)

if ($action === 'cron_process') {
    // 1. Load Campaign Settings
    $campanha = fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id = 1 AND ativo = 1");
    if (!$campanha) {
        echo json_encode(['success' => true, 'tasks' => [], 'message' => 'Campanha inativa']);
        exit;
    }

    $tasks = [];
    $membrosPorDiaLimit = intval($campanha['membros_por_dia_grupo']); // Treating as GLOBAL limit for safety
    
    // SAFETY CHECK: Verify Global Daily Usage across ALL groups
    // Counts everyone processed today (Active or Concluded)
    $globalStats = fetchOne($pdo, "
        SELECT COUNT(*) as c 
        FROM marketing_membros 
        WHERE (status = 'em_progresso' OR status = 'concluido')
        AND DATE(data_proximo_envio) = CURDATE()
    ");
    
    $globalTotalToday = intval($globalStats['c']);
    
    // If we already hit the global limit, do NOT select new members anywhere
    if ($globalTotalToday >= $membrosPorDiaLimit) {
        // Just process existing active tasks, but don't add new ones
        error_log("Limite diário global atingido: $globalTotalToday / $membrosPorDiaLimit");
        // We set a flag to prevent adding NEW members inside the loop
        $canAddNew = false;
    } else {
        $canAddNew = true;
    }

    // 2. DAILY SELECTION (Select new members for today)
    if ($canAddNew) {
        $groups = fetchData($pdo, "SELECT DISTINCT grupo_origem_jid FROM marketing_membros WHERE status = 'novo'");
        
        foreach ($groups as $g) {
            // Re-check global limit inside loop to preventing over-filling
            $currentGlobal = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE (status = 'em_progresso' OR status = 'concluido') AND DATE(data_proximo_envio) = CURDATE()");
            if ($currentGlobal['c'] >= $membrosPorDiaLimit) break;
            
            $gj = $g['grupo_origem_jid'];
            
            // For now, we simply fill the GLOBAL quota from available groups
            // If you want per-group distribution, we'd need more complex logic.
            // This FIFO approach is safer to respect the absolute limit.
            
            $slotsAvailable = $membrosPorDiaLimit - $currentGlobal['c'];
            if ($slotsAvailable <= 0) break;
            
            // Select random 'novo' from this group up to the global slots available
            $candidates = fetchData($pdo, "SELECT id FROM marketing_membros WHERE grupo_origem_jid = ? AND status = 'novo' ORDER BY RAND() LIMIT $slotsAvailable", [$gj]);
            
            foreach ($candidates as $c) {
                // Activate them
                $delayMinutes = rand($campanha['intervalo_min_minutos'], $campanha['intervalo_max_minutos']);
                $sendTime = date('Y-m-d H:i:s', strtotime("+$delayMinutes minutes"));
                
                executeQuery($pdo, "UPDATE marketing_membros SET status = 'em_progresso', data_proximo_envio = ?, ultimo_passo_id = 0 WHERE id = ?", [$sendTime, $c['id']]);
                
                // Decrement slots locally to avoid re-querying every widely
                $slotsAvailable--;
            }
        }
    }
    // Input: JSON { "group_jid": "...", "members": ["5511999...", "5511888..."] }
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
        // Sanitize phone
        $phone = preg_replace('/\D/', '', $phone);
        // Skip invalid length or admin numbers if needed
        if (strlen($phone) < 10) continue;

        try {
            $stmt->execute([$phone, $groupJid]);
            $added++;
        } catch (Exception $e) {
            // Ignore duplicates or specific insert errors
        }
    }

    echo json_encode(['success' => true, 'added' => $added]);



    // 3. GET PENDING TASKS
    // Select members ready to receive
    $readyMembers = fetchData($pdo, "SELECT m.*, c.nome as camp_nome 
        FROM marketing_membros m 
        JOIN marketing_campanhas c ON c.id = 1 
        WHERE m.status = 'em_progresso' 
        AND m.data_proximo_envio <= NOW() 
        LIMIT 20"); // Batch limit

    foreach ($readyMembers as $member) {
        // Determine next message
        $nextStep = $member['ultimo_passo_id'] + 1;
        
        // Get message content
        $msg = fetchOne($pdo, "SELECT * FROM marketing_mensagens WHERE campanha_id = 1 AND ordem = ?", [$nextStep]);
        
        if ($msg) {
            // Task found
            $tasks[] = [
                'type' => 'send_message',
                'phone' => $member['telefone'],
                'message' => $msg['conteudo'],
                'message_type' => $msg['tipo'],
                'member_id' => $member['id'],
                'step_order' => $nextStep,
                'next_delay' => $msg['delay_apos_anterior_minutos'] // To calculate next schedule
            ];
        } else {
            // No more messages -> Mark as Concluded
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
        // Find current message delay settings to plan next one
        // Note: The task contained 'next_delay'. But we need the delay of the *next* message?
        // Actually, the DB schema says `delay_apos_anterior_minutos` on the *message*.
        // So message 2 has delay X (wait X mins after msg 1).
        
        // Get the NEXT message to see its delay requirement
        $nextMsg = fetchOne($pdo, "SELECT delay_apos_anterior_minutos FROM marketing_mensagens WHERE campanha_id = 1 AND ordem = ?", [$stepOrder + 1]);
        
        if ($nextMsg) {
            $delay = $nextMsg['delay_apos_anterior_minutos'];
            $nextTime = date('Y-m-d H:i:s', strtotime("+$delay minutes"));
            
            executeQuery($pdo, "UPDATE marketing_membros SET ultimo_passo_id = ?, data_proximo_envio = ? WHERE id = ?", [$stepOrder, $nextTime, $memberId]);
        } else {
            // No next message, finish
            executeQuery($pdo, "UPDATE marketing_membros SET ultimo_passo_id = ?, status = 'concluido' WHERE id = ?", [$stepOrder, $memberId]);
        }
    } else {
        // Failed
        $reason = $input['reason'] ?? '';

        if ($reason === 'invalid_number') {
            // Block invalid number to prevent loops
            executeQuery($pdo, "UPDATE marketing_membros SET status = 'bloqueado' WHERE id = ?", [$memberId]);
        } else {
            // Other failures: Retry later
            // For now, retry in 1 hour
            $retryTime = date('Y-m-d H:i:s', strtotime("+60 minutes"));
            executeQuery($pdo, "UPDATE marketing_membros SET data_proximo_envio = ? WHERE id = ?", [$retryTime, $memberId]);
        }
    }
    
    echo json_encode(['success' => true]);
}
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
