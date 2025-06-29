<?php
require_once '/home/u898735099/domains/buscounservicio.es/db-publica.php';
require_once '/home/u898735099/domains/buscounservicio.es/db-suscripciones.php';

// Verificar que user_id esté definido
if (!isset($user_id)) {
    throw new Exception('user_id no está definido');
}

// Obtener IDs de negocios con suscripción Premium activa
$stmtSuscripciones = $pdo4->prepare("SELECT negocio_id FROM suscripciones WHERE usuario_id = ? AND estado_plan = 'activo'");
$stmtSuscripciones->execute([$user_id]);
$negocios_premium_ids = $stmtSuscripciones->fetchAll(PDO::FETCH_COLUMN);

// Si no tiene negocios Premium activos, redirigir
if (empty($negocios_premium_ids)) {
    header("Location: https://negocios.buscounservicio.es/panel/pasate-a-premium");
    exit();
}

// Obtener detalles de los negocios Premium desde la tabla negocios
$placeholders = str_repeat('?,', count($negocios_premium_ids) - 1) . '?';
$stmtNegocios = $pdo2->prepare("SELECT * FROM negocios WHERE negocio_id IN ($placeholders) ORDER BY nombre ASC");
$stmtNegocios->execute($negocios_premium_ids);
$negocios_usuario = $stmtNegocios->fetchAll(PDO::FETCH_ASSOC);
?>
