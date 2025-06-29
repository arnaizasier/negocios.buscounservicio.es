<?php
require_once __DIR__ . "/../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../src/rate-limiting.php";
require_once __DIR__ . "/../../src/headers-seguridad.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";
require_once __DIR__ . "/../../src/obtener-negocios-premium-usuario.php";

// Determinar si es edición o creación
$cupon_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit = ($cupon_id !== false && $cupon_id > 0);
$cupon = null;

// Si es edición, obtener datos del cupón
if ($is_edit) {
    $stmt = $pdo2->prepare("SELECT * FROM cupones WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$cupon_id, $user_id]);
    $cupon = $stmt->fetch();

    if (!$cupon) {
        echo "<div class='alert alert-danger'>Cupón no encontrado o no tienes permisos.</div>";
        exit;
    }
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Error de validación CSRF. Inténtalo de nuevo.';

    } else {
        $negocio_id = filter_input(INPUT_POST, 'negocio_id', FILTER_SANITIZE_NUMBER_INT);
        $codigo = trim(filter_input(INPUT_POST, 'codigo', FILTER_UNSAFE_RAW));
        $descripcion = trim(filter_input(INPUT_POST, 'descripcion', FILTER_UNSAFE_RAW));
        $tipo = filter_input(INPUT_POST, 'tipo', FILTER_UNSAFE_RAW);
        $monto_str = filter_input(INPUT_POST, 'monto', FILTER_UNSAFE_RAW);
        $monto = filter_var($monto_str, FILTER_VALIDATE_FLOAT);
        $limite_usos_str = filter_input(INPUT_POST, 'limite_usos', FILTER_UNSAFE_RAW);
        $limite_usos = filter_var($limite_usos_str, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $gasto_minimo_str = filter_input(INPUT_POST, 'gasto_minimo', FILTER_UNSAFE_RAW);
        $gasto_minimo = ($gasto_minimo_str !== '' && $gasto_minimo_str !== null) ? filter_var($gasto_minimo_str, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]) : null;
        $caducidad = filter_input(INPUT_POST, 'caducidad', FILTER_UNSAFE_RAW); 

        if (empty($negocio_id)) $errors[] = 'Debes seleccionar un negocio.';
        if (empty($codigo)) $errors[] = 'El código del cupón es obligatorio.';
        if (!in_array($tipo, ['porcentaje', 'Fijo'])) $errors[] = 'Tipo de descuento inválido.';
        if ($monto === false || $monto < 0) $errors[] = 'El monto del descuento no es válido.';
        if ($tipo === 'porcentaje' && ($monto < 1 || $monto > 100)) $errors[] = 'El porcentaje debe estar entre 1 y 100.';
        if ($tipo === 'Fijo' && $monto < 0.01) $errors[] = 'El monto fijo debe ser mayor a 0.'; 
        if ($limite_usos === false) $errors[] = 'El límite de usos no es válido.';
        if ($gasto_minimo !== null && ($gasto_minimo === false || $gasto_minimo < 0)) $errors[] = 'El gasto mínimo no es válido.';
        if (empty($caducidad)) $errors[] = 'La fecha de caducidad es obligatoria.';

        $date_format = 'Y-m-d';
        $d = DateTime::createFromFormat($date_format, $caducidad);
        if (!$d || $d->format($date_format) !== $caducidad) {
            $errors[] = 'El formato de la fecha de caducidad no es válido. Usa YYYY-MM-DD.';
        }


        // Verificar código único
        if ($codigo && $negocio_id && empty($errors)) { 
            if ($is_edit) {
                $stmt = $pdo2->prepare("SELECT COUNT(*) FROM cupones WHERE codigo = ? AND negocio_id = ? AND id != ?");
                $stmt->execute([$codigo, $negocio_id, $cupon_id]);
            } else {
                $stmt = $pdo2->prepare("SELECT COUNT(*) FROM cupones WHERE codigo = ? AND negocio_id = ?");
                $stmt->execute([$codigo, $negocio_id]);
            }
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Ya existe un cupón con ese código para este negocio.';
            }
        }

        if (empty($errors)) {
            if ($is_edit) {
                // Actualizar cupón existente
                $stmt = $pdo2->prepare("UPDATE cupones SET negocio_id=?, codigo=?, descripcion=?, tipo=?, monto=?, limite_usos=?, gasto_minimo=?, caducidad=? WHERE id=? AND usuario_id=?");
                $stmt->execute([
                    $negocio_id,
                    $codigo,
                    $descripcion,
                    $tipo,
                    $monto,
                    $limite_usos,
                    $gasto_minimo,
                    $caducidad,
                    $cupon_id,
                    $user_id
                ]);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: index.php?editado=1');
                exit;
            } else {
                // Crear nuevo cupón
                $stmt = $pdo2->prepare("INSERT INTO cupones (usuario_id, negocio_id, codigo, descripcion, tipo, monto, limite_usos, veces_usado, gasto_minimo, caducidad, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'activo')");
                $stmt->execute([
                    $user_id,
                    $negocio_id,
                    $codigo,
                    $descripcion,
                    $tipo,
                    $monto,
                    $limite_usos,
                    $gasto_minimo,
                    $caducidad
                ]);
                
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: index.php?creado=1');
                exit;
            }
        }
    }
} else {
    // Si es edición, cargar datos del cupón
    if ($is_edit) {
        $negocio_id = $cupon['negocio_id'];
        $codigo = $cupon['codigo'];
        $descripcion = $cupon['descripcion'];
        $tipo = $cupon['tipo'];
        $monto = $cupon['monto'];
        $limite_usos = $cupon['limite_usos'];
        $gasto_minimo = $cupon['gasto_minimo'];
        $caducidad = $cupon['caducidad'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Editar' : 'Crear' ?> Cupón</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #fff;
            color: #333;
        }
        .container45 {
            display: flex;
            flex-direction: row;
            width: 100%;
            height: 100vh;
        }
        .content45 {
            width: 100%;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: #2c3e50;
            text-align: center;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 1rem;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert ul { list-style: none; padding: 0; }
        .alert li { margin-bottom: 5px; }
        .form-block {
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 15px;
            background-color: #fff;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .mb-3 { display: flex; flex-direction: column; gap: 8px; }
        .form-label { font-weight: 600; color: #333; font-size: 1rem; }
        .form-control, .form-select {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #2755d3;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .btn {
            display: inline-block;
            padding: 12px 20px;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            color: #fff;
            background-color: #2755d3;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-right: 10px;
            line-height: 1;
            height: 40px;
            text-decoration: none;
        }
        .btn:hover { background-color: #e56d1a; }
        .btn-secondary { background-color: #6c757d; }
        .btn-secondary:hover { background-color: #5a6268; }
        @media (max-width: 600px) {
            .container45 { flex-direction: column; }
            .content45 { padding: 15px; }
            .container { margin: 20px; padding: 15px; }
            h1 { font-size: 1.5rem; }
            .form-control, .form-select { font-size: 0.9rem; }
            .btn { padding: 10px; font-size: 0.9rem; height: 36px; }
        }
    </style>
</head>
<body>
    <div class="container45">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar.php'; ?>
        <div class="content45" id="content45">
            <div class="container">
                <h1><?= $is_edit ? 'Editar' : 'Crear' ?> Cupón</h1>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">¡Cupón <?= $is_edit ? 'actualizado' : 'creado' ?> correctamente!</div>
                <?php endif; ?>
                <?php if (empty($negocios_usuario)): ?>
                    <div class="alert alert-warning">No tienes negocios registrados. Por favor, crea un negocio primero.</div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="form-block">
                        <div class="mb-3">
                            <label for="negocio_id" class="form-label">Negocio</label>
                            <select name="negocio_id" id="negocio_id" class="form-select" required>
                                <option value="">Selecciona un negocio</option>
                                <?php foreach ($negocios_usuario as $negocio): ?>
                                    <option value="<?= $negocio['negocio_id'] ?>" <?= isset($negocio_id) && $negocio_id == $negocio['negocio_id'] ? 'selected' : '' ?>><?= htmlspecialchars($negocio['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="codigo" class="form-label">Código del Cupón</label>
                            <input type="text" name="codigo" id="codigo" class="form-control" value="<?= htmlspecialchars($codigo ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción <span style="font-weight:normal;color:#888;">(opcional)</span></label>
                            <textarea name="descripcion" id="descripcion" class="form-control"><?= htmlspecialchars($descripcion ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo de Descuento</label>
                            <select name="tipo" id="tipo" class="form-select" required>
                                <option value="porcentaje" <?= (isset($tipo) && $tipo == 'porcentaje') ? 'selected' : '' ?>>Porcentaje (%)</option>
                                <option value="Fijo" <?= (isset($tipo) && $tipo == 'Fijo') ? 'selected' : '' ?>>Monto Fijo (€)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="monto" class="form-label">Valor del Descuento</label>
                            <input type="number" name="monto" id="monto" class="form-control" min="1" step="0.01" value="<?= htmlspecialchars($monto ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="limite_usos" class="form-label">Límite de Usos</label>
                            <div style="display:flex;align-items:center;gap:10px; margin-bottom:12px;">
                                <input type="number" name="limite_usos" id="limite_usos" class="form-control" min="0" style="margin-bottom:0;" value="<?= htmlspecialchars($limite_usos ?? '0') ?>">
                                <span style="font-size:0.95em;color:#777;">0 para usos ilimitados</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="gasto_minimo" class="form-label">Gasto Mínimo (opcional)</label>
                            <input type="number" name="gasto_minimo" id="gasto_minimo" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($gasto_minimo ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="caducidad" class="form-label">Fecha de Caducidad</label>
                            <input type="date" name="caducidad" id="caducidad" class="form-control" value="<?= htmlspecialchars($caducidad ?? '') ?>" required>
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="btn"><?= $is_edit ? 'Guardar Cambios' : 'Crear Cupón' ?></button>
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="../../assets/js/sidebar.js"></script>
</body>
</html>