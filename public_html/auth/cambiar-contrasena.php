<?php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

session_start();

if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

require_once '../../config.php';

use Delight\Auth\Auth;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['last_request_time'])) {
    $_SESSION['last_request_time'] = time();
    $_SESSION['request_count'] = 1;
} else {
    $time_diff = time() - $_SESSION['last_request_time'];
    if ($time_diff < 1) {
        $_SESSION['request_count']++;
        if ($_SESSION['request_count'] > 10) {
            http_response_code(429);
            die('Demasiadas solicitudes. Inténtalo de nuevo más tarde.');
        }
    } else {
        $_SESSION['request_count'] = 1;
        $_SESSION['last_request_time'] = time();
    }
}

function sanitizeInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $input;
}

$error = '';
$success = '';

if (isset($_GET['selector']) && isset($_GET['token'])) {
    $selector = filter_var($_GET['selector'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $token = filter_var($_GET['token'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (strlen($selector) > 64 || strlen($token) > 64) {
        error_log("Intento de parámetros demasiado largos - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $error = 'Enlace no válido.';
    } else {
        $auth = new Auth($pdo);

        try {
            $auth->canResetPasswordOrThrow($selector, $token);

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                    $error = 'Token de seguridad inválido.';
                } else {
                    $newPassword = $_POST['new_password'] ?? '';
                    $confirmPassword = $_POST['confirm_password'] ?? '';

                    if (strlen($newPassword) < 8) {
                        $error = 'La contraseña debe tener al menos 8 caracteres.';
                    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
                        $error = 'La contraseña debe contener al menos una letra mayúscula.';
                    } elseif (!preg_match('/[0-9]/', $newPassword)) {
                        $error = 'La contraseña debe contener al menos un número.';
                    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
                        $error = 'La contraseña debe contener al menos un carácter especial.';
                    } elseif (strlen($newPassword) > 128) {
                        $error = 'La contraseña es demasiado larga.';
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = 'Las contraseñas no coinciden.';
                    } else {
                        try {
                            $auth->resetPassword($selector, $token, $newPassword);
                            
                            session_unset();
                            session_destroy();
                            
                            $success = 'Tu contraseña ha sido establecida exitosamente. Serás redirigido al login.';
                            header("refresh:3;url=/auth/login.php?password_set=success");
                        } catch (Exception $e) {
                            error_log("Error al establecer contraseña: " . $e->getMessage() . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                            $error = 'Error al establecer la contraseña. Inténtalo de nuevo.';
                        }
                    }
                }
            }
        } catch (\Delight\Auth\InvalidSelectorTokenPairException $e) {
            error_log("Intento fallido de establecimiento de contraseña: selector=$selector, token=$token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $error = 'El enlace no es válido o ha expirado. Contacta con tu empleador para una nueva invitación.';
        } catch (\Delight\Auth\TokenExpiredException $e) {
            $error = 'El enlace de invitación ha expirado. Contacta con tu empleador para una nueva invitación.';
        } catch (\Delight\Auth\ResetDisabledException $e) {
            $error = 'El establecimiento de contraseña está deshabilitado.';
        } catch (\Delight\Auth\TooManyRequestsException $e) {
            $error = 'Demasiadas solicitudes. Por favor, inténtelo más tarde.';
        } catch (Exception $e) {
            error_log("Error general en cambiar-contrasena.php: " . $e->getMessage() . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $error = 'Ha ocurrido un error. Inténtalo de nuevo más tarde.';
        }
    }
} else {
    $error = 'Enlace de invitación inválido.';
}

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; img-src * data: blob:; font-src 'self';");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Establecer Contraseña - Buscounservicio</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/recuperar-contrasena.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body>
    <div class="contenedor">
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="https://buscounservicio.es/imagenes/recursos/logo-png-azul.png" alt="Buscounservicio" style="width: 200px; height: auto;">
        </div>
        
        <h1 class="titulo">Establecer tu Contraseña</h1>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            Bienvenido al equipo. Por favor, establece tu contraseña para acceder a tu cuenta.
        </p>
        
        <?php if ($error): ?>
            <div class="alerta alerta-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alerta alerta-exito">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$success && !$error): ?>
            <form class="formulario" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="grupo-formulario">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           name="new_password" 
                           placeholder="Nueva contraseña" 
                           required 
                           minlength="8"
                           maxlength="128"
                           id="password">
                </div>
                
                <div class="grupo-formulario">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           name="confirm_password" 
                           placeholder="Confirmar nueva contraseña" 
                           required 
                           minlength="8"
                           maxlength="128"
                           id="confirm_password">
                </div>
                
                <div class="password-requirements" style="margin: 15px 0; font-size: 14px; color: #666;">
                    <p><strong>Requisitos de la contraseña:</strong></p>
                    <ul style="margin: 10px 0 0 20px; line-height: 1.6;">
                        <li>Al menos 8 caracteres</li>
                        <li>Una letra mayúscula</li>
                        <li>Un número</li>
                        <li>Un carácter especial (!@#$%^&*etc.)</li>
                    </ul>
                </div>
                
                <button type="submit" class="boton">Establecer Contraseña</button>
            </form>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <p style="color: #666; font-size: 14px;">
                ¿Necesitas ayuda? <a href="https://buscounservicio.es/paginas/contacto/" style="color: #2755d3; text-decoration: none;">Contacta con soporte</a>
            </p>
        </div>
    </div>
    
    <script nonce="<?php echo $nonce; ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePassword() {
                const value = password.value;
                const hasMinLength = value.length >= 8;
                const hasUppercase = /[A-Z]/.test(value);
                const hasNumber = /[0-9]/.test(value);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(value);
                
                password.style.borderColor = (hasMinLength && hasUppercase && hasNumber && hasSpecial) ? '#28a745' : '#dc3545';
            }
            
            function validateConfirmPassword() {
                const match = password.value === confirmPassword.value;
                confirmPassword.style.borderColor = match ? '#28a745' : '#dc3545';
            }
            
            password.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validateConfirmPassword);
            
            if (window.top !== window.self) {
                window.top.location = window.self.location;
            }
            
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && (node.tagName === 'SCRIPT' || node.tagName === 'IFRAME')) {
                                console.warn('Intento de inyección detectado');
                                node.remove();
                            }
                        });
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    </script>
</body>
</html> 