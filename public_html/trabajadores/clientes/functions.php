<?php
require_once __DIR__ . "/../../src/sesiones-seguras.php";

define('SECURE_ACCESS', true);
session_start();

require_once __DIR__ . "/../../src/rate-limiting.php";

if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize_input($data) {
    $data = (string) $data; 
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    return preg_match('/^[0-9+\-\s()]{6,20}$/', $phone);
}

function encrypt_data($data) {
    if (empty($data)) {
        return '';
    }
    
    $cipher = 'AES-256-CBC';
    $key = hash('sha256', ENCRYPT_KEY);
    $iv = substr(hash('sha256', ENCRYPT_IV), 0, 16);
    
    return base64_encode(openssl_encrypt($data, $cipher, $key, 0, $iv));
}

function decrypt_data($encrypted_data) {
    if (empty($encrypted_data)) {
        return '';
    }
    
    $cipher = 'AES-256-CBC';
    $key = hash('sha256', ENCRYPT_KEY);
    $iv = substr(hash('sha256', ENCRYPT_IV), 0, 16);
    
    $decrypted = openssl_decrypt(base64_decode($encrypted_data), $cipher, $key, 0, $iv);
    return $decrypted !== false ? $decrypted : '';
}

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";
require_once __DIR__ . "/../../../db-suscripciones.php";
require_once __DIR__ . "/../../../db-crm.php";
require_once __DIR__ . "/../../../db-venta_productos.php";

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-trabajador.php";

// Obtener datos del trabajador
$worker_data = requireWorkerRole();
$negocio_id = $worker_data['negocio_id'];
$worker_name = $worker_data['nombre'] . ' ' . $worker_data['apellido'];

// Obtener nombre del negocio
$nombre_negocio = '';
try {
    $stmtNegocio = $pdo2->prepare("SELECT nombre FROM negocios WHERE negocio_id = ?");
    $stmtNegocio->execute([$negocio_id]);
    $negocio = $stmtNegocio->fetch(PDO::FETCH_ASSOC);
    if ($negocio) {
        $nombre_negocio = $negocio['nombre'];
    }
} catch (PDOException $e) {
    $nombre_negocio = 'Negocio';
}



if (isset($_POST['editar_cliente']) && isset($_POST['cliente_id'])) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $mensaje_error = "Error de validación de seguridad. Inténtalo de nuevo.";
    } else {
        $cliente_id = (int)$_POST['cliente_id'];
        $nombre = $_POST['nombre'];
        $apellidos = $_POST['apellidos'];
        $telefono = $_POST['telefono'];
        $email = $_POST['email'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?: null;
        $notas = $_POST['notas'];

        if (empty($telefono) && empty($email)) {
            $mensaje_error = "Debes proporcionar al menos un teléfono o un email.";
        } else {
            try {
                $stmtVerify = $pdo6->prepare("SELECT COUNT(*) FROM crm WHERE cliente_id = ? AND negocio_id = ?");
                $stmtVerify->execute([$cliente_id, $negocio_id]);
                if ($stmtVerify->fetchColumn() == 0) {
                    $mensaje_error = "No tienes permiso para editar este cliente.";
                } else {
                    $stmtUpdate = $pdo6->prepare("UPDATE crm SET nombre = ?, apellidos = ?, telefono = ?, email = ?, fecha_nacimiento = ?, notas = ? WHERE cliente_id = ? AND negocio_id = ?");
                    $stmtUpdate->execute([encrypt_data($nombre), $apellidos, encrypt_data($telefono), $email, $fecha_nacimiento, encrypt_data($notas), $cliente_id, $negocio_id]);
                    $mensaje_exito = "Cliente actualizado correctamente.";
                }
            } catch (PDOException $e) {
                $mensaje_error = "Error al actualizar el cliente: " . $e->getMessage();
            }
        }
    }
}

if (isset($_POST['eliminar_cliente']) && isset($_POST['cliente_id'])) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $mensaje_error = "Error de validación de seguridad. Inténtalo de nuevo.";
    } else {
        $cliente_id = (int)$_POST['cliente_id'];

        try {
            $stmtVerify = $pdo6->prepare("SELECT COUNT(*) FROM crm WHERE cliente_id = ? AND negocio_id = ?");
            $stmtVerify->execute([$cliente_id, $negocio_id]);
            if ($stmtVerify->fetchColumn() == 0) {
                $mensaje_error = "El cliente no pertenece al negocio o no existe.";
            } else {
                $stmtDelete = $pdo6->prepare("DELETE FROM crm WHERE cliente_id = ? AND negocio_id = ?");
                $stmtDelete->execute([$cliente_id, $negocio_id]);
                $mensaje_exito = "Cliente eliminado correctamente.";
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error al eliminar el cliente: " . $e->getMessage();
        }
    }
}

if (isset($_POST['fusionar_clientes']) && isset($_POST['cliente_principal']) && isset($_POST['cliente_secundario'])) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $mensaje_error = "Error de validación de seguridad. Inténtalo de nuevo.";
    } else {
        $cliente_principal = (int)$_POST['cliente_principal'];
        $cliente_secundario = (int)$_POST['cliente_secundario'];

        if ($cliente_principal === $cliente_secundario) {
            $mensaje_error = "No puedes fusionar un cliente consigo mismo.";
        } else {
            try {
                $stmtPrincipal = $pdo6->prepare("SELECT * FROM crm WHERE cliente_id = ? AND negocio_id = ?");
                $stmtPrincipal->execute([$cliente_principal, $negocio_id]);
                $clientePrincipal = $stmtPrincipal->fetch(PDO::FETCH_ASSOC);

                $stmtSecundario = $pdo6->prepare("SELECT * FROM crm WHERE cliente_id = ? AND negocio_id = ?");
                $stmtSecundario->execute([$cliente_secundario, $negocio_id]);
                $clienteSecundario = $stmtSecundario->fetch(PDO::FETCH_ASSOC);

                if (!$clientePrincipal || !$clienteSecundario) {
                    $mensaje_error = "Uno o ambos clientes no existen o no pertenecen a este negocio.";
                } elseif (empty($clientePrincipal['email']) || empty($clienteSecundario['email']) || 
                         $clientePrincipal['email'] !== $clienteSecundario['email']) {
                    $mensaje_error = "Solo se pueden fusionar clientes que tengan el mismo email.";
                } else {
                    $pdo6->beginTransaction();
                    
                    $nombre_principal_decrypted = decrypt_data($clientePrincipal['nombre']);
                    $nombre_secundario_decrypted = decrypt_data($clienteSecundario['nombre']);
                    $telefono_principal_decrypted = decrypt_data($clientePrincipal['telefono']);
                    $telefono_secundario_decrypted = decrypt_data($clienteSecundario['telefono']);
                    $notas_principal_decrypted = decrypt_data($clientePrincipal['notas']);
                    $notas_secundario_decrypted = decrypt_data($clienteSecundario['notas']);
                    
                    $nombre = !empty($nombre_principal_decrypted) ? $nombre_principal_decrypted : $nombre_secundario_decrypted;
                    $apellidos = !empty($clientePrincipal['apellidos']) ? $clientePrincipal['apellidos'] : $clienteSecundario['apellidos'];
                    $telefono = !empty($telefono_principal_decrypted) ? $telefono_principal_decrypted : $telefono_secundario_decrypted;
                    $fecha_nacimiento = !empty($clientePrincipal['fecha_nacimiento']) ? $clientePrincipal['fecha_nacimiento'] : $clienteSecundario['fecha_nacimiento'];
                    $notas = trim(($notas_principal_decrypted ?: '') . ' ' . ($notas_secundario_decrypted ?: ''));

                    $stmtUpdate = $pdo6->prepare("UPDATE crm SET nombre = ?, apellidos = ?, telefono = ?, fecha_nacimiento = ?, notas = ? WHERE cliente_id = ? AND negocio_id = ?");
                    $stmtUpdate->execute([encrypt_data($nombre), $apellidos, encrypt_data($telefono), $fecha_nacimiento, encrypt_data($notas), $cliente_principal, $negocio_id]);

                    $stmtDelete = $pdo6->prepare("DELETE FROM crm WHERE cliente_id = ? AND negocio_id = ?");
                    $stmtDelete->execute([$cliente_secundario, $negocio_id]);

                    $pdo6->commit();
                    $mensaje_exito = "Clientes fusionados correctamente.";
                }
            } catch (PDOException $e) {
                if ($pdo6->inTransaction()) {
                    $pdo6->rollBack();
                }
                $mensaje_error = "Error al fusionar los clientes: " . $e->getMessage();
            }
        }
    }
}

$busqueda = isset($_GET['busqueda']) ? sanitize_input($_GET['busqueda']) : '';

try {
    $stmtClientes = $pdo6->prepare("SELECT * FROM crm WHERE negocio_id = ? ORDER BY nombre ASC");
    $stmtClientes->execute([$negocio_id]);
    $todos_clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($todos_clientes as &$cliente) {
        $cliente['nombre'] = decrypt_data($cliente['nombre']);
        $cliente['telefono'] = decrypt_data($cliente['telefono']);
        $cliente['notas'] = decrypt_data($cliente['notas']);
    }
    
    if (!empty($busqueda)) {
        $clientes = array_filter($todos_clientes, function($cliente) use ($busqueda) {
            $busqueda_lower = strtolower($busqueda);
            return strpos(strtolower($cliente['nombre']), $busqueda_lower) !== false ||
                   strpos(strtolower($cliente['apellidos']), $busqueda_lower) !== false ||
                   strpos(strtolower($cliente['email']), $busqueda_lower) !== false ||
                   strpos(strtolower($cliente['telefono']), $busqueda_lower) !== false;
        });
    } else {
        $clientes = $todos_clientes;
    }

} catch (PDOException $e) {
    die('Error al obtener clientes: ' . $e->getMessage());
}

function procesarNuevoCliente() {
    global $pdo6, $user_id, $negocio_id;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' 
        && !isset($_POST['editar_cliente']) 
        && !isset($_POST['eliminar_cliente'])
        && isset($_POST['nombre'])) {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            return "<div class='alert alert-danger'>Error de validación de seguridad. Inténtalo de nuevo.</div>";
        }

        $nombre = sanitize_input($_POST['nombre']);
        $apellidos = sanitize_input($_POST['apellidos']);
        $telefono = sanitize_input($_POST['telefono']);
        $email = sanitize_input($_POST['email']);
        $fecha_nacimiento = sanitize_input($_POST['fecha_nacimiento']);
        $notas = sanitize_input($_POST['notas']);
        
        if (empty($nombre)) {
            return "<div class='alert alert-danger'>El nombre es obligatorio.</div>";
        }
        
        if (!empty($email) && !validate_email($email)) {
            return "<div class='alert alert-danger'>El formato del email no es válido.</div>";
        }
        
        if (!empty($telefono) && !validate_phone($telefono)) {
            return "<div class='alert alert-danger'>El formato del teléfono no es válido.</div>";
        }

        if (empty($telefono) && empty($email)) {
            return "<div class='alert alert-danger'>Debes proporcionar al menos un teléfono o un email.</div>";
        }

        try {
            $stmt = $pdo6->prepare("INSERT INTO crm (usuario_id, negocio_id, nombre, apellidos, telefono, email, fecha_nacimiento, notas, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $negocio_id, encrypt_data($nombre), $apellidos, encrypt_data($telefono), $email, $fecha_nacimiento, encrypt_data($notas)]);
            
            $mensaje = "<div class='alert alert-success'>Cliente añadido correctamente.</div>";
            $mensaje .= "<script>window.location.href = window.location.pathname;</script>";
            return $mensaje;
        } catch (PDOException $e) {
            error_log("Error en la base de datos: " . $e->getMessage());
            return "<div class='alert alert-danger'>Error al añadir el cliente. Por favor, inténtalo de nuevo.</div>";
        }
    }
    return "";
}

if (isset($_POST['accion']) && $_POST['accion'] === 'obtener_detalles_cliente') {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        echo json_encode(['error' => 'Error de validación de seguridad.']);
        exit;
    }

    $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;

    if ($cliente_id <= 0) {
        echo json_encode(['error' => 'Datos inválidos.']);
        exit;
    }

    try {
        $stmtCliente = $pdo6->prepare("SELECT * FROM crm WHERE cliente_id = ? AND negocio_id = ?");
        $stmtCliente->execute([$cliente_id, $negocio_id]);
        $cliente_data = $stmtCliente->fetch(PDO::FETCH_ASSOC);

        if (!$cliente_data) {
            echo json_encode(['error' => 'Cliente no encontrado o no pertenece a este negocio.']);
            exit;
        }

        $cliente_data['numero_reservas'] = $cliente_data['numero_reservas'] ?? 0;
        $cliente_data['importe_gastado'] = $cliente_data['importe_gastado'] ?? 0;
        
        $cliente_data['nombre'] = decrypt_data($cliente_data['nombre']);
        $cliente_data['telefono'] = decrypt_data($cliente_data['telefono']);
        $cliente_data['notas'] = decrypt_data($cliente_data['notas']);
        
        echo json_encode(['success' => true, 'cliente' => $cliente_data]);
        exit;

    } catch (PDOException $e) {
        error_log("Error AJAX obtener_detalles_cliente: " . $e->getMessage());
        echo json_encode(['error' => 'Error al obtener los detalles del cliente.']);
        exit;
    }
}

function obtenerClientesDuplicados($negocio_id) {
    global $pdo6;
    try {
        $stmt = $pdo6->prepare("
            SELECT email, COUNT(*) as total, GROUP_CONCAT(cliente_id) as ids
            FROM crm 
            WHERE negocio_id = ? AND email IS NOT NULL AND email != ''
            GROUP BY email 
            HAVING COUNT(*) > 1
        ");
        $stmt->execute([$negocio_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

$duplicados = obtenerClientesDuplicados($negocio_id);
?>