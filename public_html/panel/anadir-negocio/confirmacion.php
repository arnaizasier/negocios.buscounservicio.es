<?php
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(403);
        exit('Token CSRF inválido');
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . "/../../../config.php";
require_once 'conexion.php';
require_once 'helpers/enviar-correo-negocio-publicado.php';

$auth = new \Delight\Auth\Auth($pdo);
$usuario_id = verificarUsuarioAutenticado($auth);

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    header('Location: index.php');
    exit();
}

$negocio_id = intval($_GET['id']);

if ($negocio_id > PHP_INT_MAX || $negocio_id < 1) {
    header('Location: index.php');
    exit();
}

try {
    $stmt = $pdoNegocios->prepare("SELECT * FROM negocios WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id LIMIT 1");
    $stmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $negocio = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    header('Location: error.php');
    exit();
}

if (!$negocio || empty($negocio)) {
    header('Location: confirmacion.php?id=');
    exit();
}

if (!isset($negocio['email_bienvenida']) || $negocio['email_bienvenida'] === 'no') {
    header('Location: /panel/mis-ubicaciones');
    exit();
}

try {
    $email = $auth->getEmail();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
} catch (\Delight\Auth\NotLoggedInException $e) {
    header('Location: login.php');
    exit();
} catch (Exception $e) {
    error_log('Email validation error: ' . $e->getMessage());
    header('Location: login.php');
    exit();
}

$correo_enviado = false;
if ($negocio['email_bienvenida'] !== 'no' && isset($email) && !empty($email)) {
    try {
        $correo_enviado = enviarCorreoBienvenida($negocio, $email, $pdoNegocios);
    } catch (Exception $e) {
        error_log('Email sending error: ' . $e->getMessage());
    }
}

function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    if ($data === null) {
        return '';
    }
    return htmlspecialchars((string)$data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function validateUrl($url) {
    return preg_match('/^[a-zA-Z0-9_-]+$/', $url);
}

$safe_negocio = sanitizeOutput($negocio);
if (!validateUrl($negocio['url'])) {
    $safe_negocio['url'] = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Negocio añadido - Confirmación</title>
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; script-src 'self'; img-src 'self' data:;">
    <link rel="stylesheet" href="css/confirmacion.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="confirmation-container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="confirmation-title">¡Tu negocio ha sido añadido correctamente!</h1>
            
            <p class="confirmation-message">
                Gracias por registrar tu negocio <strong><?php echo $safe_negocio['nombre']; ?></strong> en nuestra plataforma.
            </p>
            
            <?php if (!empty($safe_negocio['url'])): ?>
            <div class="url-preview">
                <p>Tu perfil está disponible en:</p>
                <a href="https://buscounservicio.es/negocio/<?php echo $safe_negocio['url']; ?>" target="_blank" rel="noopener noreferrer">
                    https://buscounservicio.es/negocio/<?php echo $safe_negocio['url']; ?>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="button-group">
                <?php if (!empty($safe_negocio['url'])): ?>
                <a href="https://buscounservicio.es/negocio/<?php echo $safe_negocio['url']; ?>" target="_blank" rel="noopener noreferrer" class="button button-special">
                    <i></i> Ver mi Perfil
                </a>
                <?php endif; ?>
                
                <a href="../../panel/mis-ubicaciones" class="button">
                    <i></i> Ir al Panel
                </a>
            </div>
        </div>
    </div>
    
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
</body>
</html>