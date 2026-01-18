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
        ['referencia_imagem_1', 'assets/images/whatsapp-1.jpg', 'image', 'Imagem de referência 1'],
        ['referencia_nome_1', 'Luiz Gabriel - Petrópolis', 'text', 'Nome da referência 1'],
        ['referencia_desc_1', 'Sistema de rastreamento básico funcionando perfeitamente', 'text', 'Descrição da referência 1'],
        ['referencia_imagem_2', 'assets/images/whatsapp-2.jpg', 'image', 'Imagem de referência 2'],
        ['referencia_nome_2', 'juuh santts - Ubá', 'text', 'Nome da referência 2'],
        ['referencia_desc_2', 'Monitoramento oficial com status detalhado', 'text', 'Descrição da referência 2'],
        ['referencia_imagem_3', 'assets/images/whatsapp-3.jpg', 'image', 'Imagem de referência 3'],
        ['referencia_nome_3', 'RKZIN - Jardim Camburi', 'text', 'Nome da referência 3'],
        ['referencia_desc_3', 'Sistema oficial de monitoramento em tempo real', 'text', 'Descrição da referência 3'],
        ['referencia_imagem_4', 'assets/images/whatsapp-4.jpg', 'image', 'Imagem de referência 4'],
        ['referencia_nome_4', 'Vitor João - AdolfoSP', 'text', 'Nome da referência 4'],
        ['referencia_desc_4', 'Monitoramento com interface integrada ao WhatsApp', 'text', 'Descrição da referência 4'],
        ['referencia_imagem_5', 'assets/images/whatsapp-5.jpg', 'image', 'Imagem de referência 5'],
        ['referencia_nome_5', '', 'text', 'Nome da referência 5'],
        ['referencia_desc_5', '', 'text', 'Descrição da referência 5'],
        ['referencia_imagem_6', 'assets/images/whatsapp-6.jpg', 'image', 'Imagem de referência 6'],
        ['referencia_nome_6', '', 'text', 'Nome da referência 6'],
        ['referencia_desc_6', '', 'text', 'Descrição da referência 6'],
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

