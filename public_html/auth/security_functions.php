<?php

function setupSecurityHeaders() {
    header("X-XSS-Protection: 1; mode=block");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("X-Frame-Options: DENY");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

function setupSecureSession() {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    if (empty($_SESSION['form_token'])) {
        $_SESSION['form_token'] = bin2hex(random_bytes(16));
    }
}

function getRealIpAddr() {
    $headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR', 
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            if ($header === 'HTTP_X_FORWARDED_FOR') {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return filter_var($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', FILTER_VALIDATE_IP);
}

function checkRateLimit($pdo, $ip, $email = null, $time_window = 3600, $max_attempts = 5) {
    $current_time = time();
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM failed_attempts WHERE ip = ? AND attempt_time > ?");
        $stmt->execute([$ip, $current_time - $time_window]);
        $ip_attempts = $stmt->fetchColumn();
        
        if ($ip_attempts >= $max_attempts) {
            return false;
        }
        
        if ($email) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM failed_attempts WHERE email = ? AND attempt_time > ?");
            $stmt->execute([$email, $current_time - $time_window]);
            $email_attempts = $stmt->fetchColumn();
            
            if ($email_attempts >= $max_attempts) {
                return false;
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error checking rate limit: " . $e->getMessage());
        return true;
    }
}

function logFailedAttempt($pdo, $ip, $email = null, $reason = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO failed_attempts (ip, email, attempt_time, reason, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ip, $email, time(), $reason, $_SERVER['HTTP_USER_AGENT'] ?? '']);
        
        $stmt = $pdo->prepare("DELETE FROM failed_attempts WHERE attempt_time < ?");
        $stmt->execute([time() - 86400]);
    } catch (Exception $e) {
        error_log("Error logging failed attempt: " . $e->getMessage());
    }
}

function isValidUserAgent() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($user_agent) || strlen($user_agent) < 10) {
        return false;
    }
    
    $suspicious_patterns = [
        '/bot/i', '/crawler/i', '/spider/i', '/scraper/i',
        '/wget/i', '/curl/i', '/python/i', '/perl/i',
        '/script/i', '/automated/i', '/headless/i'
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return false;
        }
    }
    
    return true;
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validateFormToken($token) {
    return isset($_SESSION['form_token']) && hash_equals($_SESSION['form_token'], $token);
}

function regenerateTokens() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['form_token'] = bin2hex(random_bytes(16));
}

function checkHoneypot($fields = ['website', 'url', 'homepage', 'link']) {
    foreach ($fields as $field) {
        if (!empty($_POST[$field])) {
            return false;
        }
    }
    return true;
}

function sanitizeInput($input, $type = 'string') {
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return filter_var($input, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
}

function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8 || strlen($password) > 128) {
        $errors[] = 'La contraseña debe tener entre 8 y 128 caracteres';
    }
    
    if (!preg_match('/(?=.*[a-z])/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una letra minúscula';
    }
    
    if (!preg_match('/(?=.*[A-Z])/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una letra mayúscula';
    }
    
    if (!preg_match('/(?=.*\d)/', $password)) {
        $errors[] = 'La contraseña debe contener al menos un número';
    }
    
    if (!preg_match('/(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?])/', $password)) {
        $errors[] = 'La contraseña debe contener al menos un carácter especial';
    }
    
    return $errors;
}

function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['Dirección de correo electrónico inválida'];
    }
    
    if (strlen($email) > 254) {
        return ['La dirección de correo es demasiado larga'];
    }
    
    return [];
}

function validateName($name, $field_name = 'nombre') {
    $errors = [];
    
    if (empty($name) || strlen($name) < 2 || strlen($name) > 50) {
        $errors[] = "El {$field_name} debe tener entre 2 y 50 caracteres";
    }
    
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $name)) {
        $errors[] = "El {$field_name} solo puede contener letras y espacios";
    }
    
    return $errors;
}

function validatePhone($phone) {
    if (empty($phone) || !preg_match('/^[0-9+\s()-]{6,20}$/', $phone)) {
        return ['Número de teléfono inválido'];
    }
    
    return [];
}

function blockSuspiciousActivity($pdo, $ip, $reason = 'Suspicious activity detected') {
    logFailedAttempt($pdo, $ip, null, $reason);
    http_response_code(403);
    exit('Acceso denegado');
}

function isValidReferer() {
    if (!isset($_SERVER['HTTP_REFERER'])) {
        return false;
    }
    
    $referer = parse_url($_SERVER['HTTP_REFERER']);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    return $referer['host'] === $host;
}

function preventClickjacking() {
    header("X-Frame-Options: DENY");
    header("Content-Security-Policy: frame-ancestors 'none';");
}

function logSecurityEvent($message, $data = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => getRealIpAddr(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'message' => $message,
        'data' => $data
    ];
    
    error_log("SECURITY: " . json_encode($logEntry));
} 