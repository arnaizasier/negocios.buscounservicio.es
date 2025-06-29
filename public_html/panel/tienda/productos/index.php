<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../../../../config.php';
require_once '../../../../db-publica.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

try {
    $stmt = $pdo2->prepare("SELECT * FROM productos WHERE usuario_id = ? ORDER BY nombre ASC");
    $stmt->execute([$user_id]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener productos: " . $e->getMessage());
    $productos = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Productos</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="../../../assets/css/sidebar.css" rel="stylesheet">
    <link href="../../../assets/css/marca.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <div class="container45">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar.php'; ?>
        <div class="content45" id="content45">
            <div class="main-container">
                <div class="header-flex">
                    <h1 class="mb-0">Mis Productos</h1>
                    <a href="gestionar-producto.php" class="btn btn-primary">Añadir Producto</a>
                </div>
                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Imagen</th>
                                <th>Nombre</th>
                                <th>Precio</th>
                                <th>Unidades</th>
                                <th>SKU</th>
                                <th>Visible</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr><td colspan="8" class="text-center">No hay productos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                    <tr>
                                        <td data-label="ID"><?php echo htmlspecialchars($producto['producto_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Imagen">
                                            <?php
                                            if ($producto['url_imagenes']) {
                                                $imagenes = explode(',', $producto['url_imagenes']);
                                                $img_path = trim($imagenes[0]);
                                                
                                                if (!empty($img_path) && strpos($img_path, '..') === false && strpos($img_path, '//') === false) {
                                                    $img_url = 'https://buscounservicio.es/' . ltrim($img_path, '/');
                                                    echo '<img src="' . htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8') . '" alt="Producto" class="table-img" onerror="this.style.display=\'none\'">';
                                                } else {
                                                    echo 'Imagen no válida';
                                                }
                                            } else {
                                                echo 'Sin imagen';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Nombre"><?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Precio"><?php echo number_format((float)$producto['precio'], 2); ?> €</td>
                                        <td data-label="Unidades"><?php echo intval($producto['unidades']); ?></td>
                                        <td data-label="SKU"><?php echo htmlspecialchars($producto['sku'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Visible"><?php echo $producto['visible'] ? 'Sí' : 'No'; ?></td>
                                        <td data-label="Acciones">
                                            <a href="gestionar-producto.php?id=<?php echo intval($producto['producto_id']); ?>" class="btn btn-warning btn-sm">Editar</a>
                                            <form action="eliminar_producto.php" method="POST" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="producto_id" value="<?php echo intval($producto['producto_id']); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="../../../assets/js/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.querySelectorAll('.inline-form').forEach(form => {
            form.addEventListener('submit', function(event) {
                event.preventDefault(); 
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¡No podrás revertir esto!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminarlo!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit(); 
                    }
                });
            });
        });
    </script>
</body>
</html>