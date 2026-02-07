<?php
// Versão MINIMALISTA para DEBUG de API Marketing
// Objetivo: Testar apenas a inserção de membros, sem CRON ou outras lógicas complexas.

// Habilitar erros para debug (será capturado como texto antes do JSON se houver erro)
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // 1. Configs
    if (!file_exists('includes/config.php'))
        throw new Exception("Config missing");
    require_once 'includes/config.php';

    if (!file_exists('includes/db_connect.php'))
        throw new Exception("DB Connect missing");
    require_once 'includes/db_connect.php';

    // 2. Verificar Ação
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'save_members') {

        // 3. Receber Dados
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        if (!$input) {
            echo json_encode(['success' => false, 'message' => 'No input data']);
            exit;
        }

        $groupJid = $input['group_jid'] ?? '';
        $members = $input['members'] ?? [];

        if (empty($groupJid) || empty($members)) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
            exit;
        }

        // 4. Inserir no Banco
        $stmt = $pdo->prepare("INSERT IGNORE INTO marketing_membros (telefone, grupo_origem_jid, status) VALUES (?, ?, 'novo')");
        $added = 0;

        foreach ($members as $phone) {
            // Normalizar básico
            if (strpos($phone, '@') === false) {
                $phone = preg_replace('/\D/', '', $phone);
                if (strlen($phone) < 10)
                    continue;
            }

            try {
                $stmt->execute([$phone, $groupJid]);
                $added++;
            }
            catch (Exception $e) {
            // Ignore duplicate errors
            }
        }

        echo json_encode(['success' => true, 'added' => $added]);
        exit;

    }
    else {
        echo json_encode(['success' => false, 'message' => 'Action not supported in debug mode']);
    }

}
catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $e->getMessage()]);
}
?>