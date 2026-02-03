<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    // Jogar todas as 1800 mensagens pendentes para o futuro distante (ex: 2025)
    // Assim o bot PÁRA de tentar enviar elas agora.
    // Você poderá reativá-las aos poucos ou deixar o "Zerar Limite" puxar algumas.
    
    // SQL: Atualiza data_proximo_envio para daqui a 30 dias de quem está atrasado
    $pdo->exec("UPDATE marketing_membros 
                SET data_proximo_envio = DATE_ADD(NOW(), INTERVAL 30 DAY) 
                WHERE status = 'em_progresso' 
                AND data_proximo_envio <= NOW()");
                
    echo "EMERGÊNCIA: Fila de atrasados foi jogada para o próximo mês.\n";
    echo "O bot vai parar de disparar loucamente.\n";

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
