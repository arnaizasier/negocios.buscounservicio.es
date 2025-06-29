<?php
require_once '/home/u898735099/domains/buscounservicio.es/config.php';

// Verificar que user_id esté definido
if (!isset($user_id)) {
    throw new Exception('user_id no está definido');
}

// Obtener el correo electrónico del usuario
$stmt_user_email = $pdo2->prepare("SELECT correo FROM users WHERE id = ?");
$stmt_user_email->execute([$user_id]);
$bussines_email_data = $stmt_user_email->fetch(PDO::FETCH_ASSOC);

$bussines_email = null;
if ($bussines_email_data) {
    $bussines_email = $bussines_email_data['correo'];
}

?>