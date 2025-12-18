<?php
/**
 * Log Helper - Sistema de logs para produção (sem debug)
 */

class LogHelper {
    private static $logFile = null;
    private static $logLevel = 'INFO';
    
    public static function init() {
        self::$logFile = getConfig('LOG_FILE', __DIR__ . '/../logs/system.log');
        self::$logLevel = getConfig('LOG_LEVEL', 'INFO');
        
        // Criar diretório se não existir
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Níveis de log: DEBUG, INFO, WARNING, ERROR
     */
    private static function shouldLog($level) {
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        $currentLevel = $levels[self::$logLevel] ?? 1;
        $messageLevel = $levels[$level] ?? 1;
        
        // Em produção, não logar DEBUG
        $isProduction = (getenv('ENVIRONMENT') === 'production' || !getConfig('DEBUG_MODE', false));
        if ($isProduction && $level === 'DEBUG') {
            return false;
        }
        
        return $messageLevel >= $currentLevel;
    }
    
    /**
     * Sanitizar mensagem de log (remover informações sensíveis)
     */
    private static function sanitizeLogMessage($message) {
        // Remover passwords
        $message = preg_replace('/password[=:]\s*\S+/i', 'password=***', $message);
        // Remover tokens
        $message = preg_replace('/token[=:]\s*\S+/i', 'token=***', $message);
        // Remover senhas
        $message = preg_replace('/senha[=:]\s*\S+/i', 'senha=***', $message);
        // Remover API keys
        $message = preg_replace('/api[_-]?key[=:]\s*\S+/i', 'api_key=***', $message);
        // Remover credenciais
        $message = preg_replace('/(user|username|login)[=:]\s*\S+/i', '$1=***', $message);
        
        return $message;
    }
    
    /**
     * Escrever log
     */
    public static function log($message, $level = 'INFO', $context = []) {
        self::init();
        
        if (!self::shouldLog($level)) {
            return;
        }
        
        $sanitized = self::sanitizeLogMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] $sanitized$contextStr" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Métodos de conveniência
     */
    public static function info($message, $context = []) {
        self::log($message, 'INFO', $context);
    }
    
    public static function warning($message, $context = []) {
        self::log($message, 'WARNING', $context);
    }
    
    public static function error($message, $context = []) {
        self::log($message, 'ERROR', $context);
    }
    
    public static function debug($message, $context = []) {
        self::log($message, 'DEBUG', $context);
    }
    
    /**
     * Log de ação do usuário (auditoria)
     */
    public static function audit($action, $userId = null, $details = []) {
        $context = array_merge([
            'action' => $action,
            'user_id' => $userId ?? $_SESSION['user_id'] ?? 'anonymous',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $details);
        
        self::info("AUDIT: $action", $context);
    }
    
    /**
     * Limpar logs antigos
     */
    public static function cleanOldLogs($daysToKeep = 30) {
        $logDir = dirname(self::$logFile);
        $files = glob($logDir . '/*.log');
        $cutoff = time() - ($daysToKeep * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                self::info("Log antigo removido: " . basename($file));
            }
        }
    }
}

// Manter compatibilidade com função antiga (deprecated)
function writeLog($message, $level = 'INFO') {
    LogHelper::log($message, $level);
}
?>

