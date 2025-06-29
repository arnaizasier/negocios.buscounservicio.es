<?php
require_once '../../config.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return filter_var(trim($ips[0]), FILTER_VALIDATE_IP);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        return filter_var($_SERVER['HTTP_X_FORWARDED'], FILTER_VALIDATE_IP);
    } elseif (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
        return filter_var($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'], FILTER_VALIDATE_IP);
    } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        return filter_var($_SERVER['HTTP_FORWARDED_FOR'], FILTER_VALIDATE_IP);
    } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
        return filter_var($_SERVER['HTTP_FORWARDED'], FILTER_VALIDATE_IP);
    } else {
        return filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
    }
}

function checkRateLimit($pdo, $ip, $email = null) {
    $time_window = 3600;
    $max_attempts = 5;
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
        '/script/i', '/automated/i'
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return false;
        }
    }
    
    return true;
}

$client_ip = getRealIpAddr();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (empty($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(16));
}
$form_token = $_SESSION['form_token'];

header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-Frame-Options: DENY");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

$auth = new \Delight\Auth\Auth($pdo);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (!isValidUserAgent()) {
        logFailedAttempt($pdo, $client_ip, null, 'Invalid User Agent');
        http_response_code(403);
        exit('Acceso denegado');
    }
    
    if (!checkRateLimit($pdo, $client_ip)) {
        logFailedAttempt($pdo, $client_ip, null, 'Rate limit exceeded');
        $error = 'Demasiados intentos. Por favor, espere antes de intentar nuevamente.';
    } elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        logFailedAttempt($pdo, $client_ip, null, 'CSRF token mismatch');
        $error = 'Error de verificación de seguridad. Por favor, inténtelo de nuevo.';
    } elseif (!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']) {
        logFailedAttempt($pdo, $client_ip, null, 'Form token mismatch');
        $error = 'Error de verificación de formulario. Por favor, inténtelo de nuevo.';
    } elseif (!empty($_POST['website']) || !empty($_POST['url'])) {
        logFailedAttempt($pdo, $client_ip, null, 'Honeypot triggered');
        http_response_code(403);
        exit('Acceso denegado');
    } else {
        $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $apellido = filter_input(INPUT_POST, 'apellido', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = 'negocio';
        $telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        $validationErrors = [];
        
        if (empty($nombre) || strlen($nombre) < 2 || strlen($nombre) > 50) {
            $validationErrors[] = 'El nombre debe tener entre 2 y 50 caracteres';
        }
        
        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $nombre)) {
            $validationErrors[] = 'El nombre solo puede contener letras y espacios';
        }
        
        if (empty($apellido) || strlen($apellido) < 2 || strlen($apellido) > 50) {
            $validationErrors[] = 'El apellido debe tener entre 2 y 50 caracteres';
        }
        
        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $apellido)) {
            $validationErrors[] = 'El apellido solo puede contener letras y espacios';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validationErrors[] = 'Dirección de correo electrónico inválida';
        }
        
        if (strlen($email) > 254) {
            $validationErrors[] = 'La dirección de correo es demasiado larga';
        }
        
        if (empty($telefono) || !preg_match('/^[0-9+\s()-]{6,20}$/', $telefono)) {
            $validationErrors[] = 'Número de teléfono inválido';
        }
        
        if (strlen($password) < 8 || strlen($password) > 128) {
            $validationErrors[] = 'La contraseña debe tener entre 8 y 128 caracteres';
        }
        
        if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?])/', $password)) {
            $validationErrors[] = 'La contraseña debe contener al menos una mayúscula, una minúscula, un número y un carácter especial';
        }
        
        if (!checkRateLimit($pdo, $client_ip, $email)) {
            $validationErrors[] = 'Demasiados intentos con este email. Por favor, espere.';
        }
        
        if (!empty($validationErrors)) {
            logFailedAttempt($pdo, $client_ip, $email, 'Validation failed: ' . $validationErrors[0]);
            $error = $validationErrors[0];
        } else {
            try {
                session_regenerate_id(true);
                
                $nombreCompleto = trim($nombre . ' ' . $apellido);
                $userId = $auth->register($email, $password, $nombreCompleto, function ($selector, $token) {
                });
                
                try {
                    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $status = (int) $stmt->fetchColumn();
                    
                    $status = $status | 1;
                    
                    $stmt = $pdo->prepare("UPDATE users SET status = ?, verified = 1, role = ?, registration_ip = ?, last_login_ip = ? WHERE id = ?");
                    $stmt->execute([$status, $role, $client_ip, $client_ip, $userId]);
                }
                catch (Exception $e) {
                    error_log("Error al verificar usuario automáticamente: " . $e->getMessage());
                }
                
                $stmt = $pdo->prepare("UPDATE users SET phone = ?, first_name = ?, last_name = ? WHERE id = ?");
                $stmt->execute([$telefono, $nombre, $apellido, $userId]);

                require_once 'helpers/enviar-correo-admin.php';
                enviarCorreoNuevoRegistro($email, $nombre, $apellido, $role);
                
                                    try {
                        $auth->login($email, $password);
                        
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $_SESSION['form_token'] = bin2hex(random_bytes(16));
                        
                        header('Location: /panel/mis-direcciones');
                        exit;
                    } catch (Exception $loginError) {
                    error_log("Error al iniciar sesión automáticamente después del registro: " . $loginError->getMessage());
                    $success = 'Registro exitoso. Por favor, inicia sesión para continuar.';
                }
            }
            catch (\Delight\Auth\InvalidEmailException $e) {
                logFailedAttempt($pdo, $client_ip, $email, 'Invalid email');
                $error = 'Dirección de correo electrónico inválida';
            }
            catch (\Delight\Auth\InvalidPasswordException $e) {
                logFailedAttempt($pdo, $client_ip, $email, 'Invalid password');
                $error = 'Contraseña inválida';
            }
            catch (\Delight\Auth\UserAlreadyExistsException $e) {
                logFailedAttempt($pdo, $client_ip, $email, 'User already exists');
                $error = 'El usuario ya existe';
            }
            catch (\Delight\Auth\TooManyRequestsException $e) {
                logFailedAttempt($pdo, $client_ip, $email, 'Too many requests');
                $error = 'Demasiadas solicitudes. Por favor, inténtelo más tarde';
            }
            catch (Exception $e) {
                logFailedAttempt($pdo, $client_ip, $email, 'Registration error: ' . $e->getMessage());
                error_log("Error en registro de usuario: " . $e->getMessage());
                $error = 'Ha ocurrido un error en el registro. Por favor, inténtelo más tarde.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>
    <link rel="stylesheet" href="../assets/css/marca.css">
    <link rel="stylesheet" href="css/registro.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="contenedor">
        <div class="texto-lateral">
            <h1>Únete a nuestra comunidad</h1>
            <p>Crea tu cuenta en segundos y comienza a disfrutar de nuestra plataforma.</p>
        </div>

        <div class="formulario-seccion">
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($form_token, ENT_QUOTES, 'UTF-8'); ?>">
                
                <div style="position: absolute; left: -9999px;">
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                    <input type="url" name="url" tabindex="-1" autocomplete="off">
                </div>
                
                <div class="grupo-formulario">
                    <i class="fas fa-user"></i>
                    <input type="text" name="nombre" placeholder="Nombre" required maxlength="50" 
                           pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+" autocomplete="given-name"
                           value="<?php echo isset($nombre) ? htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                
                <div class="grupo-formulario">
                    <i class="fas fa-user"></i>
                    <input type="text" name="apellido" placeholder="Apellido" required maxlength="50" 
                           pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+" autocomplete="family-name"
                           value="<?php echo isset($apellido) ? htmlspecialchars($apellido, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                
                <div class="grupo-formulario">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Correo electrónico" required maxlength="254" autocomplete="email"
                           value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                
                <div class="grupo-formulario">
                    <i class="fas fa-phone"></i>
                    <input type="tel" name="telefono" placeholder="Teléfono" required pattern="[0-9+\s()-]{6,20}" autocomplete="tel"
                           value="<?php echo isset($telefono) ? htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                
                <div class="grupo-formulario">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Contraseña" required minlength="8" maxlength="128" 
                           id="password" autocomplete="new-password">
                    <button type="button" onclick="togglePasswordVisibility()">Mostrar</button>
                    <small>Mínimo 8 caracteres: mayúscula, minúscula, número y carácter especial</small>
                </div>
                
                <button type="submit" name="register" class="boton">Registrarse</button>
                
                <div class="texto-centrado">
                    ¿Ya tienes cuenta? <a href="login.php" class="enlace">Inicia sesión aquí</a>
                </div>
            </form>
        </div>
    </div>
    <script>
    function togglePasswordVisibility() {
        const passwordField = document.getElementById('password');
        const type = passwordField.type === 'password' ? 'text' : 'password';
        passwordField.type = type;
        event.target.textContent = type === 'password' ? 'Mostrar' : 'Ocultar';
    }
    
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alerta');
        alerts.forEach(function(alert) {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 300);
        });
    }, 5000);
    </script>
</body>
</html>