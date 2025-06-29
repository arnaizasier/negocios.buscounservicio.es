<?php

// Verificar si el usuario está logeado
if (!$auth->isLoggedIn()) {
    // Capturar la URL actual
    $redirect_url = $_SERVER['REQUEST_URI'];
    // Guardar la URL en la sesión
    $_SESSION['redirect_url'] = $redirect_url;
    // Redirigir al login
    header('Location: /auth/login.php');
    exit;
}
?>