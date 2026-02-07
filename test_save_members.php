<?php
// Teste isolado para ação save_members
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "=== INICIANDO TESTE ===\n";

try {
    echo "1. Carregando configs...\n";
    if (!file_exists('includes/config.php'))
        die("ERRO: includes/config.php não encontrado");
    require_once 'includes/config.php';

    echo "2. Carregando DB...\n";
    if (!file_exists('includes/db_connect.php'))
        die("ERRO: includes/db_connect.php não encontrado");
    require_once 'includes/db_connect.php';
    echo "   DB Conectado!\n";

    // Simular dados de entrada
    $groupJid = "123456@g.us";
    $members = ["5511999999999@s.whatsapp.net", "5511888888888"]; // Um JID e um número normal

    echo "3. Preparando Query...\n";
    $sql = "INSERT IGNORE INTO marketing_membros (telefone, grupo_origem_jid, status) VALUES (?, ?, 'novo')";
    $stmt = $pdo->prepare($sql);
    echo "   Query preparada!\n";

    echo "4. Inserindo membros...\n";
    $added = 0;
    foreach ($members as $phone) {
        echo "   Processando: $phone -> ";

        // Lógica copiada do api_marketing.php
        if (strpos($phone, '@') === false) {
            $phone = preg_replace('/\D/', '', $phone);
            if (strlen($phone) < 10) {
                echo "Ignorado (muito curto)\n";
                continue;
            }
        }

        try {
            $stmt->execute([$phone, $groupJid]);
            echo "Inserido! \n";
            $added++;
        }
        catch (Exception $e) {
            echo "ERRO SQL: " . $e->getMessage() . "\n";
        }
    }

    echo "=== TUDO OK! ===\n";
    echo "Total adicionado: $added\n";

}
catch (Exception $e) {
    echo "\n\n!!! ERRO FATAL !!!\n";
    echo $e->getMessage();
    echo "\n" . $e->getTraceAsString();
}
?>