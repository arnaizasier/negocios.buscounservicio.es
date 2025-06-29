<?php
session_start();

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";
require_once __DIR__ . "/../../../db-venta_productos.php";

use Delight\Auth\Auth;

$auth = new Auth($pdo);

if (!$auth->isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

$user_id = $auth->getUserId();
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user['role'] !== 'negocio') {
    echo "<div class='alert alert-danger'>Acceso denegado. Solo los negocios pueden acceder.</div>";
    exit;
}

$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();
$nombre_negocio = $usuario['username'] ?? 'Negocio';

try {
    $stmt = $pdo5->prepare("SELECT * FROM ventas WHERE id_usuario = ? AND estado = 'completado' ORDER BY fecha DESC");
    $stmt->execute([$user_id]);
    $pedidos = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al recuperar datos: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_estado'])) {
    $id_venta = $_POST['id_venta'] ?? 0;
    $nuevo_estado = $_POST['nuevo_estado'] ?? '';
    
    if ($id_venta && $nuevo_estado) {
        try {
            $stmt = $pdo5->prepare("UPDATE ventas SET estado_pedido = ? WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$nuevo_estado, $id_venta, $user_id]);
            
            header('Location: pedidos.php?success=1');
            exit;
        } catch (PDOException $e) {
            $error = "Error al actualizar estado: " . $e->getMessage();
        }
    }
}

$mostrar_detalle = false;
$detalle_pedido = null;
$detalles_productos = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_venta = $_GET['id'];
    
    try {
        $stmt = $pdo5->prepare("SELECT * FROM ventas WHERE id = ? AND id_usuario = ? AND estado = 'completado'");
        $stmt->execute([$id_venta, $user_id]);
        $detalle_pedido = $stmt->fetch();
        
        if ($detalle_pedido) {
            $mostrar_detalle = true;
            
            if (!empty($detalle_pedido['id_cliente'])) {
                $stmt_user = $pdo->prepare("SELECT email, first_name, last_name, phone FROM users WHERE id = ?");
                $stmt_user->execute([$detalle_pedido['id_cliente']]);
                $cliente_info = $stmt_user->fetch();
                
                if ($cliente_info) {
                    $detalle_pedido = array_merge($detalle_pedido, $cliente_info);
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Error al recuperar detalles del pedido: " . $e->getMessage();
    }
}

if ($detalle_pedido && isset($detalle_pedido['id_producto'])) {
    try {
        $stmt_producto = $pdo2->prepare("SELECT nombre, url_imagenes FROM productos WHERE producto_id = ?");
        $stmt_producto->execute([$detalle_pedido['id_producto']]);
        $producto_info = $stmt_producto->fetch();

        if ($producto_info) {
            $detalle_pedido['nombre_producto'] = $producto_info['nombre'];
            $imagenes = explode(',', $producto_info['url_imagenes']);
            $detalle_pedido['imagen_producto'] = $imagenes[0];
        }
    } catch (PDOException $e) {
        $error = "Error al recuperar detalles del producto: " . $e->getMessage();
    }
}

foreach ($pedidos as &$pedido) {
    try {
        $stmt_producto = $pdo2->prepare("SELECT nombre FROM productos WHERE producto_id = ?");
        $stmt_producto->execute([$pedido['id_producto']]);
        $producto_info = $stmt_producto->fetch();
        
        if ($producto_info) {
            $pedido['nombre_producto'] = $producto_info['nombre'];
        } else {
            $pedido['nombre_producto'] = 'Producto no encontrado';
        }
    } catch (PDOException $e) {
        $pedido['nombre_producto'] = 'Error al cargar producto';
    }
}

$titulo_pagina = $mostrar_detalle ? "Detalle del Pedido #{$detalle_pedido['numero_pedido']}" : "Gestión de Pedidos";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?> - Panel de Negocio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <link href="../../assets/css/marca.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f6f9;
            color: #333;
        }

        .container45 {
            display: flex;
            min-height: 100vh;
        }

        .content45 {
            flex: 1;
            padding: 20px;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 16px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-flex h1 {
            font-size: 2rem;
            font-family: "Poppins1", Sans-Serif;
            font-weight: 700;
            color: #333;
        }

        .order-detail {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .order-detail .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background-color: #2755d3;
            color: #fff;
            border-radius: 25px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }

        .order-detail .btn-back:hover {
            background-color: #0056b3;
        }

        .order-detail .btn-back i {
            margin-right: 8px;
        }

        .cards-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        @media (max-width: 767px) {
            .cards-container {
                grid-template-columns: 1fr;
            }
        }

        .info-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .info-card h4 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
        }

        .info-card .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f1f1;
        }

        .info-card .info-item:last-child {
            border-bottom: none;
        }

        .info-card .info-item label {
            font-weight: bold;
            color: #555;
            flex: 1;
        }

        .info-card .info-item span {
            flex: 2;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .bg-success {
            background-color: #28a745;
            color: #fff;
        }

        .bg-warning {
            background-color: #ff8728;
            color: #fff;
        }

        .bg-primary {
            background-color: #007bff;
            color: #fff;
        }

        .bg-danger {
            background-color: #dc3545;
            color: #fff;
        }

        .product-image {
            max-width: 200px;
            border-radius: 8px;
            margin-top: 10px;
            display: block;
        }

        .form-card .info-item select {
            flex: 2;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
        }

        .form-card .btn-primary {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border-radius: 25px;
            background-color: #2755d3;
            border: none;
            color: #fff;
            margin-top: 10px;
            transition: background-color 0.3s;
        }

        .form-card .btn-primary:hover {
            background-color: #0056b3;
        }

        @media (max-width: 767px) {
            .info-card .info-item {
                flex-direction: column;
            }

            .info-card .info-item label,
            .info-card .info-item span,
            .info-card .info-item select {
                flex: none;
                margin-bottom: 5px;
            }

            .product-image {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container45">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar.php'; ?>
        <div class="content45" id="content45">
            <div class="main-container">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">La operación se ha realizado con éxito.</div>
                <?php endif; ?>
                
                <div class="header-flex">
                    <h1><?php echo $titulo_pagina; ?></h1>
                </div>
                
                <?php if ($mostrar_detalle && $detalle_pedido): ?>
                <div class="order-detail">
                    <a href="pedidos" class="btn-back"><i class="fas fa-arrow-left"></i> Volver a todos los pedidos</a>
                    
                    <div class="cards-container">
                        <div class="info-card">
                            <h4>Información del Pedido</h4>
                            <div class="info-item">
                                <label>Número de Pedido:</label>
                                <span><?php echo htmlspecialchars($detalle_pedido['numero_pedido']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Fecha:</label>
                                <span><?php echo date('d/m/Y H:i', strtotime($detalle_pedido['fecha'])); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Importe Total:</label>
                                <span><?php echo number_format($detalle_pedido['cantidad_total'], 2); ?> €</span>
                            </div>
                            <div class="info-item">
                                <label>Coste Producto:</label>
                                <span><?php echo number_format($detalle_pedido['precio_producto'], 2); ?> €</span>
                            </div>
                            <div class="info-item">
                                <label>Coste Envío:</label>
                                <span><?php echo number_format($detalle_pedido['precio_envio'], 2); ?> €</span>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <h4>Información del Cliente</h4>
                            <div class="info-item">
                                <label>Nombre:</label>
                                <span><?php echo htmlspecialchars(($detalle_pedido['first_name'] ?? '') . ' ' . ($detalle_pedido['last_name'] ?? '')); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Email:</label>
                                <span><?php echo htmlspecialchars($detalle_pedido['email'] ?? 'No disponible'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Teléfono:</label>
                                <span><?php echo htmlspecialchars($detalle_pedido['phone'] ?? 'No disponible'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Dirección de Envío:</label>
                                <span><?php echo htmlspecialchars(($detalle_pedido['direccion'] ?? '') . ', ' . ($detalle_pedido['codido_postal'] ?? '') . ', ' . ($detalle_pedido['pais'] ?? '')); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <h4>Información del Producto</h4>
                            <div class="info-item">
                                <label>Nombre del Producto:</label>
                                <span><?php echo htmlspecialchars($detalle_pedido['nombre_producto'] ?? 'No disponible'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Imagen del Producto:</label>
                                <span>
                                    <?php if (!empty($detalle_pedido['imagen_producto'])): ?>
                                        <img src="<?php echo 'https://buscounservicio.es/' . htmlspecialchars($detalle_pedido['imagen_producto']); ?>" alt="Imagen del Producto" class="product-image">
                                    <?php else: ?>
                                        No disponible
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-card form-card">
                            <h4>Actualizar Estado del Envío</h4>
                            <form method="POST" action="pedidos.php">
                                <input type="hidden" name="id_venta" value="<?php echo $detalle_pedido['id']; ?>">
                                
                                <div class="info-item">
                                    <label for="nuevo_estado">Nuevo Estado:</label>
                                    <select name="nuevo_estado" id="nuevo_estado" required>
                                        <option value="">Seleccionar estado</option>
                                        <option value="Pendiente" <?php if($detalle_pedido['estado_pedido'] === 'Pendiente') echo 'selected'; ?>>Pendiente</option>
                                        <option value="Enviado" <?php if($detalle_pedido['estado_pedido'] === 'Enviado') echo 'selected'; ?>>Enviado</option>
                                        <option value="Recibido" <?php if($detalle_pedido['estado_pedido'] === 'Recibido') echo 'selected'; ?>>Recibido</option>
                                        <option value="Incidencia" <?php if($detalle_pedido['estado_pedido'] === 'Incidencia') echo 'selected'; ?>>Incidencia</option>
                                    </select>
                                </div>
                                
                                <button type="submit" name="actualizar_estado" class="btn btn-primary">Actualizar Estado</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <?php if (count($pedidos) > 0): ?>
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Número Pedido</th>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th>Importe</th>
                                    <th>Estado Envío</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td data-label="Número Pedido"><?php echo htmlspecialchars($pedido['numero_pedido']); ?></td>
                                    <td data-label="Fecha"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></td>
                                    <td data-label="Producto"><?php echo htmlspecialchars($pedido['nombre_producto'] ?? 'No disponible'); ?></td>
                                    <td data-label="Importe"><?php echo number_format($pedido['cantidad_total'], 2); ?> €</td>
                                    <td data-label="Estado Envío">
                                        <span class="badge 
                                            <?php 
                                            if ($pedido['estado_pedido'] === 'Pendiente') echo 'bg-warning';
                                            elseif ($pedido['estado_pedido'] === 'Enviado') echo 'bg-primary';
                                            elseif ($pedido['estado_pedido'] === 'Recibido') echo 'bg-success';
                                            elseif ($pedido['estado_pedido'] === 'Incidencia') echo 'bg-danger';
                                            ?>">
                                            <?php echo htmlspecialchars($pedido['estado_pedido']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Acciones">
                                        <a href="pedidos.php?id=<?php echo $pedido['id']; ?>" class="btn btn-primary btn-sm">Ver Pedido</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">No tienes pedidos registrados actualmente.</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/sidebar.js"></script>
</body>
</html>