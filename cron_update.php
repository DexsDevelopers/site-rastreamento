<?php
/**
 * Cron Job para atualização automática de status
 * Versão segura com prepared statements
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';

try {
    // Buscar códigos com próximas etapas
    $sql = "SELECT codigo, MIN(data) as proximo FROM rastreios_status 
            WHERE data > NOW() GROUP BY codigo";
    
    $results = fetchData($pdo, $sql);
    
    $updated = 0;
    foreach ($results as $row) {
        // Liberar o próximo passo alterando a data para "agora"
        $update_sql = "UPDATE rastreios_status SET data = NOW() 
                       WHERE codigo = ? AND data = ? LIMIT 1";
        
        $stmt = executeQuery($pdo, $update_sql, [$row['codigo'], $row['proximo']]);
        if ($stmt->rowCount() > 0) {
            $updated++;
            notifyWhatsappLatestStatus($pdo, $row['codigo']);
        }
    }
    
    writeLog("Cron update executado: $updated rastreios atualizados", 'INFO');
    
} catch (Exception $e) {
    writeLog("Erro no cron update: " . $e->getMessage(), 'ERROR');
}
?>
