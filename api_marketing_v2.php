<?php
/**
 * API Marketing - Backend Brain (Versão 2 - CRON Isolado)
 * Baseado no api_marketing_minimal.php que funcionou.
 * Adicionando APENAS a estrutura base, sem lógica pesada.
 */

// 1. Configurações Iniciais
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    // 2. Carregar Dependências
    if (!file_exists('includes/config.php'))
        throw new Exception("Config missing");
    require_once 'includes/config.php';

    if (!file_exists('includes/db_connect.php'))
        throw new Exception("DB Connect missing");
    require_once 'includes/db_connect.php';

    // 3. Timezone
    date_default_timezone_set('America/Sao_Paulo');

    // 4. Capturar Input
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Ler corpo da requisição JSON
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true) ?? [];

    // --- ROTEAMENTO ---

    // AÇÃO 1: SALVAR MEMBROS (Cópia exata do minimal que funcionou)
    if ($action === 'save_members') {
        if ($method !== 'POST')
            throw new Exception("Use POST");

        $groupJid = $input['group_jid'] ?? '';
        $members = $input['members'] ?? [];

        if (empty($groupJid) || empty($members)) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO marketing_membros (telefone, grupo_origem_jid, status) VALUES (?, ?, 'novo')");
        $added = 0;

        foreach ($members as $phone) {
            if (strpos($phone, '@') === false) {
                $phone = preg_replace('/\D/', '', $phone);
                if (strlen($phone) < 10)
                    continue;
            }

            try {
                $stmt->execute([$phone, $groupJid]);
                if ($stmt->rowCount() > 0)
                    $added++;
            }
            catch (Exception $e) {
            // Ignore
            }
        }

        echo json_encode(['success' => true, 'added' => $added]);
        exit;
    }

    // AÇÃO 2: CRON PROCESS (Versão Ultra Simplificada para teste)
    elseif ($action === 'cron_process') {
        // Retornar sucesso vazio para testar se o crash é aqui
        echo json_encode(['success' => true, 'tasks' => [], 'message' => 'CRON Disabled for Debug']);
        exit;
    }

    // AÇÃO 3: LIMPAR TUDO
    elseif ($action === 'clear_all_members') {
        executeQuery($pdo, "TRUNCATE TABLE marketing_membros");
        echo json_encode(['success' => true]);
        exit;
    }

    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>