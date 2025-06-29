<?php
session_start();

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";
require_once __DIR__ . "/../../../db-suscripciones.php";
require_once 'helpers/enviar-correo-cancelacion.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Función para validar token CSRF
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para sanitizar entrada
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Función para validar ID de negocio
function validateNegocioId($negocio_id, $user_id, $pdo2) {
    if (!is_numeric($negocio_id) || $negocio_id <= 0) {
        return false;
    }
    
    try {
        $stmt = $pdo2->prepare("SELECT COUNT(*) FROM negocios WHERE negocio_id = ? AND usuario_id = ?");
        $stmt->execute([$negocio_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error validando negocio_id: " . $e->getMessage());
        return false;
    }
}

try {
    $stmtNegocios = $pdo2->prepare("SELECT * FROM negocios WHERE usuario_id = ? ORDER BY nombre ASC");
    $stmtNegocios->execute([$user_id]);
    $negocios_usuario = $stmtNegocios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al obtener negocios: ' . $e->getMessage());
    die('Error interno del servidor.');
}

// Validar negocio_id con medidas de seguridad
if (isset($_GET['negocio_id'])) {
    $negocio_id_input = filter_input(INPUT_GET, 'negocio_id', FILTER_VALIDATE_INT);
    if ($negocio_id_input && validateNegocioId($negocio_id_input, $user_id, $pdo2)) {
        $negocio_id = $negocio_id_input;
    } else {
        // Si el negocio_id no es válido, usar el primero disponible o null
        $negocio_id = !empty($negocios_usuario) ? $negocios_usuario[0]['negocio_id'] : null;
    }
} elseif (!empty($negocios_usuario)) {
    $negocio_id = $negocios_usuario[0]['negocio_id'];
} else {
    $negocio_id = null;
}

try {
    $stmt = $pdo4->prepare("SELECT * FROM suscripciones WHERE usuario_id = :user_id" . ($negocio_id ? " AND negocio_id = :negocio_id" : ""));
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if ($negocio_id) {
        $stmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
    }
    $stmt->execute();

    $suscripcion = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($suscripcion) {
        // Manejar el caso cuando el plan es null o vacío
        $plan_raw = $suscripcion['plan'] ?? '';
        $plan = !empty($plan_raw) ? sanitizeInput($plan_raw) : "Gratis";
        
        $estado_plan = sanitizeInput($suscripcion['estado_plan'] ?? '');
        $fecha_expiracion = sanitizeInput($suscripcion['fecha_expiracion'] ?? '');
        $destacado = (int)$suscripcion['destacado'];
        $tipo_destacado = sanitizeInput($suscripcion['tipo_destacado'] ?? '');
        $estado_destacado = sanitizeInput($suscripcion['estado_destacado'] ?? '');
        $expiracion_fecha = sanitizeInput($suscripcion['expiracion_fecha'] ?? '');
        $estado_plan_display = ucfirst(strtolower($estado_plan));
        $estado_destacado_display = ucfirst(strtolower($estado_destacado));
    } else {
        $plan = "Gratis";
        $estado_plan = "";
        $fecha_expiracion = "";
        $destacado = 0;
        $tipo_destacado = "";
        $estado_destacado = "Sin destacar";
        $expiracion_fecha = "";
        $estado_plan_display = "";
        $estado_destacado_display = "Sin destacar";
    }

} catch (PDOException $e) {
    error_log("Error de conexión (DB4): " . $e->getMessage());
    die("Error interno del servidor.");
}

try {
    $stmtCorreo = $pdo->prepare("SELECT email FROM users WHERE id = :user_id");
    $stmtCorreo->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmtCorreo->execute();

    $usuario = $stmtCorreo->fetch(PDO::FETCH_ASSOC);

    if ($usuario && !empty($usuario['email'])) {
        $correoUsuario = sanitizeInput($usuario['email']);
    } else {
        error_log("No se encontró el correo del usuario ID: " . $user_id);
        die("Error interno del servidor.");
    }

} catch (PDOException $e) {
    error_log("Error de conexión al obtener el correo: " . $e->getMessage());
    die("Error interno del servidor.");
}

// Procesamiento de formularios con validación CSRF y rate limiting
$mensaje_success = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRF($csrf_token)) {
        die('Token de seguridad inválido.');
    }
    
    // Rate limiting simple basado en sesión
    $current_time = time();
    if (!isset($_SESSION['last_action_time'])) {
        $_SESSION['last_action_time'] = 0;
    }
    
    if (($current_time - $_SESSION['last_action_time']) < 5) {
        die('Demasiadas solicitudes. Espera unos segundos.');
    }
    
    $_SESSION['last_action_time'] = $current_time;
    
    // Validar que el negocio pertenece al usuario
    if ($negocio_id && !validateNegocioId($negocio_id, $user_id, $pdo2)) {
        die('Acceso no autorizado.');
    }
    
    if (isset($_POST['cancelar_plan'])) {
        try {
            $stmtUpdate = $pdo4->prepare("UPDATE suscripciones SET estado_plan = 'cancelado' WHERE usuario_id = :user_id" . ($negocio_id ? " AND negocio_id = :negocio_id" : ""));
            $stmtUpdate->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            if ($negocio_id) {
                $stmtUpdate->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
            }
            $stmtUpdate->execute();
            
            if ($stmtUpdate->rowCount() > 0) {
                enviarCorreoCancelacionPlan($correoUsuario, $user_id);
                $mensaje_success = 'Plan cancelado exitosamente.';
            } else {
                $mensaje_error = 'No se pudo cancelar el plan.';
            }
        } catch (PDOException $e) {
            error_log("Error al actualizar el estado del plan: " . $e->getMessage());
            $mensaje_error = 'Error interno del servidor.';
        }
    }

    if (isset($_POST['cancelar_destacado'])) {
        try {
            $stmtUpdate = $pdo4->prepare("UPDATE suscripciones SET estado_destacado = 'cancelado' WHERE usuario_id = :user_id" . ($negocio_id ? " AND negocio_id = :negocio_id" : ""));
            $stmtUpdate->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            if ($negocio_id) {
                $stmtUpdate->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
            }
            $stmtUpdate->execute();
            
            if ($stmtUpdate->rowCount() > 0) {
                enviarCorreoCancelacionDestacado($correoUsuario, $user_id);
                $mensaje_success = 'Destacado cancelado exitosamente.';
            } else {
                $mensaje_error = 'No se pudo cancelar el destacado.';
            }
        } catch (PDOException $e) {
            error_log("Error al actualizar el estado del destacado: " . $e->getMessage());
            $mensaje_error = 'Error interno del servidor.';
        }
    }
    
    // Recargar datos después de la actualización
    if ($mensaje_success) {
        header("Location: " . $_SERVER['PHP_SELF'] . ($negocio_id ? "?negocio_id=" . $negocio_id : "") . "&success=1");
        exit;
    }
}

// Mostrar mensaje de éxito si viene de redirección
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $mensaje_success = 'Operación realizada exitosamente.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <link href="../../assets/css/marca.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
    </style>
</head>
<body>
    <div class="container45">
        <?php include '../../assets/includes/sidebar.php'; ?>
        <div id="content45" class="content45">
            <div class="header-container">
                <h1 class="page-title">Tu Perfil</h1>
                
                <?php if (!empty($negocios_usuario) && count($negocios_usuario) > 1): ?>
                    <div class="negocio-selector">
                        <form method="get" action="index.php" class="negocio-form">
                            <select name="negocio_id" id="negocio_id" class="negocio-select" onchange="this.form.submit()">
                                <?php foreach ($negocios_usuario as $negocio): ?>
                                    <option value="<?php echo (int)$negocio['negocio_id']; ?>" <?php if ($negocio_id == $negocio['negocio_id']) echo 'selected'; ?>>
                                        <?php echo sanitizeInput($negocio['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($mensaje_success): ?>
                <div class="alert alert-success"><?php echo $mensaje_success; ?></div>
            <?php endif; ?>
            
            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger"><?php echo $mensaje_error; ?></div>
            <?php endif; ?>
            
            <?php if (empty($negocios_usuario)): ?>
                <div class="no-negocio-alert">No tienes negocios registrados.</div>
            <?php endif; ?>
            
            <div class="cards-grid">
                <div class="card">
                    <h2 class="card-title">Tu Plan</h2>
                    <p class="info-item"><i class="fas fa-box icon-blue"></i><strong>Plan: </strong> <?php echo $plan; ?></p>
                    <?php if ($plan === 'Gratis'): ?>
                        <a href="stripe?negocio_id=<?php echo (int)$negocio_id; ?>" class="btn btn-primary">Actualizar a Premium</a>
                    <?php endif; ?>
                    <?php if ($plan !== 'Gratis'): ?>
                        <p class="info-item <?php echo $estado_plan_display === 'Activo' ? 'text-success' : ($estado_plan_display === 'Cancelado' ? 'text-danger' : ''); ?>">
                            <i class="fas fa-check-circle icon-blue"></i><strong>Estado:</strong> <?php echo $estado_plan_display; ?>
                        </p>
                        <p class="info-item"><i class="fas fa-calendar-alt icon-blue"></i><strong>Expiración:</strong> <?php echo $fecha_expiracion; ?></p>
                    <?php endif; ?>
                    <?php if ($estado_plan === 'activo'): ?>
                        <form method="post" id="cancel-plan-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="cancelar_plan" value="1">
                            <button type="submit" class="btn btn-danger">Cancelar Plan</button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <h2 class="card-title">Destacado</h2>
                    <?php if ($destacado === 1): ?>
                        <p class="info-item"><i class="fas fa-box icon-blue"></i><strong>Tipo:</strong> <?php echo $tipo_destacado; ?></p>
                        <p class="info-item <?php echo $estado_destacado_display === 'Activo' ? 'text-success' : ($estado_destacado_display === 'Cancelado' ? 'text-danger' : ''); ?>">
                            <i class="fas fa-check-circle icon-blue"></i><strong>Estado:</strong> <?php echo $estado_destacado_display; ?>
                        </p>
                        <p class="info-item"><i class="fas fa-calendar-alt icon-blue"></i><strong>Expiración:</strong> <?php echo $expiracion_fecha; ?></p>
                    <?php else: ?>
                        <p class="info-item"><i class="fas fa-check-circle icon-blue"></i><strong>Estado:</strong> Sin destacar</p>
                        <a href="/panel/destaca-tu-negocio/?negocio_id=<?php echo (int)$negocio_id; ?>" class="btn btn-primary">Destacar mi negocio</a>
                    <?php endif; ?>
                    <?php if ($destacado === 1 && $estado_destacado === 'activo'): ?>
                        <form method="post" id="cancel-destacado-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="cancelar_destacado" value="1">
                            <button type="submit" class="btn btn-danger">Cancelar Destacado</button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="card card-full">
                    <h2 class="card-title">Enlaces</h2>
                    <?php
                        $negocio_activo = null;
                        foreach ($negocios_usuario as $n) {
                            if ($n['negocio_id'] == $negocio_id) {
                                $negocio_activo = $n;
                                break;
                            }
                        }
                        $perfil_url = $negocio_activo && !empty($negocio_activo['url'])
                            ? "https://buscounservicio.es/negocio/" . sanitizeInput($negocio_activo['url'])
                            : "";
                        $qr_url = $perfil_url ? ("https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($perfil_url)) : "";
                    ?>
                    <div class="enlaces-container">
                        <div class="enlaces-qr">
                            <?php if ($perfil_url): ?>
                                <a href="<?php echo $perfil_url; ?>" class="profile-link" target="_blank" rel="noopener noreferrer">
                                    <?php echo $perfil_url; ?>
                                </a>
                                <div class="qr-wrapper">
                                    <button type="button" id="descargar-qr-btn" data-qr-url="<?php echo htmlspecialchars($qr_url); ?>" class="qr-btn">
                                        <img src="<?php echo htmlspecialchars($qr_url); ?>" alt="QR Negocio" class="qr-img">
                                        <div class="qr-text">Descargar QR</div>
                                    </button>
                                    <button type="button" id="copiar-enlace-btn" data-link="<?php echo htmlspecialchars($perfil_url); ?>" class="btn-link">
                                        <i class="fas fa-link"></i> Copiar enlace
                                    </button>
                                    <span id="copiar-enlace-msg" class="copy-msg hidden">¡Enlace copiado!</span>
                                </div>
                            <?php else: ?>
                                <div class="no-url">No hay URL de perfil para este negocio.</div>
                            <?php endif; ?>
                        </div>
                        <div class="enlaces-social">
                            <?php if ($perfil_url): ?>
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($perfil_url); ?>" target="_blank" rel="noopener noreferrer" class="btn-social btn-facebook">
                                    <i class="fab fa-facebook-f"></i> Compartir en Facebook
                                </a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($perfil_url); ?>" target="_blank" rel="noopener noreferrer" class="btn-social btn-twitter">
                                    <span class="x-icon">
                                        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/>
                                        </svg>
                                    </span> Compartir en X
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Rate limiting del lado del cliente
        let lastActionTime = 0;
        const RATE_LIMIT_MS = 5000; // 5 segundos
        
        function checkRateLimit() {
            const currentTime = Date.now();
            if (currentTime - lastActionTime < RATE_LIMIT_MS) {
                Swal.fire({
                    title: 'Espera un momento',
                    text: 'Debes esperar unos segundos antes de realizar otra acción.',
                    icon: 'warning',
                    confirmButtonText: 'Entendido'
                });
                return false;
            }
            lastActionTime = currentTime;
            return true;
        }
        
        const planForm = document.getElementById('cancel-plan-form');
        if (planForm) {
            planForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                if (!checkRateLimit()) {
                    return;
                }
                
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¡No podrás revertir esta acción!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, cancelar plan',
                    cancelButtonText: 'No, mantener plan'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch(window.location.href, {
                            method: 'POST',
                            body: new FormData(planForm),
                            credentials: 'same-origin'
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la respuesta del servidor');
                            }
                            return response.text();
                        })
                        .then(data => {
                            Swal.fire({
                                title: '¡Plan cancelado!',
                                text: 'Tu plan ha sido cancelado con éxito.',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                window.location.reload();
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: 'Hubo un problema al cancelar el plan. Inténtalo de nuevo.',
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        });
                    }
                });
            });
        }

        const destacadoForm = document.getElementById('cancel-destacado-form');
        if (destacadoForm) {
            destacadoForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                if (!checkRateLimit()) {
                    return;
                }
                
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¡No podrás revertir esta acción!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, cancelar destacado',
                    cancelButtonText: 'No, mantener destacado'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch(window.location.href, {
                            method: 'POST',
                            body: new FormData(destacadoForm),
                            credentials: 'same-origin'
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la respuesta del servidor');
                            }
                            return response.text();
                        })
                        .then(data => {
                            Swal.fire({
                                title: '¡Destacado cancelado!',
                                text: 'El destacado de tu negocio ha sido cancelado con éxito.',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                window.location.reload();
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: 'Hubo un problema al cancelar el destacado. Inténtalo de nuevo.',
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        });
                    }
                });
            });
        }

        const qrBtn = document.getElementById('descargar-qr-btn');
        if (qrBtn) {
            qrBtn.addEventListener('click', function() {
                const qrUrl = qrBtn.getAttribute('data-qr-url');
                if (!qrUrl) return;
                
                fetch(qrUrl)
                    .then(resp => {
                        if (!resp.ok) {
                            throw new Error('Error al descargar QR');
                        }
                        return resp.blob();
                    })
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = 'qr-negocio.png';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'No se pudo descargar el código QR.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    });
            });
        }

        const copiarBtn = document.getElementById('copiar-enlace-btn');
        const copiarMsg = document.getElementById('copiar-enlace-msg');
        if (copiarBtn) {
            copiarBtn.addEventListener('click', function() {
                const link = copiarBtn.getAttribute('data-link');
                if (!link) return;
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(link).then(function() {
                        if (copiarMsg) {
                            copiarMsg.classList.remove('hidden');
                            setTimeout(() => copiarMsg.classList.add('hidden'), 1800);
                        }
                    }).catch(function() {
                        // Fallback para navegadores antiguos
                        fallbackCopyToClipboard(link);
                    });
                } else {
                    fallbackCopyToClipboard(link);
                }
            });
        }
        
        function fallbackCopyToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                if (copiarMsg) {
                    copiarMsg.classList.remove('hidden');
                    setTimeout(() => copiarMsg.classList.add('hidden'), 1800);
                }
            } catch (err) {
                console.error('Error al copiar al portapapeles:', err);
            }
            
            document.body.removeChild(textArea);
        }
    });
    </script>
</body>
</html>