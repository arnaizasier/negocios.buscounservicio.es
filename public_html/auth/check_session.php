<?php
// Configuraciones de seguridad para la sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Headers de seguridad
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'none'");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header('X-Frame-Options: DENY');

// Verificar que la solicitud sea AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit('Acceso prohibido');
}

// Verificar token CSRF
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || !isset($_SESSION['csrf_token']) || 
    $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit(json_encode(['error' => 'CSRF verification failed', 'expired' => true]));
}

// Verificar si la sesión ha expirado (12 horas de inactividad)
$session_expired = false;
$timeout = 43200; // 12 horas en segundos

if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] > $timeout)) {
    $session_expired = true;
    
    // Si la sesión ha expirado, destruirla
    if (isset($_SESSION['last_activity'])) {
        session_unset();
        session_destroy();
    }
} else {
    // Actualizar el tiempo de última actividad
    $_SESSION['last_activity'] = time();
}

// Enviar respuesta JSON
header('Content-Type: application/json');
echo json_encode(['expired' => $session_expired]);
exit;