<?php
require_once '/home/u898735099/domains/buscounservicio.es/db-publica.php';

// Verificar que user_id esté definido
if (!isset($user_id)) {
    throw new Exception('user_id no está definido');
}

// Obtener negocios del usuario
try {
    $stmtNegocios = $pdo2->prepare("SELECT negocio_id, nombre FROM negocios WHERE usuario_id = ? ORDER BY nombre ASC");
    $stmtNegocios->execute([$user_id]);
    $negocios_usuario = $stmtNegocios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error al obtener negocios: ' . $e->getMessage());
}

?>