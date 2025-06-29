<?php

// Determinar el negocio activo y verificar que pertenezca al usuario actual (seguridad)
if (isset($_GET['id_negocio'])) {
    $id_negocio_solicitado = intval($_GET['id_negocio']);
    if (!in_array($id_negocio_solicitado, $negocios)) {
        // Si el ID solicitado no pertenece al usuario, redirigir sin parámetros
        header('Location: index.php');
        exit;
    }
    $id_negocio = $id_negocio_solicitado;
} elseif (!empty($negocios)) {
    // Usar el primer negocio disponible
    $id_negocio = $negocios[0];
} else {
    // No tiene negocios, redirigir
    header('Location: index.php');
    exit;
}

?>