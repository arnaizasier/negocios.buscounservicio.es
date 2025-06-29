<?php
// Incluir la configuración principal
require_once '/home/u898735099/domains/negocios.buscounservicio.es/config.php';
require_once '/home/u898735099/domains/negocios.buscounservicio.es/db-publica.php';


// Verificar si el usuario está autenticado
function verificarUsuarioAutenticado($auth) {
    if (!$auth->isLoggedIn()) {
        // Asegurar que la sesión esté iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Capturar la URL actual
        $redirect_url = $_SERVER['REQUEST_URI'];
        // Guardar la URL en la sesión
        $_SESSION['redirect_url'] = $redirect_url;
        // Redirigir al login
        header('Location: /auth/login.php');
        exit();
    }
    return $auth->getUserId();
}

// Función para sanitizar inputs
function sanitizarInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para convertir nombre a URL amigable
function generarURL($nombre) {
    $url = iconv('UTF-8', 'ASCII//TRANSLIT', $nombre); // mejor manejo de tildes y caracteres especiales
    $url = strtolower($url);
    $url = preg_replace('/[^a-z0-9\s-]/', '', $url);
    $url = preg_replace('/[\s-]+/', '-', $url);
    return trim($url, '-');
}
?>
