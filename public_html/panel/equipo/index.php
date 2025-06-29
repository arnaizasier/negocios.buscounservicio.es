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

function formatearHorario($horario) {
    if (empty($horario) || $horario === 'null') {
        return 'Mismo horario que el centro';
    }
    
    if (strlen($horario) > 2048) {
        return 'Horario personalizado';
    }
    
    $horario_data = json_decode($horario, true);
    
    if (!is_array($horario_data) || json_last_error() !== JSON_ERROR_NONE) {
        return 'Horario personalizado';
    }
    
    $dias_permitidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $dias_con_horario = [];
    
    foreach ($horario_data as $dia => $datos) {
        if (!in_array(strtolower($dia), $dias_permitidos) || !is_array($datos)) {
            continue;
        }
        
        if (!isset($datos['cerrado']) || $datos['cerrado'] !== true) {
            $dias_con_horario[] = ucfirst(sanitizeInput($dia));
        }
    }
    
    if (empty($dias_con_horario)) {
        return 'Sin horario definido';
    }
    
    return 'Trabaja: ' . implode(', ', array_slice($dias_con_horario, 0, 7));
}

if (!$user_id || !is_numeric($user_id)) {
    header('Location: /login');
    exit;
}

try {
    $stmtNegocios = $pdo2->prepare("SELECT negocio_id, nombre FROM negocios WHERE usuario_id = ? ORDER BY nombre ASC LIMIT 50");
    $stmtNegocios->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmtNegocios->execute();
    $negocios_usuario = $stmtNegocios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al obtener negocios - User ID: ' . $user_id . ' - Error: ' . $e->getMessage() . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    http_response_code(500);
    die('Error interno del servidor.');
}

$negocio_id = null;
$trabajadores = array();

if (isset($_GET['negocio_id'])) {
    $negocio_id_input = filter_input(INPUT_GET, 'negocio_id', FILTER_VALIDATE_INT, 
        array("options" => array("min_range" => 1, "max_range" => 999999999))
    );
    
    if ($negocio_id_input && validateNegocioId($negocio_id_input, $user_id, $pdo2)) {
        $negocio_id = $negocio_id_input;
    } else {
        error_log("Intento de acceso no autorizado - User ID: " . $user_id . " - Negocio ID: " . ($negocio_id_input ?? 'invalid') . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $negocio_id = !empty($negocios_usuario) ? $negocios_usuario[0]['negocio_id'] : null;
    }
} elseif (!empty($negocios_usuario)) {
    $negocio_id = $negocios_usuario[0]['negocio_id'];
}

if ($negocio_id) {
    try {
        $stmtTrabajadores = $pdo2->prepare("SELECT id, nombre, apellido, rol, horario, url_foto, invitacion_enviada, color_calendario FROM trabajadores WHERE negocio_id = ? AND admin_id = ? ORDER BY nombre ASC LIMIT 100");
        $stmtTrabajadores->bindParam(1, $negocio_id, PDO::PARAM_INT);
        $stmtTrabajadores->bindParam(2, $user_id, PDO::PARAM_INT);
        $stmtTrabajadores->execute();
        $trabajadores = $stmtTrabajadores->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error al obtener trabajadores - User ID: ' . $user_id . ' - Negocio ID: ' . $negocio_id . ' - Error: ' . $e->getMessage() . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $trabajadores = array();
    }
}

$success_message = '';
$allowed_success_types = ['added', 'updated', 'invitation_sent'];
if (isset($_GET['success']) && in_array($_GET['success'], $allowed_success_types)) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = 'Trabajador agregado exitosamente';
            break;
        case 'updated':
            $success_message = 'Trabajador actualizado exitosamente';
            break;
        case 'invitation_sent':
            $success_message = 'Invitación enviada exitosamente al trabajador';
            break;
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
    <title>Gestión de Equipo</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/marca.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <?php if ($success_message): ?>
        <div class="success-message" id="success-message">
            <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="container45">
        <?php include '../../assets/includes/sidebar.php'; ?>
        
        <div class="content45" id="content45">
            <div class="main-container">
                <div class="team-header">
                    <h1 class="page-title">Gestión de Equipo</h1>
                    <a href="gestion-trabajador" class="btn btn-primary" rel="noopener">
                        Agregar Trabajador
                    </a>
                </div>
                
                <?php if (!empty($negocios_usuario) && count($negocios_usuario) > 1): ?>
                    <div class="form-section">
                        <h2 class="section-title">Seleccionar Negocio</h2>
                        <form method="get" action="index">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="form-group">
                                <select name="negocio_id" id="negocio_id" class="form-control" onchange="this.form.submit()" required>
                                    <?php foreach ($negocios_usuario as $negocio): ?>
                                        <option value="<?php echo (int)$negocio['negocio_id']; ?>" 
                                                <?php if ($negocio_id == $negocio['negocio_id']) echo 'selected'; ?>>
                                            <?php echo sanitizeInput($negocio['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($negocios_usuario)): ?>
                    <div class="alert alert-warning">
                        No tienes negocios registrados. 
                        <a href="../anadir-negocio" style="color: #856404; text-decoration: underline;" rel="noopener">Añadir un negocio primero</a>
                    </div>
                <?php elseif (empty($trabajadores)): ?>
                    <div class="no-workers">
                        <h3>No hay trabajadores registrados</h3>
                        <p>Comienza agregando tu primer trabajador al equipo</p>
                        <a href="gestion-trabajador" class="btn btn-primary" rel="noopener">
                            Agregar Primer Trabajador
                        </a>
                    </div>
                <?php else: ?>
                    <div class="team-cards">
                        <?php foreach ($trabajadores as $trabajador): ?>
                            <?php 
                            $color_borde = !empty($trabajador['color_calendario']) ? $trabajador['color_calendario'] : '#024ddf';
                            ?>
                            <div class="worker-card">
                                <?php if (!empty($trabajador['url_foto'])): ?>
                                    <img src="<?php echo sanitizeInput($trabajador['url_foto']); ?>" 
                                         alt="Foto de <?php echo sanitizeInput($trabajador['nombre']); ?>" 
                                         class="worker-photo"
                                         style="border-color: <?php echo $color_borde; ?>;"
                                         loading="lazy"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="worker-photo-placeholder" 
                                         style="display: none; border-color: <?php echo $color_borde; ?>;">
                                        👤
                                    </div>
                                <?php else: ?>
                                    <div class="worker-photo-placeholder"
                                         style="border-color: <?php echo $color_borde; ?>;">
                                        👤
                                    </div>
                                <?php endif; ?>
                                
                                <div class="worker-name">
                                    <?php echo sanitizeInput($trabajador['nombre'] . ' ' . $trabajador['apellido']); ?>
                                </div>
                                
                                <div class="worker-role">
                                    <?php echo ucfirst(sanitizeInput($trabajador['rol'])); ?>
                                </div>
                                
                                <div class="worker-schedule">
                                    <?php echo formatearHorario($trabajador['horario']); ?>
                                </div>
                                
                                <div class="worker-actions">
                                    <a href="gestion-trabajador?id=<?php echo (int)$trabajador['id']; ?>" 
                                       class="btn btn-primary" rel="noopener">
                                        Editar
                                    </a>
                                    <?php if ($trabajador['invitacion_enviada'] !== 1): ?>
                                        <a href="invitar-trabajador?id=<?php echo (int)$trabajador['id']; ?>" 
                                           class="btn btn-secondary" rel="noopener"
                                           style="margin-left: 10px;">
                                            Invitar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/sidebar.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
        function getCSRFToken() {
            return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        }
        
        const successMessage = document.getElementById('success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.remove();
                }, 300);
            }, 3000);
        }
        
        if (window.location.search.includes('success=')) {
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }
        
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