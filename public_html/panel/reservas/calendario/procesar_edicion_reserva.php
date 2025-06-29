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
$negocio_id = isset($_POST['negocio_id']) ? intval($_POST['negocio_id']) : 0;
$servicio = isset($_POST['servicio']) ? trim($_POST['servicio']) : '';
$fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
$hora = isset($_POST['hora']) ? trim($_POST['hora']) : '';
$duracion = isset($_POST['duracion']) ? intval($_POST['duracion']) : 0;
$precio = isset($_POST['precio']) ? floatval($_POST['precio']) : 0;
$id_trabajador = isset($_POST['id_trabajador']) && $_POST['id_trabajador'] !== '' ? intval($_POST['id_trabajador']) : null;

if (!$id_reserva || !$negocio_id || !$servicio || !$fecha || !$hora || !$duracion || !$precio) {
    $response = ['success' => false, 'message' => 'Faltan datos obligatorios.'];
    echo json_encode($response);
    exit;
}

// Validar formato de hora (HH:MM)
if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora)) {
    $response = ['success' => false, 'message' => 'Formato de hora inválido.'];
    echo json_encode($response);
    exit;
}

// Comprobar que la reserva existe y pertenece a uno de sus negocios
$sql_reserva = "SELECT * FROM reservas WHERE id_reserva = :id_reserva";
$stmt_reserva = $pdo5->prepare($sql_reserva);
$stmt_reserva->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);
$stmt_reserva->execute();
$reserva = $stmt_reserva->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    $response = ['success' => false, 'message' => 'Reserva no encontrada.'];
    echo json_encode($response);
    exit;
}

// Verificar que el negocio pertenece al usuario
$sql_negocio = "SELECT 1 FROM negocios WHERE negocio_id = ? AND usuario_id = ?";
$stmt_negocio = $pdo2->prepare($sql_negocio);
$stmt_negocio->execute([$negocio_id, $user_id]);
$es_dueno = $stmt_negocio->fetchColumn();

if (!$es_dueno) {
    $response = ['success' => false, 'message' => 'No tienes permiso para editar esta reserva.'];
    echo json_encode($response);
    exit;
}

// Formatear fecha y hora de inicio/fin
$fecha_inicio_str = "$fecha $hora:00"; 
$fecha_fin_str = null;
try {
    $fecha_inicio_dt = new DateTime($fecha_inicio_str);
    $fecha_fin_dt = clone $fecha_inicio_dt;
    $fecha_fin_dt->modify("+$duracion minutes");
    $fecha_fin_str = $fecha_fin_dt->format('Y-m-d H:i:s');
    $fecha_inicio_str = $fecha_inicio_dt->format('Y-m-d H:i:s');
} catch (\Exception $e) {
    $response = ['success' => false, 'message' => 'Error al calcular la fecha/hora de fin.'];
    echo json_encode($response);
    exit;
}

// Verificar que el trabajador pertenece al negocio (si se especifica)
if ($id_trabajador !== null) {
    $sql_trabajador = "SELECT 1 FROM trabajadores WHERE id = ? AND negocio_id = ?";
    $stmt_trabajador = $pdo2->prepare($sql_trabajador);
    $stmt_trabajador->execute([$id_trabajador, $negocio_id]);
    $trabajador_valido = $stmt_trabajador->fetchColumn();
    
    if (!$trabajador_valido) {
        $response = ['success' => false, 'message' => 'El trabajador seleccionado no pertenece a este negocio.'];
        echo json_encode($response);
        exit;
    }
}

// Verificar que no hay solapamiento con otras reservas (excluyendo la actual)
$sql_solapamiento = "SELECT 1 FROM reservas 
                     WHERE id_negocio = :negocio_id 
                     AND id_reserva != :id_reserva";

if ($id_trabajador !== null) {
    $sql_solapamiento .= " AND id_trabajador = :id_trabajador";
}

$sql_solapamiento .= " AND (
                         (fecha_inicio < :fecha_fin AND fecha_fin > :fecha_inicio)
                     )";

$stmt_solapamiento = $pdo5->prepare($sql_solapamiento);
$stmt_solapamiento->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
$stmt_solapamiento->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);
$stmt_solapamiento->bindParam(':fecha_inicio', $fecha_inicio_str);
$stmt_solapamiento->bindParam(':fecha_fin', $fecha_fin_str);

if ($id_trabajador !== null) {
    $stmt_solapamiento->bindParam(':id_trabajador', $id_trabajador, PDO::PARAM_INT);
}

$stmt_solapamiento->execute();
$hay_solapamiento = $stmt_solapamiento->fetchColumn();

if ($hay_solapamiento) {
    $response = ['success' => false, 'message' => 'La fecha y hora seleccionadas se solapan con otra reserva.'];
    echo json_encode($response);
    exit;
}

// Actualizar la reserva
try {
    $sql_update = "UPDATE reservas 
                  SET servicio = :servicio, 
                      fecha_inicio = :fecha_inicio, 
                      fecha_fin = :fecha_fin, 
                      duracion = :duracion,
                      precio = :precio,
                      id_trabajador = :id_trabajador
                  WHERE id_reserva = :id_reserva";
    
    $stmt_update = $pdo5->prepare($sql_update);
    $stmt_update->bindParam(':servicio', $servicio);
    $stmt_update->bindParam(':fecha_inicio', $fecha_inicio_str);
    $stmt_update->bindParam(':fecha_fin', $fecha_fin_str);
    $stmt_update->bindParam(':duracion', $duracion, PDO::PARAM_INT);
    $stmt_update->bindParam(':precio', $precio);
    $stmt_update->bindParam(':id_trabajador', $id_trabajador, PDO::PARAM_INT);
    $stmt_update->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);

    if ($stmt_update->execute()) {
        $response = ['success' => true, 'message' => 'Reserva actualizada correctamente.'];
    } else {
        $errorInfo = $stmt_update->errorInfo();
        $response = ['success' => false, 'message' => 'Error al actualizar la reserva: ' . $errorInfo[2]];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Excepción: ' . $e->getMessage()];
}

echo json_encode($response); 