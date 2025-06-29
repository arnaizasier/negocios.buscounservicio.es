<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-venta_productos.php';
require_once '../../../../db-crm.php';
require_once '../../../../db-publica.php';

use Delight\Auth\Auth;

// Funciones de encriptación
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

function sanitize_input($data) {
    $data = (string) $data; 
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

$response = ['success' => false, 'message' => 'Error desconocido.'];
header('Content-Type: application/json');

try {
    $auth = new Auth($pdo);
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        $response['message'] = 'No autenticado.';
        echo json_encode($response);
        exit;
    }
    $user_id = $auth->getUserId();
    
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$user_id]);
    $user = $stmt_role->fetch();
    
    if (!$user || $user['role'] !== 'negocio') {
        http_response_code(403);
        $response['message'] = 'Acceso denegado.';
        echo json_encode($response);
        exit;
    }
} catch (\Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error interno del servidor (auth).';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

$negocio_id = filter_input(INPUT_POST, 'negocio_id', FILTER_SANITIZE_NUMBER_INT);
$fecha = htmlspecialchars(trim($_POST['fecha'] ?? ''));
$hora = htmlspecialchars(trim($_POST['hora'] ?? ''));
$servicios_multiples = htmlspecialchars(trim($_POST['servicios_multiples'] ?? ''));
$servicio = htmlspecialchars(trim($_POST['servicio'] ?? ''));
$duracion = filter_input(INPUT_POST, 'duracion', FILTER_SANITIZE_NUMBER_INT);
$precio = filter_input(INPUT_POST, 'precio', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$tipo_cliente = htmlspecialchars(trim($_POST['tipo_cliente'] ?? ''));
$id_trabajador = !empty($_POST['id_trabajador']) ? filter_input(INPUT_POST, 'id_trabajador', FILTER_SANITIZE_NUMBER_INT) : null;

if (!$negocio_id || !$fecha || !$hora || (!$servicios_multiples && !$servicio) || !$duracion || !$precio || !$tipo_cliente) {
    http_response_code(400);
    $response['message'] = 'Faltan campos obligatorios.';
    echo json_encode($response);
    exit;
}

if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora)) {
    http_response_code(400);
    $response['message'] = 'Formato de hora inválido.';
    echo json_encode($response);
    exit;
}

// Verificar que el negocio pertenece al usuario
try {
    $stmt_verificar_negocio = $pdo2->prepare("SELECT negocio_id FROM negocios WHERE negocio_id = ? AND usuario_id = ?");
    $stmt_verificar_negocio->execute([$negocio_id, $user_id]);
    if (!$stmt_verificar_negocio->fetch()) {
        http_response_code(403);
        $response['message'] = 'No tienes permiso para crear reservas en este negocio.';
        echo json_encode($response);
        exit;
    }
} catch (\Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error al verificar permisos del negocio.';
    echo json_encode($response);
    exit;
}

$fecha_inicio_str = "$fecha $hora:00"; 
$fecha_fin_str = null;
try {
    $fecha_inicio_dt = new DateTime($fecha_inicio_str);
    $fecha_fin_dt = clone $fecha_inicio_dt;
    $fecha_fin_dt->modify("+$duracion minutes");
    $fecha_fin_str = $fecha_fin_dt->format('Y-m-d H:i:s');
    $fecha_inicio_str = $fecha_inicio_dt->format('Y-m-d H:i:s');
} catch (\Exception $e) {
    http_response_code(400);
    $response['message'] = 'Error al calcular la fecha/hora de fin.';
    echo json_encode($response);
    exit;
}

$id_cliente_crm_db = null;
$final_reserva_id_cliente = null;
$final_reserva_id_cliente_crm = null;
$error_cliente = null;

try {
    if ($tipo_cliente === 'nuevo') {
        $nombre = sanitize_input($_POST['nombre'] ?? '');
        $apellidos = sanitize_input($_POST['apellidos'] ?? '');
        $telefono = sanitize_input($_POST['telefono'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null;

        if (!$nombre || !$telefono) {
            $error_cliente = "Nombre y teléfono son obligatorios para crear un nuevo cliente.";
        } else {
            $stmt_nuevo_cliente = $pdo6->prepare("INSERT INTO crm (usuario_id, negocio_id, nombre, apellidos, telefono, email) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_nuevo_cliente->execute([
                $user_id, 
                $negocio_id, 
                encrypt_data($nombre), 
                $apellidos, 
                encrypt_data($telefono), 
                $email
            ]);
            $id_cliente_crm_db = $pdo6->lastInsertId();

            $final_reserva_id_cliente = 0;
            $final_reserva_id_cliente_crm = $id_cliente_crm_db;
        }
    } elseif ($tipo_cliente === 'existente') {
        $seleccionado_crm_cliente_id_input = filter_input(INPUT_POST, 'cliente_id', FILTER_SANITIZE_NUMBER_INT);
        $seleccionado_users_id_input = filter_input(INPUT_POST, 'usuario_id', FILTER_SANITIZE_NUMBER_INT);

        if ($seleccionado_users_id_input) { 
            $final_reserva_id_cliente = $seleccionado_users_id_input;
            $final_reserva_id_cliente_crm = 0;

            $stmt_check_crm = $pdo6->prepare("SELECT cliente_id FROM crm WHERE cliente_con_cuenta_id = ? AND negocio_id = ? LIMIT 1");
            $stmt_check_crm->execute([$final_reserva_id_cliente, $negocio_id]);
            $existente_crm = $stmt_check_crm->fetch(PDO::FETCH_ASSOC);

            if ($existente_crm) {
                $id_cliente_crm_db = $existente_crm['cliente_id'];
            } else {
                $stmt_user_details = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
                $stmt_user_details->execute([$final_reserva_id_cliente]);
                $user_data = $stmt_user_details->fetch(PDO::FETCH_ASSOC);
                if ($user_data) {
                    $stmt_create_crm_for_user = $pdo6->prepare("INSERT INTO crm (usuario_id, negocio_id, cliente_con_cuenta_id, nombre, apellidos, telefono, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_create_crm_for_user->execute([
                        $user_id, 
                        $negocio_id, 
                        $final_reserva_id_cliente, 
                        encrypt_data($user_data['first_name']), 
                        $user_data['last_name'], 
                        encrypt_data($user_data['phone']), 
                        $user_data['email']
                    ]);
                    $id_cliente_crm_db = $pdo6->lastInsertId();
                } else {
                    $error_cliente = "Datos del usuario seleccionado no encontrados para crear entrada CRM.";
                    $id_cliente_crm_db = 0;
                }
            }

        } elseif ($seleccionado_crm_cliente_id_input) { 
            $id_cliente_crm_db = $seleccionado_crm_cliente_id_input;
            
            $stmt_cliente_acct = $pdo6->prepare("SELECT cliente_con_cuenta_id FROM crm WHERE cliente_id = ? AND negocio_id = ?");
            $stmt_cliente_acct->execute([$id_cliente_crm_db, $negocio_id]);
            $crm_acct_data = $stmt_cliente_acct->fetch(PDO::FETCH_ASSOC);
            
            if ($crm_acct_data && !empty($crm_acct_data['cliente_con_cuenta_id'])) {
                $final_reserva_id_cliente = $crm_acct_data['cliente_con_cuenta_id'];
                $final_reserva_id_cliente_crm = 0;
            } else {
                $final_reserva_id_cliente = 0;
                $final_reserva_id_cliente_crm = $id_cliente_crm_db;
            }
        } else {
            $error_cliente = "Debe seleccionar un cliente existente (CRM o usuario registrado) o crear uno nuevo.";
        }
    } elseif ($tipo_cliente === 'sin_cliente') {
        $id_cliente_crm_db = 0; 
        $final_reserva_id_cliente = 0;
        $final_reserva_id_cliente_crm = 0; 
    } else {
        $error_cliente = "Tipo de cliente no válido.";
    }

    if ($error_cliente) {
        http_response_code(400);
        $response['message'] = $error_cliente;
        echo json_encode($response);
        exit;
    }

    // Verificar solapamiento de reservas
    $stmt_solapamiento = $pdo5->prepare(
        "SELECT id_reserva FROM reservas 
         WHERE id_negocio = ? 
         AND (id_trabajador = ? OR id_trabajador IS NULL OR ? IS NULL)
         AND estado_reserva != 'cancelada'
         AND (
             (fecha_inicio <= ? AND fecha_fin > ?) OR
             (fecha_inicio < ? AND fecha_fin >= ?) OR
             (fecha_inicio >= ? AND fecha_fin <= ?)
         )"
    );
    $stmt_solapamiento->execute([
        $negocio_id, 
        $id_trabajador, $id_trabajador,
        $fecha_inicio_str, $fecha_inicio_str,
        $fecha_fin_str, $fecha_fin_str,
        $fecha_inicio_str, $fecha_fin_str
    ]);

    if ($stmt_solapamiento->fetch()) {
        http_response_code(409);
        $response['message'] = 'Ya existe una reserva en este horario.';
        echo json_encode($response);
        exit;
    }

    $pdo5->beginTransaction();
    $pdo6->beginTransaction();
    
    $reservaGuardada = false;
    $clienteActualizado = false;
    $id_reserva = null;

    try {
        $stmt_reserva = $pdo5->prepare(
            "INSERT INTO reservas (id_cliente, id_cliente_crm, id_negocio, id_trabajador, fecha_inicio, fecha_fin, fecha_reserva, estado_reserva, estado_pago, precio, servicios_multiples, servicio, duracion) 
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)"
        );
        
        $reservaGuardada = $stmt_reserva->execute([
            $final_reserva_id_cliente, 
            $final_reserva_id_cliente_crm,
            $negocio_id,
            $id_trabajador,
            $fecha_inicio_str,
            $fecha_fin_str,
            'confirmada',
            'Pendiente',
            $precio,
            $servicios_multiples,
            $servicio,
            $duracion
        ]);
        
        if (!$reservaGuardada) {
            throw new PDOException("Error al insertar la reserva");
        }
        
        $id_reserva = $pdo5->lastInsertId();

        if ($id_cliente_crm_db && $id_cliente_crm_db > 0) {
            $stmt_update_crm = $pdo6->prepare("UPDATE crm SET numero_reservas = numero_reservas + 1 WHERE cliente_id = ?");
            $clienteActualizado = $stmt_update_crm->execute([$id_cliente_crm_db]);
        } else {
            $clienteActualizado = true;
        }

        if ($reservaGuardada && $clienteActualizado) {
            $pdo5->commit();
            $pdo6->commit();
            $response['success'] = true;
            $response['message'] = 'Reserva añadida correctamente.';
            $response['reserva_id'] = $id_reserva;
            http_response_code(201);
        } else {
            throw new Exception("No se pudo guardar la reserva o actualizar el cliente.");
        }

    } catch (\Exception $e) {
        $pdo5->rollBack();
        $pdo6->rollBack();
        throw $e;
    }

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    $response['debug_info'] = [
        'error_code' => $e->getCode(),
        'sql_state' => $e->errorInfo[0] ?? null,
        'driver_code' => $e->errorInfo[1] ?? null,
        'driver_message' => $e->errorInfo[2] ?? null
    ];
    echo json_encode($response);
} catch (\Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error interno del servidor: ' . $e->getMessage();
    echo json_encode($response);
}