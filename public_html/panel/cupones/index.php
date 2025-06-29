<?php
require_once __DIR__ . "/../../src/sesiones-seguras.php";
session_start();

require_once __DIR__ . "/../../src/rate-limiting.php";
require_once __DIR__ . "/../../src/headers-seguridad.php";

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";
require_once __DIR__ . "/../../src/obtener-negocios-premium-usuario.php";


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $stmt = $pdo2->prepare("
        SELECT c.*, n.nombre as negocio_nombre
        FROM cupones c
        JOIN negocios n ON c.negocio_id = n.negocio_id
        WHERE c.usuario_id = ?
        ORDER BY n.nombre ASC, c.caducidad DESC
    ");
    $stmt->execute([$user_id]);
    $cupones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    exit('Error al cargar los cupones');
}

$cupones_por_negocio = [];
foreach ($cupones as $cupon) {
    $cupones_por_negocio[$cupon['negocio_id']]['nombre'] = $cupon['negocio_nombre'];
    $cupones_por_negocio[$cupon['negocio_id']]['cupones'][] = $cupon;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupones</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <link href="../../assets/css/marca.css" rel="stylesheet">
    <link href="cupones.css" rel="stylesheet">
</head>
<body>
<div class="container45">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar.php'; ?>
    <div class="content45" id="content45">
        <div class="content-wrapper">
            <div class="header-flexx">
                <h1>Cupones</h1>
                <a href="gestionar_cupon" class="btn btn-primary">Añadir Cupón</a>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            <?php if (empty($cupones_por_negocio)): ?>
                <div class="alert alert-info">No hay cupones registrados.</div>
            <?php else: ?>
                <?php foreach ($cupones_por_negocio as $negocio_id => $data): ?>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Estado</th>
                                    <th>Descripción</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                    <th>Límite Usos</th>
                                    <th>Veces Usado</th>
                                    <th>Gasto Mínimo</th>
                                    <th>Caducidad</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['cupones'] as $cupon): ?>
                                    <tr>
                                        <td data-label="Código"><?php echo htmlspecialchars($cupon['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Estado">
                                            <?php
                                            $badge_class = 'badge-activo';
                                            $estado = $cupon['estado'];
                                            if (!empty($cupon['caducidad']) && strtotime($cupon['caducidad']) < time()) {
                                                $badge_class = 'badge-caducado';
                                                $estado = 'caducado';
                                            } elseif ($cupon['estado'] == 'inactivo') {
                                                $badge_class = 'badge-inactivo';
                                            }
                                            ?>
                                            <span class="badge <?php echo htmlspecialchars($badge_class); ?>">
                                                <?php echo ucfirst(htmlspecialchars($estado)); ?>
                                            </span>
                                        </td>
                                        <td data-label="Descripción"><?php echo htmlspecialchars($cupon['descripcion'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Tipo"><?php echo ucfirst(htmlspecialchars($cupon['tipo'])); ?></td>
                                        <td data-label="Monto" style="white-space: nowrap;">
                                            <?php
                                            if (empty($cupon['monto'])) {
                                                echo 'N/A';
                                            } else {
                                                if ($cupon['tipo'] === 'Porcentaje') {
                                                    echo htmlspecialchars($cupon['monto']) . ' %';
                                                } else {
                                                    echo number_format($cupon['monto'], 2) . ' €';
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Límite Usos"><?php echo $cupon['limite_usos'] == 0 ? 'Ilimitado' : htmlspecialchars($cupon['limite_usos']); ?></td>
                                        <td data-label="Veces Usado"><?php echo htmlspecialchars($cupon['veces_usado']); ?></td>
                                        <td data-label="Gasto Mínimo"><?php echo empty($cupon['gasto_minimo']) ? 'Sin mínimo' : number_format($cupon['gasto_minimo'], 2) . ' €'; ?></td>
                                        <td data-label="Caducidad"><?php echo !empty($cupon['caducidad']) ? date('d/m/Y', strtotime($cupon['caducidad'])) : 'Sin caducidad'; ?></td>
                                        <td data-label="Acciones">
                                            <a href="gestionar_cupon?id=<?php echo urlencode($cupon['id']); ?>" class="btn btn-edit">Editar</a>
                                            <form action="eliminar_cupon.php" method="POST" class="inline-form" style="display:inline;">
                                                <input type="hidden" name="cupon_id" value="<?php echo htmlspecialchars($cupon['id']); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <button type="submit" class="btn btn-delete">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Custom Confirmation Modal -->
<div id="custom-confirm-modal" class="modal-overlay">
    <div class="modal-content">
        <h2>¿Estás seguro?</h2>
        <p>Esta acción no se puede deshacer.</p>
        <div class="modal-buttons">
            <button id="confirm-cancel-btn" class="btn btn-cancel">Cancelar</button>
            <button id="confirm-delete-btn" class="btn btn-delete">Sí, eliminar</button>
        </div>
    </div>
</div>

<script src="../../assets/js/sidebar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('custom-confirm-modal');
    const cancelBtn = document.getElementById('confirm-cancel-btn');
    const confirmBtn = document.getElementById('confirm-delete-btn');
    let formToSubmit = null;

    document.querySelectorAll('.inline-form').forEach(form => {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            formToSubmit = form;
            modal.classList.add('show');
        });
    });

    function hideModal() {
        modal.classList.remove('show');
        formToSubmit = null;
    }

    cancelBtn.addEventListener('click', hideModal);

    confirmBtn.addEventListener('click', function() {
        if (formToSubmit) {
            const submitButton = formToSubmit.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
            formToSubmit.submit();
        }
        hideModal();
    });

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            hideModal();
        }
    });
});
</script>
</body>
</html>