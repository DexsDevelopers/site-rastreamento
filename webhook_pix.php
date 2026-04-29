<?php
/**
 * Webhook receptor para PixGhost
 * URL para configurar no painel PixGhost: https://seudominio.com/webhook_pix.php
 * 
 * Payload esperado do PixGhost:
 * {
 *   "event": "payment.completed",
 *   "transaction_id": 123,
 *   "pix_id": "abc123",
 *   "amount": 25.00,
 *   "amount_net": 24.00,
 *   "customer_name": "João Silva",
 *   "status": "paid"
 * }
 */

header('Content-Type: application/json');

// Apenas aceitar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Ler o body JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log do webhook recebido
$logFile = __DIR__ . '/logs/webhook_pix.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logEntry = date('Y-m-d H:i:s') . ' | ' . $json . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Validar payload
if (!$data || empty($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Verificar se é pagamento confirmado
$status = strtolower($data['status']);
if ($status !== 'paid' && $status !== 'confirmed' && $data['event'] !== 'payment.completed') {
    http_response_code(200);
    echo json_encode(['received' => true, 'action' => 'ignored', 'reason' => 'not a payment confirmation']);
    exit;
}

$pixId     = $data['pix_id'] ?? $data['transaction_id'] ?? null;
$externalId = trim($data['external_id'] ?? '');

// Conectar ao banco de dados
try {
    require_once __DIR__ . '/includes/db_connect.php';

    $rastreioDireto = null;

    // 1) Match primário: external_id == codigo (gerado via gerar_taxa_pix.php)
    if ($externalId) {
        $rastreioDireto = fetchOne($pdo,
            "SELECT DISTINCT codigo, cidade, tipo_entrega 
             FROM rastreios_status 
             WHERE codigo = ? AND taxa_paga = FALSE AND taxa_valor IS NOT NULL
             LIMIT 1",
            [$externalId]
        );
        if ($rastreioDireto) {
            $logMsg = date('Y-m-d H:i:s') . " | MATCH external_id: {$externalId}\n";
            file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
        }
    }

    // 2) Fallback legado: taxa_pix contém o pix_id
    if (!$rastreioDireto && $pixId) {
        $rastreioDireto = fetchOne($pdo,
            "SELECT DISTINCT codigo, cidade, tipo_entrega 
             FROM rastreios_status 
             WHERE taxa_pix LIKE ? AND taxa_paga = FALSE
             LIMIT 1",
            ['%' . $pixId . '%']
        );
        if ($rastreioDireto) {
            $logMsg = date('Y-m-d H:i:s') . " | MATCH legado taxa_pix LIKE pix_id: {$pixId}\n";
            file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
        }
    }

    $updated = false;

    if ($rastreioDireto) {
        $updated = marcarComoPago($pdo, $rastreioDireto['codigo'], $rastreioDireto['cidade'], $rastreioDireto['tipo_entrega']);
        $logMsg  = date('Y-m-d H:i:s') . " | PAGO: {$rastreioDireto['codigo']} | external_id={$externalId} pix_id={$pixId}\n";
        file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
    } else {
        $logMsg = date('Y-m-d H:i:s') . " | SEM MATCH | external_id={$externalId} pix_id={$pixId}\n";
        file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
    }

    http_response_code(200);
    echo json_encode([
        'received'    => true,
        'pix_id'      => $pixId,
        'external_id' => $externalId,
        'updated'     => $updated,
        'codigo'      => $rastreioDireto ? $rastreioDireto['codigo'] : null
    ]);

} catch (Exception $e) {
    error_log("Webhook PIX erro: " . $e->getMessage());
    $logMsg = date('Y-m-d H:i:s') . " | ERRO: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);

    http_response_code(200);
    echo json_encode(['received' => true, 'error' => 'internal']);
}

/**
 * Marca um rastreio como pago e adiciona etapa de entrega
 */
function marcarComoPago($pdo, $codigo, $cidade, $tipoEntrega) {
    // Verificar se já foi marcado como pago
    $existing = fetchData($pdo,
        "SELECT id FROM rastreios_status WHERE codigo = ? AND titulo LIKE '%saiu para entrega%'",
        [$codigo]
    );

    if (count($existing) > 0) {
        return false; // Já foi processado
    }

    // Remover taxa pendente
    executeQuery($pdo,
        "UPDATE rastreios_status SET taxa_valor = NULL, taxa_pix = NULL, taxa_paga = TRUE WHERE codigo = ?",
        [$codigo]
    );

    // Inserir etapa de "saiu para entrega"
    executeQuery($pdo,
        "INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor, tipo_entrega, taxa_paga) 
         VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, TRUE)",
        [
            $codigo,
            $cidade ?: 'Centro de Distribuição',
            '🚀 Objeto saiu para entrega',
            '🚀 Objeto saiu para entrega',
            'Pagamento confirmado! Seu pacote já foi liberado e está em rota de entrega para sua residência.',
            '#2563EB',
            $tipoEntrega ?: 'NORMAL'
        ]
    );

    return true;
}
