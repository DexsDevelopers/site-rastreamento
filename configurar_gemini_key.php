<?php
/**
 * Configurar Chave da API do Gemini
 * Atualiza a chave da API do Gemini no banco de dados
 * 
 * USO: Configure a chave pelo painel admin_bot_ia.php
 * Este arquivo foi removido por segurança - não deve conter chaves hardcoded
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<h2>🔑 Configurar Chave da API do Gemini</h2>";
echo "<p style='color:#f59e0b;'>⚠️ <strong>Este script foi removido por segurança.</strong></p>";
echo "<p>Para configurar a chave da API do Gemini, use o painel administrativo:</p>";
echo "<hr>";
echo "<h3>Como configurar:</h3>";
echo "<ol>";
echo "<li>Acesse o <a href='admin_bot_ia.php' style='color:#8B5CF6;'>Painel de IA do Bot</a></li>";
echo "<li>Vá na aba <strong>Configurações</strong></li>";
echo "<li>Cole sua chave da API do Gemini no campo <strong>API Key do Gemini</strong></li>";
echo "<li>Clique em <strong>Salvar</strong></li>";
echo "</ol>";
echo "<hr>";
echo "<p><a href='admin_bot_ia.php' style='color:#8B5CF6;'>→ Ir para Painel de IA</a></p>";
echo "<p><a href='dashboard.php' style='color:#8B5CF6;'>→ Voltar ao Dashboard</a></p>";
?>

