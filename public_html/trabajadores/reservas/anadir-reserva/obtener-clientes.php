<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-crm.php';

use Delight\Auth\Auth;

header('Content-Type: application/json');
$response = ['error' => 'Error desconocido al obtener clientes'];

try {
    $auth = new Auth($pdo);
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        $response['error'] = 'No autenticado';
        echo json_encode($response);
        exit;
    }
    $user_id_negocio = $auth->getUserId();
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$user_id_negocio]);
    $user = $stmt_role->fetch();

    if (!$user || $user['role'] !== 'negocio') {
        http_response_code(403);
        $response['error'] = 'Acceso denegado';
        echo json_encode($response);
        exit;
    }
} catch (\Exception $e) {
    http_response_code(500);
    $response['error'] = 'Error interno del servidor (auth)';
    echo json_encode($response);
    exit;
}

$negocio_id = filter_input(INPUT_GET, 'negocio_id', FILTER_SANITIZE_NUMBER_INT);
if (!$negocio_id) {
    http_response_code(400);
    $response['error'] = 'ID de negocio no proporcionado o inválido';
    echo json_encode($response);
    exit;
}

try {
    $stmt_crm = $pdo6->prepare(
        "SELECT cliente_id, nombre, apellidos, telefono, email 
         FROM crm 
         WHERE usuario_id = ? AND negocio_id = ?"
    );
    $stmt_crm->execute([$user_id_negocio, $negocio_id]);
    $clientes_crm = $stmt_crm->fetchAll(PDO::FETCH_ASSOC);

    $clientes_final = [];

    foreach ($clientes_crm as $cliente) {
        $cliente_data = [
            'cliente_id' => $cliente['cliente_id'],
            'nombre' => !empty($cliente['nombre']) ? $cliente['nombre'] : 'Sin nombre',
            'apellidos' => $cliente['apellidos'],
            'telefono' => $cliente['telefono'],
            'email' => $cliente['email'],
            'tiene_cuenta' => false,
            'usuario_id' => null
        ];
        $clientes_final[] = $cliente_data;
    }

    echo json_encode($clientes_final);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['error'] = 'Error de base de datos al obtener clientes';
    echo json_encode($response);
} catch (\Exception $e) {
    http_response_code(500);
    $response['error'] = 'Error interno del servidor al obtener clientes';
    echo json_encode($response);
}