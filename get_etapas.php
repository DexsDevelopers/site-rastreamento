<?php
/**
 * API para buscar etapas de rastreamento
 * Versão segura com prepared statements
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';
require_once 'includes/rastreio_media.php';

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
        "taxa_pix" => null,
        "data_inicial" => null,
        "cliente_nome" => null,
        "cliente_whatsapp" => null,
        "cliente_notificar" => false,
        "foto_url" => null
    ];
    
    // Buscar contato WhatsApp
    $contato = getWhatsappContact($pdo, $codigo);
    if ($contato) {
        $dados["cliente_nome"] = $contato["nome"];
        $dados["cliente_whatsapp"] = $contato["telefone_original"];
        $dados["cliente_notificar"] = (int) $contato["notificacoes_ativas"] === 1;
    }
    
    if (empty($results)) {
        header("Content-Type: application/json");
        echo json_encode($dados);
        exit;
    }
    
    // Pegar cidade do primeiro registro (mais antigo)
    $dados["cidade"] = $results[0]["cidade"] ?? "";

    $foto = getRastreioFoto($pdo, $codigo);
    if ($foto) {
        $dados["foto_url"] = $foto["url"];
    }
    
    // Pegar taxa do registro mais recente que tenha taxa
    $taxaValor = null;
    $taxaPix = null;
    foreach (array_reverse($results) as $row) {
        if (!empty($row["taxa_valor"]) && !empty($row["taxa_pix"])) {
            $taxaValor = $row["taxa_valor"];
            $taxaPix = $row["taxa_pix"];
            break;
        }
    }
    $dados["taxa_valor"] = $taxaValor;
    $dados["taxa_pix"] = $taxaPix;
    
    // Pegar data inicial do primeiro registro
    if (!empty($results[0]["data"])) {
        $dados["data_inicial"] = date("Y-m-d\TH:i", strtotime($results[0]["data"]));
    }
    
    // Identificar etapas presentes
    foreach ($results as $row) {
        if (strpos($row["titulo"], "Objeto postado") !== false && !in_array("postado", $dados["etapas"])) {
            $dados["etapas"][] = "postado";
        }
        if (strpos($row["titulo"], "Em trânsito") !== false && !in_array("transito", $dados["etapas"])) {
            $dados["etapas"][] = "transito";
        }
        if (strpos($row["titulo"], "centro de distribuição") !== false && !in_array("distribuicao", $dados["etapas"])) {
            $dados["etapas"][] = "distribuicao";
        }
        if (strpos($row["titulo"], "Saiu para entrega") !== false && !in_array("entrega", $dados["etapas"])) {
            $dados["etapas"][] = "entrega";
        }
        if (strpos($row["titulo"], "Entregue") !== false && !in_array("entregue", $dados["etapas"])) {
            $dados["etapas"][] = "entregue";
        }
    }
    
    header("Content-Type: application/json");
    echo json_encode($dados);
    
} catch (Exception $e) {
    writeLog("Erro ao buscar etapas: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
