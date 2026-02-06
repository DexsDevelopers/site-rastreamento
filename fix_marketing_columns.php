<?php
/**
 * Fix: Adicionar colunas faltantes na tabela marketing_membros
 */

require_once 'includes/db_connect.php';

echo "🔧 Corrigindo estrutura da tabela marketing_membros...\n\n";

try {
    // Adicionar coluna data_entrada_fluxo se não existir
    $pdo->exec("ALTER TABLE marketing_membros 
                ADD COLUMN IF NOT EXISTS data_entrada_fluxo DATETIME DEFAULT NULL COMMENT 'Quando o lead entrou no fluxo (primeira mensagem)'");
    echo "✅ Coluna 'data_entrada_fluxo' adicionada/verificada\n";
    
    // Preencher data_entrada_fluxo para registros existentes (usar created_at ou NOW)
    $pdo->exec("UPDATE marketing_membros 
                SET data_entrada_fluxo = COALESCE(created_at, NOW()) 
                WHERE data_entrada_fluxo IS NULL AND status != 'novo'");
    echo "✅ Dados históricos preenchidos\n";
    
    // Verificar outras colunas importantes
    $pdo->exec("ALTER TABLE marketing_membros 
                ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
    echo "✅ Coluna 'created_at' verificada\n";
    
    echo "\n✅ Estrutura corrigida com sucesso!\n";
    echo "\n📊 Verificando dados...\n";
    
    $stats = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN data_entrada_fluxo IS NOT NULL THEN 1 ELSE 0 END) as com_data,
        SUM(CASE WHEN status = 'novo' THEN 1 ELSE 0 END) as novos
        FROM marketing_membros")->fetch();
    
    echo "Total de membros: {$stats['total']}\n";
    echo "Com data de entrada: {$stats['com_data']}\n";
    echo "Status 'novo': {$stats['novos']}\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Concluído! Pode fechar esta aba.\n";
