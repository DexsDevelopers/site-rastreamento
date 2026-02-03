<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/plain');

echo "=== DIAGNÓSTICO DE MARKETING ===\n";
echo "Hora do Servidor (PHP): " . date('Y-m-d H:i:s') . "\n";
$dbTime = fetchOne($pdo, "SELECT NOW() as t");
echo "Hora do Banco (MySQL): " . $dbTime['t'] . "\n\n";

// 1. Checar Campanha
$camp = fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id=1");
echo "[Campanha]\n";
echo "Ativo: " . ($camp['ativo'] ? 'SIM' : 'NÃO') . "\n";
echo "Membros/Dia: " . $camp['membros_por_dia_grupo'] . "\n";
echo "Intervalos: " . $camp['intervalo_min_minutos'] . " - " . $camp['intervalo_max_minutos'] . " min\n\n";

// 2. Estatísticas de Membros
echo "[Membros - Totais por Status]\n";
$stats = fetchData($pdo, "SELECT status, COUNT(*) as c FROM marketing_membros GROUP BY status");
foreach ($stats as $s) {
    echo "Status '{$s['status']}': {$s['c']}\n";
}

// 3. Detalhes 'em_progresso'
echo "\n[Análise 'em_progresso']\n";
$pending = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE status = 'em_progresso' AND data_proximo_envio <= NOW()");
echo "Pendentes (Envio <= AGORA): " . $pending['c'] . "\n";

$future = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE status = 'em_progresso' AND data_proximo_envio > NOW()");
echo "Futuros (Envio > AGORA): " . $future['c'] . "\n";

// 4. Ver Limite Diário
echo "\n[Limite Diário Global]\n";
$today = fetchOne($pdo, "SELECT COUNT(*) as c FROM marketing_membros WHERE (status = 'em_progresso' OR status = 'concluido') AND DATE(data_proximo_envio) = CURDATE()");
echo "Usados Hoje (pelo scheduler): " . $today['c'] . "\n";

// 5. Ver amostra de 'em_progresso'
echo "\n[Amostra de Próximos Envios]\n";
$sample = fetchData($pdo, "SELECT id, telefone, data_proximo_envio, status, ultimo_passo_id FROM marketing_membros WHERE status='em_progresso' ORDER BY data_proximo_envio ASC LIMIT 5");
foreach ($sample as $m) {
    echo "ID: {$m['id']} | Tel: {$m['telefone']} | Prox: {$m['data_proximo_envio']} | Passo: {$m['ultimo_passo_id']}\n";
}
