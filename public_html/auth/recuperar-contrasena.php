<?php
require_once '../../config.php';
require_once 'helpers/enviar-correo-contrasena-perdida.php';

$error = '';
$success = '';

$auth = new \Delight\Auth\Auth($pdo);

if (isset($_POST['recover'])) {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Dirección de correo electrónico inválida';
    } else {
        try {
            $auth->forgotPassword($email, function ($selector, $token) use ($email) {
                enviarCorreoRecuperacion($email, $selector, $token);
            });
            
            $success = 'Se ha enviado un enlace para restablecer la contraseña a su correo electrónico';
        }
        catch (\Delight\Auth\InvalidEmailException $e) {
            $error = 'Dirección de correo electrónico inválida';
        }
        catch (\Delight\Auth\EmailNotVerifiedException $e) {
            $error = 'El correo electrónico no está verificado';
        }
        catch (\Delight\Auth\ResetDisabledException $e) {
            $error = 'El restablecimiento de contraseña está deshabilitado';
        }
        catch (\Delight\Auth\TooManyRequestsException $e) {
            $error = 'Demasiadas solicitudes. Por favor, inténtelo más tarde';
        }
        catch (Exception $e) {
            $error = 'Ocurrió un error al procesar su solicitud. Por favor, inténtelo de nuevo';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline';">
    <title>Recuperar Contraseña</title>
    <link rel="stylesheet" href="../../assets/css/marca.css">
    <link rel="stylesheet" href="css/recuperar-contrasena.css">
</head>
<body>
    <div class="contenedor">
        <h1 class="titulo">Recuperar Contraseña</h1>
        <p class="subtitulo">Ingresa tu correo electrónico para recibir un enlace de recuperación</p>
        
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
            <div class="grupo-formulario">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Correo electrónico" required>
            </div>
            
            <button type="submit" name="recover" class="boton">Enviar enlace de recuperación</button>
            
            <div class="texto-centrado">
                <a href="/auth/login" class="enlace">Volver a inicio de sesión</a>
            </div>
        </form>
    </div>
</body>
</html>