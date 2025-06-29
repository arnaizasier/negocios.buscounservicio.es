<?php
// Verificar que no se hayan enviado headers previamente
if (!headers_sent()) {
    
    // === HEADERS DE SEGURIDAD BÁSICOS ===
    
    // Evitar que la página sea mostrada dentro de un iframe (clickjacking)
    header('X-Frame-Options: SAMEORIGIN');
    
    // Evitar ataques XSS en navegadores antiguos
    header('X-XSS-Protection: 1; mode=block');
    
    // Evitar que el navegador detecte el tipo de contenido incorrecto
    header('X-Content-Type-Options: nosniff');
    
    // === HEADERS HTTPS (solo si tienes SSL) ===
    
    // Verificar si la conexión es HTTPS antes de enviar HSTS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        // Forzar el uso de HTTPS (sin preload para evitar problemas)
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // === POLÍTICA DE REFERRER ===
    
    // Controlar qué información de referrer se envía
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // === CONTENT SECURITY POLICY (CSP) BÁSICA ===
    
    // CSP permisiva para evitar problemas con la mayoría de aplicaciones
    $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
           "img-src 'self' data: https: http:; " .
           "font-src 'self' https: data:; " .
           "style-src 'self' 'unsafe-inline' https:; " .
           "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:";
    
    header("Content-Security-Policy: " . $csp);
    
    // === ENCODING ===
    
    // Establecer charset por defecto
    header('Content-Type: text/html; charset=UTF-8');
}
?>