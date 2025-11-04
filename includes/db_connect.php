<?php
/**
 * Conexão segura com o banco de dados
 * Utiliza prepared statements para prevenir SQL injection
 */

// Configurações do banco a partir de includes/config.php
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db   = defined('DB_NAME') ? DB_NAME : '';
$user = defined('DB_USER') ? DB_USER : '';
$pass = defined('DB_PASS') ? DB_PASS : '';
$charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

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
    error_log("Erro de conexão com o banco: " . $e->getMessage());
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        http_response_code(500);
        echo '<pre style="background:#111;color:#fff;padding:16px;border-radius:8px;">';
        echo 'Falha ao conectar no banco.' . "\n";
        echo 'Host: ' . htmlspecialchars($host) . "\n";
        echo 'DB: ' . htmlspecialchars($db) . "\n";
        echo 'Erro: ' . htmlspecialchars($e->getMessage());
        echo '</pre>';
        exit;
    }
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Função para executar queries seguras
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erro na query: " . $e->getMessage());
        throw $e;
    }
}

// Função para buscar dados de forma segura
function fetchData($pdo, $sql, $params = []) {
    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt->fetchAll();
}

// Função para buscar um único registro
function fetchOne($pdo, $sql, $params = []) {
    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt->fetch();
}
?>
