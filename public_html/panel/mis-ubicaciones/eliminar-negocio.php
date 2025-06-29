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

// Verificar si se ha enviado el ID del negocio
if (isset($_POST['negocio_id'])) {
    $negocio_id = $_POST['negocio_id'];

    // Obtener las fotos asociadas al negocio
    $stmt = $pdo2->prepare("SELECT url_fotos FROM negocios WHERE negocio_id = ? AND usuario_id = ?");
    $stmt->execute([$negocio_id, $user_id]);
    $negocio = $stmt->fetch();

    if ($negocio && !empty($negocio['url_fotos'])) {
        $fotos = json_decode($negocio['url_fotos'], true);
        foreach ($fotos as $foto) {
            $rutaCompleta = '../../' . $foto;
            if (file_exists($rutaCompleta)) {
                unlink($rutaCompleta);
            }
        }
    }

    // Preparar la consulta para eliminar el negocio
    $stmt = $pdo2->prepare("DELETE FROM negocios WHERE negocio_id = ? AND usuario_id = ?");
    $stmt->execute([$negocio_id, $user_id]);

    // Redirigir a la página de negocios
    header('Location: index.php?mensaje=Negocio eliminado con éxito');
    exit;
} else {
    echo "<div class='alert alert-danger'>No se ha proporcionado un ID de negocio.</div>";
}
?>
