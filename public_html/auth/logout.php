<?php
require_once '../../config.php';

// Iniciar sesión con configuraciones seguras
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); 
ini_set('session.use_only_cookies', 1);
session_start();

// Protección contra CSRF
$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['csrf_token'])) {
    if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        // Si no hay token o no coincide, mostrar página de confirmación
        if (!isset($_GET['confirm'])) {
            header("X-XSS-Protection: 1; mode=block");
            header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'");
            header("X-Content-Type-Options: nosniff");
            header("Referrer-Policy: strict-origin-when-cross-origin");
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Cerrar sesión</title>
                <meta name="robots" content="noindex, nofollow">
                <link rel="stylesheet" href="../assets/css/marca.css">   
                <link rel="stylesheet" href="css/logout.css">
            </head>
            <body>
                <div class="contenedor">
                    <h1>¿Desea cerrar la sesión?</h1>
                    <p>Haga clic en el botón para confirmar.</p>
                    <a href="logout.php?confirm=1&token=<?php echo urlencode($_SESSION['csrf_token']); ?>" class="boton">Cerrar sesión</a>
                    <a href="/" class="enlace">Cancelar</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
}

$auth = new \Delight\Auth\Auth($pdo);
$auth->logOut();

header("Location: /");
exit;