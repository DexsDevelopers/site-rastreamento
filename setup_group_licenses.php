<?php
/**
 * Setup - Tabela de Licenças de Grupos
 * Sistema de aluguel do bot para grupos WhatsApp
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<h2>🔑 Setup - Sistema de Licenças de Grupos</h2>";

try {
    // Criar tabela de licenças
    $sql = "CREATE TABLE IF NOT EXISTS bot_group_licenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        license_key VARCHAR(50) NOT NULL UNIQUE,
        group_jid VARCHAR(100) DEFAULT NULL,
        group_name VARCHAR(255) DEFAULT NULL,
        days_purchased INT NOT NULL DEFAULT 30,
        activated_at DATETIME DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        status ENUM('pending', 'active', 'expired', 'revoked') DEFAULT 'pending',
        created_by VARCHAR(100) DEFAULT 'admin',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_license_key (license_key),
        INDEX idx_group_jid (group_jid),
        INDEX idx_status (status),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "<p>✅ Tabela <code>bot_group_licenses</code> criada com sucesso!</p>";
    
    // Criar tabela de histórico de ativações
    $sql2 = "CREATE TABLE IF NOT EXISTS bot_license_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        license_id INT NOT NULL,
        action ENUM('created', 'activated', 'renewed', 'expired', 'revoked') NOT NULL,
        group_jid VARCHAR(100) DEFAULT NULL,
        group_name VARCHAR(255) DEFAULT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (license_id) REFERENCES bot_group_licenses(id) ON DELETE CASCADE,
        INDEX idx_license_id (license_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql2);
    echo "<p>✅ Tabela <code>bot_license_history</code> criada com sucesso!</p>";
    
    // Criar tabela de configurações de preços
    $sql3 = "CREATE TABLE IF NOT EXISTS bot_license_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        days INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql3);
    echo "<p>✅ Tabela <code>bot_license_plans</code> criada com sucesso!</p>";
    
    // Inserir planos padrão se não existirem (força recriação se menos de 6)
    $checkPlans = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_license_plans");
    if ($checkPlans['total'] < 6) {
        // Limpar planos existentes e recriar
        $pdo->exec("DELETE FROM bot_license_plans");
        
        $plans = [
            ['Semanal', 7, 19.90, 'Licença por 7 dias'],
            ['Quinzenal', 15, 34.90, 'Licença por 15 dias'],
            ['Mensal', 30, 49.90, 'Licença por 30 dias'],
            ['Trimestral', 90, 129.90, 'Licença por 90 dias'],
            ['Semestral', 180, 229.90, 'Licença por 180 dias'],
            ['Anual', 365, 399.90, 'Licença por 365 dias']
        ];
        
        foreach ($plans as $plan) {
            executeQuery($pdo, 
                "INSERT INTO bot_license_plans (name, days, price, description) VALUES (?, ?, ?, ?)",
                $plan
            );
            echo "<p>✅ Plano '{$plan[0]}' - {$plan[1]} dias - R$ " . number_format($plan[2], 2, ',', '.') . " inserido!</p>";
        }
        echo "<p>✅ " . count($plans) . " planos inseridos!</p>";
    } else {
        echo "<p>✅ Planos já existem (" . $checkPlans['total'] . " planos)</p>";
    }
    
    echo "<hr>";
    echo "<h3>✅ Setup concluído com sucesso!</h3>";
    echo "<p><a href='admin_bot_licenses.php'>→ Ir para Gerenciamento de Licenças</a></p>";
    echo "<p><a href='dashboard.php'>→ Voltar ao Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

