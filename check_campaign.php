<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/plain');

echo "=== Verificação de Campanha ===\n";
$campanha = fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id = 1");
if ($campanha) {
    echo "ID: " . $campanha['id'] . "\n";
    echo "Nome: " . $campanha['nome'] . "\n";
    echo "Ativo: " . ($campanha['ativo'] ? 'SIM' : 'NÃO') . "\n";
    echo "Limite Diário: " . $campanha['membros_por_dia_grupo'] . "\n";
} else {
    echo "Campanha não encontrada!\n";
}

echo "\n=== Verificação de Membros ===\n";
$stats = fetchData($pdo, "SELECT status, COUNT(*) as total FROM marketing_membros GROUP BY status");
foreach ($stats as $s) {
    echo "Status: " . $s['status'] . " | Total: " . $s['total'] . "\n";
}

echo "\n=== Próximos Envios Pendentes ===\n";
$pendentes = fetchData($pdo, "SELECT id, telefone, status, data_proximo_envio FROM marketing_membros WHERE status = 'em_progresso' AND data_proximo_envio <= NOW() LIMIT 5");
foreach ($pendentes as $p) {
    echo "ID: " . $p['id'] . " | Telefone: " . $p['telefone'] . " | Envio: " . $p['data_proximo_envio'] . "\n";
}

echo "\n=== Configuração API Bot ===\n";
require_once 'includes/whatsapp_helper.php';
$config = whatsappApiConfig();
print_r($config);
?>
