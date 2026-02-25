<?php
/**
 * Script para criar tabela de configurações da página inicial
 * Execute este arquivo uma vez para configurar o banco de dados
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<pre>";
echo "=== Configurando tabela de configurações da página inicial ===\n\n";

try {
    // Tabela de configurações da homepage
    $sql = "CREATE TABLE IF NOT EXISTS homepage_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(100) NOT NULL UNIQUE,
        valor TEXT,
        tipo VARCHAR(50) DEFAULT 'text' COMMENT 'text, number, image, html',
        descricao TEXT,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_chave (chave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Tabela homepage_config criada/verificada!\n";
    
    // Inserir configurações padrão
    $defaultConfigs = [
        ['nome_empresa', 'Helmer Logistics', 'text', 'Nome da empresa exibido no logo'],
        ['titulo_hero', 'Acompanhe seus Recebimentos em Tempo Real', 'text', 'Título principal da página'],
        ['descricao_hero', 'Verifique o status dos seus recebimentos com tecnologia de ponta e acompanhamento em tempo real', 'text', 'Descrição abaixo do título'],
        ['badge_satisfacao', '98.7% de Satisfação', 'text', 'Texto do badge de satisfação'],
        ['badge_entregas', '5.247 Entregas', 'text', 'Texto do badge de entregas'],
        ['badge_cidades', '247 Cidades', 'text', 'Texto do badge de cidades'],


















        // Seção "Como funciona"
        ['how_it_works_title', 'Como funciona', 'text', 'Título da seção Como funciona'],
        ['feature1_title', '1) Rastreie', 'text', 'Título do Feature 1'],
        ['feature1_description', 'Digite o código e a cidade para validar e ver o status do envio.', 'text', 'Descrição do Feature 1'],
        ['feature2_title', '2) Acompanhe', 'text', 'Título do Feature 2'],
        ['feature2_description', 'Veja a linha do tempo com todas as etapas do seu recebimento.', 'text', 'Descrição do Feature 2'],
        ['feature3_title', '3) Entrega Expressa', 'text', 'Título do Feature 3'],
        ['feature3_description', 'Antecipe em 3 dias com confirmação rápida por PIX, quando disponível.', 'text', 'Descrição do Feature 3'],
        // Prova social
        ['social_proof1_title', 'Satisfação 98,7%', 'text', 'Título da Prova Social 1'],
        ['social_proof1_link_text', 'Ver metodologia', 'text', 'Texto do link da Prova Social 1'],
        ['social_proof2_title', '+5.247 Entregas', 'text', 'Título da Prova Social 2'],
        ['social_proof2_link_text', 'Ver histórico', 'text', 'Texto do link da Prova Social 2'],
        ['social_proof3_title', 'Confiabilidade', 'text', 'Título da Prova Social 3'],
        ['social_proof3_link_text', 'Política e garantias', 'text', 'Texto do link da Prova Social 3'],
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO homepage_config (chave, valor, tipo, descricao) VALUES (?, ?, ?, ?)");
    foreach ($defaultConfigs as $config) {
        $stmt->execute($config);
    }
    echo "✅ Configurações padrão inseridas!\n";
    
    echo "\n=== CONFIGURAÇÃO CONCLUÍDA! ===\n";
    echo "\nAcesse: admin_homepage.php para editar as configurações.\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";


