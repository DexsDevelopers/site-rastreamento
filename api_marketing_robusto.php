<?php
/**
 * API Marketing - Backend Brain (Robust Version)
 * Reconstruído para evitar Erro 500 em produção (Hostinger)
 */

// 1. Configurações Iniciais e Tratamento de Erro
ini_set('display_errors', 0); // Em produção, não mostrar erros no output (quebra JSON)
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    // 2. Carregar Dependências com Verificação
    if (!file_exists('includes/config.php'))
        throw new Exception("Arquivo includes/config.php não encontrado");
    require_once 'includes/config.php';

    if (!file_exists('includes/db_connect.php'))
        throw new Exception("Arquivo includes/db_connect.php não encontrado");
    require_once 'includes/db_connect.php';

    // 3. Configurar Timezone
    date_default_timezone_set('America/Sao_Paulo');
    try {
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    catch (Exception $e) {
        error_log("Aviso: Falha ao definir timezone MySQL: " . $e->getMessage());
    }

    // 4. Capturar Input
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Ler corpo da requisição JSON (se houver)
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true) ?? [];

    // --- ROTEAMENTO DE AÇÕES ---

    // AÇÃO 1: SALVAR MEMBROS (Prioridade Máxima - Deve funcionar sem falhas)
    if ($action === 'save_members') {
        if ($method !== 'POST')
            throw new Exception("Método inválido para save_members (Use POST)");

        $groupJid = $input['group_jid'] ?? '';
        $members = $input['members'] ?? [];

        if (empty($groupJid) || empty($members)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos (group_jid ou members)']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO marketing_membros (telefone, grupo_origem_jid, status) VALUES (?, ?, 'novo')");
        $added = 0;

        foreach ($members as $phone) {
            // Se não for JID (contém @), limpar caracteres não numéricos
            if (strpos($phone, '@') === false) {
                $phone = preg_replace('/\D/', '', $phone);
                if (strlen($phone) < 10)
                    continue; // Ignorar números inválidos
            }

            try {
                $stmt->execute([$phone, $groupJid]);
                if ($stmt->rowCount() > 0)
                    $added++;
            }
            catch (Exception $e) {
            // Silenciar erro de duplicidade ou logar
            // error_log("Erro ao inserir membro $phone: " . $e->getMessage());
            }
        }

        echo json_encode(['success' => true, 'added' => $added, 'message' => "Processado com sucesso. $added novos membros."]);
        exit;
    }

    // AÇÃO 2: PROCESSAR TAREFAS (CRON)
    elseif ($action === 'cron_process') {
        // Versão Simplificada e Segura do CRON

        // Verificar campanha ativa
        $campanha = fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id = 1 AND ativo = 1");
        if (!$campanha) {
            echo json_encode(['success' => true, 'tasks' => [], 'message' => 'Campanha inativa']);
            exit;
        }

        $tasks = [];
        $limiteDiario = intval($campanha['membros_por_dia_grupo'] ?? 100);

        // A. Seleção Diária (Novos -> Em Progresso)
        // Verificar quantos já estão em progresso hoje
        $hojeStats = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE (status = 'em_progresso' OR status = 'concluido') AND DATE(data_proximo_envio) = CURDATE()");
        $hojeCount = intval($hojeStats['c']);

        if ($hojeCount < $limiteDiario) {
            $vagas = $limiteDiario - $hojeCount;
            // Buscar novos membros de grupos distintos
            $novosGrupos = fetchData($pdo, "SELECT DISTINCT grupo_origem_jid FROM marketing_membros WHERE status = 'novo' LIMIT 5");

            foreach ($novosGrupos as $g) {
                if ($vagas <= 0)
                    break;

                $gj = $g['grupo_origem_jid'];
                $candidatos = fetchData($pdo, "SELECT id FROM marketing_membros WHERE grupo_origem_jid = ? AND status = 'novo' LIMIT $vagas", [$gj]);

                foreach ($candidatos as $c) {
                    $delay = rand(1, 5); // Delay pequeno inicial
                    $sendTime = date('Y-m-d H:i:s', strtotime("+$delay minutes"));
                    executeQuery($pdo, "UPDATE marketing_membros SET status = 'em_progresso', data_proximo_envio = ?, ultimo_passo_id = 0, data_entrada_fluxo = CURDATE() WHERE id = ?", [$sendTime, $c['id']]);
                    $vagas--;
                }
            }
        }

        // B. Buscar Tarefas Pendentes
        // Query otimizada para evitar erro de sintaxe
        $sqlTasks = "
            SELECT m.id, m.telefone, m.ultimo_passo_id, msg.conteudo, msg.tipo, msg.ordem
            FROM marketing_membros m
            JOIN marketing_mensagens msg ON (m.ultimo_passo_id + 1) = msg.ordem
            WHERE m.status = 'em_progresso' 
            AND m.data_proximo_envio <= NOW()
            ORDER BY m.data_proximo_envio ASC
            LIMIT 5
        ";

        $pendingTasks = fetchData($pdo, $sqlTasks);

        foreach ($pendingTasks as $task) {
            // Travar tarefa (jogar para futuro pra não repetir)
            executeQuery($pdo, "UPDATE marketing_membros SET data_proximo_envio = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?", [$task['id']]);

            // Gerar ID anti-ban simples
            $randomId = substr(md5(uniqid()), 0, 6);
            $msgContent = $task['conteudo'] . "\n\n_" . $randomId . "_";

            $tasks[] = [
                'type' => 'send_message',
                'phone' => $task['telefone'],
                'message' => $msgContent,
                'message_type' => $task['tipo'] ?? 'texto',
                'member_id' => $task['id'],
                'step_order' => $task['ordem']
            ];
        }

        echo json_encode(['success' => true, 'tasks' => $tasks]);
        exit;
    }

    // AÇÃO 3: ATUALIZAR TAREFA
    elseif ($action === 'update_task') {
        $memberId = $input['member_id'];
        $stepOrder = $input['step_order'];
        $success = $input['success'];
        $reason = $input['reason'] ?? '';

        if ($success) {
            // Verificar se tem próximo passo
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
            // Falhou
            if ($reason === 'invalid_number') {
                executeQuery($pdo, "UPDATE marketing_membros SET status = 'bloqueado' WHERE id = ?", [$memberId]);
            }
            else {
                // Tentar de novo em 1 hora
                $retryTime = date('Y-m-d H:i:s', strtotime("+1 hour"));
                executeQuery($pdo, "UPDATE marketing_membros SET data_proximo_envio = ?, status = 'em_progresso' WHERE id = ?", [$retryTime, $memberId]);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // AÇÃO 4: RESETAR LIMITE (Manual)
    elseif ($action === 'reset_daily_limit') {
        $ontem = date('Y-m-d H:i:s', strtotime('-1 day'));
        executeQuery($pdo, "UPDATE marketing_membros SET data_proximo_envio = ? WHERE (status = 'em_progresso' OR status = 'concluido') AND DATE(data_proximo_envio) = CURDATE()", [$ontem]);
        echo json_encode(['success' => true, 'message' => 'Limite diário resetado.']);
        exit;
    }

    // AÇÃO 5: LIMPAR TUDO
    elseif ($action === 'clear_all_members') {
        executeQuery($pdo, "TRUNCATE TABLE marketing_membros");
        echo json_encode(['success' => true, 'message' => 'Todos os contatos foram removidos.']);
        exit;
    }

    // Nenhuma ação reconhecida
    else {
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida: ' . htmlspecialchars($action)]);
    }

}
catch (Exception $e) {
    // Capturar erro fatal e retornar JSON limpo
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro Interno: ' . $e->getMessage()]);
}
?>