<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

header('Content-Type: text/plain');

echo "=== Configurando Logs para Marketing ===\n\n";

try {
    // 1. Verificar se existe a automação 'Campanha Marketing'
    $auto = fetchOne($pdo, "SELECT id FROM bot_automations WHERE nome = 'Campanha Marketing' LIMIT 1");

    if ($auto) {
        $id = $auto['id'];
        echo "✅ Automação de Marketing já existe (ID: $id).\n";
    } else {
        // Criar
        $sql = "INSERT INTO bot_automations (nome, descricao, ativo, tipo, gatilho, resposta, prioridade) 
                VALUES ('Campanha Marketing', 'Logs automáticos da campanha de marketing', 1, 'mensagem_especifica', 'SYSTEM_MARKETING', 'Dinâmico', -1)";
        
        executeQuery($pdo, $sql);
        $id = $pdo->lastInsertId();
        echo "✅ Criada automação de sistema para logs (ID: $id).\n";
    }

    // 2. Verificar se a tabela de logs aceita NULL no automation_id (Opcional, mas bom saber)
    // Na verdade, vamos usar o ID que acabamos de pegar, então não precisa alterar a tabela.

    echo "\nID para uso no api_marketing.php: $id\n";
    echo "Agora você deve atualizar o api_marketing.php para usar este ID.\n";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
