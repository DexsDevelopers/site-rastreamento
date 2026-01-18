<?php
/**
 * Script de teste para verificar configurações da homepage
 * Acesse este arquivo no navegador para ver se os dados estão sendo salvos
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<pre>";
echo "=== TESTE DE CONFIGURAÇÕES DA HOMEPAGE ===\n\n";

try {
    // Verificar se tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'homepage_config'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Tabela homepage_config NÃO EXISTE!\n";
        echo "   Execute: setup_homepage_config.php primeiro\n\n";
    } else {
        echo "✅ Tabela homepage_config existe\n\n";
        
        // Listar todas as configurações
        $configs = fetchData($pdo, "SELECT * FROM homepage_config ORDER BY chave");
        echo "Configurações encontradas: " . count($configs) . "\n\n";
        
        foreach ($configs as $config) {
            echo "[{$config['chave']}] = \"{$config['valor']}\" (tipo: {$config['tipo']})\n";
        }
        
        // Testar função getHomepageConfig
        echo "\n=== TESTE DE LEITURA ===\n";
        
        function getHomepageConfig($pdo, $chave, $default = '') {
            try {
                $result = fetchOne($pdo, "SELECT valor FROM homepage_config WHERE chave = ?", [$chave]);
                return $result && isset($result['valor']) ? $result['valor'] : $default;
            } catch (Exception $e) {
                return $default;
            }
        }
        
        $badgeEntregas = getHomepageConfig($pdo, 'badge_entregas', '5.247 Entregas');
        $badgeSatisfacao = getHomepageConfig($pdo, 'badge_satisfacao', '98.7% de Satisfação');
        $badgeCidades = getHomepageConfig($pdo, 'badge_cidades', '247 Cidades');
        
        echo "badge_entregas: \"$badgeEntregas\"\n";
        echo "badge_satisfacao: \"$badgeSatisfacao\"\n";
        echo "badge_cidades: \"$badgeCidades\"\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
echo "</pre>";

