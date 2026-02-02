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
                executeQuery($pdo, "UPDATE marketing_membros SET status = 'em_progresso', data_proximo_envio = ?, ultimo_passo_id = 0 WHERE id = ?", [$sendTime, $c['id']]);
                $slotsAvailable--;
            }
        }
    }

    // 3. GET PENDING TASKS
    $readyMembers = fetchData($pdo, "SELECT m.*, c.nome as camp_nome 
        FROM marketing_membros m 
        JOIN marketing_campanhas c ON c.id = 1 
        WHERE m.status = 'em_progresso' 
        AND m.data_proximo_envio <= NOW() 
        LIMIT 20"); 

    if (!empty($readyMembers)) {
        error_log("[Marketing Cron] Found " . count($readyMembers) . " ready members.");
    } 

    foreach ($readyMembers as $member) {
        // Find next message strictly after current step (handles gaps)
        $msg = fetchOne($pdo, "SELECT * FROM marketing_mensagens WHERE campanha_id = 1 AND ordem > ? ORDER BY ordem ASC LIMIT 1", [$member['ultimo_passo_id']]);
        
        if ($msg) {
            // Task found
            $tasks[] = [
                'type' => 'send_message',
                'phone' => $member['telefone'],
                'message' => $msg['conteudo'],
                'message_type' => $msg['tipo'],
                'member_id' => $member['id'],
                'step_order' => $msg['ordem'], // Use actual message order
                'next_delay' => $msg['delay_apos_anterior_minutos'] 
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
        // Get the NEXT message to see its delay requirement
        $nextMsg = fetchOne($pdo, "SELECT delay_apos_anterior_minutos FROM marketing_mensagens WHERE campanha_id = 1 AND ordem > ? ORDER BY ordem ASC LIMIT 1", [$stepOrder]);
        
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
