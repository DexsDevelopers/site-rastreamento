<?php
require_once 'includes/config.php';
require_once 'includes/whatsapp_helper.php';

$config = whatsappApiConfig();
echo "URL BASE: " . $config['base_url'] . "\n";
echo "TOKEN: " . $config['token'] . "\n";
echo "ENABLED: " . ($config['enabled'] ? 'YES' : 'NO') . "\n";
?>
