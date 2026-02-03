<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/plain');

try {
    // Aumentar intervalos de 10-20 min para algo mais seguro, ex: 20-40 min
    // E reduzir o limite diário global para 150 (estava 10 no check anterior? espere.)
    // De acordo com o check anterior: "Limite Diário: 10". 
    // Se o usuário quer aumentar os intervalos, vamos fazer isso.
    
    $sql = "UPDATE marketing_campanhas SET 
            intervalo_min_minutos = 30, 
            intervalo_max_minutos = 60 
            WHERE id = 1";
    $pdo->exec($sql);
    echo "Intervalos da campanha atualizados para 30-60 minutos.\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
