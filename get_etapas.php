<?php
/**
 * API para buscar etapas de rastreamento
 * Versão segura com prepared statements
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Verificar se o código foi fornecido
if (!isset($_GET['codigo']) || empty($_GET['codigo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Código de rastreamento é obrigatório']);
    exit;
}

try {
    $codigo = sanitizeInput($_GET['codigo']);
    
    $sql = "SELECT * FROM rastreios_status WHERE codigo = ? ORDER BY data ASC";
    $results = fetchData($pdo, $sql, [$codigo]);
    
    $dados = [
        "cidade" => "",
        "etapas" => [],
        "taxa_valor" => null,
        "taxa_pix" => null
    ];
    
    foreach ($results as $row) {
        $dados["cidade"] = $row["cidade"];
        $dados["taxa_valor"] = $row["taxa_valor"];
        $dados["taxa_pix"] = $row["taxa_pix"];
        
        if (strpos($row["titulo"], "Objeto postado") !== false) $dados["etapas"][] = "postado";
        if (strpos($row["titulo"], "Em trânsito") !== false) $dados["etapas"][] = "transito";
        if (strpos($row["titulo"], "centro de distribuição") !== false) $dados["etapas"][] = "distribuicao";
        if (strpos($row["titulo"], "Saiu para entrega") !== false) $dados["etapas"][] = "entrega";
        if (strpos($row["titulo"], "Entregue") !== false) $dados["etapas"][] = "entregue";
    }
    
    header("Content-Type: application/json");
    echo json_encode($dados);
    
} catch (Exception $e) {
    writeLog("Erro ao buscar etapas: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
