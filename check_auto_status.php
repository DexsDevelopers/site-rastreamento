<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

$res = fetchData($pdo, "SELECT id, nome, ativo FROM bot_automations");
print_r($res);
