<?php
/**
 * API Marketing - Backend Brain
 * Handles member synchronization, daily selection, and message dispatching.
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Security check (Basic)
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$token = $_SERVER['HTTP_X_API_TOKEN'] ?? $_GET['token'] ?? '';
// Validate token if needed, usually matches config
// $expectedToken = ...; 

if ($action === 'save_members') {
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

} elseif ($action === 'cron_process') {
    // 1. Load Campaign Settings
    $campanha = fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id = 1 AND ativo = 1");
    if (!$campanha) {
        echo json_encode(['success' => true, 'tasks' => [], 'message' => 'Campanha inativa']);
        exit;
    }

    $tasks = [];
    $now = date('Y-m-d H:i:s');
    $membrosPorDia = $campanha['membros_por_dia_grupo'];
    
    // 2. DAILY SELECTION (Select new members for today)
    // Check if we need to select for this group-day
    // Strategy: We can't easily check "did we select for Group X today?" without a log table.
    // Alternative: Check members in 'em_progresso' created/updated recently?
    // Robust approach: A standard "last_run" log or just checking counts.
    
    // Simplification: Run selection every time, but LIMIT 5 WHERE date(data_proximo_envio) != today?
    // No, 'novo' members have NULL data_proximo_envio.
    
    // Let's iterate all distinct groups in marketing_membros
    $groups = fetchData($pdo, "SELECT DISTINCT grupo_origem_jid FROM marketing_membros");
    
    foreach ($groups as $g) {
        $gj = $g['grupo_origem_jid'];
        
        // Count how many are already 'em_progresso' or 'concluido' (or converted today) for this group
        // This logic is tricky without a separate 'campaign_runs' table.
        // Let's assume 'em_progresso' means currently active.
        
        // Count active for today:
        $activeCount = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE grupo_origem_jid = ? AND status = 'em_progresso' AND DATE(data_proximo_envio) = CURDATE()", [$gj]);
        $count = $activeCount['c'];

        if ($count < $membrosPorDia) {
            $needed = $membrosPorDia - $count;
            // Select random 'novo'
            $candidates = fetchData($pdo, "SELECT id FROM marketing_membros WHERE grupo_origem_jid = ? AND status = 'novo' ORDER BY RAND() LIMIT $needed", [$gj]);
            
            foreach ($candidates as $c) {
                // Activate them
                // Set first send time: NOW + random delay (minutes)
                // Random start time between now and +interval_max
                $delayMinutes = rand($campanha['intervalo_min_minutos'], $campanha['intervalo_max_minutos']);
                $sendTime = date('Y-m-d H:i:s', strtotime("+$delayMinutes minutes"));
                
                executeQuery($pdo, "UPDATE marketing_membros SET status = 'em_progresso', data_proximo_envio = ?, ultimo_passo_id = 0 WHERE id = ?", [$sendTime, $c['id']]);
            }
        }
    }

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
        // Failed? Retry later or block?
        // For now, retry in 1 hour
        $retryTime = date('Y-m-d H:i:s', strtotime("+60 minutes"));
        executeQuery($pdo, "UPDATE marketing_membros SET data_proximo_envio = ? WHERE id = ?", [$retryTime, $memberId]);
    }
    
    echo json_encode(['success' => true]);
}
?>
