<?php
require_once __DIR__ . '/../../db-publica.php';

if (empty($negocios)) {
    header("Location: /panel/anade-tu-negocio.php");
    exit;
}
?>