<?php
require_once __DIR__ . "/../../src/sesiones-seguras.php";

// Configuraciones de seguridad para sesiones (más equilibradas)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax'); // Cambiado de Strict a Lax para mejor compatibilidad
ini_set('session.gc_maxlifetime', 43200); // 12 horas en lugar de 1 hora
ini_set('display_errors', 0);
error_reporting(0);

// Generar nonce para CSP antes de enviar headers
$nonce = base64_encode(random_bytes(16));

// Headers de seguridad (equilibrados) - antes de session_start()
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline' 'nonce-{$nonce}'; style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-Frame-Options: SAMEORIGIN"); // Cambiado de DENY a SAMEORIGIN
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

session_start();

// Regenerar ID de sesión periódicamente (cada 2 horas en lugar de 30 minutos)
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 7200) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Verificar expiración de sesión (12 horas de inactividad en lugar de 1 hora)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 43200)) {
    session_unset();
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Funciones de seguridad
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($data) {
    if ($data === null || $data === '') return '';
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateNumeric($value, $min = 0, $max = PHP_INT_MAX) {
    $value = filter_var($value, FILTER_VALIDATE_FLOAT);
    return ($value !== false && $value >= $min && $value <= $max) ? $value : false;
}

function isValidUserAgent() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($user_agent) || strlen($user_agent) < 10) {
        return false;
    }
    
    $suspicious_patterns = [
        '/bot/i', '/crawler/i', '/spider/i', '/scraper/i',
        '/wget/i', '/curl/i', '/python/i', '/perl/i',
        '/script/i', '/automated/i', '/headless/i'
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return false;
        }
    }
    
    return true;
}

function checkRateLimit($action, $limit = 5, $timeframe = 300) {
    $key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 0, 'time' => time()];
    }
    
    $rate_data = $_SESSION['rate_limit'][$key];
    
    if (time() - $rate_data['time'] > $timeframe) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'time' => time()];
        return true;
    }
    
    if ($rate_data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION['rate_limit'][$key]['count']++;
    return true;
}

function logSecurityEvent($message, $data = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'message' => $message,
        'data' => $data
    ];
    
    error_log("SECURITY_CARTERA: " . json_encode($logEntry));
}

// Validaciones de seguridad iniciales (reactivadas)
if (!isValidUserAgent()) {
    logSecurityEvent('Invalid User Agent detected', ['user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
    http_response_code(403);
    exit('Acceso denegado');
}

// Verificar referer para requests POST (reactivado)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (empty($referer) || parse_url($referer, PHP_URL_HOST) !== $_SERVER['HTTP_HOST']) {
        logSecurityEvent('Invalid referer for POST request', ['referer' => $referer]);
        http_response_code(403);
        exit('Acceso denegado');
    }
}

// Los archivos rate-limiting.php y headers-seguridad.php no existen, funciones definidas localmente

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";
require_once __DIR__ . "/../../../db-venta_productos.php";

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";
require_once __DIR__ . "/email-functions.php";

// Get user data
$stmt_user = $pdo->prepare("SELECT role, email, username, first_name, last_name FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

if (!$user || $user['role'] !== 'negocio') {
    echo "<div class='alert alert-danger'>Acceso denegado.</div>";
    exit;
}

$stmt_negocios = $pdo2->prepare("SELECT negocio_id, nombre FROM negocios WHERE usuario_id = ?");
$stmt_negocios->execute([$user_id]);
$negocios_data = $stmt_negocios->fetchAll(PDO::FETCH_ASSOC);
$negocios = array_column($negocios_data, 'negocio_id');
$negocios_nombres = array_column($negocios_data, 'nombre', 'negocio_id');

require_once __DIR__ . "/../../src/comprobar-si-tiene-negocios.php";

if (isset($_GET['id_negocio'])) {
    $id_negocio_solicitado = validateNumeric($_GET['id_negocio'], 1);
    if ($id_negocio_solicitado === false) {
        logSecurityEvent('Invalid id_negocio parameter', ['user_id' => $user_id, 'value' => $_GET['id_negocio']]);
        header('Location: index.php');
        exit;
    }
    if (!in_array($id_negocio_solicitado, $negocios)) {
        logSecurityEvent('Unauthorized negocio access via GET', ['user_id' => $user_id, 'negocio_id' => $id_negocio_solicitado]);
        header('Location: index.php');
        exit;
    }
    $id_negocio = $id_negocio_solicitado;
} elseif (count($negocios) === 1) {
    $id_negocio = $negocios[0];
} else {
    $id_negocio = $negocios[0];
}



$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retirar'])) {
    // Validaciones de seguridad para el formulario
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        logSecurityEvent('CSRF token validation failed', ['user_id' => $user_id]);
        $error = 'Error de verificación de seguridad. Por favor, inténtelo de nuevo.';
    } elseif (!checkRateLimit('withdraw', 5, 1800)) {
        logSecurityEvent('Rate limit exceeded for withdraw', ['user_id' => $user_id]);
        $error = 'Demasiados intentos de retiro. Por favor, espere 30 minutos antes de intentarlo nuevamente.';
    } elseif (!empty($_POST['honeypot']) || !empty($_POST['website'])) {
        logSecurityEvent('Honeypot triggered', ['user_id' => $user_id]);
        http_response_code(403);
        exit('Acceso denegado');
    } else {
        // Sanitizar y validar datos de entrada
        $id_negocio_form = validateNumeric($_POST['id_negocio'] ?? 0, 1);
        if ($id_negocio_form === false) {
            logSecurityEvent('Invalid negocio_id provided', ['user_id' => $user_id, 'value' => $_POST['id_negocio'] ?? '']);
            $error = 'ID de negocio inválido';
        } elseif (!in_array($id_negocio_form, $negocios)) {
            logSecurityEvent('Unauthorized negocio access attempt', ['user_id' => $user_id, 'negocio_id' => $id_negocio_form]);
            $error = 'Operación no permitida';
        } else {
            $id_negocio = $id_negocio_form;
            
            // Validar método de pago
            $metodo_pago = sanitizeInput($_POST['metodo_pago'] ?? '');
            $metodos_validos = ['PayPal', 'Transferencia Bancaria'];
            if (!in_array($metodo_pago, $metodos_validos)) {
                logSecurityEvent('Invalid payment method', ['user_id' => $user_id, 'method' => $metodo_pago]);
                $error = 'Método de pago inválido';
            } else {
                // Validar cantidad
                $cantidad = validateNumeric($_POST['cantidad_total'] ?? 0, 0.01, 50000);
                if ($cantidad === false) {
                    logSecurityEvent('Invalid amount provided', ['user_id' => $user_id, 'amount' => $_POST['cantidad_total'] ?? '']);
                    $error = 'Cantidad inválida';
                } else {
                    // Sanitizar datos de pago
                    $datos_pago = sanitizeInput($_POST['datos_pago'] ?? '');
                    $beneficiario = sanitizeInput($_POST['beneficiario'] ?? '');
                    
                                         // Validaciones específicas según método de pago
                    if ($metodo_pago === 'PayPal') {
                        if (!filter_var($datos_pago, FILTER_VALIDATE_EMAIL)) {
                            $error = 'Correo electrónico de PayPal inválido';
                        } elseif (strlen($datos_pago) > 100) {
                            $error = 'Correo electrónico demasiado largo';
                        }
                    } elseif ($metodo_pago === 'Transferencia Bancaria') {
                        if (empty($datos_pago) || !preg_match('/^[A-Z0-9\s]{10,34}$/', $datos_pago)) {
                            $error = 'IBAN inválido';
                        } elseif (empty($beneficiario) || strlen($beneficiario) < 2 || strlen($beneficiario) > 100) {
                            $error = 'Nombre del beneficiario inválido';
                        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\-\.]+$/', $beneficiario)) {
                            $error = 'El nombre del beneficiario contiene caracteres no válidos';
                        }
                    }
                    
                    if (empty($error) && $cantidad <= 0) {
                        $error = 'No hay fondos disponibles para retirar';
                    } elseif (empty($error)) {
                        try {
                $pdo5->beginTransaction();
                
                $fecha_limite_retiro = (new DateTime())->sub(new DateInterval('P1M'))->format('Y-m-d H:i:s');
                $stmt = $pdo5->prepare("UPDATE retirada SET estado = 'Pagado' WHERE usuario_id = ? AND negocio_id = ? AND estado = 'Pendiente' AND fecha <= ?");
                $stmt->execute([$user_id, $id_negocio, $fecha_limite_retiro]);

                $stmt = $pdo5->prepare("INSERT INTO retirada_historial (negocio_id, usuario_id, cantidad, metodo_pago, datos_pago, beneficiario, fecha, estado) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'Pendiente')");
                $stmt->execute([$id_negocio, $user_id, $cantidad, $metodo_pago, $datos_pago, $beneficiario]);
                $id_retiro = $pdo5->lastInsertId();
 
                $pdo5->commit();

                $nombre_negocio = $negocios_nombres[$id_negocio];

                // Send email notifications
                $email_results = enviarNotificacionesRetiro($user, $nombre_negocio, $id_negocio, $user_id, number_format($cantidad, 2), $metodo_pago, $datos_pago, $id_retiro);
                $enviado1 = $email_results['user_email_sent'];
                $enviado2 = $email_results['admin_email_sent'];
                
                $mensaje = "Retiro de " . number_format($cantidad, 2) . "€ procesado correctamente mediante {$metodo_pago}";
                if (!$enviado1 || !$enviado2) {
                    $mensaje .= " (Hubo un problema al enviar las notificaciones por correo)";
                }
            } catch (Exception $e) {
                            $pdo5->rollBack();
                            $error = 'Error al procesar el retiro: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}

$sql = "SELECT id, negocio_id, tipo, numero_pedido, cantidad, fecha, estado 
        FROM retirada 
        WHERE usuario_id = :usuario_id AND negocio_id = :negocio_id AND estado = 'Pendiente'";

$stmt = $pdo5->prepare($sql);
$stmt->bindParam(':usuario_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':negocio_id', $id_negocio, PDO::PARAM_INT);
$stmt->execute();
$retiradas_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pagina_actual = isset($_GET['pagina']) ? validateNumeric($_GET['pagina'], 1, 1000) : 1;
if ($pagina_actual === false) {
    logSecurityEvent('Invalid pagina parameter', ['user_id' => $user_id, 'value' => $_GET['pagina'] ?? '']);
    $pagina_actual = 1;
}
$filas_por_pagina = 9;
$offset = ($pagina_actual - 1) * $filas_por_pagina;

$sql_transacciones = "SELECT id, negocio_id, tipo, numero_pedido, cantidad, fecha, estado 
                     FROM retirada 
                     WHERE usuario_id = :usuario_id AND negocio_id = :negocio_id
                     ORDER BY fecha DESC
                     LIMIT :filas_por_pagina OFFSET :offset";

$stmt_transacciones = $pdo5->prepare($sql_transacciones);
$stmt_transacciones->bindParam(':usuario_id', $user_id, PDO::PARAM_INT);
$stmt_transacciones->bindParam(':negocio_id', $id_negocio, PDO::PARAM_INT);
$stmt_transacciones->bindParam(':filas_por_pagina', $filas_por_pagina, PDO::PARAM_INT);
$stmt_transacciones->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt_transacciones->execute();
$historial_transacciones = $stmt_transacciones->fetchAll(PDO::FETCH_ASSOC);

$sql_total_transacciones = "SELECT COUNT(*) as total 
                           FROM retirada 
                           WHERE usuario_id = :usuario_id AND negocio_id = :negocio_id";

$stmt_total_transacciones = $pdo5->prepare($sql_total_transacciones);
$stmt_total_transacciones->bindParam(':usuario_id', $user_id, PDO::PARAM_INT);
$stmt_total_transacciones->bindParam(':negocio_id', $id_negocio, PDO::PARAM_INT);
$stmt_total_transacciones->execute();
$total_transacciones = $stmt_total_transacciones->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_transacciones / $filas_por_pagina);

$total_reservas_pendiente = 0;
$total_ventas_pendiente = 0;
$comision_reservas_pendiente = 0;
$comision_ventas_pendiente = 0;

$total_reservas_disponible = 0;
$total_ventas_disponible = 0;
$comision_reservas_disponible = 0;
$comision_ventas_disponible = 0;

$fecha_limite_retiro_ventas = new DateTime();
$fecha_limite_retiro_ventas->sub(new DateInterval('P1M')); 

$fecha_limite_retiro_reservas = new DateTime();
$fecha_limite_retiro_reservas->sub(new DateInterval('P1D'));

foreach ($retiradas_pendientes as $retirada) {
    $fecha_transaccion = new DateTime($retirada['fecha']);
    
    if ($retirada['tipo'] === 'reserva') {
        $disponible_para_retiro = $fecha_transaccion <= $fecha_limite_retiro_reservas;
        $total_reservas_pendiente += $retirada['cantidad'];
        $comision_reservas_pendiente += $retirada['cantidad'] * 0.03; 
        if ($disponible_para_retiro) {
            $total_reservas_disponible += $retirada['cantidad'];
            $comision_reservas_disponible += $retirada['cantidad'] * 0.03;
        }
    } elseif ($retirada['tipo'] === 'venta') {
        $disponible_para_retiro = $fecha_transaccion <= $fecha_limite_retiro_ventas;
        $total_ventas_pendiente += $retirada['cantidad'];
        $comision_ventas_pendiente += $retirada['cantidad'] * 0.05; 
        if ($disponible_para_retiro) {
            $total_ventas_disponible += $retirada['cantidad'];
            $comision_ventas_disponible += $retirada['cantidad'] * 0.05;
        }
    }
}

$total_neto_reservas_pendiente = $total_reservas_pendiente - $comision_reservas_pendiente;
$total_neto_ventas_pendiente = $total_ventas_pendiente - $comision_ventas_pendiente;
$total_neto_pendiente = $total_neto_reservas_pendiente + $total_neto_ventas_pendiente;

$total_neto_reservas_disponible = $total_reservas_disponible - $comision_reservas_disponible;
$total_neto_ventas_disponible = $total_ventas_disponible - $comision_ventas_disponible;
$total_neto_disponible = $total_neto_reservas_disponible + $total_neto_ventas_disponible;

// Paginación para historial de retiros
$pagina_retiros = isset($_GET['pagina_retiros']) ? validateNumeric($_GET['pagina_retiros'], 1, 1000) : 1;
if ($pagina_retiros === false) {
    logSecurityEvent('Invalid pagina_retiros parameter', ['user_id' => $user_id, 'value' => $_GET['pagina_retiros'] ?? '']);
    $pagina_retiros = 1;
}
$filas_por_pagina_retiros = 10;
$offset_retiros = ($pagina_retiros - 1) * $filas_por_pagina_retiros;

$sql_historial = "SELECT id, negocio_id, cantidad, metodo_pago, fecha, estado 
                 FROM retirada_historial 
                 WHERE usuario_id = :usuario_id AND negocio_id = :negocio_id 
                 ORDER BY fecha DESC
                 LIMIT :filas_por_pagina OFFSET :offset";

$stmt_historial = $pdo5->prepare($sql_historial);
$stmt_historial->bindParam(':usuario_id', $user_id, PDO::PARAM_INT);
$stmt_historial->bindParam(':negocio_id', $id_negocio, PDO::PARAM_INT);
$stmt_historial->bindParam(':filas_por_pagina', $filas_por_pagina_retiros, PDO::PARAM_INT);
$stmt_historial->bindParam(':offset', $offset_retiros, PDO::PARAM_INT);
$stmt_historial->execute();
$historial_retiradas = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

$sql_total_retiros = "SELECT COUNT(*) as total 
                     FROM retirada_historial 
                     WHERE usuario_id = :usuario_id AND negocio_id = :negocio_id";

$stmt_total_retiros = $pdo5->prepare($sql_total_retiros);
$stmt_total_retiros->bindParam(':usuario_id', $user_id, PDO::PARAM_INT);
$stmt_total_retiros->bindParam(':negocio_id', $id_negocio, PDO::PARAM_INT);
$stmt_total_retiros->execute();
$total_retiros = $stmt_total_retiros->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas_retiros = ceil($total_retiros / $filas_por_pagina_retiros);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartera</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/marca.css">
    <link rel="stylesheet" href="cartera.css">
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
    <div class="container45">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar.php'; ?>
        <div id="content45" class="content45">
            <div class="cartera-container">
                <div class="cartera-header">
                    <h1>Cartera</h1>
                    <?php
                    if (count($negocios) > 1) {
                        echo "<form method='get' id='filtroNegocioForm' class='negocio-filtro-form'>";
                        echo "<label for='negocioSelect'></label>";
                        echo "<select name='id_negocio' id='negocioSelect' onchange=\"document.getElementById('filtroNegocioForm').submit();\">";
                        foreach ($negocios as $n) {
                            $sel = (isset($_GET['id_negocio']) && $_GET['id_negocio'] == $n) ? ' selected' : ((empty($_GET['id_negocio']) && isset($id_negocio) && $id_negocio == $n) ? ' selected' : '');
                            $nombre_negocio = isset($negocios_nombres[$n]) ? htmlspecialchars($negocios_nombres[$n]) : ("Negocio #{$n}");
                            echo "<option value='{$n}'{$sel}>{$nombre_negocio}</option>";
                        }
                        echo "</select>";
                        echo "</form>";
                    }
                    ?>
                </div>
                
                <?php if (!empty($mensaje)): ?>
                <div class="mensaje-exito">
                    <?php echo $mensaje; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="mensaje-error">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="dashboard-row">
                    <div class="balance-card">
                        <h2>Balance Disponible</h2>
                        <div class="balance-totals">
                            <div class="balance-item">
                                <span class="balance-label">Total Reservas Pendiente (bruto):</span>
                                <span class="balance-value"><?php echo number_format($total_reservas_pendiente, 2); ?>€</span>
                            </div>
                            <div class="balance-item">
                                <span class="balance-label">Total Ventas Pendiente (bruto):</span>
                                <span class="balance-value"><?php echo number_format($total_ventas_pendiente, 2); ?>€</span>
                            </div>
                            <div class="balance-item total">
                                <span class="balance-label">Total Pendiente Neto:</span>
                                <span class="balance-value"><?php echo number_format($total_neto_pendiente, 2); ?>€</span>
                            </div>
                        </div>
                        <hr style="margin: 20px 0;">
                        <div class="balance-totals">
                            <div class="balance-item">
                                <span class="balance-label">Total Reservas Disponible para Retirar (bruto):</span>
                                <span class="balance-value"><?php echo number_format($total_reservas_disponible, 2); ?>€</span>
                            </div>
                            <div class="balance-item">
                                <span class="balance-label">Total Ventas Disponible para Retirar (bruto):</span>
                                <span class="balance-value"><?php echo number_format($total_ventas_disponible, 2); ?>€</span>
                            </div>
                            <div class="balance-item total">
                                <span class="balance-label">Total Disponible para Retirar:</span>
                                <span class="balance-value"><?php echo number_format($total_neto_disponible, 2); ?>€</span>
                            </div>
                        </div>
                        <?php if ($total_neto_pendiente > 0 && $total_neto_disponible == 0): ?>
                            <p class="info-texto" style="margin-top: 15px; color: #ffc107;">Para poder retirar el dinero de las reservas debe transcurrir 1 día, y para las ventas 30 días, ya que ese es el plazo que tienen los usuarios para solicitar una devolución.</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($total_neto_disponible > 0): ?>
                    <div class="retiro-card">
                        <h2>Retirar Fondos</h2>
                        <form method="post" action="" class="retiro-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="id_negocio" value="<?php echo $id_negocio; ?>">
                            <input type="hidden" name="cantidad_total" value="<?php echo $total_neto_disponible; ?>">
                            <input type="text" name="honeypot" style="display:none;" tabindex="-1" autocomplete="off">
                            
                            <div class="form-group">
                                <label for="metodo_pago">Método de pago:</label>
                                <select name="metodo_pago" id="metodo_pago" required>
                                    <option value="">Seleccione método</option>
                                    <option value="PayPal">PayPal</option>
                                    <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="datos_paypal" style="display:none;">
                                <label for="datos_pago_paypal">Correo electrónico de PayPal:</label>
                                <input type="email" id="datos_pago_paypal" placeholder="ejemplo@email.com" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}">
                            </div>
                            
                            <div class="form-group" id="datos_banco" style="display:none;">
                                <label for="datos_pago_banco">Cuenta bancaria (IBAN):</label>
                                <input type="text" id="datos_pago_banco" placeholder="ESXX XXXX XXXX XXXX XXXX XXXX" pattern="[A-Z0-9\s]{10,34}">
                                <label for="beneficiario">Nombre del beneficiario:</label>
                                <input type="text" id="beneficiario" placeholder="Nombre del beneficiario">
                            </div>
                            
                            <div class="form-group">
                                <label>Cantidad a retirar:</label>
                                <div class="cantidad-retiro"><?php echo number_format($total_neto_disponible, 2); ?>€</div>
                                <p class="info-texto">Esta cantidad es el total neto después de aplicar las comisiones.</p>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="retirar" class="btn-retirar">Retirar Fondos</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($historial_transacciones)): ?>
                <div class="detalles-card">
                    <h2>Historial de Transacciones</h2>
                    <div class="tabla-responsive">
                        <table class="tabla-detalles">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Referencia</th>
                                    <th>Importe Bruto</th>
                                    <th>Comisión</th>
                                    <th>Importe Neto</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Disponibilidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial_transacciones as $transaccion): ?>
                                <?php 
                                    $comision_porcentaje = $transaccion['tipo'] === 'reserva' ? 3 : 5;
                                    $comision = $transaccion['cantidad'] * ($comision_porcentaje / 100);
                                    $neto = $transaccion['cantidad'] - $comision;
                                    $clase_estado = strtolower($transaccion['estado']);
                                    
                                    $fecha_transaccion_dt = new DateTime($transaccion['fecha']);
                                    $disponible_para_retiro_historial = false;
                                    
                                    if ($transaccion['estado'] === 'Pendiente') {
                                        if ($transaccion['tipo'] === 'reserva') {
                                            $disponible_para_retiro_historial = $fecha_transaccion_dt <= $fecha_limite_retiro_reservas;
                                        } else {
                                            $disponible_para_retiro_historial = $fecha_transaccion_dt <= $fecha_limite_retiro_ventas;
                                        }
                                    }
                                    
                                    $mensaje_disponibilidad = '';
                                    if ($transaccion['estado'] === 'Pendiente') {
                                        if ($disponible_para_retiro_historial) {
                                            $mensaje_disponibilidad = '<span style="color: green;">Disponible</span>';
                                        } else {
                                            $fecha_disponible_estimada = clone $fecha_transaccion_dt;
                                            if ($transaccion['tipo'] === 'reserva') {
                                                $fecha_disponible_estimada->add(new DateInterval('P1D'));
                                            } else {
                                                $fecha_disponible_estimada->add(new DateInterval('P1M'));
                                            }
                                            $mensaje_disponibilidad = '<span style="color: orange;">Disponible el ' . $fecha_disponible_estimada->format('d/m/Y') . '</span>';
                                        }
                                    } elseif ($transaccion['estado'] === 'Pagado') {
                                        $mensaje_disponibilidad = '<span style="color: blue;">Retirado</span>';
                                    } else {
                                         $mensaje_disponibilidad = '-';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo ucfirst($transaccion['tipo']); ?></td>
                                    <td><?php echo htmlspecialchars($transaccion['numero_pedido']); ?></td>
                                    <td><?php echo number_format($transaccion['cantidad'], 2); ?>€</td>
                                    <td><?php echo number_format($comision, 2); ?>€ (<?php echo $comision_porcentaje; ?>%)</td>
                                    <td><?php echo number_format($neto, 2); ?>€</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($transaccion['fecha'])); ?></td>
                                    <td class="estado-<?php echo $clase_estado; ?>"><?php echo $transaccion['estado']; ?></td>
                                    <td><?php echo $mensaje_disponibilidad; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="paginacion">
                        <?php if ($total_paginas > 1): ?>
                            <?php if ($pagina_actual > 1): ?>
                                <a href="?id_negocio=<?php echo $id_negocio; ?>&pagina=<?php echo $pagina_actual - 1; ?>">Anterior</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <a href="?id_negocio=<?php echo $id_negocio; ?>&pagina=<?php echo $i; ?>" <?php echo ($i == $pagina_actual) ? 'class="activa"' : ''; ?>><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($pagina_actual < $total_paginas): ?>
                                <a href="?id_negocio=<?php echo $id_negocio; ?>&pagina=<?php echo $pagina_actual + 1; ?>">Siguiente</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($historial_retiradas)): ?>
                <div class="historial-card">
                    <h2>Historial de Retiros</h2>
                    <div class="tabla-responsive">
                        <table class="tabla-historial">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Método</th>
                                    <th>Importe</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial_retiradas as $historial): ?>
                                <tr>
                                    <td><?php echo $historial['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($historial['fecha'])); ?></td>
                                    <td><?php echo htmlspecialchars($historial['metodo_pago']); ?></td>
                                    <td><?php echo number_format($historial['cantidad'], 2); ?>€</td>
                                    <td class="estado-<?php echo strtolower($historial['estado']); ?>"><?php echo $historial['estado']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="paginacion">
                        <?php if ($total_paginas_retiros > 1): ?>
                            <?php if ($pagina_retiros > 1): ?>
                                <a href="?id_negocio=<?php echo $id_negocio; ?>&pagina_retiros=<?php echo $pagina_retiros - 1; ?>">Anterior</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_paginas_retiros; $i++): ?>
                                <a href="?id_negocio=<?php echo $id_negocio; ?>&pagina_retiros=<?php echo $i; ?>" <?php echo ($i == $pagina_retiros) ? 'class="activa"' : ''; ?>><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($pagina_retiros < $total_paginas_retiros): ?>
                                <a href="?id_negocio=<?php echo $id_negocio; ?>&pagina_retiros=<?php echo $pagina_retiros + 1; ?>">Siguiente</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/sidebar.js"></script>
    <script nonce="<?php echo $nonce; ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const metodoPago = document.getElementById('metodo_pago');
            const datosPaypal = document.getElementById('datos_paypal');
            const datosBanco = document.getElementById('datos_banco');
            const datosPaypalInput = document.getElementById('datos_pago_paypal');
            const datosBancoInput = document.getElementById('datos_pago_banco');
            const beneficiarioInput = document.getElementById('beneficiario');
            
            if (metodoPago) {
                metodoPago.addEventListener('change', function() {
                    const valor = this.value;
                    
                    datosPaypal.style.display = 'none';
                    datosBanco.style.display = 'none';
                    datosPaypalInput.required = false;
                    datosBancoInput.required = false;
                    beneficiarioInput.required = false;
                    
                    document.querySelector('input[name="datos_pago"]') ? document.querySelector('input[name="datos_pago"]').remove() : null;
                    
                    if (valor === 'PayPal') {
                        datosPaypal.style.display = 'block';
                        datosPaypalInput.required = true;
                        
                        datosPaypalInput.addEventListener('input', function() {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'datos_pago';
                            hiddenInput.value = this.value;
                            this.parentNode.appendChild(hiddenInput);
                        });
                        
                    } else if (valor === 'Transferencia Bancaria') {
                        datosBanco.style.display = 'block';
                        datosBancoInput.required = true;
                        beneficiarioInput.required = true;
                        
                        datosBancoInput.addEventListener('input', function() {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'datos_pago';
                            hiddenInput.value = this.value;
                            this.parentNode.appendChild(hiddenInput);
                        });
                        
                        beneficiarioInput.addEventListener('input', function() {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'beneficiario';
                            hiddenInput.value = this.value;
                            this.parentNode.appendChild(hiddenInput);
                        });
                    }
                });
            }
            
            document.querySelector('.retiro-form').addEventListener('submit', function(e) {
                const metodoPago = document.getElementById('metodo_pago').value;
                let datosValidos = true;
                
                // Verificar que el honeypot esté vacío
                const honeypot = document.querySelector('input[name="honeypot"]');
                if (honeypot && honeypot.value !== '') {
                    e.preventDefault();
                    return false;
                }
                
                // Verificar que existe el token CSRF
                const csrfToken = document.querySelector('input[name="csrf_token"]');
                if (!csrfToken || csrfToken.value === '') {
                    datosValidos = false;
                    alert('Error de seguridad. Por favor, recarga la página e inténtalo de nuevo.');
                }
                
                if (metodoPago === 'PayPal') {
                    const email = document.getElementById('datos_pago_paypal').value;
                    if (!email.match(/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/)) {
                        datosValidos = false;
                        alert('Por favor, introduce un correo electrónico válido para PayPal.');
                    } else if (email.length > 100) {
                        datosValidos = false;
                        alert('El correo electrónico es demasiado largo.');
                    }
                } else if (metodoPago === 'Transferencia Bancaria') {
                    const iban = document.getElementById('datos_pago_banco').value;
                    const beneficiario = document.getElementById('beneficiario').value;
                    
                    if (!iban.match(/^[A-Z0-9\s]{10,34}$/)) {
                        datosValidos = false;
                        alert('Por favor, introduce un IBAN válido.');
                    } else if (!beneficiario || beneficiario.length < 2 || beneficiario.length > 100) {
                        datosValidos = false;
                        alert('Por favor, introduce un nombre de beneficiario válido (2-100 caracteres).');
                    } else if (!beneficiario.match(/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\-\.]+$/)) {
                        datosValidos = false;
                        alert('El nombre del beneficiario contiene caracteres no válidos.');
                    }
                }
                
                if (!datosValidos) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>