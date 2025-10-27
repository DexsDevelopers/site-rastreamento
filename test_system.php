<?php
/**
 * Teste do Sistema - Helmer Logistics
 * Verifica se todas as funcionalidades estão funcionando
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<h1>🧪 Teste do Sistema Helmer Logistics</h1>";
echo "<p>Verificando funcionalidades...</p>";

$tests = [];
$totalTests = 0;
$passedTests = 0;

// Teste 1: Conexão com banco
$totalTests++;
try {
    $result = fetchOne($pdo, "SELECT 1 as test");
    if ($result && $result['test'] == 1) {
        $tests[] = "✅ Conexão com banco de dados: OK";
        $passedTests++;
    } else {
        $tests[] = "❌ Conexão com banco de dados: FALHOU";
    }
} catch (Exception $e) {
    $tests[] = "❌ Conexão com banco de dados: ERRO - " . $e->getMessage();
}

// Teste 2: Tabelas existem
$totalTests++;
try {
    $tables = ['clientes', 'indicacoes', 'compras', 'rastreios_status'];
    $allTablesExist = true;
    
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() == 0) {
            $allTablesExist = false;
            break;
        }
    }
    
    if ($allTablesExist) {
        $tests[] = "✅ Tabelas do banco: OK";
        $passedTests++;
    } else {
        $tests[] = "❌ Tabelas do banco: FALHOU - Execute setup_database.php";
    }
} catch (Exception $e) {
    $tests[] = "❌ Tabelas do banco: ERRO - " . $e->getMessage();
}

// Teste 3: Arquivos principais existem
$totalTests++;
$files = ['index.php', 'admin.php', 'indicacao.php', 'admin_indicacoes.php'];
$allFilesExist = true;

foreach ($files as $file) {
    if (!file_exists($file)) {
        $allFilesExist = false;
        break;
    }
}

if ($allFilesExist) {
    $tests[] = "✅ Arquivos principais: OK";
    $passedTests++;
} else {
    $tests[] = "❌ Arquivos principais: FALHOU - Arquivos ausentes";
}

// Teste 4: Configurações
$totalTests++;
if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    $tests[] = "✅ Configurações: OK";
    $passedTests++;
} else {
    $tests[] = "❌ Configurações: FALHOU - Verifique includes/config.php";
}

// Teste 5: Funções de segurança
$totalTests++;
try {
    $testInput = "test<script>alert('xss')</script>";
    $sanitized = sanitizeInput($testInput);
    if ($sanitized !== $testInput) {
        $tests[] = "✅ Funções de segurança: OK";
        $passedTests++;
    } else {
        $tests[] = "❌ Funções de segurança: FALHOU - Sanitização não funcionando";
    }
} catch (Exception $e) {
    $tests[] = "❌ Funções de segurança: ERRO - " . $e->getMessage();
}

// Teste 6: Sistema de indicação
$totalTests++;
try {
    if (class_exists('ReferralSystem')) {
        $tests[] = "✅ Sistema de indicação: OK";
        $passedTests++;
    } else {
        $tests[] = "❌ Sistema de indicação: FALHOU - Classe não encontrada";
    }
} catch (Exception $e) {
    $tests[] = "❌ Sistema de indicação: ERRO - " . $e->getMessage();
}

// Resultados
echo "<hr>";
echo "<h2>📊 Resultados dos Testes</h2>";
echo "<p><strong>Testes executados:</strong> $totalTests</p>";
echo "<p><strong>Testes aprovados:</strong> $passedTests</p>";
echo "<p><strong>Taxa de sucesso:</strong> " . round(($passedTests / $totalTests) * 100, 1) . "%</p>";

echo "<h3>📋 Detalhes dos Testes:</h3>";
foreach ($tests as $test) {
    echo "<p>$test</p>";
}

echo "<hr>";

if ($passedTests == $totalTests) {
    echo "<h2 style='color: #10b981;'>🎉 Sistema Funcionando Perfeitamente!</h2>";
    echo "<p>✅ Todas as funcionalidades estão operacionais.</p>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Acesse <a href='index.php'>index.php</a> para testar o rastreamento</li>";
    echo "<li>✅ Acesse <a href='indicacao.php'>indicacao.php</a> para testar indicações</li>";
    echo "<li>✅ Acesse <a href='admin.php'>admin.php</a> para gerenciar o sistema</li>";
    echo "<li>✅ Acesse <a href='admin_indicacoes.php'>admin_indicacoes.php</a> para gerenciar indicações</li>";
    echo "</ul>";
} else {
    echo "<h2 style='color: #ef4444;'>⚠️ Sistema com Problemas</h2>";
    echo "<p>❌ Algumas funcionalidades não estão funcionando corretamente.</p>";
    echo "<p><strong>Ações recomendadas:</strong></p>";
    echo "<ul>";
    echo "<li>🔧 Execute <a href='setup_database.php'>setup_database.php</a> para criar as tabelas</li>";
    echo "<li>🔧 Verifique as configurações em includes/config.php</li>";
    echo "<li>🔧 Confirme se todos os arquivos foram enviados</li>";
    echo "<li>🔧 Verifique as permissões de arquivo</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><small>Teste executado em: " . date('Y-m-d H:i:s') . "</small></p>";
echo "<p><small>Helmer Logistics - Sistema de Indicação v2.0</small></p>";
?>

