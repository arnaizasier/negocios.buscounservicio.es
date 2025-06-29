<?php
session_start();
require_once '../../config.php';

if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if (isset($_GET['selector']) && isset($_GET['token'])) {
    $selector = filter_var($_GET['selector'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $token = filter_var($_GET['token'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $auth = new \Delight\Auth\Auth($pdo);

    try {
        $auth->canResetPasswordOrThrow($selector, $token);

        if (isset($_POST['reset'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $error = 'Solicitud no válida.';
            } else {
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                    $error = 'La contraseña debe tener al menos 8 caracteres, una mayúscula y un número.';
                } elseif ($newPassword === $confirmPassword) {
                    $auth->resetPassword($selector, $token, $newPassword);
                    
                    session_unset();
                    session_destroy();
                    
                    header("Location: /auth/login.php?password_reset=success");
                    exit();
                } else {
                    $error = 'Las contraseñas no coinciden.';
                }
            }
        }
    } catch (\Delight\Auth\InvalidSelectorTokenPairException $e) {
        error_log("Intento fallido de restablecimiento: selector=$selector, token=$token, IP=" . $_SERVER['REMOTE_ADDR']);
        $error = 'No se pudo procesar la solicitud. El enlace no es válido o ha expirado.';
        header("refresh:5;url=/auth/recuperar-contrasena.php");
    } catch (\Delight\Auth\TokenExpiredException $e) {
        $error = 'El enlace de restablecimiento ha expirado. Por favor, solicite uno nuevo.';
        header("refresh:5;url=/auth/recuperar-contrasena.php");
    } catch (\Delight\Auth\ResetDisabledException $e) {
        $error = 'El restablecimiento de contraseña está deshabilitado.';
    } catch (\Delight\Auth\TooManyRequestsException $e) {
        $error = 'Demasiadas solicitudes. Por favor, inténtelo más tarde.';
    }
} else {
    $error = 'No se pudo procesar la solicitud.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <link rel="stylesheet" href="../../assets/css/marca.css">
    <link rel="stylesheet" href="css/recuperar-contrasena.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous">
</head>
<body>
    <div class="contenedor">
        <h1 class="titulo">Restablecer Contraseña</h1>
        
        <?php if ($error): ?>
            <div class="alerta alerta-error">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alerta alerta-exito">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <form class="formulario" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="grupo-formulario">
                <i class="fas fa-lock"></i>
                <input type="password" name="new_password" placeholder="Nueva contraseña" required>
            </div>
            
            <div class="grupo-formulario">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm_password" placeholder="Confirmar nueva contraseña" required>
            </div>
            
            <button type="submit" name="reset" class="boton">Restablecer Contraseña</button>
        </form>
    </div>
</body>
</html>