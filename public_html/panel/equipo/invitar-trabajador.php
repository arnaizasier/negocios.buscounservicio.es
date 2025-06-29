<?php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

session_start();

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";
require_once __DIR__ . "/helpers/email-functions.php";

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

if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
    (time() - $_SESSION['csrf_token_time']) > 3600) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

if (!isset($_SESSION['user_ip'])) {
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
} else {
    if ($_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        session_destroy();
        header('Location: /login?error=session_invalid');
        exit;
    }
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $input = preg_replace('/[<>"\']/', '', $input);
    
    return $input;
}

function validateNegocioId($negocio_id, $user_id, $pdo2) {
    if (!is_numeric($negocio_id) || $negocio_id <= 0 || $negocio_id > PHP_INT_MAX) {
        return false;
    }
    
    if ($negocio_id > 999999999) {
        return false;
    }
    
    try {
        $stmt = $pdo2->prepare("SELECT COUNT(*) FROM negocios WHERE negocio_id = ? AND usuario_id = ?");
        $stmt->execute([$negocio_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error validando negocio_id: " . $e->getMessage() . " - User ID: " . $user_id . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }
}

function validateTrabajadorId($trabajador_id, $user_id, $pdo2) {
    if (!is_numeric($trabajador_id) || $trabajador_id <= 0 || $trabajador_id > PHP_INT_MAX) {
        return false;
    }
    
    try {
        $stmt = $pdo2->prepare("SELECT COUNT(*) FROM trabajadores WHERE id = ? AND admin_id = ?");
        $stmt->execute([$trabajador_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error validando trabajador_id: " . $e->getMessage() . " - User ID: " . $user_id . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }
}



$error_message = '';
$success_message = '';

if (!$user_id || !is_numeric($user_id)) {
    header('Location: /login');
    exit;
}

$trabajador_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, 
    array("options" => array("min_range" => 1, "max_range" => 999999999))
);

if (!$trabajador_id || !validateTrabajadorId($trabajador_id, $user_id, $pdo2)) {
    header('Location: index?error=invalid_worker');
    exit;
}

try {
    $stmt = $pdo2->prepare("SELECT nombre, apellido, negocio_id FROM trabajadores WHERE id = ? AND admin_id = ?");
    $stmt->execute([$trabajador_id, $user_id]);
    $trabajador = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al obtener trabajador - User ID: ' . $user_id . ' - Error: ' . $e->getMessage() . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: index?error=database_error');
    exit;
}

if (!$trabajador) {
    header('Location: index?error=worker_not_found');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Token de seguridad inválido.';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        
        if (!$email) {
            $error_message = 'El correo electrónico no es válido.';
        } else {
            $email = strtolower(trim($email));
            
            if (strlen($email) > 249) {
                $error_message = 'El correo electrónico es demasiado largo.';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $existingUser = $stmt->fetch();
                    
                    if ($existingUser) {
                        $error_message = 'Ya existe una cuenta con este correo electrónico.';
                    } else {
                        $temporaryPassword = bin2hex(random_bytes(12));
                        
                        try {
                            $admin = $auth->admin();
                            $newUserId = $admin->createUser($email, $temporaryPassword, null);
                            
                            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, role = ?, registration_ip = ? WHERE id = ?");
                            $stmt->execute([
                                sanitizeInput($trabajador['nombre']),
                                sanitizeInput($trabajador['apellido']),
                                'trabajador',
                                $_SERVER['REMOTE_ADDR'] ?? null,
                                $newUserId
                            ]);
                            
                            $stmtUpdateTrabajador = $pdo2->prepare("UPDATE trabajadores SET invitacion_enviada = 1, cuenta_id = ? WHERE id = ? AND admin_id = ?");
                            $stmtUpdateTrabajador->execute([$newUserId, $trabajador_id, $user_id]);
                            
                            $auth->forgotPassword($email, function($selector, $token) use ($trabajador, $email) {
                                $resetLink = "https://negocios.buscounservicio.es/auth/cambiar-contrasena.php?selector=" . urlencode($selector) . "&token=" . urlencode($token);
                                
                                $correoHTML = generarCorreoHTML(
                                    sanitizeInput($trabajador['nombre']),
                                    sanitizeInput($trabajador['apellido']),
                                    $resetLink
                                );
                                
                                enviarCorreoBrevo($email, 'Invitación al equipo - Establece tu contraseña', $correoHTML);
                            });
                            
                            header('Location: index?success=invitation_sent');
                            exit;
                            
                        } catch (Exception $e) {
                            error_log('Error creando usuario trabajador: ' . $e->getMessage() . ' - User ID: ' . $user_id . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                            $error_message = 'Error al crear la cuenta del trabajador.';
                        }
                    }
                } catch (PDOException $e) {
                    error_log('Error verificando email existente: ' . $e->getMessage() . ' - User ID: ' . $user_id . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                    $error_message = 'Error al verificar el correo electrónico.';
                }
            }
        }
    }
}

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; img-src * data: blob:; font-src 'self';");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitar Trabajador</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/invitar-trabajador.css">
</head>
<body>
    <div class="container45">
        <?php include '../../assets/includes/sidebar.php'; ?>
        
        <div class="content45" id="content45">
            <div class="main-container">
                <a href="index" class="back-link">← Volver al equipo</a>
                
                <div class="invitation-form">
                    <h1 style="text-align: center; color: #333; margin-bottom: 30px;">Invitar Trabajador</h1>
                    
                    <div class="worker-info">
                        <strong>Trabajador:</strong> <?php echo sanitizeInput($trabajador['nombre'] . ' ' . $trabajador['apellido']); ?>
                    </div>
                    
                    <?php if ($error_message): ?>
                        <div class="error-message">
                            <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-group">
                            <label for="email">Correo Electrónico del Trabajador *</label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-control" 
                                   required 
                                   maxlength="249"
                                   placeholder="trabajador@ejemplo.com"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            <small style="color: #666; font-size: 14px;">Se enviará una invitación a este correo para que el trabajador pueda crear su contraseña. Tambien se enviaran correos para las reservas que reciba el trabajador</small>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">
                                Enviar Invitación
                            </button>
                            <a href="index" class="btn btn-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/sidebar.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
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
        
        let sessionTimeout;
        function resetSessionTimeout() {
            clearTimeout(sessionTimeout);
            sessionTimeout = setTimeout(() => {
                alert('Tu sesión ha expirado por inactividad. Serás redirigido al login.');
                window.location.href = '/login?timeout=1';
            }, 30 * 60 * 1000);
        }
        
        ['click', 'keypress', 'scroll', 'mousemove'].forEach(event => {
            document.addEventListener(event, resetSessionTimeout, true);
        });
        
        resetSessionTimeout();
    </script>
</body>
</html> 