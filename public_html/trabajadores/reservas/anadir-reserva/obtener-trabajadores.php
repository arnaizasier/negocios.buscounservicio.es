<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";

require_once '../../../../config.php';
require_once '../../../../db-publica.php';
require_once '../../../../db-venta_productos.php';
require_once '../../../../db-crm.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

header('Content-Type: application/json');

if (!isset($_GET['negocio_id']) || empty($_GET['negocio_id'])) {
    echo json_encode(['error' => 'ID de negocio requerido']);
    exit;
}

$negocio_id = filter_input(INPUT_GET, 'negocio_id', FILTER_VALIDATE_INT);

if ($negocio_id === false || $negocio_id <= 0) {
    echo json_encode(['error' => 'ID de negocio inválido']);
    exit;
}

try {
    $stmt_verificar = $pdo2->prepare("SELECT negocio_id FROM negocios WHERE negocio_id = ? AND usuario_id = ?");
    $stmt_verificar->execute([$negocio_id, $user_id]);
    
    if (!$stmt_verificar->fetch()) {
        echo json_encode(['error' => 'No tienes permisos para este negocio']);
        exit;
    }
    
    $stmt_trabajadores = $pdo2->prepare("
        SELECT id, nombre, apellido, rol, horario, url_foto, color_calendario 
        FROM trabajadores 
        WHERE negocio_id = ? 
        ORDER BY nombre ASC, apellido ASC
    ");
    $stmt_trabajadores->execute([$negocio_id]);
    $trabajadores = $stmt_trabajadores->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($trabajadores);
    
} catch (PDOException $e) {
    error_log("Error al obtener trabajadores: " . $e->getMessage());
    echo json_encode(['error' => 'Error al obtener los trabajadores']);
}
?> 