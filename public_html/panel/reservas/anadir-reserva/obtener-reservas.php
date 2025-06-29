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

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

if (empty($_GET['negocio_id']) || empty($_GET['fecha'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit;
}

$negocio_id = filter_input(INPUT_GET, 'negocio_id', FILTER_SANITIZE_NUMBER_INT);
$fecha = htmlspecialchars(trim($_GET['fecha']));
$trabajador_id = isset($_GET['trabajador_id']) ? filter_input(INPUT_GET, 'trabajador_id', FILTER_SANITIZE_NUMBER_INT) : null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Formato de fecha inválido']);
    exit;
}

try {
    $fecha_inicio = $fecha . ' 00:00:00';
    $fecha_fin = $fecha . ' 23:59:59';
    
    $sql = "SELECT fecha_inicio, fecha_fin FROM reservas WHERE id_negocio = ? AND fecha_inicio >= ? AND fecha_inicio <= ? AND estado_reserva != 'cancelada'";
    $params = [$negocio_id, $fecha_inicio, $fecha_fin];
    
    if (!empty($trabajador_id)) {
        $sql .= " AND id_trabajador = ?";
        $params[] = $trabajador_id;
    }
    
    $stmt = $pdo5->prepare($sql);
    $stmt->execute($params);
    
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($reservas);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error al obtener las reservas: ' . $e->getMessage()]);
}