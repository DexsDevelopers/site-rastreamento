<?php
/**
 * Conexão segura com o banco de dados
 * Utiliza prepared statements para prevenir SQL injection
 */

// Configurações do banco (fixas como estava antes)
$host = 'localhost';
$db = 'u853242961_rastreio';
$user = 'u853242961_johan71';
$pass = 'Lucastav8012@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
}
catch (\PDOException $e) {
    error_log("Erro de conexão com o banco: " . $e->getMessage());
    // Sempre lançar exceção - deixar o código que usa tratar
    throw new \PDOException("Erro de conexão com o banco de dados: " . $e->getMessage(), (int)$e->getCode());
}

// Função para executar queries seguras
function executeQuery($pdo, $sql, $params = [])
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    catch (PDOException $e) {
        error_log("Erro na query: " . $e->getMessage());
        throw $e;
    }
}

// Função para buscar dados de forma segura
function fetchData($pdo, $sql, $params = [])
{
    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt->fetchAll();
}

// Função para buscar um único registro
function fetchOne($pdo, $sql, $params = [])
{
    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt->fetch();
}