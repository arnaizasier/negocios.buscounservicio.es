<?php
session_start();


header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; img-src \'self\' data: https:; font-src \'self\' https://cdnjs.cloudflare.com; connect-src \'self\'; frame-ancestors \'none\';');
header('X-Frame-Options: DENY');
header('X-Permitted-Cross-Domain-Policies: none');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validación de referrer
function validateReferrer() {
    $allowedDomains = ['negocios.buscounservicio.es', 'www.negocios.buscounservicio.es'];
    
    if (!isset($_SERVER['HTTP_REFERER'])) {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';
    }
    
    $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    return in_array($refererHost, $allowedDomains, true);
}

// Sanitización y validación de entrada
function sanitizeInput($input, $type = 'string') {
    if ($input === null) {
        return null;
    }
    
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);
        case 'string':
        default:
            return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
}

function validateInput($input, $type, $required = true) {
    if ($required && ($input === null || $input === '')) {
        return false;
    }
    
    if (!$required && ($input === null || $input === '')) {
        return true;
    }
    
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false && $input > 0;
        case 'string':
            return is_string($input) && strlen($input) <= 1000; // Límite de longitud
        case 'csrf_token':
            return is_string($input) && strlen($input) === 64 && ctype_xdigit($input);
        default:
            return false;
    }
}

// Validar referrer para requests POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateReferrer()) {
    http_response_code(403);
    error_log('Invalid referrer detected from IP: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']));
    exit('Acceso denegado');
}

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";

use Delight\Auth\Auth;

try {
    $auth = new Auth($pdo);
    $user_id = $auth->getUserId();
    
    if (!$user_id) {
        header('Location: /login');
        exit();
    }
    
} catch (Exception $e) {
    error_log('Auth error: ' . $e->getMessage());
    header('Location: /login');
    exit();
}

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";

function sanitizeOutput($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function validateImagePath($path) {
    if (!is_string($path) || empty($path)) {
        return false;
    }
    
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    // Validar que sea una URL válida
    if (!filter_var($path, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    // Validar que sea del dominio permitido
    $allowedDomains = ['imagenes.buscounservicio.es', 'buscounservicio.es'];
    $parsedUrl = parse_url($path);
    
    if (!isset($parsedUrl['host']) || !in_array($parsedUrl['host'], $allowedDomains)) {
        return false;
    }
    
    // Validaciones de seguridad adicionales
    if (str_contains($path, '..') || 
        preg_match('/[<>"\'\s]/', $path)) {
        return false;
    }
    
    return in_array($extension, $allowedExtensions) && 
           strlen($path) <= 500;
}

// Validar parámetros GET si existen
if (!empty($_GET)) {
    foreach ($_GET as $key => $value) {
        if (!validateInput($key, 'string') || !validateInput($value, 'string')) {
            http_response_code(400);
            error_log('Invalid GET parameter detected: ' . $key . '=' . $value);
            exit('Parámetros inválidos');
        }
    }
}

try {
    $stmt = $pdo2->prepare("SELECT negocio_id, nombre, ubicacion, url_fotos FROM negocios WHERE usuario_id = ? ORDER BY nombre ASC LIMIT 100");
    $stmt->execute([$user_id]);
    $negocios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    $negocios = [];
}

// Generar nonce para CSP
$nonce = base64_encode(random_bytes(16));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Negocios</title>
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">

    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <link href="../../assets/css/marca.css" rel="stylesheet">
    <link href="mis-ubicaciones.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container45">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar.php'; ?>
        <div class="content45" id="content45">
            <div class="main-container">
                <div class="header-flex">
                    <h1>Mis Negocios</h1>
                    <a href="../anadir-negocio" class="btn btn-primary">
                        <i></i> Añadir Negocio
                    </a>
                </div>
                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Imagen</th>
                                <th>Nombre</th>
                                <th>Ubicación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($negocios)): ?>
                                <tr><td colspan="4" class="text-center">No hay negocios registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($negocios as $negocio): ?>
                                    <tr>
                                        <td data-label="Imagen">
                                            <?php
                                            if (!empty($negocio['url_fotos'])) {
                                                $imagenes = json_decode($negocio['url_fotos'], true);
                                                if (json_last_error() === JSON_ERROR_NONE && is_array($imagenes) && !empty($imagenes)) {
                                                    $img_url = $imagenes[0];
                                                    if (validateImagePath($img_url)) {
                                                        echo '<img src="' . sanitizeOutput($img_url) . '" alt="Negocio" class="table-img" loading="lazy">';
                                                    } else {
                                                        echo '<div class="no-image">Imagen no válida</div>';
                                                    }
                                                } else {
                                                    echo '<div class="no-image">Sin imagen</div>';
                                                }
                                            } else {
                                                echo '<div class="no-image">Sin imagen</div>';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Nombre"><?php echo sanitizeOutput($negocio['nombre']); ?></td>
                                        <td data-label="Ubicación">
                                            <?php 
                                            $ubicacion = $negocio['ubicacion'] ?? 'Ubicación no disponible';
                                            $ubicacion = sanitizeOutput($ubicacion);
                                            echo strlen($ubicacion) > 60 ? substr($ubicacion, 0, 60) . '...' : $ubicacion; 
                                            ?>
                                        </td>
                                        <td data-label="Acciones">
                                            <div class="action-buttons">
                                                <a href="https://negocios.buscounservicio.es/panel/anadir-negocio/index?id=<?php echo (int)$negocio['negocio_id']; ?>" class="btn btn-warning btn-sm">
                                                    <i></i> Editar
                                                </a>
                                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo (int)$negocio['negocio_id']; ?>)">
                                                    <i></i> Eliminar
                                                </button>
                                                <a href="../perfil/" class="btn btn-success btn-sm">
                                                    <i></i> Cambiar Plan
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <form id="deleteForm" method="POST" action="eliminar-negocio.php" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo sanitizeOutput($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="negocio_id" id="deleteNegocioId">
    </form>
    
    <script src="../../assets/js/sidebar.js"></script>
    <script nonce="<?php echo $nonce; ?>">
    'use strict';
    
    const CSP_NONCE = '<?php echo sanitizeOutput($_SESSION['csrf_token']); ?>';
    
    // Validación de entrada en JavaScript
    function validateNegocioId(id) {
        return Number.isInteger(id) && id > 0 && id <= 999999999;
    }
    
    function confirmDelete(negocioId) {
        if (!validateNegocioId(negocioId)) {
            console.error('ID de negocio inválido');
            Swal.fire({
                title: 'Error',
                text: 'ID de negocio inválido',
                icon: 'error'
            });
            return;
        }
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡No podrás revertir esto!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminarlo',
            cancelButtonText: 'Cancelar',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('deleteForm');
                const negocioIdInput = document.getElementById('deleteNegocioId');
                
                if (form && negocioIdInput) {
                    negocioIdInput.value = negocioId;
                    form.submit();
                } else {
                    console.error('Error: Formulario no encontrado');
                    Swal.fire({
                        title: 'Error',
                        text: 'Error interno del sistema',
                        icon: 'error'
                    });
                }
            }
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Validar que estamos en el dominio correcto
        if (!window.location.hostname.endsWith('negocios.buscounservicio.es')) {
            console.error('Dominio no autorizado');
            return;
        }
        
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                // Validar CSRF token antes del envío
                const csrfToken = form.querySelector('input[name="csrf_token"]');
                if (csrfToken && csrfToken.value !== CSP_NONCE) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Error de seguridad',
                        text: 'Token de seguridad inválido',
                        icon: 'error'
                    });
                    return;
                }
                
                const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    setTimeout(() => {
                        submitButton.disabled = false;
                    }, 3000);
                }
            });
        });
        
        // Protección adicional contra clickjacking
        if (window.top !== window.self) {
            document.body.style.display = 'none';
            console.error('Clickjacking detectado');
        }
    });
    </script>
</body>
</html>