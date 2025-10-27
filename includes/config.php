<?php
/**
 * Configurações do Sistema Helmer Logistics
 * Arquivo centralizado de configurações
 */

// Configurações de segurança
define('SECURITY_SALT', 'helmer_2025_secure_salt_xyz789');
define('SESSION_TIMEOUT', 3600); // 1 hora
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'u853242961_rastreio');
define('DB_USER', 'u853242961_johan71');
define('DB_PASS', 'Lucastav8012@');
define('DB_CHARSET', 'utf8mb4');

// Configurações de cache
define('CACHE_ENABLED', true);
define('CACHE_TIME', 600); // 10 minutos
define('CACHE_DIR', __DIR__ . '/../cache/');

// Configurações de automação
define('AUTOMATION_ENABLED', true);
define('AUTOMATION_INTERVAL', 30); // minutos
define('AUTOMATION_LOG_FILE', __DIR__ . '/../logs/automation.log');

// Configurações de notificação
define('NOTIFICATION_EMAIL', 'admin@helmer.com');
define('NOTIFICATION_ENABLED', true);
define('NOTIFICATION_SMS_ENABLED', false);

// Configurações de upload
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Configurações de rate limiting
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100); // por hora
define('RATE_LIMIT_WINDOW', 3600); // 1 hora

// Configurações de logging
define('LOG_ENABLED', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', __DIR__ . '/../logs/system.log');

// Configurações de API
define('API_ENABLED', true);
define('API_RATE_LIMIT', 1000); // requests por hora
define('API_KEY_LENGTH', 32);

// Configurações de backup
define('BACKUP_ENABLED', true);
define('BACKUP_INTERVAL', 86400); // 24 horas
define('BACKUP_RETENTION', 7); // dias

// Configurações de monitoramento
define('MONITORING_ENABLED', true);
define('MONITORING_INTERVAL', 300); // 5 minutos
define('ALERT_EMAIL', 'alerts@helmer.com');

// Configurações de desenvolvimento
define('DEBUG_MODE', false);
define('SHOW_ERRORS', false);
define('LOG_QUERIES', false);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Headers de segurança
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' cdnjs.cloudflare.com fonts.googleapis.com; font-src \'self\' fonts.gstatic.com; img-src \'self\' data:;');
}

// Função para obter configuração
function getConfig($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

// Função para verificar se está em modo debug
function isDebugMode() {
    return getConfig('DEBUG_MODE', false);
}

// Função para logging
function writeLog($message, $level = 'INFO') {
    if (!getConfig('LOG_ENABLED', true)) {
        return;
    }
    
    $logFile = getConfig('LOG_FILE');
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    if ($logFile && is_writable(dirname($logFile))) {
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Função para sanitizar entrada
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Função para validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para gerar token seguro
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Função para hash seguro
function secureHash($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

// Função para verificar hash
function verifyHash($password, $hash) {
    return password_verify($password, $hash);
}

// Inicializar sessão segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Criar diretórios necessários
$directories = [
    getConfig('CACHE_DIR'),
    getConfig('AUTOMATION_LOG_FILE') ? dirname(getConfig('AUTOMATION_LOG_FILE')) : null,
    getConfig('LOG_FILE') ? dirname(getConfig('LOG_FILE')) : null
];

foreach ($directories as $dir) {
    if ($dir && !is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>

