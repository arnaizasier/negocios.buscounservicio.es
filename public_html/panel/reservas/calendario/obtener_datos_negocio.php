<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-publica.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

header('Content-Type: application/json');

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

// Verificar que se haya proporcionado un ID de negocio
if (!isset($_GET['negocio_id']) || empty($_GET['negocio_id'])) {
    echo json_encode(['error' => 'ID de negocio no proporcionado']);
    exit;
}

$negocio_id = intval($_GET['negocio_id']);

// Verificar que el negocio pertenezca al usuario
$sql_negocio = "SELECT negocio_id, nombre, url, telefono, tipo_reserva, horario_apertura, espacios_reservas, menu_servicios FROM negocios WHERE negocio_id = ? AND usuario_id = ?";
$stmt_negocio = $pdo2->prepare($sql_negocio);
$stmt_negocio->execute([$negocio_id, $user_id]);
$negocio = $stmt_negocio->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    echo json_encode(['error' => 'Negocio no encontrado o no pertenece al usuario']);
    exit;
}

// Devolver datos del negocio
echo json_encode(['negocio' => $negocio]); 