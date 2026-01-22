<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<pre>";
echo "=== Atualizando Schema para Múltiplos Grupos ===\n\n";

try {
    // Adicionar coluna grupos_permitidos se não existir
    $sql = "SHOW COLUMNS FROM bot_automations LIKE 'grupos_permitidos'";
    $stmt = $pdo->query($sql);
    
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna 'grupos_permitidos'...\n";
        $pdo->exec("ALTER TABLE bot_automations ADD COLUMN grupos_permitidos TEXT DEFAULT NULL COMMENT 'JSON array ou lista separada por vírgula de JIDs permitidos' AFTER grupo_id");
        echo "✅ Coluna adicionada com sucesso!\n";
        
        // Migrar dados existentes
        echo "Migrando dados de 'grupo_id' para 'grupos_permitidos'...\n";
        $sqlMigrate = "UPDATE bot_automations SET grupos_permitidos = grupo_id WHERE grupo_id IS NOT NULL AND grupos_permitidos IS NULL";
        $pdo->exec($sqlMigrate);
        echo "✅ Dados migrados!\n";
    } else {
        echo "ℹ️ Coluna 'grupos_permitidos' já existe.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\nConcluído!</pre>";
?>