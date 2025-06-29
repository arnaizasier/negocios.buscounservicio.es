<?php
require_once '../../config.php';
require_once '../../db-publica.php';

$error = '';
$success = '';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 43200);
session_start();

if (!isset($_SESSION['regenerated']) || $_SESSION['regenerated'] < (time() - 300)) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = time();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 43200)) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['last_activity'] = time();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$csp_nonce = base64_encode(random_bytes(16));

$redirect_url = '/';

function validateRedirectUrl($url) {
    if (empty($url)) {
        return true;
    }
    
    if (strpos($url, '://') !== false) {
        $parsed_url = parse_url($url);
        if ($parsed_url === false || empty($parsed_url['host'])) {
            return false;
        }
        
        $allowed_domains = [
            'negocios.buscounservicio.es',
            'www.negocios.buscounservicio.es',
        ];
        
        return in_array($parsed_url['host'], $allowed_domains, true);
    }
    
    return preg_match('/^[\w\-\/\.\?\=\&]+$/', $url) && !preg_match('/\.\.\//', $url);
}

function makeAbsoluteUrl($url) {
    if (empty($url)) {
        return '/index.php';
    }
    
    if (!preg_match('/^(\/[\w\-\/\.\?\=\&]+|\w+:\/\/[\w\-\.]+\/?[\w\-\/\.\?\=\&]*)$/', $url)) {
        return '/index.php';
    }
    
    if (preg_match("~^https?://~i", $url) && validateRedirectUrl($url)) {
        return $url;
    }
    
    $url = preg_replace('/[^\w\-\/.\?\=\&]/', '', $url);
    $url = ltrim($url, './');
    if (substr($url, 0, 1) !== '/') {
        $url = '/' . $url;
    }
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = 'negocios.buscounservicio.es';
    
    return "$protocol://$host$url";
}

if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $potential_redirect = urldecode($_GET['redirect']);
    if (strpos($potential_redirect, 'selector=') === false && strpos($potential_redirect, 'token=') === false && validateRedirectUrl($potential_redirect)) {
        $_SESSION['redirect_url'] = $potential_redirect;
        $redirect_url = $potential_redirect;
    }
} elseif (isset($_SESSION['redirect_url']) && validateRedirectUrl($_SESSION['redirect_url'])) {
    $redirect_url = $_SESSION['redirect_url'];
} elseif (isset($_SERVER['HTTP_REFERER']) && validateRedirectUrl($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'selector=') === false && strpos($_SERVER['HTTP_REFERER'], 'token=') === false) {
    $_SESSION['redirect_url'] = $_SERVER['HTTP_REFERER'];
    $redirect_url = $_SERVER['HTTP_REFERER'];
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['last_login_attempt'])) {
    $_SESSION['last_login_attempt'] = 0;
}

function checkLoginAttempts() {
    $max_attempts = 5;
    $lockout_time = 900;
    
    if ($_SESSION['login_attempts'] >= $max_attempts) {
        $time_passed = time() - $_SESSION['last_login_attempt'];
        if ($time_passed < $lockout_time) {
            return false;
        } else {
            $_SESSION['login_attempts'] = 0;
        }
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Error de verificación de seguridad. Por favor, inténtelo de nuevo.';
    } elseif (!checkLoginAttempts()) {
        $error = 'Demasiados intentos fallidos. Por favor, inténtelo de nuevo más tarde.';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $remember = true;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Formato de correo electrónico inválido';
            $_SESSION['login_attempts']++;
            $_SESSION['last_login_attempt'] = time();
        } else {
            try {
                $auth = new \Delight\Auth\Auth($pdo);
                
                $saved_redirect = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : $redirect_url;
                
                if (isset($_GET['password_reset']) && $_GET['password_reset'] === 'success') {
                    $saved_redirect = '/index.php';
                    $_SESSION['success_message'] = 'Contraseña restablecida con éxito. Ahora puede iniciar sesión con su nueva contraseña.';
                }
                
                $auth->login($email, $password, $remember);
                
                session_regenerate_id(true);
                
                $_SESSION['last_activity'] = time();
                $_SESSION['login_attempts'] = 0;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                $user_id = $auth->getUserId();
                $redirect_by_role = false;
                
                try {
                    $stmt_worker = $pdo2->prepare("SELECT id FROM trabajadores WHERE cuenta_id = ?");
                    $stmt_worker->execute([$user_id]);
                    $is_worker = $stmt_worker->fetch(PDO::FETCH_ASSOC);
                    
                    if ($is_worker) {
                        $final_redirect = makeAbsoluteUrl('/trabajadores/reservas/calendario');
                        $redirect_by_role = true;
                    }
                } catch (PDOException $e) {
                    error_log("Error verificando rol trabajador: " . $e->getMessage());
                }
                
                if (!$redirect_by_role) {
                    try {
                        $stmt_user = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                        $stmt_user->execute([$user_id]);
                        $user_role = $stmt_user->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user_role && $user_role['role'] === 'negocio') {
                            $final_redirect = makeAbsoluteUrl('/panel/mis-ubicaciones');
                            $redirect_by_role = true;
                        }
                    } catch (PDOException $e) {
                        error_log("Error verificando rol negocio: " . $e->getMessage());
                    }
                }
                
                if (!$redirect_by_role) {
                    $final_redirect = makeAbsoluteUrl($saved_redirect);
                }
                
                unset($_SESSION['redirect_url']);
                
                header('Location: ' . $final_redirect);
                exit;
            } catch (\Delight\Auth\InvalidEmailException $e) {
                $error = 'Credenciales incorrectas';
                $_SESSION['login_attempts']++;
                $_SESSION['last_login_attempt'] = time();
            } catch (\Delight\Auth\InvalidPasswordException $e) {
                $error = 'Credenciales incorrectas';
                $_SESSION['login_attempts']++;
                $_SESSION['last_login_attempt'] = time();
            } catch (\Delight\Auth\EmailNotVerifiedException $e) {
                $error = 'Credenciales incorrectas';
                $_SESSION['login_attempts']++;
                $_SESSION['last_login_attempt'] = time();
            } catch (\Delight\Auth\TooManyRequestsException $e) {
                $error = 'Demasiados intentos. Por favor, inténtelo más tarde';
            }
        }
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $csrf_token = $_SESSION['csrf_token'];
    }
}

header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$csp_nonce' https://cdnjs.cloudflare.com; style-src 'self' 'nonce-$csp_nonce' https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com; frame-src 'none'; object-src 'none'");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header('X-Frame-Options: DENY');

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = 'Se ha enviado un enlace para restablecer la contraseña a su correo electrónico';
}

if (isset($_GET['password_reset']) && $_GET['password_reset'] === 'success') {
    $success = 'Contraseña restablecida con éxito. Ahora puede iniciar sesión con su nueva contraseña.';
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="../assets/css/marca.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="contenedor">
        <div class="texto-lateral">
            <h1>Ingresa a tu cuenta</h1>
        </div>

        <div class="formulario-seccion">
            <?php if ($error): ?>
                <div class="alerta alerta-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alerta alerta-exito">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form class="formulario tarjeta-login" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="grupo-formulario">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Correo electrónico" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="grupo-formulario">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Contraseña" required>
                </div>

                <div class="fila-opciones">
                    <a href="/auth/recuperar-contrasena" class="enlace">¿Olvidaste tu contraseña?</a>
                </div>
        
                <button type="submit" name="login" class="boton">Iniciar Sesión</button>
                
                <div class="texto-centrado">
                    ¿No tienes cuenta? <a href="registro" class="enlace">Regístrate aquí</a>
                </div>
            </form>
        </div>
    </div>
    
    <script nonce="<?php echo $csp_nonce; ?>">
    (function() {
        const inactivityTime = 12 * 60 * 60 * 1000;
        let timeout;

        function resetTimer() {
            clearTimeout(timeout);
            timeout = setTimeout(checkSession, inactivityTime);
        }

        function checkSession() {
            fetch('check_session.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': '<?php echo $csrf_token; ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.expired) {
                    window.location.href = 'logout.php';
                } else {
                    resetTimer();
                }
            })
            .catch(() => {
                window.location.href = 'logout.php';
            });
        }

        ['mousemove', 'keypress', 'scroll', 'click', 'touchstart'].forEach(function(event) {
            document.addEventListener(event, resetTimer, false);
        });

        resetTimer();
    })();
    </script>
</body>
</html>