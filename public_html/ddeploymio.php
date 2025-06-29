<?php
// deploy.php — ejecuta git pull al hacer push en GitHub

// Seguridad: token secreto para evitar accesos no autorizados
$token = 'Zr3m89mXBq63L1pWjU7a';

// Validar que el token recibido por la URL es correcto
if (!isset($_GET['token']) || $_GET['token'] !== $token) {
    http_response_code(403);
    exit('Acceso no autorizado');
}

// Ejecutar git pull en la raíz del proyecto
$output = shell_exec('cd /home/u898735099/domains/negocios.buscounservicio.es && git pull 2>&1');

// Mostrar el resultado
echo "<pre>$output</pre>";
?>
