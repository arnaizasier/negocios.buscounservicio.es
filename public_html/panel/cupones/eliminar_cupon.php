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

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Error de validación de seguridad. Por favor, inténtalo de nuevo.';
    header('Location: index.php');
    exit;
}

// Verificar que se recibió el ID del cupón
if (!isset($_POST['cupon_id']) || !is_numeric($_POST['cupon_id'])) {
    $_SESSION['error_message'] = 'ID de cupón inválido.';
    header('Location: index.php');
    exit;
}

$cupon_id = (int)$_POST['cupon_id'];

try {
    // Verificar que el cupón existe y pertenece al usuario
    $stmt = $pdo2->prepare("
        SELECT c.*
        FROM cupones c
        WHERE c.id = ? AND c.usuario_id = ?
    ");
    $stmt->execute([$cupon_id, $user_id]);
    $cupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cupon) {
        $_SESSION['error_message'] = 'Cupón no encontrado o no tienes permiso para eliminarlo.';
        header('Location: index.php');
        exit;
    }

    // Eliminar el cupón
    $stmt = $pdo2->prepare("DELETE FROM cupones WHERE id = ?");
    $stmt->execute([$cupon_id]);

    // Regenerar token CSRF
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Redirigir con mensaje de éxito
    $_SESSION['success_message'] = 'Cupón eliminado correctamente';
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    error_log("Error al eliminar cupón: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error al eliminar el cupón. Por favor, inténtalo de nuevo.';
    header('Location: index.php');
    exit;
}
?>