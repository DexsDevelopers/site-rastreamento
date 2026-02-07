<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    echo "Iniciando migração do banco de dados...\n";

    // 1. Aumentar tamanho do campo telefone para suportar JIDs completos
    $pdo->exec("ALTER TABLE marketing_membros MODIFY COLUMN telefone VARCHAR(100) NOT NULL");
    echo "✓ Coluna 'telefone' aumentada para VARCHAR(100).\n";

    // 2. Opcional: Limpar JIDs mal formatados se existirem
    // $pdo->exec("UPDATE marketing_membros SET telefone = REPLACE(telefone, '@s.wh', '@s.whatsapp.net') WHERE telefone LIKE '%@s.wh'");

    echo "\nMigração concluída com sucesso! Agora o sistema suporta JIDs completos sem truncamento.\n";
    echo "Recomendado: Clique em 'Sincronizar Grupos' no painel de Marketing para atualizar os membros com JIDs completos.";

}
catch (PDOException $e) {
    echo "ERRO NA MIGRAÇÃO: " . $e->getMessage();
}
?>