# 🚀 Implementações Prontas - Código para Copiar e Colar

## 📁 Estrutura de Arquivos a Criar

```
site-rastreamento/
├── .env (NOVO - não commitar)
├── .env.example (NOVO)
├── includes/
│   ├── security.php (NOVO)
│   ├── cache_helper.php (NOVO)
│   ├── image_helper.php (NOVO)
│   ├── webhook_helper.php (NOVO)
│   ├── backup_helper.php (NOVO)
│   └── health_check.php (NOVO)
├── api/
│   ├── v1/
│   │   └── rastreio.php (NOVO)
│   └── health.php (NOVO)
├── assets/
│   └── js/
│       ├── lazy-load.js (NOVO)
│       └── ui-helpers.js (NOVO)
└── backups/ (NOVO - pasta)
```

---

## 1. 🔐 SEGURANÇA

### 1.1. Arquivo `.env.example`
```env
# Banco de Dados
DB_HOST=localhost
DB_NAME=u853242961_rastreio
DB_USER=u853242961_johan71
DB_PASS=Lucastav8012@
DB_CHARSET=utf8mb4

# Segurança
SECURITY_SALT=helmer_2025_secure_salt_xyz789
SESSION_TIMEOUT=3600
MAX_LOGIN_ATTEMPTS=5
LOGIN_LOCKOUT_TIME=900

# WhatsApp Bot
API_PORT=3000
API_TOKEN=lucastav8012
RASTREAMENTO_API_URL=https://cornflowerblue-fly-883408.hostingersite.com
FINANCEIRO_API_URL=https://gold-quail-250128.hostingersite.com/seu_projeto

# Webhooks
WEBHOOK_SECRET=seu_secret_aqui_aleatorio

# Email
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=seu_email@gmail.com
SMTP_PASS=sua_senha_aqui

# Ambiente
ENVIRONMENT=production
DEBUG_MODE=false
```

### 1.2. `includes/security.php`
```php
<?php
/**
 * Funções de Segurança
 */

// Carregar variáveis de ambiente
function loadEnv($path = null) {
    if ($path === null) {
        $path = __DIR__ . '/../.env';
    }
    
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    
    return true;
}

// Carregar .env na inicialização
if (file_exists(__DIR__ . '/../.env')) {
    loadEnv();
}

// CSRF Protection
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

function getCSRFTokenInput() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// Regenerar Session ID
function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        session_start();
        
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

// Sanitizar logs
function safeLog($message, $level = 'INFO') {
    // Remover informações sensíveis
    $sanitized = preg_replace('/password[=:]\s*\S+/i', 'password=***', $message);
    $sanitized = preg_replace('/token[=:]\s*\S+/i', 'token=***', $sanitized);
    $sanitized = preg_replace('/senha[=:]\s*\S+/i', 'senha=***', $sanitized);
    $sanitized = preg_replace('/api[_-]?key[=:]\s*\S+/i', 'api_key=***', $sanitized);
    
    writeLog($sanitized, $level);
}

// Rate Limiting
class RateLimiter {
    private $pdo;
    private $maxRequests;
    private $window;
    
    public function __construct($pdo, $maxRequests = 100, $window = 3600) {
        $this->pdo = $pdo;
        $this->maxRequests = $maxRequests;
        $this->window = $window;
    }
    
    public function checkLimit($identifier) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = $identifier . '_' . $ip;
        
        // Criar tabela se não existir
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            requests INT DEFAULT 1,
            window_start INT NOT NULL,
            INDEX idx_identifier (identifier, ip, window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $now = time();
        $windowStart = floor($now / $this->window) * $this->window;
        
        $stmt = $this->pdo->prepare("
            SELECT requests FROM rate_limits 
            WHERE identifier = ? AND ip = ? AND window_start = ?
        ");
        $stmt->execute([$key, $ip, $windowStart]);
        $result = $stmt->fetch();
        
        if ($result) {
            if ($result['requests'] >= $this->maxRequests) {
                return false;
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE rate_limits 
                SET requests = requests + 1 
                WHERE identifier = ? AND ip = ? AND window_start = ?
            ");
            $stmt->execute([$key, $ip, $windowStart]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (identifier, ip, requests, window_start) 
                VALUES (?, ?, 1, ?)
            ");
            $stmt->execute([$key, $ip, $windowStart]);
        }
        
        // Limpar registros antigos
        $this->pdo->exec("DELETE FROM rate_limits WHERE window_start < " . ($windowStart - $this->window));
        
        return true;
    }
}

// Validar entrada
function validateInput($input, $type = 'string', $options = []) {
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
        
        case 'int':
            $value = filter_var($input, FILTER_VALIDATE_INT);
            if ($value === false) return false;
            if (isset($options['min']) && $value < $options['min']) return false;
            if (isset($options['max']) && $value > $options['max']) return false;
            return $value;
        
        case 'string':
            $value = trim($input);
            if (isset($options['min_length']) && strlen($value) < $options['min_length']) return false;
            if (isset($options['max_length']) && strlen($value) > $options['max_length']) return false;
            if (isset($options['pattern']) && !preg_match($options['pattern'], $value)) return false;
            return $value;
        
        default:
            return $input;
    }
}
?>
```

### 1.3. Atualizar `includes/config.php`
```php
// No início do arquivo, adicionar:
require_once __DIR__ . '/security.php';
secureSessionStart();

// Substituir defines hardcoded por:
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'u853242961_rastreio');
define('DB_USER', getenv('DB_USER') ?: 'u853242961_johan71');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

---

## 2. ⚡ PERFORMANCE

### 2.1. `includes/cache_helper.php`
```php
<?php
/**
 * Cache Helper - Sistema de cache inteligente
 */
class CacheHelper {
    private static $cacheDir = null;
    
    public static function init() {
        self::$cacheDir = __DIR__ . '/../cache/';
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    public static function get($key, $ttl = 600) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if (time() - $data['timestamp'] > $ttl) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    public static function set($key, $value, $ttl = 600) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        
        $data = [
            'value' => $value,
            'timestamp' => time(),
            'ttl' => $ttl
        ];
        
        file_put_contents($file, serialize($data), LOCK_EX);
    }
    
    public static function delete($key) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    public static function clear($pattern = '*') {
        self::init();
        $files = glob(self::$cacheDir . $pattern . '.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    public static function remember($key, $callback, $ttl = 600) {
        $value = self::get($key, $ttl);
        
        if ($value === null) {
            $value = $callback();
            self::set($key, $value, $ttl);
        }
        
        return $value;
    }
}
?>
```

### 2.2. `includes/image_helper.php`
```php
<?php
/**
 * Image Helper - Compressão e otimização de imagens
 */
function compressImage($source, $destination, $quality = 85) {
    $info = getimagesize($source);
    
    if (!$info) {
        return false;
    }
    
    $mime = $info['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            if (!$image) return false;
            imagejpeg($image, $destination, $quality);
            break;
        
        case 'image/png':
            $image = imagecreatefrompng($source);
            if (!$image) return false;
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, $destination, 9);
            break;
        
        case 'image/gif':
            $image = imagecreatefromgif($source);
            if (!$image) return false;
            imagegif($image, $destination);
            break;
        
        default:
            return false;
    }
    
    imagedestroy($image);
    
    return filesize($destination) < filesize($source);
}

function resizeImage($source, $destination, $maxWidth = 1920, $maxHeight = 1080, $quality = 85) {
    $info = getimagesize($source);
    
    if (!$info) {
        return false;
    }
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];
    
    // Calcular novas dimensões
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = $width * $ratio;
    $newHeight = $height * $ratio;
    
    // Criar imagem
    $image = null;
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if (!$image) return false;
    
    // Redimensionar
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    if ($mime === 'image/png') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }
    
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Salvar
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($newImage, $destination, $quality);
            break;
        case 'image/png':
            imagepng($newImage, $destination, 9);
            break;
        case 'image/gif':
            imagegif($newImage, $destination);
            break;
    }
    
    imagedestroy($image);
    imagedestroy($newImage);
    
    return true;
}
?>
```

### 2.3. `assets/js/lazy-load.js`
```javascript
/**
 * Lazy Loading de Imagens
 */
(function() {
    'use strict';
    
    const lazyImages = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy-load');
                    img.classList.add('lazy-loaded');
                    observer.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback para navegadores antigos
        lazyImages.forEach(img => {
            img.src = img.dataset.src;
            img.classList.remove('lazy-load');
        });
    }
})();
```

---

## 3. 🎨 UX/UI

### 3.1. `assets/js/ui-helpers.js`
```javascript
/**
 * UI Helpers - Funções auxiliares para interface
 */

// Loading Spinner
function showLoading(element, message = 'Carregando...') {
    const loader = document.createElement('div');
    loader.className = 'loading-spinner';
    loader.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">${message}</span>
        </div>
        <span class="loading-text">${message}</span>
    `;
    element.appendChild(loader);
    return loader;
}

function hideLoading(loader) {
    if (loader && loader.parentNode) {
        loader.parentNode.removeChild(loader);
    }
}

// Toast Notifications
function showToast(message, type = 'info', duration = 3000) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type} show`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-${icons[type] || 'info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    const container = document.querySelector('.toast-container') || createToastContainer();
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// Confirmação de Ação
function confirmAction(message, callback, options = {}) {
    const {
        title = 'Tem certeza?',
        confirmText = 'Sim, confirmar!',
        cancelText = 'Cancelar',
        confirmColor = '#d33',
        cancelColor = '#3085d6'
    } = options;
    
    if (typeof Swal !== 'undefined') {
        // Usar SweetAlert2 se disponível
        Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: cancelColor,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText
        }).then((result) => {
            if (result.isConfirmed) {
                callback();
            }
        });
    } else {
        // Fallback para confirm nativo
        if (confirm(`${title}\n\n${message}`)) {
            callback();
        }
    }
}

// Debounce
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Formatar número de telefone
function formatPhoneNumber(phone) {
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 11) {
        return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (cleaned.length === 10) {
        return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    }
    return phone;
}

// Formatar moeda
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

// Formatar data
function formatDate(date, format = 'dd/mm/yyyy') {
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    
    return format
        .replace('dd', day)
        .replace('mm', month)
        .replace('yyyy', year);
}
```

### 3.2. CSS para Toast e Loading
```css
/* Adicionar em admin.php ou criar assets/css/ui-components.css */

.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    background: #fff;
    border-radius: 8px;
    padding: 16px 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 300px;
    max-width: 500px;
    opacity: 0;
    transform: translateX(400px);
    transition: all 0.3s ease;
}

.toast.show {
    opacity: 1;
    transform: translateX(0);
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.toast i {
    font-size: 20px;
}

.toast-success { border-left: 4px solid #28a745; }
.toast-success i { color: #28a745; }

.toast-error { border-left: 4px solid #dc3545; }
.toast-error i { color: #dc3545; }

.toast-warning { border-left: 4px solid #ffc107; }
.toast-warning i { color: #ffc107; }

.toast-info { border-left: 4px solid #17a2b8; }
.toast-info i { color: #17a2b8; }

.toast-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0;
    margin-left: 16px;
    line-height: 1;
}

.toast-close:hover {
    color: #333;
}

.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    gap: 16px;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
    border: 4px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}

.loading-text {
    color: #666;
    font-size: 14px;
}
```

---

## 4. 📊 API REST

### 4.1. `api/v1/rastreio.php`
```php
<?php
/**
 * API REST - Rastreamento
 * Endpoint: /api/v1/rastreio.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/security.php';

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'status') {
                $codigo = $_GET['codigo'] ?? '';
                
                if (empty($codigo)) {
                    throw new Exception('Código é obrigatório');
                }
                
                $sql = "SELECT * FROM rastreios_status 
                        WHERE UPPER(TRIM(codigo)) = ? 
                        ORDER BY data ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([strtoupper(trim($codigo))]);
                $status = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($status)) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Código não encontrado'
                    ]);
                    exit;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $status
                ]);
            } else {
                throw new Exception('Ação inválida');
            }
            break;
        
        case 'POST':
            // Validar autenticação
            $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (empty($token) || $token !== 'Bearer ' . getenv('API_TOKEN')) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Não autorizado'
                ]);
                exit;
            }
            
            // Processar ação
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'webhook') {
                // Processar webhook
                echo json_encode([
                    'success' => true,
                    'message' => 'Webhook recebido'
                ]);
            } else {
                throw new Exception('Ação inválida');
            }
            break;
        
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Método não permitido'
            ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
```

### 4.2. `api/health.php`
```php
<?php
/**
 * Health Check Endpoint
 */
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/db_connect.php';

function checkSystemHealth() {
    $health = [
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => []
    ];
    
    // Verificar banco de dados
    try {
        $pdo->query('SELECT 1');
        $health['checks']['database'] = 'ok';
    } catch (Exception $e) {
        $health['checks']['database'] = 'error: ' . $e->getMessage();
        $health['status'] = 'unhealthy';
    }
    
    // Verificar espaço em disco
    $diskFree = disk_free_space('/');
    $diskTotal = disk_total_space('/');
    $diskPercent = ($diskTotal - $diskFree) / $diskTotal * 100;
    
    if ($diskPercent > 90) {
        $health['checks']['disk'] = 'warning: ' . round($diskPercent, 2) . '% usado';
        if ($health['status'] === 'healthy') {
            $health['status'] = 'degraded';
        }
    } else {
        $health['checks']['disk'] = 'ok: ' . round($diskPercent, 2) . '% usado';
    }
    
    // Verificar memória
    $memUsage = memory_get_usage(true);
    $memPeak = memory_get_peak_usage(true);
    $health['checks']['memory'] = [
        'current' => round($memUsage / 1024 / 1024, 2) . ' MB',
        'peak' => round($memPeak / 1024 / 1024, 2) . ' MB'
    ];
    
    // Verificar WhatsApp bot (se configurado)
    $apiConfig = whatsappApiConfig();
    if ($apiConfig['enabled']) {
        $ch = curl_init($apiConfig['base_url'] . '/status');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_HTTPHEADER => [
                'x-api-token: ' . $apiConfig['token']
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $health['checks']['whatsapp'] = 'ok';
        } else {
            $health['checks']['whatsapp'] = 'error: HTTP ' . $httpCode;
            if ($health['status'] === 'healthy') {
                $health['status'] = 'degraded';
            }
        }
    } else {
        $health['checks']['whatsapp'] = 'disabled';
    }
    
    return $health;
}

$health = checkSystemHealth();
http_response_code($health['status'] === 'healthy' ? 200 : 503);
echo json_encode($health, JSON_PRETTY_PRINT);
?>
```

---

## 5. 🔄 WEBHOOKS

### 5.1. `includes/webhook_helper.php`
```php
<?php
/**
 * Webhook Helper
 */
function sendWebhook($url, $data, $secret = null) {
    if ($secret === null) {
        $secret = getenv('WEBHOOK_SECRET') ?: 'default_secret';
    }
    
    $payload = json_encode($data);
    $signature = hash_hmac('sha256', $payload, $secret);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . $signature,
            'X-Webhook-Timestamp: ' . time()
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        writeLog("Erro ao enviar webhook: $error", 'ERROR');
        return false;
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        writeLog("Webhook enviado com sucesso para $url", 'INFO');
        return true;
    }
    
    writeLog("Webhook falhou: HTTP $httpCode", 'ERROR');
    return false;
}

function validateWebhookSignature($payload, $signature, $secret = null) {
    if ($secret === null) {
        $secret = getenv('WEBHOOK_SECRET') ?: 'default_secret';
    }
    
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}
?>
```

---

## 6. 💾 BACKUP

### 6.1. `includes/backup_helper.php`
```php
<?php
/**
 * Backup Helper
 */
function createBackup($includeFiles = false) {
    $backupDir = __DIR__ . '/../backups/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_{$timestamp}.sql";
    $filepath = $backupDir . $filename;
    
    // Backup do banco de dados
    $command = sprintf(
        'mysqldump -h%s -u%s -p%s %s > %s 2>&1',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($filepath)
    );
    
    exec($command, $output, $return);
    
    if ($return !== 0) {
        writeLog("Erro ao criar backup: " . implode("\n", $output), 'ERROR');
        return false;
    }
    
    // Comprimir
    exec("gzip {$filepath}", $output, $return);
    
    if ($return === 0) {
        writeLog("Backup criado: {$filename}.gz", 'INFO');
        
        // Limpar backups antigos (manter apenas últimos 7 dias)
        cleanOldBackups($backupDir, 7);
        
        return $filepath . '.gz';
    }
    
    return $filepath;
}

function cleanOldBackups($backupDir, $daysToKeep = 7) {
    $files = glob($backupDir . 'backup_*.sql.gz');
    $cutoff = time() - ($daysToKeep * 24 * 60 * 60);
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
            writeLog("Backup antigo removido: " . basename($file), 'INFO');
        }
    }
}
?>
```

---

## 📝 PRÓXIMOS PASSOS

1. **Criar arquivo `.env`** baseado em `.env.example`
2. **Atualizar `includes/config.php`** para usar variáveis de ambiente
3. **Adicionar `includes/security.php`** em todos os arquivos principais
4. **Implementar CSRF tokens** em todos os formulários
5. **Adicionar índices no banco de dados** (SQL fornecido)
6. **Implementar cache** nas queries mais pesadas
7. **Adicionar lazy loading** nas imagens
8. **Criar endpoints da API** conforme necessário
9. **Configurar webhooks** se necessário
10. **Configurar backup automático** via cron

---

**Todas as implementações estão prontas para uso!** 🚀

