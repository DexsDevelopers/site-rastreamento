<?php
/**
 * Configurar Chave da API do Gemini
 * Atualiza a chave da API do Gemini no banco de dados
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

$geminiApiKey = 'AIzaSyCSi6prQM7XctTSIej59qhDED8EPkdSTzU';

echo "<h2>🔑 Configurando Chave da API do Gemini</h2>";

try {
    // Verificar se a tabela existe
    try {
        $pdo->query("SELECT 1 FROM bot_ia_settings LIMIT 1");
    } catch (PDOException $e) {
        echo "<p style='color:red;'>❌ Tabela bot_ia_settings não existe. Execute setup_bot_ia.php primeiro.</p>";
        echo "<p><a href='setup_bot_ia.php'>→ Executar Setup da IA</a></p>";
        exit;
    }
    
    // Atualizar ou inserir a chave da API
    $stmt = $pdo->prepare("
        INSERT INTO bot_ia_settings (setting_key, setting_value, setting_description) 
        VALUES ('gemini_api_key', ?, 'Chave da API do Google Gemini')
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    
    $stmt->execute([$geminiApiKey, $geminiApiKey]);
    
    // Verificar se foi salvo corretamente
    $stmt = $pdo->prepare("SELECT setting_value FROM bot_ia_settings WHERE setting_key = 'gemini_api_key'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['setting_value'] === $geminiApiKey) {
        $maskedKey = substr($geminiApiKey, 0, 8) . '...' . substr($geminiApiKey, -4);
        echo "<p style='color:#22c55e;'>✅ <strong>Chave da API configurada com sucesso!</strong></p>";
        echo "<p>Chave: <code>{$maskedKey}</code></p>";
        echo "<hr>";
        echo "<h3>Próximos passos:</h3>";
        echo "<ul>";
        echo "<li><a href='admin_bot_ia.php' style='color:#8B5CF6;'>→ Verificar configurações da IA</a></li>";
        echo "<li><a href='dashboard.php' style='color:#8B5CF6;'>→ Voltar ao Dashboard</a></li>";
        echo "</ul>";
    } else {
        echo "<p style='color:red;'>❌ Erro ao salvar a chave da API.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

