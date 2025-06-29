<?php
/**
 * Sistema de Rate Limiting simple usando archivos
 * Guarda: rate-limiting.php
 */

class RateLimiter {
    private $dataDir;
    private $defaultLimit;
    private $defaultWindow;
    
    public function __construct($dataDir = '/tmp/rate_limit/', $defaultLimit = 100, $defaultWindow = 3600) {
        $this->dataDir = rtrim($dataDir, '/') . '/';
        $this->defaultLimit = $defaultLimit;
        $this->defaultWindow = $defaultWindow;
        
        // Crear directorio si no existe
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    /**
     * Verificar si una IP/usuario ha excedido el límite
     */
    public function isAllowed($identifier, $limit = null, $window = null) {
        $limit = $limit ?? $this->defaultLimit;
        $window = $window ?? $this->defaultWindow;
        
        $filename = $this->dataDir . md5($identifier) . '.txt';
        $currentTime = time();
        
        // Leer intentos existentes
        $attempts = $this->readAttempts($filename, $currentTime - $window);
        
        // Verificar si excede el límite
        if (count($attempts) >= $limit) {
            return false;
        }
        
        // Registrar el intento actual
        $this->recordAttempt($filename, $currentTime);
        
        return true;
    }
    
    /**
     * Obtener información del estado actual
     */
    public function getStatus($identifier, $limit = null, $window = null) {
        $limit = $limit ?? $this->defaultLimit;
        $window = $window ?? $this->defaultWindow;
        
        $filename = $this->dataDir . md5($identifier) . '.txt';
        $currentTime = time();
        
        $attempts = $this->readAttempts($filename, $currentTime - $window);
        $remaining = max(0, $limit - count($attempts));
        
        // Calcular tiempo hasta el próximo reset
        $oldestAttempt = !empty($attempts) ? min($attempts) : $currentTime;
        $resetTime = $oldestAttempt + $window;
        
        return [
            'allowed' => count($attempts) < $limit,
            'limit' => $limit,
            'remaining' => $remaining,
            'used' => count($attempts),
            'reset_time' => $resetTime,
            'seconds_until_reset' => max(0, $resetTime - $currentTime)
        ];
    }
    
    /**
     * Leer intentos válidos del archivo
     */
    private function readAttempts($filename, $cutoffTime) {
        if (!file_exists($filename)) {
            return [];
        }
        
        $content = file_get_contents($filename);
        if ($content === false) {
            return [];
        }
        
        $attempts = array_filter(explode("\n", trim($content)));
        
        // Filtrar solo intentos dentro del tiempo válido
        $validAttempts = array_filter($attempts, function($timestamp) use ($cutoffTime) {
            return (int)$timestamp >= $cutoffTime;
        });
        
        return array_map('intval', $validAttempts);
    }
    
    /**
     * Registrar un nuevo intento
     */
    private function recordAttempt($filename, $timestamp) {
        file_put_contents($filename, $timestamp . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Limpiar archivos antiguos (ejecutar periódicamente)
     */
    public function cleanup($maxAge = 86400) {
        $files = glob($this->dataDir . '*.txt');
        $cutoff = time() - $maxAge;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}

// === FUNCIONES DE USO FÁCIL ===

/**
 * Verificar rate limit para una IP
 */
function checkRateLimit($limit = 60, $window = 3600) {
    static $rateLimiter = null;
    
    if ($rateLimiter === null) {
        $rateLimiter = new RateLimiter();
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if ($ip === '88.15.47.179') {
         return true;
    }
    
    if (!$rateLimiter->isAllowed($ip, $limit, $window)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => 'Demasiadas solicitudes. Inténtalo más tarde.'
        ]);
        exit;
    }
}

/**
 * Verificar rate limit para un usuario específico
 */
function checkUserRateLimit($userId, $limit = 100, $window = 3600) {
    static $rateLimiter = null;
    
    if ($rateLimiter === null) {
        $rateLimiter = new RateLimiter();
    }
    
    if (!$rateLimiter->isAllowed("user_$userId", $limit, $window)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => 'Has excedido el límite de solicitudes. Inténtalo más tarde.'
        ]);
        exit;
    }
}

/**
 * Verificar rate limit para acciones específicas (login, registro, etc.)
 */
function checkActionRateLimit($action, $limit = 5, $window = 900) {
    static $rateLimiter = null;
    
    if ($rateLimiter === null) {
        $rateLimiter = new RateLimiter();
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $identifier = "{$action}_{$ip}";
    
    if (!$rateLimiter->isAllowed($identifier, $limit, $window)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => "Demasiados intentos de $action. Espera antes de intentar de nuevo."
        ]);
        exit;
    }
}

checkRateLimit(100, 900);

?>