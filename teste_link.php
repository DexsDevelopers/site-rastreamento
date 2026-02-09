<?php
require_once 'includes/config.php';
require_once 'includes/whatsapp_helper.php';

// Simular configuração se necessário (mas o require config já carrega)

$codigo = "TESTE123";
$cidade = "São Paulo";

echo "Testando buildWhatsappTrackingLink...\n";
$link = buildWhatsappTrackingLink($codigo, $cidade);
echo "Link gerado: " . $link . "\n";

if (strpos($link, 'cidade=') !== false) {
    echo "SUCESSO: Cidade encontrada no link.\n";
}
else {
    echo "FALHA: Cidade NÃO encontrada no link.\n";
}

// Testar com cidade vazia
$linkVazio = buildWhatsappTrackingLink($codigo, '');
echo "Link sem cidade: " . $linkVazio . "\n";