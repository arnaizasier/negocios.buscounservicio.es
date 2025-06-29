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

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-trabajador.php";

header('Content-Type: application/json');

try {
    // Obtener datos del trabajador actual
    $worker_data = requireWorkerRole();
    $worker_negocio_id = $worker_data['negocio_id'];
    $worker_permissions = $worker_data['permisos'];
    
    // Solo mostrar trabajadores si tiene permisos 2
    if ($worker_permissions != 2) {
        echo json_encode([
            'success' => true,
            'trabajadores' => [],
            'total' => 0
        ]);
        exit;
    }
    
    $stmt_trabajadores = $pdo2->prepare("
        SELECT id, nombre, apellido, rol, url_foto, horario, color_calendario
        FROM trabajadores 
        WHERE negocio_id = ? 
        ORDER BY nombre ASC, apellido ASC
    ");
    $stmt_trabajadores->execute([$worker_negocio_id]);
    $trabajadores = $stmt_trabajadores->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'trabajadores' => $trabajadores,
        'total' => count($trabajadores)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 