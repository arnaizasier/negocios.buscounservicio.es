<?php
// Iniciar sesión con configuración segura
session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
]);

require_once '../../../../config.php';
require_once '../../../../db-publica.php';
require_once '../../../../db-venta_productos.php';

use Delight\Auth\Auth;

function enforceAuthAndRole($pdo, $requiredRole = 'negocio') {
    $auth = new Auth($pdo);
    if (!$auth->isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: /auth/login.php');
        exit;
    }
    $user_id = $auth->getUserId();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== $requiredRole) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Acceso denegado. No tienes permiso para acceder a esta página.'
        ];
        header('Location: /error.php');
        exit;
    }
    return $user_id;
}

$user_id = enforceAuthAndRole($pdo, 'negocio');
$auth = new Auth($pdo);

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener datos de negocios
$stmt_negocios = $pdo2->prepare("SELECT negocio_id, nombre, reservas, tipo_reserva, pago_reservas, espacios_reservas FROM negocios WHERE usuario_id = ?");
$stmt_negocios->execute([$user_id]);
$negocios_data = $stmt_negocios->fetchAll(PDO::FETCH_ASSOC);

if (empty($negocios_data)) {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'No tienes negocios registrados.'];
    header('Location: /error.php');
    exit;
}

// Determinar el negocio seleccionado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['negocio_selector'])) {
    // Si se envió el formulario de selección de negocio
    $negocio_id = (int)$_POST['negocio_id'];
    // Guardar en sesión para mantener la selección
    $_SESSION['selected_negocio_id'] = $negocio_id;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_direccion'])) {
    // Si se envió el formulario de guardar dirección, usar el negocio especificado en ese formulario
    $negocio_id = (int)$_POST['negocio_id'];
    $_SESSION['selected_negocio_id'] = $negocio_id;
} elseif (isset($_SESSION['selected_negocio_id'])) {
    // Usar el negocio guardado en sesión si existe
    $negocio_id = $_SESSION['selected_negocio_id'];
} else {
    // Por defecto, usar el primer negocio
    $negocio_id = $negocios_data[0]['negocio_id'];
    $_SESSION['selected_negocio_id'] = $negocio_id;
}

$negocio_seleccionado = array_filter($negocios_data, fn($n) => $n['negocio_id'] == $negocio_id)[0] ?? null;

$error_msg = '';
$success_msg = '';

// Procesar formulario de dirección de devolución
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_direccion'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Token CSRF inválido.";
    } else {
        $negocio_id = isset($_POST['negocio_id']) ? (int)$_POST['negocio_id'] : $negocio_id;
        $calle = strip_tags(trim($_POST['calle'] ?? ''));
        $piso = strip_tags(trim($_POST['piso'] ?? ''));
        $ciudad = strip_tags(trim($_POST['ciudad'] ?? ''));
        $codigo_postal = strip_tags(trim($_POST['codigo_postal'] ?? ''));

        $errors = [];
        // Validar calle
        if (empty($calle) || strlen($calle) > 100) {
            $errors[] = "La dirección es inválida (máximo 100 caracteres).";
        }
        // Validar ciudad
        if (empty($ciudad) || strlen($ciudad) > 100) {
            $errors[] = "La ciudad es inválida (máximo 100 caracteres).";
        }
        // Validar código postal (formato español: 5 dígitos, 01000 a 52999)
        if (!preg_match('/^(0[1-9]|[1-4][0-9]|5[0-2])[0-9]{3}$/', $codigo_postal)) {
            $errors[] = "El código postal es inválido (debe ser un número de 5 dígitos válido en España).";
        }

        if (empty($errors)) {
            try {
                $stmt_check = $pdo5->prepare("SELECT id FROM ajustes_tienda WHERE negocio_id = ?");
                $stmt_check->execute([$negocio_id]);
                $existing = $stmt_check->fetch();

                $sql = $existing
                    ? "UPDATE ajustes_tienda SET calle = ?, piso = ?, ciudad = ?, codigo_postal = ? WHERE negocio_id = ?"
                    : "INSERT INTO ajustes_tienda (negocio_id, usuario_id, calle, piso, ciudad, codigo_postal) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_update = $pdo5->prepare($sql);
                $params = $existing
                    ? [$calle, $piso, $ciudad, $codigo_postal, $negocio_id]
                    : [$negocio_id, $user_id, $calle, $piso, $ciudad, $codigo_postal];
                $stmt_update->execute($params);

                $success_msg = "Dirección de devolución guardada correctamente para el negocio seleccionado.";
            } catch (PDOException $e) {
                $error_msg = "Ocurrió un error al guardar la dirección.";
            }
        } else {
            $error_msg = implode("<br>", $errors);
        }
    }
}

// Obtener dirección de devolución actual
$direccion_devolucion = [];
if ($negocio_id) {
    try {
        $stmt_direccion = $pdo5->prepare("SELECT calle, piso, ciudad, codigo_postal FROM ajustes_tienda WHERE negocio_id = ?");
        $stmt_direccion->execute([$negocio_id]);
        $direccion_devolucion = $stmt_direccion->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        // Eliminar el registro de logs
        // error_log("PDO Error fetching return address: " . $e->getMessage(), 3, '/home/u898735099/domains/buscounservicio.es/logs/error.log');
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes de Tienda - Panel Tienda</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include_once '../../../assets/includes/sidebar.php'; ?>
    <div id="main-content">
        <div class="container">
            <h1>Ajustes de Tienda</h1>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (count($negocios_data) > 1): ?>
                <form method="POST" action="">
                    <input type="hidden" name="negocio_selector" value="1">
                    <label for="negocio_id">Seleccionar Negocio:</label>
                    <select id="negocio_id" name="negocio_id" onchange="this.form.submit()">
                        <?php foreach ($negocios_data as $negocio): ?>
                            <option value="<?php echo $negocio['negocio_id']; ?>" <?php echo $negocio['negocio_id'] == $negocio_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($negocio['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
            <h2>Dirección para Devoluciones</h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="negocio_id" value="<?php echo $negocio_id; ?>">
                <label for="calle">Dirección:</label>
                <input type="text" id="calle" name="calle" value="<?php echo htmlspecialchars($direccion_devolucion['calle'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                <label for="piso">Dirección 2:</label>
                <input type="text" id="piso" name="piso" value="<?php echo htmlspecialchars($direccion_devolucion['piso'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <label for="ciudad">Ciudad:</label>
                <input type="text" id="ciudad" name="ciudad" value="<?php echo htmlspecialchars($direccion_devolucion['ciudad'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                <label for="codigo_postal">Código Postal:</label>
                <input type="text" id="codigo_postal" name="codigo_postal" value="<?php echo htmlspecialchars($direccion_devolucion['codigo_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                <button type="submit" name="guardar_direccion" class="btn btn-guardar">Guardar Dirección</button>
            </form>
        </div>
    </div>
</body>
</html>