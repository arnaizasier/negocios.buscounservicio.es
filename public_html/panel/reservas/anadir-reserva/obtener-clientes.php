<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-crm.php';

use Delight\Auth\Auth;

// Funciones de encriptación/desencriptación
function encrypt_data($data) {
    if (empty($data)) {
        return '';
    }
    
    $cipher = 'AES-256-GCM';
    $key = hash('sha256', ENCRYPT_KEY . ENCRYPT_SALT);
    $iv = random_bytes(12);
    $tag = '';
    
    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    
    if ($encrypted === false) {
        return '';
    }
    
    return base64_encode($iv . $tag . $encrypted);
}

function decrypt_data($encrypted_data) {
    if (empty($encrypted_data)) {
        return '';
    }
    
    $data = base64_decode($encrypted_data);
    if ($data === false || strlen($data) < 28) {
        return '';
    }
    
    $cipher = 'AES-256-GCM';
    $key = hash('sha256', ENCRYPT_KEY . ENCRYPT_SALT);
    
    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $encrypted = substr($data, 28);
    
    $decrypted = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    
    return $decrypted !== false ? $decrypted : '';
}

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
        // Desencriptar los campos sensibles según la guía
        $nombre_desencriptado = decrypt_data($cliente['nombre']);
        $telefono_desencriptado = decrypt_data($cliente['telefono']);
        
        $cliente_data = [
            'cliente_id' => $cliente['cliente_id'],
            'nombre' => !empty($nombre_desencriptado) ? $nombre_desencriptado : 'Sin nombre',
            'apellidos' => $cliente['apellidos'], // No se encripta según la guía
            'telefono' => $telefono_desencriptado,
            'email' => $cliente['email'], // No se encripta según la guía
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