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
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

header('Content-Type: application/json');

try {
    if (!isset($_GET['id_negocio'])) {
        throw new Exception('ID de negocio no proporcionado');
    }
    
    $id_negocio = filter_input(INPUT_GET, 'id_negocio', FILTER_VALIDATE_INT);
    
    if ($id_negocio === false || $id_negocio <= 0) {
        throw new Exception('ID de negocio inválido');
    }
    
    $stmt_verificar = $pdo2->prepare("SELECT negocio_id FROM negocios WHERE usuario_id = ? AND negocio_id = ?");
    $stmt_verificar->execute([$user_id, $id_negocio]);
    
    if (!$stmt_verificar->fetch()) {
        throw new Exception('No tienes permisos para acceder a este negocio');
    }
    
    $stmt_trabajadores = $pdo2->prepare("
        SELECT id, nombre, apellido, rol, url_foto, horario, color_calendario
        FROM trabajadores 
        WHERE negocio_id = ? 
        ORDER BY nombre ASC, apellido ASC
    ");
    $stmt_trabajadores->execute([$id_negocio]);
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