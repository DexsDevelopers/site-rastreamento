<?php
/**
 * Endpoint para solicitar upgrade de entrega expressa (3 dias)
 * Define taxa (valor + PIX) para o código informado e sinaliza pagamento pendente
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

header('Content-Type: application/json; charset=UTF-8');

if (!getConfig('EXPRESS_ENABLED', true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Função indisponível no momento']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$codigo = isset($_POST['codigo']) ? sanitizeInput($_POST['codigo']) : '';
$cidade = isset($_POST['cidade']) ? sanitizeInput($_POST['cidade']) : '';

if ($codigo === '' || $cidade === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Código e cidade são obrigatórios']);
    exit;
}

try {
    // Verificar existência do código e cidade correspondente
    $rows = fetchData($pdo, "SELECT cidade, taxa_valor, taxa_pix FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC", [strtoupper(trim($codigo))]);
    if (empty($rows)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Código não encontrado']);
        exit;
    }

    // Validar cidade pelo primeiro registro válido
    $cidadeRegistro = '';
    foreach ($rows as $r) {
        if (!empty($r['cidade'])) { $cidadeRegistro = $r['cidade']; break; }
    }
    if ($cidadeRegistro === '' || strcasecmp(trim($cidadeRegistro), $cidade) !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cidade não confere com o código informado']);
        exit;
    }

    // Buscar valores dinâmicos de config.json (fallback para constantes)
    $valor = (float) getDynamicConfig('EXPRESS_FEE_VALUE', 29.90);
    $pixKey = (string) getDynamicConfig('EXPRESS_PIX_KEY', 'pix@exemplo.com');

    // Verificar se já existe taxa e se difere da configuração atual
    $existeTaxa = false; $taxaValorExistente = null; $taxaPixExistente = null;
    foreach ($rows as $r) {
        if (!empty($r['taxa_valor']) && !empty($r['taxa_pix'])) {
            $existeTaxa = true;
            $taxaValorExistente = (float) $r['taxa_valor'];
            $taxaPixExistente = (string) $r['taxa_pix'];
            break;
        }
    }

    if ($existeTaxa) {
        // Se divergente, atualizar; senão, idempotente
        if (abs($taxaValorExistente - $valor) > 0.0001 || $taxaPixExistente !== $pixKey) {
            $sql = "UPDATE rastreios_status SET taxa_valor = ?, taxa_pix = ? WHERE UPPER(TRIM(codigo)) = ?";
            executeQuery($pdo, $sql, [$valor, $pixKey, strtoupper(trim($codigo))]);
            writeLog("Taxa expressa ATUALIZADA para o código {$codigo}", 'INFO');
            echo json_encode(['success' => true, 'message' => 'Taxa atualizada. Utilize a nova chave PIX.', 'updated' => true]);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Taxa já registrada. Aguardando pagamento.', 'already' => true]);
        exit;
    }

    // Não havia taxa: registrar agora
    $sql = "UPDATE rastreios_status SET taxa_valor = ?, taxa_pix = ? WHERE UPPER(TRIM(codigo)) = ?";
    executeQuery($pdo, $sql, [$valor, $pixKey, strtoupper(trim($codigo))]);

    writeLog("Taxa expressa registrada para o código {$codigo}", 'INFO');
    echo json_encode(['success' => true, 'message' => 'Taxa registrada. Siga as instruções de pagamento PIX.']);
} catch (Exception $e) {
    writeLog('Erro em solicitar_express: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
}


