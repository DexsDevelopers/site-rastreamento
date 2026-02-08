<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    $configs = [
        'nome_empresa' => 'Loggi',
        'titulo_hero' => 'O rastreio do seu envio é prático',
        'descricao_hero' => 'Acompanhe seu pedido em tempo real com a Loggi. Frete grátis para todo o Brasil.',
        'badge_satisfacao' => 'Loggi para você',
        'badge_entregas' => 'Loggi para empresas',
        'badge_cidades' => 'Ajudar',
        'how_it_works_title' => 'A Loggi entrega onde você precisar',
        'feature1_title' => 'Para você',
        'feature1_description' => 'Envie pacotes para qualquer lugar do Brasil de forma rápida e segura.',
        'feature2_title' => 'Para empresas',
        'feature2_description' => 'Soluções completas de logística para o seu e-commerce crescer.',
        'feature3_title' => 'Entrega Expressa',
        'feature3_description' => 'Antecipe para 3 dias com pagamento rápido por PIX, caso precise de urgência.'
    ];

    foreach ($configs as $chave => $valor) {
        $stmt = $pdo->prepare("UPDATE homepage_config SET valor = ? WHERE chave = ?");
        $stmt->execute([$valor, $chave]);
        echo "Updated $chave to $valor<br>";
    }

    echo "<h1>DONE!</h1>";
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>