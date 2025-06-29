<?php
session_start();

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validarRateLimit($user_id) {
    $limite_tiempo = 300; 
    $limite_intentos = 10; 
    
    if (!isset($_SESSION['upload_attempts_trabajador'])) {
        $_SESSION['upload_attempts_trabajador'] = [];
    }
    
    $tiempo_actual = time();
    $intentos = $_SESSION['upload_attempts_trabajador'];
    
    $intentos = array_filter($intentos, function($timestamp) use ($tiempo_actual, $limite_tiempo) {
        return ($tiempo_actual - $timestamp) < $limite_tiempo;
    });
    
    if (count($intentos) >= $limite_intentos) {
        return false;
    }
    
    $intentos[] = $tiempo_actual;
    $_SESSION['upload_attempts_trabajador'] = $intentos;
    
    return true;
}

$tiposPermitidos = [
    'image/jpeg', 
    'image/jpg', 
    'image/png', 
    'image/gif', 
    'image/webp'
];

$mimeTypesPermitidos = [
    'image/jpeg',
    'image/png', 
    'image/gif',
    'image/webp'
];

$extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

$tamanoMaximo = 5 * 1024 * 1024;
$tamanoMinimo = 1024; 

// Colores disponibles para el calendario
$colores_calendario = [
    '#FF5733' => 'Rojo Coral',
    '#33FF57' => 'Verde Lima',
    '#3357FF' => 'Azul Real',
    '#FF33F1' => 'Magenta',
    '#33FFF1' => 'Cian',
    '#F1FF33' => 'Amarillo Lima',
    '#FF8C33' => 'Naranja',
    '#8C33FF' => 'Violeta',
    '#33FF8C' => 'Verde Menta',
    '#FF338C' => 'Rosa Fucsia',
    '#8CFF33' => 'Verde Claro',
    '#338CFF' => 'Azul Cielo',
    '#FF3333' => 'Rojo Brillante',
    '#33FFC7' => 'Verde Agua',
    '#C733FF' => 'Púrpura',
    '#FFC733' => 'Dorado',
    '#33C7FF' => 'Azul Claro',
    '#C7FF33' => 'Lima Brillante',
    '#FF33C7' => 'Rosa Vibrante',
    '#33FF33' => 'Verde Puro'
];

$firmasArchivos = [
    'jpeg' => [
        "\xFF\xD8\xFF\xE0",
        "\xFF\xD8\xFF\xE1", 
        "\xFF\xD8\xFF\xE8",
        "\xFF\xD8\xFF\xDB"
    ],
    'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
    'gif' => ["GIF87a", "GIF89a"],
    'webp' => ["RIFF"]
];

function validarFirmaArchivo($rutaArchivo, $extension) {
    global $firmasArchivos;
    
    if (!isset($firmasArchivos[$extension])) {
        return false;
    }
    
    $handle = fopen($rutaArchivo, 'rb');
    if (!$handle) {
        return false;
    }
    
    $cabecera = fread($handle, 12);
    fclose($handle);
    
    foreach ($firmasArchivos[$extension] as $firma) {
        if (substr($cabecera, 0, strlen($firma)) === $firma) {
            if ($extension === 'webp') {
                return strpos($cabecera, 'WEBP') !== false;
            }
            return true;
        }
    }
    
    return false;
}

function validarImagenSegura($rutaArchivo) {
    if (!function_exists('finfo_open')) {
        error_log("Extensión fileinfo no disponible");
        return false;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        error_log("No se pudo abrir finfo");
        return false;
    }
    
    $mimeType = finfo_file($finfo, $rutaArchivo);
    finfo_close($finfo);
    
    global $mimeTypesPermitidos;
    if (!in_array($mimeType, $mimeTypesPermitidos)) {
        error_log("MIME type no permitido: " . $mimeType);
        return false;
    }
    
    $imageInfo = getimagesize($rutaArchivo);
    if ($imageInfo === false) {
        error_log("getimagesize falló para: " . $rutaArchivo);
        return false;
    }
    
    if ($imageInfo[0] < 50 || $imageInfo[1] < 50) {
        error_log("Imagen demasiado pequeña: " . $imageInfo[0] . "x" . $imageInfo[1]);
        return false;
    }
    
    if ($imageInfo[0] > 5000 || $imageInfo[1] > 5000) {
        error_log("Imagen demasiado grande: " . $imageInfo[0] . "x" . $imageInfo[1]);
        return false;
    }
    
    $contenido = file_get_contents($rutaArchivo, false, null, 0, 1024);
    $patronesPeligrosos = [
        '/<\?php/i',
        '/<script/i', 
        '/javascript:/i',
        '/vbscript:/i',
        '/onload=/i',
        '/onerror=/i',
        '/eval\(/i',
        '/base64_decode/i',
        '/exec\(/i',
        '/system\(/i',
        '/shell_exec/i'
    ];
    
    foreach ($patronesPeligrosos as $patron) {
        if (preg_match($patron, $contenido)) {
            error_log("Contenido peligroso detectado en archivo");
            return false;
        }
    }
    
    return true;
}

function limpiarNombreArchivo($nombre) {
    $nombre = preg_replace('/[^a-zA-Z0-9._-]/', '', $nombre);
    $nombre = preg_replace('/\.+/', '.', $nombre);
    return substr($nombre, 0, 100);
}

function validateNegocioId($negocio_id, $user_id, $pdo2) {
    if (!is_numeric($negocio_id) || $negocio_id <= 0) {
        return false;
    }
    
    try {
        $stmt = $pdo2->prepare("SELECT COUNT(*) FROM negocios WHERE negocio_id = ? AND usuario_id = ?");
        $stmt->execute([$negocio_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error validando negocio_id: " . $e->getMessage());
        return false;
    }
}

function validateTrabajadorId($trabajador_id, $user_id, $pdo2) {
    if (!is_numeric($trabajador_id) || $trabajador_id <= 0) {
        return false;
    }
    
    try {
        $stmt = $pdo2->prepare("SELECT COUNT(*) FROM trabajadores WHERE id = ? AND admin_id = ?");
        $stmt->execute([$trabajador_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error validando trabajador_id: " . $e->getMessage());
        return false;
    }
}

function convertToWebP($sourcePath, $destinationPath, $quality = 30, $targetWidth = 200, $targetHeight = 200) {
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick($sourcePath);
            
            $imagick->thumbnailImage($targetWidth, $targetHeight, true, true);
            
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($quality);
            $imagick->stripImage();
            $result = $imagick->writeImage($destinationPath);
            $imagick->destroy();
            return $result;
        } catch (Exception $e) {
            error_log("Error converting to WebP with Imagick: " . $e->getMessage());
        }
    }
    
    if (extension_loaded('gd')) {
        try {
            $imageInfo = getimagesize($sourcePath);
            $mimeType = $imageInfo['mime'];
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            
            switch ($mimeType) {
                case 'image/jpeg':
                    $originalImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $originalImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $originalImage = imagecreatefromgif($sourcePath);
                    break;
                default:
                    return false;
            }
            
            if ($originalImage === false) {
                return false;
            }
            
            $aspectRatio = $originalWidth / $originalHeight;
            
            if ($aspectRatio > 1) {
                $newWidth = $targetWidth;
                $newHeight = $targetWidth / $aspectRatio;
            } else {
                $newHeight = $targetHeight;
                $newWidth = $targetHeight * $aspectRatio;
            }
            
            $resizedImage = imagecreatetruecolor($targetWidth, $targetHeight);
            
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefill($resizedImage, 0, 0, $transparent);
            } else {
                $white = imagecolorallocate($resizedImage, 255, 255, 255);
                imagefill($resizedImage, 0, 0, $white);
            }
            
            $offsetX = ($targetWidth - $newWidth) / 2;
            $offsetY = ($targetHeight - $newHeight) / 2;
            
            imagecopyresampled(
                $resizedImage, $originalImage,
                $offsetX, $offsetY, 0, 0,
                $newWidth, $newHeight, $originalWidth, $originalHeight
            );
            
            $result = imagewebp($resizedImage, $destinationPath, $quality);
            
            imagedestroy($originalImage);
            imagedestroy($resizedImage);
            
            return $result;
        } catch (Exception $e) {
            error_log("Error converting to WebP with GD: " . $e->getMessage());
        }
    }
    
    error_log("Neither Imagick nor GD with WebP support is available");
    return false;
}

$mensaje_success = '';
$mensaje_error = '';
$is_edit = false;
$trabajador_data = null;

$trabajador_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($trabajador_id && validateTrabajadorId($trabajador_id, $user_id, $pdo2)) {
    $is_edit = true;
    try {
        $stmt = $pdo2->prepare("SELECT * FROM trabajadores WHERE id = ? AND admin_id = ?");
        $stmt->execute([$trabajador_id, $user_id]);
        $trabajador_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$trabajador_data) {
            $mensaje_error = 'Trabajador no encontrado.';
            $is_edit = false;
        }
    } catch (PDOException $e) {
        error_log("Error al obtener trabajador: " . $e->getMessage());
        $mensaje_error = 'Error interno del servidor.';
        $is_edit = false;
    }
}

try {
    $stmtNegocios = $pdo2->prepare("SELECT * FROM negocios WHERE usuario_id = ? ORDER BY nombre ASC");
    $stmtNegocios->execute([$user_id]);
    $negocios_usuario = $stmtNegocios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al obtener negocios: ' . $e->getMessage());
    die('Error interno del servidor.');
}

if (empty($negocios_usuario)) {
    $mensaje_error = 'No tienes negocios registrados. Primero debes añadir un negocio.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($negocios_usuario)) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRF($csrf_token)) {
        die('Token de seguridad inválido.');
    }
    
    $current_time = time();
    if (!isset($_SESSION['last_action_time'])) {
        $_SESSION['last_action_time'] = 0;
    }
    
    if (($current_time - $_SESSION['last_action_time']) < 3) {
        die('Demasiadas solicitudes. Espera unos segundos.');
    }
    
    $_SESSION['last_action_time'] = $current_time;
    
    $negocio_id = filter_input(INPUT_POST, 'negocio_id', FILTER_VALIDATE_INT);
    $nombre = filter_var($_POST['nombre'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $apellido = filter_var($_POST['apellido'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $rol = filter_var($_POST['rol'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $permisos = filter_input(INPUT_POST, 'permisos', FILTER_VALIDATE_INT);
    $tipo_horario = filter_var($_POST['tipo_horario'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $color_calendario = filter_var($_POST['color_calendario'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $horario_data = $_POST['horario_trabajador'] ?? [];
    $edit_id = filter_input(INPUT_POST, 'trabajador_id', FILTER_VALIDATE_INT);
    
    if (!$negocio_id || !validateNegocioId($negocio_id, $user_id, $pdo2)) {
        $mensaje_error = 'Negocio no válido o no autorizado.';
    } elseif (empty($nombre) || strlen($nombre) < 2 || strlen($nombre) > 100) {
        $mensaje_error = 'El nombre debe tener entre 2 y 100 caracteres.';
    } elseif (empty($apellido) || strlen($apellido) < 2 || strlen($apellido) > 100) {
        $mensaje_error = 'El apellido debe tener entre 2 y 100 caracteres.';
    } elseif (empty($rol) || strlen($rol) < 2 || strlen($rol) > 50) {
        $mensaje_error = 'El puesto debe tener entre 2 y 50 caracteres.';
    } elseif ($permisos === false || !in_array($permisos, [1, 2])) {
        $mensaje_error = 'Debes seleccionar un nivel de permisos válido.';
    } elseif (empty($color_calendario) || !array_key_exists($color_calendario, $colores_calendario)) {
        $mensaje_error = 'Debes seleccionar un color válido para el calendario.';
    } elseif ($edit_id && !validateTrabajadorId($edit_id, $user_id, $pdo2)) {
        $mensaje_error = 'ID de trabajador no válido.';
    } else {
        $nombre = sanitizeInput($nombre);
        $apellido = sanitizeInput($apellido);
        $rol = sanitizeInput($rol);
        $color_calendario = sanitizeInput($color_calendario);
        
        if ($tipo_horario === 'mismo_centro') {
            $horario = null;
        } else {
            $horario_procesado = [];
            if (!empty($horario_data) && is_array($horario_data)) {
                foreach ($horario_data as $dia => $datos) {
                    $cerrado = isset($datos['cerrado']) && $datos['cerrado'] === 'true';
                    
                    if ($cerrado) {
                        $horario_procesado[$dia] = ['cerrado' => true];
                    } else {
                        if (isset($datos['rangos']) && is_array($datos['rangos'])) {
                            $rangos = [];
                            foreach ($datos['rangos'] as $rango) {
                                if (!empty($rango['inicio']) && !empty($rango['fin'])) {
                                    $rangos[] = [
                                        'inicio' => sanitizeInput($rango['inicio']),
                                        'fin' => sanitizeInput($rango['fin'])
                                    ];
                                }
                            }
                            
                            if (!empty($rangos)) {
                                $horario_procesado[$dia] = ['rangos' => $rangos];
                            }
                        }
                    }
                }
            }
            
            $horario = !empty($horario_procesado) ? json_encode($horario_procesado) : '';
        }
        
        $url_foto = '';
        
        if ($edit_id && $trabajador_data) {
            $url_foto = $trabajador_data['url_foto'];
        }
        
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            if (!validarRateLimit($user_id)) {
                $mensaje_error = 'Has excedido el límite de subidas. Por favor, espera unos minutos antes de intentar de nuevo.';
            } else {
                $tamanoArchivo = $_FILES['foto']['size'];
                
                if ($tamanoArchivo < $tamanoMinimo) {
                    $mensaje_error = 'El archivo es demasiado pequeño (mínimo 1KB).';
                } elseif ($tamanoArchivo > $tamanoMaximo) {
                    $mensaje_error = 'El archivo es demasiado grande. Máximo 5MB.';
                } else {
                    $nombreOriginal = limpiarNombreArchivo($_FILES['foto']['name']);
                    if (empty($nombreOriginal)) {
                        $mensaje_error = 'Nombre de archivo no válido.';
                    } else {
                        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
                        
                        if (!in_array($extension, $extensionesPermitidas)) {
                            $mensaje_error = 'Extensión de archivo no permitida. Solo se permiten: JPG, PNG, GIF, WebP.';
                        } else {
                            $tmpName = $_FILES['foto']['tmp_name'];
                            
                            if (!is_uploaded_file($tmpName)) {
                                $mensaje_error = 'Error de seguridad: archivo no subido correctamente.';
                            } else {
                                $extensionNormalizada = ($extension === 'jpg') ? 'jpeg' : $extension;
                                if (!validarFirmaArchivo($tmpName, $extensionNormalizada)) {
                                    $mensaje_error = 'El archivo no tiene la firma correcta para su tipo.';
                                } else {
                                    if (!validarImagenSegura($tmpName)) {
                                        $mensaje_error = 'El archivo no pasó las validaciones de seguridad.';
                                    } else {
                                        $upload_dir = __DIR__ . '/../../imagenes/trabajadores/';
                                        
                                        if (!is_dir($upload_dir)) {
                                            if (!mkdir($upload_dir, 0755, true)) {
                                                $mensaje_error = 'No se pudo crear el directorio de imágenes de trabajadores.';
                                            }
                                        }
                                        
                                        if (empty($mensaje_error) && (!is_writable($upload_dir) || !is_readable($upload_dir))) {
                                            $mensaje_error = 'El directorio de imágenes no tiene los permisos correctos.';
                                        }
                                        
                                        if (empty($mensaje_error)) {
                                            if ($edit_id && !empty($url_foto)) {
                                                $old_file = $upload_dir . basename($url_foto);
                                                if (file_exists($old_file) && is_file($old_file)) {
                                                    unlink($old_file);
                                                }
                                            }
                                            
                                            $nombreTemp = 'temp_trabajador_' . $user_id . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
                                            $rutaTemp = $upload_dir . $nombreTemp;
                                            
                                            if (move_uploaded_file($tmpName, $rutaTemp)) {
                                                chmod($rutaTemp, 0644);
                                                
                                                if (!validarImagenSegura($rutaTemp)) {
                                                    unlink($rutaTemp);
                                                    $mensaje_error = 'El archivo subido no pasó las validaciones de seguridad adicionales.';
                                                } else {
                                                    $nombreNuevo = 'trabajador_' . $user_id . '_' . bin2hex(random_bytes(16)) . '.webp';
                                                    $rutaCompleta = $upload_dir . $nombreNuevo;
                                                    
                                                    if (convertToWebP($rutaTemp, $rutaCompleta, 30)) {
                                                        unlink($rutaTemp);
                                                        chmod($rutaCompleta, 0644);
                                                        $url_foto = '/imagenes/trabajadores/' . $nombreNuevo;
                                                    } else {
                                                        if ($extension === 'webp') {
                                                            if (rename($rutaTemp, $rutaCompleta)) {
                                                                chmod($rutaCompleta, 0644);
                                                                $url_foto = '/imagenes/trabajadores/' . $nombreNuevo;
                                                            } else {
                                                                unlink($rutaTemp);
                                                                $mensaje_error = 'Error al procesar el archivo WebP.';
                                                            }
                                                        } else {
                                                            unlink($rutaTemp);
                                                            $mensaje_error = 'Error al convertir la imagen a formato WebP.';
                                                        }
                                                    }
                                                }
                                            } else {
                                                $mensaje_error = 'Error al subir la imagen.';
                                                error_log("Failed to move uploaded file. Source: " . $tmpName . " Destination: " . $rutaTemp);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Manejar errores de subida
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'El archivo es demasiado grande (límite del servidor).',
                UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande (límite del formulario).',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal.',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo.',
                UPLOAD_ERR_EXTENSION => 'Subida detenida por extensión.'
            ];
            
            $error_code = $_FILES['foto']['error'];
            $mensaje_error = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : 'Error desconocido al subir archivo.';
            error_log("Upload error code: " . $error_code . " - " . $mensaje_error);
        }
        
        if (empty($mensaje_error)) {
            try {
                if ($edit_id) {
                    $stmt = $pdo2->prepare("UPDATE trabajadores SET negocio_id = ?, nombre = ?, apellido = ?, horario = ?, url_foto = ?, rol = ?, color_calendario = ?, permisos = ? WHERE id = ? AND admin_id = ?");
                    $stmt->execute([$negocio_id, $nombre, $apellido, $horario, $url_foto, $rol, $color_calendario, $permisos, $edit_id, $user_id]);
                    header('Location: index?success=updated');
                    exit();
                } else {
                    $stmt = $pdo2->prepare("INSERT INTO trabajadores (admin_id, negocio_id, nombre, apellido, horario, url_foto, rol, color_calendario, permisos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $negocio_id, $nombre, $apellido, $horario, $url_foto, $rol, $color_calendario, $permisos]);
                    header('Location: index?success=added');
                    exit();
                }
                
            } catch (PDOException $e) {
                error_log("Error al guardar trabajador: " . $e->getMessage());
                $mensaje_error = 'Error interno del servidor al guardar los datos.';
            }
        }
    }
}

$page_title = $is_edit ? 'Editar Trabajador' : 'Agregar Trabajador';
$button_text = $is_edit ? 'Actualizar Trabajador' : 'Agregar Trabajador';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="css/gestion-trabajador.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body data-is-edit="<?php echo $is_edit ? 'true' : 'false'; ?>">
    <div class="container45">
        <?php include '../../assets/includes/sidebar.php'; ?>
        
        <div class="content45" id="content45">
            <div class="main-container">
                <?php if ($is_edit): ?>
                    <div class="page-header">
                        <h1 class="page-title">
                            <i></i> <?php echo $page_title; ?>
                        </h1>
                        <button type="button" class="btn-delete" id="btn-eliminar-trabajador" data-trabajador-id="<?php echo (int)$trabajador_data['id']; ?>">
                            Eliminar Trabajador
                        </button>
                    </div>
                <?php else: ?>
                    <h1 class="page-title">
                        <i></i> <?php echo $page_title; ?>
                    </h1>
                <?php endif; ?>
                
                <?php if ($mensaje_success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $mensaje_success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($mensaje_error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $mensaje_error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($negocios_usuario)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> No tienes negocios registrados. 
                        <a href="../anadir-negocio" style="color: #856404; text-decoration: underline;">Añadir un negocio primero</a>
                    </div>
                <?php else: ?>
                    <form method="POST" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <?php if ($is_edit && $trabajador_data): ?>
                            <input type="hidden" name="trabajador_id" value="<?php echo (int)$trabajador_data['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-section">
                            <div class="form-group">
                                <label for="negocio_id" class="form-label">
                                    Negocio <span class="required">*</span>
                                </label>
                                <select name="negocio_id" id="negocio_id" class="form-control" required>
                                    <option value="">Selecciona un negocio</option>
                                    <?php foreach ($negocios_usuario as $negocio): ?>
                                        <?php 
                                        $selected = '';
                                        if ($is_edit && $trabajador_data && $trabajador_data['negocio_id'] == $negocio['negocio_id']) {
                                            $selected = 'selected';
                                        } elseif (!$is_edit && isset($_POST['negocio_id']) && $_POST['negocio_id'] == $negocio['negocio_id']) {
                                            $selected = 'selected';
                                        }
                                        ?>
                                        <option value="<?php echo (int)$negocio['negocio_id']; ?>" <?php echo $selected; ?>>
                                            <?php echo sanitizeInput($negocio['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h2 class="section-title">
                                <i></i> Información Personal
                            </h2>
                            
                            <div class="form-group">
                                <label for="nombre" class="form-label">
                                    Nombre <span class="required">*</span>
                                </label>
                                <?php 
                                $nombre_value = '';
                                if ($is_edit && $trabajador_data) {
                                    $nombre_value = sanitizeInput($trabajador_data['nombre']);
                                } elseif (isset($_POST['nombre'])) {
                                    $nombre_value = sanitizeInput($_POST['nombre']);
                                }
                                ?>
                                <input type="text" 
                                       name="nombre" 
                                       id="nombre" 
                                       class="form-control" 
                                       value="<?php echo $nombre_value; ?>"
                                       required 
                                       minlength="2" 
                                       maxlength="100"
                                       placeholder="Ingresa el nombre del trabajador">
                            </div>
                            
                            <div class="form-group">
                                <label for="apellido" class="form-label">
                                    Apellido <span class="required">*</span>
                                </label>
                                <?php 
                                $apellido_value = '';
                                if ($is_edit && $trabajador_data) {
                                    $apellido_value = sanitizeInput($trabajador_data['apellido']);
                                } elseif (isset($_POST['apellido'])) {
                                    $apellido_value = sanitizeInput($_POST['apellido']);
                                }
                                ?>
                                <input type="text" 
                                       name="apellido" 
                                       id="apellido" 
                                       class="form-control" 
                                       value="<?php echo $apellido_value; ?>"
                                       required 
                                       minlength="2" 
                                       maxlength="100"
                                       placeholder="Ingresa el apellido del trabajador">
                            </div>
                            
                            <div class="form-group">
                                <label for="rol" class="form-label">
                                    Puesto <span class="required">*</span>
                                </label>
                                <?php 
                                $rol_value = '';
                                if ($is_edit && $trabajador_data) {
                                    $rol_value = sanitizeInput($trabajador_data['rol']);
                                } elseif (isset($_POST['rol'])) {
                                    $rol_value = sanitizeInput($_POST['rol']);
                                }
                                ?>
                                <input type="text" 
                                       name="rol" 
                                       id="rol" 
                                       class="form-control" 
                                       value="<?php echo $rol_value; ?>"
                                       required 
                                       minlength="2" 
                                       maxlength="50"
                                       placeholder="Ejemplo: Recepcionista, Cocinero, Vendedor...">
                                <div class="help-text">Describe el puesto o cargo del trabajador</div>
                            </div>

                            <div class="form-group">
                                <label for="permisos" class="form-label">
                                    Permisos de Reservas <span class="required">*</span>
                                </label>
                                <?php 
                                $permisos_value = '';
                                if ($is_edit && $trabajador_data && isset($trabajador_data['permisos'])) {
                                    $permisos_value = $trabajador_data['permisos'];
                                } elseif (isset($_POST['permisos'])) {
                                    $permisos_value = $_POST['permisos'];
                                } else {
                                    $permisos_value = '1'; // Valor por defecto
                                }
                                ?>
                                <select name="permisos" id="permisos" class="form-control" required>
                                    <option value="1" <?php echo ($permisos_value == '1') ? 'selected' : ''; ?>>
                                        Ver/Editar solo sus reservas
                                    </option>
                                    <option value="2" <?php echo ($permisos_value == '2') ? 'selected' : ''; ?>>
                                        Ver/Editar todas las reservas
                                    </option>
                                </select>
                                <div class="help-text">Selecciona el nivel de acceso que tendrá el trabajador a las reservas</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="color_calendario" class="form-label">
                                    Color en el Calendario <span class="required">*</span>
                                </label>
                                <?php 
                                $color_seleccionado = '';
                                if ($is_edit && $trabajador_data) {
                                    $color_seleccionado = $trabajador_data['color_calendario'];
                                } elseif (isset($_POST['color_calendario'])) {
                                    $color_seleccionado = $_POST['color_calendario'];
                                }
                                ?>
                                <div class="color-picker-container">
                                    <?php foreach ($colores_calendario as $color_hex => $color_nombre): ?>
                                        <label class="color-option">
                                            <input type="radio" 
                                                   name="color_calendario" 
                                                   value="<?php echo $color_hex; ?>"
                                                   <?php echo ($color_seleccionado === $color_hex) ? 'checked' : ''; ?>
                                                   required>
                                            <div class="color-display" 
                                                 style="background-color: <?php echo $color_hex; ?>" 
                                                 title="<?php echo $color_nombre; ?>">
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="help-text">Selecciona el color que aparecerá en el calendario para este trabajador</div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h2 class="section-title">
                                <i></i> Horario y Foto
                            </h2>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Horario de Trabajo <span class="required">*</span>
                                </label>
                                
                                <div class="horario-opciones" style="margin-bottom: 20px;">
                                    <button type="button" class="btn-mismo-horario" id="btn-mismo-horario">
                                        <i></i> Mismo horario que el centro
                                    </button>
                                    <button type="button" class="btn-horario-personalizado" id="btn-horario-personalizado">
                                        <i></i> Horario personalizado
                                    </button>
                                </div>
                                
                                <?php 
                                $horario_trabajador = [];
                                if ($is_edit && $trabajador_data && !empty($trabajador_data['horario'])) {
                                    $horario_decoded = json_decode($trabajador_data['horario'], true);
                                    if (is_array($horario_decoded)) {
                                        $horario_trabajador = $horario_decoded;
                                    }
                                }
                                
                                $dias_trabajador = [
                                    'lunes' => 'Lunes',
                                    'martes' => 'Martes',
                                    'miercoles' => 'Miércoles',
                                    'jueves' => 'Jueves',
                                    'viernes' => 'Viernes',
                                    'sabado' => 'Sábado',
                                    'domingo' => 'Domingo'
                                ];
                                ?>
                                
                                <input type="hidden" name="tipo_horario" id="tipo-horario" value="<?php echo ($is_edit && $trabajador_data && $trabajador_data['horario'] === null) ? 'mismo_centro' : 'personalizado'; ?>">
                                
                                <div class="mensaje-mismo-horario" id="mensaje-mismo-horario" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        El trabajador seguirá el mismo horario de apertura del centro seleccionado.
                                    </div>
                                </div>
                                
                                <div class="horario-trabajador-container" id="horario-trabajador-container">
                                    <?php foreach ($dias_trabajador as $dia_key => $dia_nombre): ?>
                                    <div class="horario-dia-trabajador" id="horario-trabajador-<?php echo $dia_key; ?>">
                                        <div class="dia-header-trabajador">
                                            <h5><?php echo $dia_nombre; ?></h5>
                                            <div class="dia-controles-trabajador">
                                                <label for="trabajador-abierto-<?php echo $dia_key; ?>" class="switch-trabajador">
                                                    <input type="checkbox" id="trabajador-abierto-<?php echo $dia_key; ?>" class="toggle-trabajador-abierto" 
                                                           <?php echo (!isset($horario_trabajador[$dia_key]['cerrado']) || $horario_trabajador[$dia_key]['cerrado'] !== true) ? 'checked' : ''; ?>>
                                                    <span class="slider-trabajador"></span>
                                                </label>
                                                <span class="toggle-label-trabajador">Trabaja</span>
                                            </div>
                                        </div>
                                        
                                        <div class="horario-rangos-trabajador" id="rangos-trabajador-<?php echo $dia_key; ?>" 
                                             <?php echo (isset($horario_trabajador[$dia_key]['cerrado']) && $horario_trabajador[$dia_key]['cerrado'] === true) ? 'style="display:none;"' : ''; ?>>
                                            
                                            <?php 
                                            $rangos_trabajador = [];
                                            if (isset($horario_trabajador[$dia_key]['rangos']) && is_array($horario_trabajador[$dia_key]['rangos'])) {
                                                $rangos_trabajador = $horario_trabajador[$dia_key]['rangos'];
                                            } elseif (!isset($horario_trabajador[$dia_key]['cerrado']) || $horario_trabajador[$dia_key]['cerrado'] !== true) {
                                                $rangos_trabajador = [['inicio' => '09:00', 'fin' => '18:00']];
                                            }
                                            
                                            if (!empty($rangos_trabajador)): 
                                                foreach ($rangos_trabajador as $index => $rango): 
                                            ?>
                                            <div class="rango-horario-trabajador">
                                                <select class="hora-inicio-trabajador" name="horario_trabajador[<?php echo $dia_key; ?>][rangos][<?php echo $index; ?>][inicio]">
                                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                                        <?php for ($m = 0; $m < 60; $m += 30): ?>
                                                            <?php 
                                                                $hora = sprintf('%02d:%02d', $h, $m);
                                                                $selected = ($hora === $rango['inicio']) ? 'selected' : '';
                                                            ?>
                                                            <option value="<?php echo $hora; ?>" <?php echo $selected; ?>><?php echo $hora; ?></option>
                                                        <?php endfor; ?>
                                                    <?php endfor; ?>
                                                </select>
                                                <span class="separador-trabajador">—</span>
                                                <select class="hora-fin-trabajador" name="horario_trabajador[<?php echo $dia_key; ?>][rangos][<?php echo $index; ?>][fin]">
                                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                                        <?php for ($m = 0; $m < 60; $m += 30): ?>
                                                            <?php 
                                                                $hora = sprintf('%02d:%02d', $h, $m);
                                                                $selected = ($hora === $rango['fin']) ? 'selected' : '';
                                                            ?>
                                                            <option value="<?php echo $hora; ?>" <?php echo $selected; ?>><?php echo $hora; ?></option>
                                                        <?php endfor; ?>
                                                    <?php endfor; ?>
                                                </select>
                                                <button type="button" class="btn-eliminar-rango-trabajador">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <?php 
                                                endforeach; 
                                            else: 
                                            ?>
                                            <div class="rango-horario-trabajador">
                                                <select class="hora-inicio-trabajador" name="horario_trabajador[<?php echo $dia_key; ?>][rangos][0][inicio]">
                                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                                        <?php for ($m = 0; $m < 60; $m += 30): ?>
                                                            <?php 
                                                                $hora = sprintf('%02d:%02d', $h, $m);
                                                                $selected = ($hora === '09:00') ? 'selected' : '';
                                                            ?>
                                                            <option value="<?php echo $hora; ?>" <?php echo $selected; ?>><?php echo $hora; ?></option>
                                                        <?php endfor; ?>
                                                    <?php endfor; ?>
                                                </select>
                                                <span class="separador-trabajador">—</span>
                                                <select class="hora-fin-trabajador" name="horario_trabajador[<?php echo $dia_key; ?>][rangos][0][fin]">
                                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                                        <?php for ($m = 0; $m < 60; $m += 30): ?>
                                                            <?php 
                                                                $hora = sprintf('%02d:%02d', $h, $m);
                                                                $selected = ($hora === '18:00') ? 'selected' : '';
                                                            ?>
                                                            <option value="<?php echo $hora; ?>" <?php echo $selected; ?>><?php echo $hora; ?></option>
                                                        <?php endfor; ?>
                                                    <?php endfor; ?>
                                                </select>
                                                <button type="button" class="btn-eliminar-rango-trabajador">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn-agregar-rango-trabajador" data-dia="<?php echo $dia_key; ?>">
                                                <i class="fas fa-plus"></i> Añadir horario
                                            </button>
                                        </div>
                                        
                                        <input type="hidden" class="estado-cerrado-trabajador" name="horario_trabajador[<?php echo $dia_key; ?>][cerrado]" 
                                               value="<?php echo (isset($horario_trabajador[$dia_key]['cerrado']) && $horario_trabajador[$dia_key]['cerrado'] === true) ? 'true' : 'false'; ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="foto" class="form-label">
                                    Foto del Trabajador
                                </label>
                                
                                <?php if ($is_edit && $trabajador_data && !empty($trabajador_data['url_foto'])): ?>
                                    <div class="current-photo" style="margin-bottom: 15px;">
                                        <p style="margin-bottom: 10px; font-weight: 500;">Foto actual:</p>
                                        <img src="<?php echo sanitizeInput($trabajador_data['url_foto']); ?>" 
                                             alt="Foto actual" 
                                             style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #024ddf;">
                                        <p style="margin-top: 5px; font-size: 14px; color: #666;">Selecciona una nueva foto para reemplazar la actual</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="file-input-wrapper">
                                    <input type="file" 
                                           name="foto" 
                                           id="foto" 
                                           class="file-input" 
                                           accept="image/jpeg,image/png,image/gif,image/webp">
                                    <div class="file-input-display">
                                        <i class="fas fa-camera file-icon"></i>
                                        <span class="file-text">Seleccionar foto <?php echo $is_edit ? '(opcional - mantener actual)' : '(opcional)'; ?></span>
                                    </div>
                                </div>
                                <div class="help-text">Formatos permitidos: JPG, PNG, GIF, WebP. Tamaño: entre 1KB y 5MB</div>
                                <div id="imagePreview" class="image-preview" style="display: none;">
                                    <img id="previewImg" class="preview-image" src="" alt="Vista previa">
                                </div>
                            </div>
                        </div>
                        
                        <div class="btn-container">
                            <button type="submit" class="btn btn-primary">
                                <i></i> <?php echo $button_text; ?>
                            </button>
                            <a href="index" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Volver al Equipo
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <template id="template-rango-horario-trabajador">
        <div class="rango-horario-trabajador">
            <select class="hora-inicio-trabajador" name="horario_trabajador[{DIA}][rangos][{INDEX}][inicio]">
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <?php for ($m = 0; $m < 60; $m += 30): ?>
                        <?php $hora = sprintf('%02d:%02d', $h, $m); ?>
                        <option value="<?php echo $hora; ?>"><?php echo $hora; ?></option>
                    <?php endfor; ?>
                <?php endfor; ?>
            </select>
            <span class="separador-trabajador">—</span>
            <select class="hora-fin-trabajador" name="horario_trabajador[{DIA}][rangos][{INDEX}][fin]">
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <?php for ($m = 0; $m < 60; $m += 30): ?>
                        <?php $hora = sprintf('%02d:%02d', $h, $m); ?>
                        <option value="<?php echo $hora; ?>"><?php echo $hora; ?></option>
                    <?php endfor; ?>
                <?php endfor; ?>
            </select>
            <button type="button" class="btn-eliminar-rango-trabajador">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </template>
    
    <script>
        window.workerData = {
            csrfToken: '<?php echo $_SESSION['csrf_token']; ?>',
            isEdit: <?php echo $is_edit ? 'true' : 'false'; ?>,
            trabajadorId: <?php echo $is_edit && $trabajador_data ? (int)$trabajador_data['id'] : 'null'; ?>
        };
    </script>
    <script src="../../assets/js/sidebar.js"></script>
    <script src="js/gestion-trabajador.js"></script>
</body>
</html> 
