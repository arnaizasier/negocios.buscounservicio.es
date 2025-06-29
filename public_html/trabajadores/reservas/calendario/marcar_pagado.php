<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-publica.php';
require_once '../../../../db-venta_productos.php';

use Delight\Auth\Auth;

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar Content-Type
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($content_type, 'application/x-www-form-urlencoded') === false && 
    strpos($content_type, 'multipart/form-data') === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Content-Type inválido']);
    exit;
}

// Verificar CSRF Token
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// Verificar Referer para prevenir ataques CSRF adicionales
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$allowed_domain = parse_url('https://negocios.buscounservicio.es', PHP_URL_HOST);
$referer_domain = parse_url($referer, PHP_URL_HOST);
if ($referer_domain !== $allowed_domain) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Origen no autorizado']);
    exit;
}

$auth = new Auth($pdo);
$user_id = $auth->getUserId();

header('Content-Type: application/json');
require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-trabajador.php";

// Obtener datos del trabajador actual
$worker_data = requireWorkerRole();
$current_worker_id = $worker_data['id'];
$worker_negocio_id = $worker_data['negocio_id'];
$worker_permissions = $worker_data['permisos'];

// Validación estricta de entrada
if (!isset($_POST['id_reserva'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de reserva no proporcionado']);
    exit;
}

// Sanitización y validación del ID
$id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
if ($id_reserva === false || $id_reserva <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de reserva inválido']);
    exit;
}

// Limitar tamaño del ID para prevenir ataques de overflow
if ($id_reserva > 2147483647) { // MAX INT32
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de reserva fuera de rango']);
    exit;
}

try {
    // Iniciar transacción para atomicidad
    $pdo5->beginTransaction();
    
    // Verificar existencia de la reserva con bloqueo para evitar race conditions
    $sql = "SELECT id_negocio, id_trabajador, estado_pago FROM reservas WHERE id_reserva = ? FOR UPDATE";
    $stmt = $pdo5->prepare($sql);
    $stmt->execute([$id_reserva]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reserva) {
        $pdo5->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
        exit;
    }
    
    // Verificar si ya está pagada para evitar operaciones innecesarias
    if ($reserva['estado_pago'] === 'Pagado') {
        $pdo5->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'La reserva ya está marcada como pagada']);
        exit;
    }
    
    // Verificar permisos del trabajador
    if ($reserva['id_negocio'] != $worker_negocio_id) {
        $pdo5->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para modificar esta reserva']);
        exit;
    }
    
    // Si tiene permisos 1, solo puede modificar sus propias reservas
    if ($worker_permissions == 1 && $reserva['id_trabajador'] != $current_worker_id) {
        $pdo5->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para modificar esta reserva']);
        exit;
    }
    
    // Actualizar estado de pago
    $sql_update = "UPDATE reservas SET estado_pago = 'Pagado' WHERE id_reserva = ?";
    $stmt_update = $pdo5->prepare($sql_update);
    
    if ($stmt_update->execute([$id_reserva])) {
        // Verificar que realmente se actualizó una fila
        if ($stmt_update->rowCount() === 0) {
            $pdo5->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la reserva']);
            exit;
        }
        
        $pdo5->commit();
        
        echo json_encode(['success' => true, 'message' => 'Reserva marcada como pagada']);
    } else {
        $pdo5->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el pago']);
    }
    
} catch (PDOException $e) {
    // Rollback en caso de error
    if ($pdo5->inTransaction()) {
        $pdo5->rollBack();
    }
    
    // Log del error sin exponer detalles al usuario
    error_log("Error en marcar_pagado.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
} catch (Exception $e) {
    // Rollback para cualquier otra excepción
    if ($pdo5->inTransaction()) {
        $pdo5->rollBack();
    }
    
    error_log("Error inesperado en marcar_pagado.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>