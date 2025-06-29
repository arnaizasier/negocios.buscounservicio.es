<?php
session_start();

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validarRateLimit($user_id) {
    $limite_tiempo = 300;
    $limite_intentos = 5;
    
    if (!isset($_SESSION['delete_attempts_trabajador'])) {
        $_SESSION['delete_attempts_trabajador'] = [];
    }
    
    $tiempo_actual = time();
    $intentos = $_SESSION['delete_attempts_trabajador'];
    
    $intentos = array_filter($intentos, function($timestamp) use ($tiempo_actual, $limite_tiempo) {
        return ($tiempo_actual - $timestamp) < $limite_tiempo;
    });
    
    if (count($intentos) >= $limite_intentos) {
        return false;
    }
    
    $intentos[] = $tiempo_actual;
    $_SESSION['delete_attempts_trabajador'] = $intentos;
    
    return true;
}

function validateTrabajadorId($trabajador_id, $user_id, $pdo2) {
    if (!is_numeric($trabajador_id) || $trabajador_id <= 0) {
        return false;
    }
    
    try {
        $stmt = $pdo2->prepare("SELECT COUNT(*) FROM trabajadores WHERE id = ? AND admin_id = ?");
        $stmt->execute([$trabajador_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error validando trabajador_id: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCSRF($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

$current_time = time();
if (!isset($_SESSION['last_delete_time'])) {
    $_SESSION['last_delete_time'] = 0;
}

if (($current_time - $_SESSION['last_delete_time']) < 3) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Demasiadas solicitudes. Espera unos segundos']);
    exit;
}

$_SESSION['last_delete_time'] = $current_time;

if (!validarRateLimit($user_id)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Has excedido el límite de eliminaciones. Espera unos minutos']);
    exit;
}

$trabajador_id = filter_input(INPUT_POST, 'trabajador_id', FILTER_VALIDATE_INT);

if (!$trabajador_id || !validateTrabajadorId($trabajador_id, $user_id, $pdo2)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de trabajador no válido']);
    exit;
}

try {
    $stmt = $pdo2->prepare("SELECT url_foto FROM trabajadores WHERE id = ? AND admin_id = ?");
    $stmt->execute([$trabajador_id, $user_id]);
    $trabajador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trabajador) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Trabajador no encontrado']);
        exit;
    }
    
    $pdo2->beginTransaction();
    
    $stmt = $pdo2->prepare("DELETE FROM trabajadores WHERE id = ? AND admin_id = ?");
    $result = $stmt->execute([$trabajador_id, $user_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        if (!empty($trabajador['url_foto'])) {
            $foto_path = __DIR__ . '/../../imagenes/trabajadores/' . basename($trabajador['url_foto']);
            if (file_exists($foto_path) && is_file($foto_path)) {
                unlink($foto_path);
            }
        }
        
        $pdo2->commit();
        echo json_encode(['success' => true, 'message' => 'Trabajador eliminado correctamente']);
    } else {
        $pdo2->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se pudo eliminar el trabajador']);
    }
    
} catch (PDOException $e) {
    $pdo2->rollBack();
    error_log("Error al eliminar trabajador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?> 