<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-publica.php';
require_once '../../../../db-venta_productos.php';
require_once '../../../../db-crm.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<p class="text-danger">ID de reserva no proporcionado.</p>';
    exit;
}

$id_reserva = intval($_GET['id']);

$sql = "SELECT * FROM reservas WHERE id_reserva = :id_reserva";
$stmt = $pdo5->prepare($sql);
$stmt->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);
$stmt->execute();
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    echo '<p class="text-danger">Reserva no encontrada o no tiene permiso para verla.</p>';
    exit;
}

$sql_negocio = "SELECT 1 FROM negocios WHERE negocio_id = ? AND usuario_id = ?";
$stmt_negocio = $pdo2->prepare($sql_negocio);
$stmt_negocio->execute([$reserva['id_negocio'], $user_id]);
$es_dueno = $stmt_negocio->fetchColumn();

if (!$es_dueno) {
    echo '<p class="text-danger">No tienes permiso para ver esta reserva.</p>';
    exit;
}

// Buscar información del cliente
$cliente = null;

// Si id_cliente tiene valor válido, buscar en la tabla users
if (!empty($reserva['id_cliente']) && $reserva['id_cliente'] > 0) {
    $sql_cliente = "SELECT first_name, last_name, email, phone FROM users WHERE id = ?";
    $stmt_cliente = $pdo->prepare($sql_cliente);
    $stmt_cliente->execute([$reserva['id_cliente']]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
    
    if ($cliente) {
        $reserva['nombre_cliente'] = $cliente['first_name'];
        $reserva['apellidos_cliente'] = $cliente['last_name'];
        $reserva['email_cliente'] = $cliente['email'];
        $reserva['telefono_cliente'] = $cliente['phone'];
    }
}

// Si no se encontró cliente en users o id_cliente es NULL/0, buscar en CRM
$cliente_crm = null;
if (!$cliente && !empty($reserva['id_cliente_crm'])) {
    $sql_cliente_crm = "SELECT nombre, apellidos, telefono, email FROM crm WHERE cliente_id = ?";
    $stmt_cliente_crm = $pdo6->prepare($sql_cliente_crm);
    $stmt_cliente_crm->execute([$reserva['id_cliente_crm']]);
    $cliente_crm = $stmt_cliente_crm->fetch(PDO::FETCH_ASSOC);
    
    if ($cliente_crm) {
        $reserva['nombre_cliente'] = decrypt_data($cliente_crm['nombre']);
        $reserva['apellidos_cliente'] = $cliente_crm['apellidos'];
        $reserva['email_cliente'] = $cliente_crm['email'];
        $reserva['telefono_cliente'] = decrypt_data($cliente_crm['telefono']);
    }
}

// Si no se encontró cliente en ninguna tabla, usar valores por defecto
if (!$cliente && !$cliente_crm) {
    $reserva['nombre_cliente'] = 'Cliente';
    $reserva['apellidos_cliente'] = 'Desconocido';
    $reserva['email_cliente'] = 'No disponible';
    $reserva['telefono_cliente'] = 'No disponible';
}

// Buscar información del trabajador
$trabajador = null;
if (!empty($reserva['id_trabajador']) && $reserva['id_trabajador'] > 0) {
    $sql_trabajador = "SELECT nombre, apellido FROM trabajadores WHERE id = ?";
    $stmt_trabajador = $pdo2->prepare($sql_trabajador);
    $stmt_trabajador->execute([$reserva['id_trabajador']]);
    $trabajador = $stmt_trabajador->fetch(PDO::FETCH_ASSOC);
}

$fecha_inicio = new DateTime($reserva['fecha_inicio']);
$fecha_fin = new DateTime($reserva['fecha_fin']);
$fecha_reserva = new DateTime($reserva['fecha_reserva']);

$clase_estado = '';
switch(strtolower($reserva['estado_reserva'] ?? '')) {
    case 'confirmada':
        $clase_estado = 'text-success';
        break;
    case 'pendiente':
        $clase_estado = 'text-warning';
        break;
    case 'cancelada':
        $clase_estado = 'text-danger';
        break;
    default:
        $clase_estado = 'text-secondary';
}

$clase_pago = strtolower($reserva['estado_pago'] ?? '') == 'pagado' ? 'text-success' : 'text-danger';

?>

<div class="row">
    <div class="col-md-6">
        <h3>Reserva</h3>
        <p><strong>Servicio:</strong> <?php echo htmlspecialchars($reserva['servicio'] ?? ''); ?></p>
        <p><strong>Duración:</strong> <?php echo htmlspecialchars($reserva['duracion'] ?? ''); ?> minutos</p>
        <p><strong>Precio:</strong> <?php echo number_format($reserva['precio'] ?? 0, 2); ?> €</p>
        <p><strong>Fecha y hora:</strong> <?php echo $fecha_inicio->format('d/m/Y H:i'); ?> - <?php echo $fecha_fin->format('H:i'); ?></p>
        <p><strong>Reservado el:</strong> <?php echo $fecha_reserva->format('d/m/Y'); ?></p>
        <p><strong>Estado:</strong> <span class="<?php echo $clase_estado; ?>"><?php echo ucfirst(htmlspecialchars($reserva['estado_reserva'] ?? '')); ?></span></p>
        <?php if (strtolower($reserva['estado_reserva'] ?? '') === 'cancelada' && !empty($reserva['motivo_cancelacion'])): ?>
        <p><strong>Motivo de cancelación:</strong> <?php echo htmlspecialchars($reserva['motivo_cancelacion'] ?? ''); ?></p>
        <?php endif; ?>
        <p><strong>Pago:</strong> <span class="<?php echo $clase_pago; ?>"><?php echo ucfirst(htmlspecialchars($reserva['estado_pago'] ?? '')); ?></span></p>
        <?php if ($reserva['pagado_online']): ?>
        <p><strong>Método de pago:</strong> Pagado online</p>
        <?php endif; ?>
        <?php if ($reserva['cupon_id']): ?>
        <p><strong>Cupón aplicado:</strong> Sí (Descuento: <?php echo number_format($reserva['descuento_cupon'], 2); ?> €)</p>
        <?php endif; ?>
        <?php if (!empty($reserva['comentario'])): ?>
        <p><strong>Comentario del cliente:</strong> <?php echo nl2br(htmlspecialchars($reserva['comentario'] ?? '')); ?></p>
        <?php endif; ?>
        <?php if (strtolower($reserva['estado_pago'] ?? '') !== 'pagado'): ?>
            <button type="button" id="btnMarcarPagado" class="btn-success" data-id="<?php echo $reserva['id_reserva']; ?>">Marcar como pagado</button>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <h3>Cliente</h3>
        <p><strong></strong> <?php echo htmlspecialchars(($reserva['nombre_cliente'] ?? '') . ' ' . ($reserva['apellidos_cliente'] ?? '')); ?></p>
        <p><strong></strong> <?php echo htmlspecialchars($reserva['telefono_cliente'] ?? ''); ?></p>
        <p><strong></strong> <?php echo htmlspecialchars($reserva['email_cliente'] ?? ''); ?></p>
        
        <h3 style="margin-top: 30px;">Trabajador</h3>
        <?php if ($trabajador): ?>
            <p><strong></strong> <?php echo htmlspecialchars(($trabajador['nombre'] ?? '') . ' ' . ($trabajador['apellido'] ?? '')); ?></p>
        <?php else: ?>
            <p><em>Sin trabajador asignado</em></p>
        <?php endif; ?>
    </div>
</div>