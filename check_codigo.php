<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

header('Content-Type: application/json; charset=UTF-8');

$codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
if ($codigo === '') {
    http_response_code(400);
    echo json_encode(['exists' => false, 'error' => 'codigo_required']);
    exit;
}

try {
    $row = fetchOne($pdo, "SELECT 1 AS e FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? LIMIT 1", [strtoupper($codigo)]);
    echo json_encode(['exists' => (bool)$row]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['exists' => false, 'error' => 'internal']);
}


