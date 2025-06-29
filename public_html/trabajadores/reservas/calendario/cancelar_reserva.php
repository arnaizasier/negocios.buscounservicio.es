<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-publica.php';
require_once '../../../../db-venta_productos.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-trabajador.php";

// Obtener datos del trabajador actual
$worker_data = requireWorkerRole();
$current_worker_id = $worker_data['id'];
$worker_negocio_id = $worker_data['negocio_id'];
$worker_permissions = $worker_data['permisos'];

$id_reserva = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$motivo_cancelacion_raw = $_POST['motivo_cancelacion'] ?? '';

if (!$id_reserva || $id_reserva <= 0) {
    echo json_encode(['error' => 'ID de reserva no válido']);
    exit;
}

if (empty(trim($motivo_cancelacion_raw))) {
    echo json_encode(['error' => 'Motivo de cancelación requerido']);
    exit;
}

$motivo_cancelacion = trim(strip_tags($motivo_cancelacion_raw));

if (strlen($motivo_cancelacion) > 255) {
    echo json_encode(['error' => 'El motivo de cancelación es demasiado largo (máximo 255 caracteres)']);
    exit;
}

if (strlen($motivo_cancelacion) < 3) {
    echo json_encode(['error' => 'El motivo de cancelación debe tener al menos 3 caracteres']);
    exit;
}

if (preg_match('/<[^>]*>|javascript:|onload=|onerror=|<script|<\/script>/', $motivo_cancelacion)) {
    echo json_encode(['error' => 'El motivo de cancelación contiene caracteres no permitidos']);
    exit;
}

if (preg_match('/[<>"\']/', $motivo_cancelacion)) {
    echo json_encode(['error' => 'El motivo de cancelación contiene caracteres especiales no permitidos']);
    exit;
}

$sql_reserva = "SELECT id_negocio, id_cliente, id_trabajador FROM reservas WHERE id_reserva = :id_reserva";
$stmt_reserva = $pdo5->prepare($sql_reserva);
$stmt_reserva->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);
$stmt_reserva->execute();
$reserva_data = $stmt_reserva->fetch(PDO::FETCH_ASSOC);

if (!$reserva_data) {
    echo json_encode(['error' => 'Reserva no encontrada']);
    exit;
}

// Verificar permisos del trabajador
if ($reserva_data['id_negocio'] != $worker_negocio_id) {
    echo json_encode(['error' => 'No tiene permiso para cancelar esta reserva']);
    exit;
}

// Si tiene permisos 1, solo puede cancelar sus propias reservas
if ($worker_permissions == 1 && $reserva_data['id_trabajador'] != $current_worker_id) {
    echo json_encode(['error' => 'No tiene permiso para cancelar esta reserva']);
    exit;
}

$sql_update = "UPDATE reservas SET estado_reserva = 'cancelada', motivo_cancelacion = :motivo_cancelacion WHERE id_reserva = :id_reserva";
$stmt_update = $pdo5->prepare($sql_update);
$stmt_update->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);
$stmt_update->bindParam(':motivo_cancelacion', $motivo_cancelacion, PDO::PARAM_STR);
$success = $stmt_update->execute();

if ($success) {
    $sql_cliente = "SELECT u.email, u.first_name, u.last_name FROM users u WHERE u.id = ?";
    $stmt_cliente = $pdo->prepare($sql_cliente);
    $stmt_cliente->execute([$reserva_data['id_cliente']]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    if ($cliente && !empty($cliente['email'])) {
        $sql_servicio = "SELECT servicio, fecha_inicio, id_negocio FROM reservas WHERE id_reserva = :id_reserva";
        $stmt_servicio = $pdo5->prepare($sql_servicio);
        $stmt_servicio->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);
        $stmt_servicio->execute();
        $reserva_info = $stmt_servicio->fetch(PDO::FETCH_ASSOC);
        $nombre_servicio = $reserva_info ? $reserva_info['servicio'] : '';
        $fecha_cita = $reserva_info ? date('d/m/Y H:i', strtotime($reserva_info['fecha_inicio'])) : '';
        $id_negocio = $reserva_info ? $reserva_info['id_negocio'] : '';

        $sql_nombre_negocio = "SELECT nombre, url FROM negocios WHERE negocio_id = ?";
        $stmt_nombre_negocio = $pdo2->prepare($sql_nombre_negocio);
        $stmt_nombre_negocio->execute([$id_negocio]);
        $negocio = $stmt_nombre_negocio->fetch(PDO::FETCH_ASSOC);
        $nombre_negocio = $negocio && !empty($negocio['nombre']) ? $negocio['nombre'] : '';
        $url_negocio = $negocio && !empty($negocio['url']) ? $negocio['url'] : 'https://buscounservicio.com/';

        $apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : (isset($brevoApiKey) ? $brevoApiKey : '');
        $toEmail = $cliente['email'];
        $toName = trim($cliente['first_name'] . ' ' . $cliente['last_name']);
        $fromEmail = 'info@buscounservicio.es';
        $fromName = 'Buscounservicio';
        $subject = 'Tu reserva ha sido cancelada';
        $content = '<div style="font-family: Arial, sans-serif; background: #f9f9f9; padding: 32px; color: #222;">
            <div style="max-width: 480px; margin: auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 32px 24px;">
                <h2 style="color: #e74c3c; text-align:center; margin-bottom: 16px;">¡Tu cita ha sido cancelada!</h2>
                <p>Hola <b>' . htmlspecialchars($toName) . '</b>,</p>
                <p>Lamentamos que tu cita en <b>' . htmlspecialchars($nombre_negocio) . '</b> ha sido <b style="color:#e74c3c;">cancelada</b>.</p>
                <div style="background:#f4f6fa; border-radius:8px; padding:16px; margin:24px 0; text-align:center;">
                    <p style="margin:0; font-size:15px; color:#333;">
                        <b>Cita:</b> ' . htmlspecialchars($nombre_servicio) . '<br>
                        <b>Fecha:</b> ' . htmlspecialchars($fecha_cita) . '<br>
                        <b>Motivo:</b> ' . htmlspecialchars($motivo_cancelacion) . '
                    </p>
                </div>
                <div style="background:#eef6ff; border-radius:8px; padding:12px 16px; margin:16px 0; text-align:center;">
                    <a href="' . htmlspecialchars($url_negocio) . '" style="color:#3074d6; text-decoration:underline; font-size:15px;">Ir al perfil del negocio</a>
                </div>
                <p style="font-size:13px; color:#888; text-align:center; margin-top:32px;">Gracias por confiar en <b>Buscounservicio</b>.<br>Este correo es solo informativo, no respondas a este mensaje.</p>
            </div>
        </div>';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json'
        ]);
        $body = [
            'sender' => [ 'name' => $fromName, 'email' => $fromEmail ],
            'to' => [ [ 'email' => $toEmail, 'name' => $toName ] ],
            'subject' => $subject,
            'htmlContent' => $content
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $response = curl_exec($ch);
        curl_close($ch);
    }
    echo json_encode(['success' => true, 'message' => 'Reserva cancelada correctamente']);
} else {
    echo json_encode(['error' => 'Error al cancelar la reserva']);
}
?>