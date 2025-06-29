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
require_once __DIR__ . "/../../../src/verificar-rol-trabajador.php";

// Obtener datos del trabajador actual
$worker_data = requireWorkerRole();
$worker_negocio_id = $worker_data['negocio_id'];

// Obtener datos del negocio del trabajador
$sql_negocio = "SELECT negocio_id, nombre, url, telefono, tipo_reserva, horario_apertura, espacios_reservas, menu_servicios FROM negocios WHERE negocio_id = ?";
$stmt_negocio = $pdo2->prepare($sql_negocio);
$stmt_negocio->execute([$worker_negocio_id]);
$negocio = $stmt_negocio->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    echo json_encode(['error' => 'Negocio no encontrado']);
    exit;
}

// Devolver datos del negocio
echo json_encode(['negocio' => $negocio]); 