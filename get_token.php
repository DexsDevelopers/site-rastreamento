<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

$token = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');
echo json_encode(['token' => $token]);
