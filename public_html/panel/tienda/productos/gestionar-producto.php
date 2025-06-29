<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";

require_once '../../../../config.php';
require_once '../../../../db-publica.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

function convertToWebP($sourcePath, $destinationPath, $quality = 70) {
    try {
        $imagick = new Imagick($sourcePath);
        $imagick->setImageFormat('webp');
        $imagick->setImageCompressionQuality($quality);
        $imagick->stripImage();
        $result = $imagick->writeImage($destinationPath);
        $imagick->destroy();
        return $result;
    } catch (Exception $e) {
        error_log("Error converting to WebP: " . $e->getMessage());
        return false;
    }
}

function uploadToCloudflareR2($localPath) {
    $fileName = basename($localPath);
    $newFileName = pathinfo($fileName, PATHINFO_FILENAME) . '.webp';
    
    $cloudflareUrl = CLOUDFLARE_R2_CDN_URL . "/productos/" . $newFileName;
    $objectKey = "productos/" . $newFileName;
    
    $apiUrl = "https://api.cloudflare.com/client/v4/accounts/" . CLOUDFLARE_R2_ACCOUNT_ID . "/r2/buckets/" . CLOUDFLARE_R2_BUCKET_NAME . "/objects/$objectKey";
    
    $fileContent = file_get_contents($localPath);
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . CLOUDFLARE_R2_API_TOKEN,
            'Content-Type: image/webp',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return $cloudflareUrl;
    } else {
        error_log("Error uploading to Cloudflare R2: HTTP {$httpCode} - {$response}");
        if ($error) {
            error_log("cURL Error: {$error}");
        }
        return false;
    }
}

function deleteFromCloudflareR2($cloudflareUrl) {
    if (strpos($cloudflareUrl, CLOUDFLARE_R2_CDN_URL . '/productos/') !== 0) {
        return false;
    }
    
    $fileName = basename($cloudflareUrl);
    $objectKey = "productos/" . $fileName;
    
    $apiUrl = "https://api.cloudflare.com/client/v4/accounts/" . CLOUDFLARE_R2_ACCOUNT_ID . "/r2/buckets/" . CLOUDFLARE_R2_BUCKET_NAME . "/objects/$objectKey";
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . CLOUDFLARE_R2_API_TOKEN,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    } else {
        error_log("Error deleting from Cloudflare R2: HTTP {$httpCode} - {$response}");
        if ($error) {
            error_log("cURL Error: {$error}");
        }
        return false;
    }
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
    
    $mimeTypesPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
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

$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$es_edicion = $producto_id > 0;
$producto = null;

if ($es_edicion) {
    $stmt = $pdo2->prepare("SELECT * FROM productos WHERE producto_id = ? AND usuario_id = ?");
    $stmt->execute([$producto_id, $user_id]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        header('Location: index.php');
        exit;
    }
}

$stmt = $pdo2->prepare("SELECT negocio_id, nombre FROM negocios WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$negocios = $stmt->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar datos
    $negocio_id = filter_input(INPUT_POST, 'negocio_id', FILTER_VALIDATE_INT);
    $nombre = trim(strip_tags(filter_input(INPUT_POST, 'nombre', FILTER_DEFAULT) ?? ''));
    $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
    $precio_compra = filter_input(INPUT_POST, 'precio_compra', FILTER_VALIDATE_FLOAT) ?? 0;
    $precio_envio = filter_input(INPUT_POST, 'precio_envio', FILTER_VALIDATE_FLOAT) ?? 0;
    $descripcion = trim(strip_tags(filter_input(INPUT_POST, 'descripcion', FILTER_DEFAULT) ?? ''));
    $visible = filter_input(INPUT_POST, 'visible', FILTER_VALIDATE_INT) ?? 1;
    $unidades = filter_input(INPUT_POST, 'unidades', FILTER_VALIDATE_INT) ?? 0;
    $aviso_stock_bajo = filter_input(INPUT_POST, 'aviso_stock_bajo', FILTER_VALIDATE_INT) ?? 0;
    $sku = trim(strip_tags(filter_input(INPUT_POST, 'sku', FILTER_DEFAULT) ?? ''));
    $nombre_proveedor = trim(strip_tags(filter_input(INPUT_POST, 'nombre_proveedor', FILTER_DEFAULT) ?? ''));

    $base_slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($nombre)));
    $base_slug = trim($base_slug, '-');

    $stmt_negocio = $pdo2->prepare("SELECT url FROM negocios WHERE negocio_id = ?");
    $stmt_negocio->execute([$negocio_id]);
    $negocio = $stmt_negocio->fetch();
    $negocio_slug = '';

    if ($negocio && !empty($negocio['url'])) {
        $negocio_url_parts = explode('/', $negocio['url']);
        $negocio_slug = end($negocio_url_parts);
        
        if (!empty($negocio_slug)) {
            $url_producto = $negocio_slug . "/producto/" . $base_slug;
        } else {
            $url_producto = "producto/" . $base_slug;
        }
    } else {
        $url_producto = "producto/" . $base_slug;
    }

    $suffix = 1;
    $temp_url = $url_producto;
    while (true) {
        if ($es_edicion) {
            $stmt = $pdo2->prepare("SELECT COUNT(*) FROM productos WHERE url_producto = ? AND producto_id != ?");
            $stmt->execute([$temp_url, $producto_id]);
        } else {
            $stmt = $pdo2->prepare("SELECT COUNT(*) FROM productos WHERE url_producto = ?");
            $stmt->execute([$temp_url]);
        }
        
        if ($stmt->fetchColumn() == 0) {
            $url_producto = $temp_url;
            break;
        }
        $temp_url = $url_producto . "-" . $suffix;
        $suffix++;
    }

    $url_imagenes = [];
    if ($es_edicion) {
        $imagenes_existentes = !empty($producto['url_imagenes']) ? explode(',', $producto['url_imagenes']) : [];
        $imagenes_eliminar = $_POST['eliminar_imagen'] ?? [];
        
        foreach ($imagenes_eliminar as $imagen) {
            $index = array_search($imagen, $imagenes_existentes);
            if ($index !== false) {
                deleteFromCloudflareR2($imagen);
                unset($imagenes_existentes[$index]);
            }
        }
        $url_imagenes = array_values($imagenes_existentes);
    }

    $upload_dir = '/tmp/productos_temp/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!empty($_FILES['imagenes']['name'][0])) {
        $total_imagenes = count($url_imagenes) + count($_FILES['imagenes']['name']);
        
        if ($total_imagenes > 5) {
            $errors[] = "No puedes tener más de 5 imágenes en total.";
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            foreach ($_FILES['imagenes']['tmp_name'] as $index => $tmp_name) {
                if ($_FILES['imagenes']['error'][$index] === UPLOAD_ERR_OK) {
                    $mime = finfo_file($finfo, $tmp_name);
                    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                        $errors[] = "La imagen " . htmlspecialchars($_FILES['imagenes']['name'][$index]) . " no es JPEG, PNG, GIF o WebP.";
                        continue;
                    }
                    
                    if (!validarImagenSegura($tmp_name)) {
                        $errors[] = "La imagen " . htmlspecialchars($_FILES['imagenes']['name'][$index]) . " no pasó las validaciones de seguridad.";
                        continue;
                    }
                    
                    $filename_temp = uniqid('producto_') . '_' . bin2hex(random_bytes(8)) . '.webp';
                    $destination_temp = $upload_dir . $filename_temp;
                    
                    if (move_uploaded_file($tmp_name, $destination_temp . '.tmp')) {
                        if (convertToWebP($destination_temp . '.tmp', $destination_temp)) {
                            unlink($destination_temp . '.tmp');
                            
                            $cloudflareUrl = uploadToCloudflareR2($destination_temp);
                            if ($cloudflareUrl) {
                                unlink($destination_temp);
                                $url_imagenes[] = $cloudflareUrl;
                            } else {
                                unlink($destination_temp);
                                $errors[] = "Error al subir la imagen " . htmlspecialchars($_FILES['imagenes']['name'][$index]) . " a Cloudflare R2.";
                            }
                        } else {
                            $ext = pathinfo($_FILES['imagenes']['name'][$index], PATHINFO_EXTENSION);
                            if ($ext === 'webp') {
                                if (rename($destination_temp . '.tmp', $destination_temp)) {
                                    $cloudflareUrl = uploadToCloudflareR2($destination_temp);
                                    if ($cloudflareUrl) {
                                        unlink($destination_temp);
                                        $url_imagenes[] = $cloudflareUrl;
                                    } else {
                                        unlink($destination_temp);
                                        $errors[] = "Error al subir la imagen " . htmlspecialchars($_FILES['imagenes']['name'][$index]) . " a Cloudflare R2.";
                                    }
                                } else {
                                    unlink($destination_temp . '.tmp');
                                    $errors[] = "Error al procesar la imagen " . htmlspecialchars($_FILES['imagenes']['name'][$index]);
                                }
                            } else {
                                unlink($destination_temp . '.tmp');
                                $errors[] = "Error al convertir la imagen " . htmlspecialchars($_FILES['imagenes']['name'][$index]) . " a WebP.";
                            }
                        }
                    } else {
                        $errors[] = "Error al subir la imagen " . htmlspecialchars($_FILES['imagenes']['name'][$index]);
                    }
                }
            }
            finfo_close($finfo);
        }
    } elseif (!$es_edicion) {
        $errors[] = "Debes subir al menos una imagen.";
    }

    if (!$negocio_id) $errors[] = "Selecciona un negocio.";
    if (empty($nombre)) $errors[] = "El nombre es obligatorio.";
    if (!$es_edicion && empty($descripcion)) $errors[] = "La descripción es obligatoria.";
    if (!$precio || $precio <= 0) $errors[] = "El precio debe ser mayor a 0.";
    if ($precio_compra < 0) $errors[] = "El precio de compra no puede ser negativo.";
    if ($precio_envio < 0) $errors[] = "El precio de envío no puede ser negativo.";
    if (!$es_edicion && (!$unidades || $unidades <= 0)) $errors[] = "Las unidades son obligatorias y deben ser mayores a 0.";

    if (empty($errors)) {
        $url_imagenes_str = implode(',', $url_imagenes);
        
        if ($es_edicion) {
            $stmt = $pdo2->prepare("
                UPDATE productos
                SET negocio_id = ?, nombre = ?, url_producto = ?, url_imagenes = ?,
                    precio = ?, precio_compra = ?, precio_envio = ?, descripcion = ?, visible = ?, unidades = ?,
                    aviso_stock_bajo = ?, sku = ?, nombre_proveedor = ?
                WHERE producto_id = ? AND usuario_id = ?
            ");
            $stmt->execute([
                $negocio_id, $nombre, $url_producto, $url_imagenes_str,
                $precio, $precio_compra, $precio_envio, $descripcion, $visible, $unidades,
                $aviso_stock_bajo, $sku, $nombre_proveedor, $producto_id, $user_id
            ]);
        } else {
            $stmt = $pdo2->prepare("
                INSERT INTO productos 
                (usuario_id, negocio_id, nombre, url_producto, url_imagenes, precio, precio_compra, precio_envio, descripcion, visible, unidades, aviso_stock_bajo, sku, nombre_proveedor)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id, $negocio_id, $nombre, $url_producto, $url_imagenes_str,
                $precio, $precio_compra, $precio_envio, $descripcion, $visible, $unidades,
                $aviso_stock_bajo, $sku, $nombre_proveedor
            ]);
        }
        
        // Verificar stock bajo
        if ($unidades <= $aviso_stock_bajo && $aviso_stock_bajo > 0) {
            $user_email = $auth->getEmail();
            $subject = "Aviso de stock bajo para " . htmlspecialchars($nombre);
            $message = "El producto " . htmlspecialchars($nombre) . " tiene $unidades unidades, por debajo del aviso de stock bajo ($aviso_stock_bajo).";
            $headers = "From: no-reply@buscounservicio.es";
            mail($user_email, $subject, $message, $headers);
        }
        
        header('Location: /panel/tienda/productos/');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $es_edicion ? 'Editar' : 'Crear'; ?> Producto</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="gestionar-producto.css">
</head>
<body>
    <div class="container">
        <h1><?php echo $es_edicion ? 'Editar' : 'Crear'; ?> Producto</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (empty($negocios)): ?>
            <div class="alert alert-warning">
                No tienes negocios registrados. Por favor, crea un negocio primero.
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-block">
                    <div class="mb-3">
                        <label for="negocio_id" class="form-label">Negocio</label>
                        <select name="negocio_id" id="negocio_id" class="form-select" required>
                            <option value="">Selecciona un negocio</option>
                            <?php foreach ($negocios as $negocio): ?>
                                <option value="<?php echo $negocio['negocio_id']; ?>" 
                                    <?php echo ($es_edicion && $negocio['negocio_id'] == $producto['negocio_id']) || 
                                              (!$es_edicion && isset($_POST['negocio_id']) && $_POST['negocio_id'] == $negocio['negocio_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($negocio['nombre'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Producto</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" 
                               value="<?php echo htmlspecialchars($es_edicion ? ($producto['nombre'] ?? '') : ($_POST['nombre'] ?? '')); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="mb-3">
                            <label for="precio" class="form-label">Precio de Venta</label>
                            <input type="number" name="precio" id="precio" class="form-control" step="0.01" 
                                   value="<?php echo $es_edicion ? ($producto['precio'] ?? '') : ($_POST['precio'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="precio_envio" class="form-label">Precio de Envío</label>
                            <input type="number" name="precio_envio" id="precio_envio" class="form-control" step="0.01" 
                                   value="<?php echo $es_edicion ? ($producto['precio_envio'] ?? '') : ($_POST['precio_envio'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción del Producto</label>
                        <textarea name="descripcion" id="descripcion" class="form-control"><?php echo htmlspecialchars($es_edicion ? ($producto['descripcion'] ?? '') : ($_POST['descripcion'] ?? '')); ?></textarea>
                    </div>
                    
                    <?php if ($es_edicion && !empty($producto['url_imagenes'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Imágenes actuales</label>
                            <div class="preview-container">
                                <?php 
                                $imagenes = explode(',', $producto['url_imagenes']);
                                foreach ($imagenes as $index => $imagen): 
                                    if (!empty($imagen)):
                                ?>
                                    <div class="img-preview">
                                        <img src="<?php echo htmlspecialchars($imagen); ?>" alt="Imagen del producto">
                                        <div class="img-actions">
                                            <input type="checkbox" name="eliminar_imagen[]" value="<?php echo htmlspecialchars($imagen); ?>" id="eliminar_<?php echo $index; ?>" style="display: none;">
                                            <button type="button" class="remove-btn" onclick="toggleDeleteImage('eliminar_<?php echo $index; ?>', this)">Eliminar</button>
                                        </div>
                                    </div>
                                <?php 
                                    endif; 
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="imagenes" class="form-label">
                            <?php echo $es_edicion ? 'Subir nuevas imágenes' : 'Imágenes del producto'; ?>
                            <span class="optional-text">(máximo 5<?php echo $es_edicion ? ' en total' : ''; ?>)</span>
                        </label>
                        <div class="image-upload-container" onclick="document.getElementById('imagenes').click()">
                            <p>Haz clic aquí para seleccionar imágenes o arrástralas aquí</p>
                            <p><small>Formatos admitidos: JPEG, PNG, GIF, WebP</small></p>
                        </div>
                        <input type="file" name="imagenes[]" id="imagenes" class="form-control" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                        <div id="file-preview"></div>
                    </div>
                </div>
                
                <div class="form-block">
                    <div class="form-row">
                        <div class="mb-3">
                            <label for="visible" class="form-label">Visible para comprar</label>
                            <select name="visible" id="visible" class="form-select">
                                <option value="1" <?php echo ($es_edicion ? $producto['visible'] : (isset($_POST['visible']) ? $_POST['visible'] : 1)) ? 'selected' : ''; ?>>Sí</option>
                                <option value="0" <?php echo ($es_edicion ? !$producto['visible'] : (isset($_POST['visible']) && $_POST['visible'] == 0)) ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="precio_compra" class="form-label">Coste de Compra <span class="optional-text">(Opcional)</span></label>
                            <input type="number" name="precio_compra" id="precio_compra" class="form-control" step="0.01" 
                                   value="<?php echo $es_edicion ? ($producto['precio_compra'] ?? '') : ($_POST['precio_compra'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="mb-3">
                            <label for="unidades" class="form-label">Unidades</label>
                            <input type="number" name="unidades" id="unidades" class="form-control" 
                                   value="<?php echo $es_edicion ? ($producto['unidades'] ?? 0) : ($_POST['unidades'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="aviso_stock_bajo" class="form-label">Aviso de Stock Bajo <span class="optional-text">(Opcional)</span></label>
                            <input type="number" name="aviso_stock_bajo" id="aviso_stock_bajo" class="form-control" 
                                   value="<?php echo $es_edicion ? ($producto['aviso_stock_bajo'] ?? 0) : ($_POST['aviso_stock_bajo'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sku" class="form-label">SKU <span class="optional-text">(Opcional)</span></label>
                        <input type="text" name="sku" id="sku" class="form-control" 
                               value="<?php echo htmlspecialchars($es_edicion ? ($producto['sku'] ?? '') : ($_POST['sku'] ?? '')); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="nombre_proveedor" class="form-label">Nombre del Proveedor <span class="optional-text">(Opcional)</span></label>
                        <input type="text" name="nombre_proveedor" id="nombre_proveedor" class="form-control" 
                               value="<?php echo htmlspecialchars($es_edicion ? ($producto['nombre_proveedor'] ?? '') : ($_POST['nombre_proveedor'] ?? '')); ?>">
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn btn-primary"><?php echo $es_edicion ? 'Guardar Cambios' : 'Crear Producto'; ?></button>
                    <a href="https://negocios.buscounservicio.es/panel/tienda/productos/" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('imagenes');
            const filePreview = document.getElementById('file-preview');
            const imageUploadContainer = document.querySelector('.image-upload-container');

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                imageUploadContainer.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                imageUploadContainer.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                imageUploadContainer.addEventListener(eventName, unhighlight, false);
            });

            function highlight() {
                imageUploadContainer.style.borderColor = '#2755d3';
                imageUploadContainer.style.backgroundColor = '#f0f7ff';
            }

            function unhighlight() {
                imageUploadContainer.style.borderColor = '#ccc';
                imageUploadContainer.style.backgroundColor = '';
            }

            imageUploadContainer.addEventListener('drop', handleDrop, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                fileInput.files = files;
                handleFiles(files);
            }

            fileInput.addEventListener('change', function() {
                handleFiles(this.files);
            });

            function handleFiles(files) {
                filePreview.innerHTML = '';
                
                if (files.length > 5) {
                    alert('Solo puedes subir un máximo de 5 imágenes.');
                    fileInput.value = '';
                    return;
                }
                
                [...files].forEach(previewFile);
            }

            function previewFile(file) {
                if (!file.type.match('image.*')) {
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('div');
                    preview.className = 'preview-item';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-preview';
                    removeBtn.innerHTML = '×';
                    removeBtn.addEventListener('click', function() {
                        preview.remove();
                        
                        const dt = new DataTransfer();
                        const files = fileInput.files;
                        for (let i = 0; i < files.length; i++) {
                            const f = files[i];
                            if (!(f === file)) {
                                dt.items.add(f);
                            }
                        }
                        fileInput.files = dt.files;
                    });
                    
                    preview.appendChild(img);
                    preview.appendChild(removeBtn);
                    filePreview.appendChild(preview);
                }
                
                reader.readAsDataURL(file);
            }
        });

        <?php if ($es_edicion): ?>
        function toggleDeleteImage(checkboxId, button) {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox.checked) {
                checkbox.checked = false;
                button.textContent = "Eliminar";
                button.style.backgroundColor = "#dc3545";
                button.parentElement.parentElement.style.opacity = "1";
            } else {
                checkbox.checked = true;
                button.textContent = "Se eliminará";
                button.style.backgroundColor = "#6c757d";
                button.parentElement.parentElement.style.opacity = "0.5";
            }
        }
        <?php endif; ?>
    </script>
</body>
</html>