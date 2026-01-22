<?php
// Configurações do banco para CLI
$host = '127.0.0.1';
$db   = 'u853242961_rastreio';
$user = 'u853242961_johan71';
$pass = 'Lucastav8012@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo "Erro de conexão com o banco (CLI): " . $e->getMessage() . "\n";
    exit(1);
}
?>
