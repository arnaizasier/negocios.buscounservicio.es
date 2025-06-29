<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-publica.php';
require_once '../../../../db-venta_productos.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

header('Content-Type: application/json');

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

// Validar datos recibidos
$id_reserva = isset($_POST['id_reserva']) ? intval($_POST['id_reserva']) : 0;
$servicio = isset($_POST['servicio']) ? trim($_POST['servicio']) : '';
$fecha_inicio = isset($_POST['fecha_inicio']) ? trim($_POST['fecha_inicio']) : '';
$fecha_fin = isset($_POST['fecha_fin']) ? trim($_POST['fecha_fin']) : '';
$duracion = isset($_POST['duracion']) ? intval($_POST['duracion']) : 0;

if (!$id_reserva || !$servicio || !$fecha_inicio || !$fecha_fin || !$duracion) {
    echo json_encode(['error' => 'Faltan datos obligatorios.']);
    exit;
}

// Comprobar que la reserva pertenece a uno de sus negocios
$sql_reserva = "SELECT * FROM reservas WHERE id_reserva = :id_reserva";
$stmt_reserva = $pdo5->prepare($sql_reserva);
$stmt_reserva->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);
$stmt_reserva->execute();
$reserva = $stmt_reserva->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    echo json_encode(['error' => 'Reserva no encontrada.']);
    exit;
}

$sql_negocio = "SELECT 1 FROM negocios WHERE negocio_id = ? AND usuario_id = ?";
$stmt_negocio = $pdo2->prepare($sql_negocio);
$stmt_negocio->execute([$reserva['id_negocio'], $user_id]);
$es_dueno = $stmt_negocio->fetchColumn();

if (!$es_dueno) {
    echo json_encode(['error' => 'No tienes permiso para editar esta reserva.']);
    exit;
}

// Actualizar la reserva
try {
    $sql_update = "UPDATE reservas SET servicio = :servicio, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, duracion = :duracion WHERE id_reserva = :id_reserva";
    $stmt_update = $pdo5->prepare($sql_update);
    $stmt_update->bindParam(':servicio', $servicio);
    $stmt_update->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt_update->bindParam(':fecha_fin', $fecha_fin);
    $stmt_update->bindParam(':duracion', $duracion, PDO::PARAM_INT);
    $stmt_update->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);

    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reserva actualizada correctamente.']);
    } else {
        $errorInfo = $stmt_update->errorInfo();
        echo json_encode(['error' => 'Error al actualizar la reserva: ' . $errorInfo[2]]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Excepción: ' . $e->getMessage()]);
}
