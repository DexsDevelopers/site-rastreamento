<?php
require_once 'includes/db_connect_cli.php';

try {
    echo "Iniciando migracao (CLI)...\n";
    
    // Check if column exists
    $sql = "SHOW COLUMNS FROM bot_automations LIKE 'grupos_permitidos'";
    $stmt = $pdo->query($sql);
    
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna grupos_permitidos...\n";
        $pdo->exec("ALTER TABLE bot_automations ADD COLUMN grupos_permitidos TEXT DEFAULT NULL COMMENT 'JSON array ou lista separada por vírgula de JIDs permitidos' AFTER grupo_id");
        echo "Coluna adicionada.\n";
        
        // Migrate existing data
        echo "Migrando dados existentes...\n";
        $sqlMigrate = "UPDATE bot_automations SET grupos_permitidos = JSON_ARRAY(grupo_id) WHERE grupo_id IS NOT NULL AND grupo_id != '' AND grupos_permitidos IS NULL";
        $pdo->exec($sqlMigrate);
        echo "Dados migrados com sucesso.\n";
    } else {
        echo "Coluna grupos_permitidos ja existe.\n";
    }
    
    echo "Migracao concluida.\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>
