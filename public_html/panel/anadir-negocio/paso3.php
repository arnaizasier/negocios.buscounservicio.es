<?php
require_once 'conexion.php';
require_once '/home/u898735099/domains/negocios.buscounservicio.es/config.php';

$auth = new \Delight\Auth\Auth($pdo);

$usuario_id = verificarUsuarioAutenticado($auth);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

function validarRateLimit($usuario_id) {
    $limite_tiempo = 300; 
    $limite_intentos = 20; 
    
    if (!isset($_SESSION['upload_attempts'])) {
        $_SESSION['upload_attempts'] = [];
    }
    
    $tiempo_actual = time();
    $intentos = $_SESSION['upload_attempts'];
    
    $intentos = array_filter($intentos, function($timestamp) use ($tiempo_actual, $limite_tiempo) {
        return ($tiempo_actual - $timestamp) < $limite_tiempo;
    });
    
    if (count($intentos) >= $limite_intentos) {
        return false;
    }
    
    $intentos[] = $tiempo_actual;
    $_SESSION['upload_attempts'] = $intentos;
    
    return true;
}

$negocio_id = intval($_GET['id']);

$stmt = $pdoNegocios->prepare("SELECT * FROM negocios WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id");
$stmt->execute([':negocio_id' => $negocio_id, ':usuario_id' => $usuario_id]);
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    header('Location: index.php');
    exit();
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
$tamanoMinimo = 1024; // 1KB mínimo

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

function validarCSRFToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

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

function limpiarNombreNegocio($nombre) {
    $nombre = strtolower(trim($nombre));
    $nombre = preg_replace('/[^a-zA-Z0-9\s]/', '', $nombre);
    $nombre = preg_replace('/\s+/', '-', $nombre);
    $nombre = preg_replace('/-+/', '-', $nombre);
    $nombre = trim($nombre, '-');
    return substr($nombre, 0, 50);
}

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
    
    $cloudflareUrl = CLOUDFLARE_R2_CDN_URL . "/negocios/" . $newFileName;
    $objectKey = "negocios/" . $newFileName;
    
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCSRFToken()) {
        $error = "Token de seguridad inválido. Por favor, recarga la página e inténtalo de nuevo.";
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => $error]);
            exit();
        }
    } elseif (!validarRateLimit($usuario_id)) {
        $error = "Has excedido el límite de subidas. Por favor, espera unos minutos antes de intentar de nuevo.";
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => $error]);
            exit();
        }
    } else {
        $directorio = '/home/u898735099/domains/buscounservicio.es/public_html/imagenes/negocios/';
        
        if (!is_dir($directorio)) {
            if (!mkdir($directorio, 0755, true)) {
                $error = "No se pudo crear el directorio de imágenes.";
            }
        }
        
        if (!isset($error) && (!is_writable($directorio) || !is_readable($directorio))) {
            $error = "El directorio de imágenes no tiene los permisos correctos.";
        }
        
        $fotos = [];
        $errorSubida = false;
        
        if (!empty($negocio['url_fotos'])) {
            $fotos = json_decode($negocio['url_fotos'], true) ?: [];
        }
    
    if (isset($_POST['reordenar_fotos']) && !empty($_POST['reordenar_fotos'])) {
        $nuevoOrden = json_decode($_POST['reordenar_fotos'], true);
        if (is_array($nuevoOrden) && count($nuevoOrden) === count($fotos)) {
            $fotosReordenadas = [];
            foreach ($nuevoOrden as $indice) {
                if (isset($fotos[$indice])) {
                    $fotosReordenadas[] = $fotos[$indice];
                }
            }
            if (count($fotosReordenadas) === count($fotos)) {
                $fotos = $fotosReordenadas;
            }
        }
    }
    
            if (!isset($error) && isset($_FILES['fotos']) && $_FILES['fotos']['error'][0] !== UPLOAD_ERR_NO_FILE) {
            $archivos = $_FILES['fotos'];
            $totalArchivos = count($archivos['name']);
            
            if (count($fotos) + $totalArchivos > 10) {
                $error = "Solo se permiten un máximo de 10 fotos en total.";
            } else {
                for ($i = 0; $i < $totalArchivos; $i++) {
                    if ($archivos['error'][$i] === UPLOAD_ERR_OK) {
                        $tamanoArchivo = $archivos['size'][$i];
                        
                        if ($tamanoArchivo < $tamanoMinimo) {
                            $error = "Uno o más archivos son demasiado pequeños (mínimo 1KB).";
                            $errorSubida = true;
                            break;
                        }
                        
                        if ($tamanoArchivo > $tamanoMaximo) {
                            $error = "Uno o más archivos exceden el tamaño máximo permitido de 5MB.";
                            $errorSubida = true;
                            break;
                        }
                        
                        $nombreOriginal = limpiarNombreArchivo($archivos['name'][$i]);
                        if (empty($nombreOriginal)) {
                            $error = "Nombre de archivo no válido.";
                            $errorSubida = true;
                            break;
                        }
                        
                        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
                        
                        if (!in_array($extension, $extensionesPermitidas)) {
                            $error = "Una o más imágenes tienen una extensión no permitida.";
                            $errorSubida = true;
                            break;
                        }
                        
                        $tmpName = $archivos['tmp_name'][$i];
                        
                        if (!is_uploaded_file($tmpName)) {
                            $error = "Error de seguridad: archivo no subido correctamente.";
                            $errorSubida = true;
                            break;
                        }
                        
                        $extensionNormalizada = ($extension === 'jpg') ? 'jpeg' : $extension;
                        if (!validarFirmaArchivo($tmpName, $extensionNormalizada)) {
                            $error = "Uno o más archivos no tienen la firma correcta para su tipo.";
                            $errorSubida = true;
                            break;
                        }
                        
                        if (!validarImagenSegura($tmpName)) {
                            $error = "Uno o más archivos no pasaron las validaciones de seguridad.";
                            $errorSubida = true;
                            break;
                        }
                    
                                            $nombreNegocioLimpio = limpiarNombreNegocio($negocio['nombre']);
                            $numeroRandom = mt_rand(1000, 9999);
                            $hashRandom = bin2hex(random_bytes(8));
                            
                            $nombreTemp = 'temp_' . $nombreNegocioLimpio . '_' . $numeroRandom . '_' . $hashRandom . '.' . $extension;
                        $rutaTemp = $directorio . $nombreTemp;
                        
                        if (move_uploaded_file($tmpName, $rutaTemp)) {
                            chmod($rutaTemp, 0644);
                            
                            if (!validarImagenSegura($rutaTemp)) {
                                unlink($rutaTemp);
                                $error = "El archivo subido no pasó las validaciones de seguridad adicionales.";
                                $errorSubida = true;
                                break;
                            }
                            
                            $nombreNuevo = $nombreNegocioLimpio . '_' . $numeroRandom . '_' . $hashRandom . '.webp';
                            $rutaCompleta = $directorio . $nombreNuevo;
                            
                            if (convertToWebP($rutaTemp, $rutaCompleta, 80)) {
                                $cloudflareUrl = uploadToCloudflareR2($rutaCompleta);
                                
                                if ($cloudflareUrl) {
                                    unlink($rutaTemp);
                                    unlink($rutaCompleta);
                                    $fotos[] = $cloudflareUrl;
                                } else {
                                    unlink($rutaTemp);
                                    unlink($rutaCompleta);
                                    $error = "Error al subir la imagen a Cloudflare R2.";
                                    $errorSubida = true;
                                    break;
                                }
                            } else {
                                if ($extension === 'webp') {
                                    if (rename($rutaTemp, $rutaCompleta)) {
                                        $cloudflareUrl = uploadToCloudflareR2($rutaCompleta);
                                        
                                        if ($cloudflareUrl) {
                                            unlink($rutaCompleta);
                                            $fotos[] = $cloudflareUrl;
                                        } else {
                                            unlink($rutaCompleta);
                                            $error = "Error al subir la imagen a Cloudflare R2.";
                                            $errorSubida = true;
                                            break;
                                        }
                                    } else {
                                        unlink($rutaTemp);
                                        $errorSubida = true;
                                    }
                                } else {
                                    unlink($rutaTemp);
                                    $error = "Error al convertir la imagen a formato WebP.";
                                    $errorSubida = true;
                                    break;
                                }
                            }
                    } else {
                        $errorSubida = true;
                    }
                } else {
                    $errorSubida = true;
                }
            }
        }
    }
    
    if ($errorSubida && !isset($error)) {
        $error = "Hubo un error al subir una o más imágenes.";
    }
    
            if (!isset($error) && isset($_POST['eliminar_foto']) && !empty($_POST['eliminar_foto'])) {
            $fotoEliminar = trim($_POST['eliminar_foto']);
            
            $indice = array_search($fotoEliminar, $fotos);
            
            if ($indice !== false) {
                // Verificar que sea una URL válida de Cloudflare
                if (strpos($fotoEliminar, CLOUDFLARE_R2_CDN_URL . '/') === 0) {
                    // Es una URL de Cloudflare R2 - eliminar de la base de datos
                    unset($fotos[$indice]);
                    $fotos = array_values($fotos);
                } else {
                    $error = "Formato de URL no válido para eliminación.";
                }
            } else {
                $error = "La imagen no pertenece a este negocio.";
            }
        }
    
    if (!isset($error)) {
        try {
            $fotosCodificadas = !empty($fotos) ? json_encode($fotos) : null;
            
            $stmt = $pdoNegocios->prepare("UPDATE negocios SET url_fotos = :url_fotos WHERE negocio_id = :negocio_id");
            $stmt->execute([
                ':url_fotos' => $fotosCodificadas,
                ':negocio_id' => $negocio_id
            ]);
            
            if (isset($_POST['siguiente'])) {
                header("Location: paso4.php?id=$negocio_id");
                exit();
            }
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => 'Imágenes guardadas correctamente']);
                exit();
            }
            
            header("Location: paso3.php?id=$negocio_id&success=1");
            exit();
        } catch (PDOException $e) {
            $error = "Error al guardar los datos: " . $e->getMessage();
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                echo json_encode(['success' => false, 'message' => $error]);
                exit();
            }
        }
    } else {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => $error]);
            exit();
        }
    }
    }
}

$fotos = [];
if (!empty($negocio['url_fotos'])) {
    $fotos = json_decode($negocio['url_fotos'], true) ?: [];
}

if (!isset($_SESSION['csrf_token']) || (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

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
    <title>Añadir Negocio - Fotos</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/paso3.css">
    <link rel="stylesheet" href="/assets/css/marca.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Galería de Imágenes</h2>
            
            <div id="messageContainer">
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-message">Las imágenes se han guardado correctamente.</div>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="reordenar_fotos" id="reordenar_fotos" value="">
                <input type="hidden" name="eliminar_foto" id="eliminar_foto" value="">
                
                <div class="form-group">
                    <label>Subir imágenes de tu negocio</label>
                    <p style="margin-top: 5px; margin-bottom: 20px; font-size: 14px; color: #666; ">
                        Puedes seleccionar varias imágenes a la vez (máximo 10 en total). Las imágenes ayudarán a tus clientes a conocer mejor tu negocio.
                    </p>
                    
                    <div class="file-input-container">
                        <div class="file-input-button">Seleccionar Imágenes</div>
                        <input type="file" id="fotos" name="fotos[]" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" multiple>
                    </div>
                    
                    <div class="saving-indicator" id="savingIndicator">
                        Subiendo imagenes...
                    </div>
                    
                    <div class="preview-container" id="imagePreview"></div>
                </div>
                
                <?php if (!empty($fotos)): ?>
                    <h3>Imágenes guardadas</h3>
                    <div class="drag-instruction">
                        <strong>Consejo:</strong> Puedes arrastrar las imágenes para cambiar su orden. La primera imagen será la principal de tu negocio.
                    </div>
                    <div class="gallery-container" id="sortableGallery">
                        <?php foreach ($fotos as $index => $foto): ?>
                            <div class="gallery-item" data-index="<?php echo $index; ?>">
                                <div class="gallery-item-order"><?php echo $index + 1; ?></div>
                                <img src="<?php echo htmlspecialchars($foto); ?>" alt="Imagen del negocio">
                                <button type="button" class="remove-img" data-foto="<?php echo htmlspecialchars($foto); ?>">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-top: 10px; font-size: 14px;">
                        Total: <?php echo count($fotos); ?> de 10 imágenes.
                    </p>
                <?php endif; ?>
                
                <div class="btn-nav">
                    <a href="paso2.php?id=<?php echo $negocio_id; ?>" class="button-secondary">Anterior</a>
                    <button type="submit" name="siguiente">Siguiente</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const inputElement = document.getElementById('fotos');
        const previewContainer = document.getElementById('imagePreview');
        const maxImages = 10;
        const currentImages = <?php echo count($fotos); ?>;
        let selectedFiles = [];
        const savingIndicator = document.getElementById('savingIndicator');
        const messageContainer = document.getElementById('messageContainer');
        
        const form = document.getElementById('uploadForm');
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        
        function ajaxSend(formData, callback) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        callback(response);
                    } catch(e) {
                        console.error('Error al analizar la respuesta del servidor', e);
                        showMessage('Ha ocurrido un error al procesar la respuesta', 'error');
                    }
                } else {
                    showMessage('Error de conexión con el servidor', 'error');
                }
                
                savingIndicator.style.display = 'none';
            };
            
            xhr.onerror = function() {
                showMessage('Error de conexión con el servidor', 'error');
                savingIndicator.style.display = 'none';
            };
            
            xhr.send(formData);
        }
        
        function showMessage(message, type = 'success') {
            messageContainer.innerHTML = '';
            const messageDiv = document.createElement('div');
            messageDiv.className = type === 'error' ? 'error-message' : 'success-message';
            messageDiv.textContent = message;
            messageContainer.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    messageContainer.innerHTML = '';
                }, 500);
            }, 5000);
        }

        inputElement.addEventListener('change', function(e) {
            const files = e.target.files;
            
            if (files.length === 0) return;
            
            const tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            const tamanoMaximo = 5 * 1024 * 1024; 
            const tamanoMinimo = 1024; 
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (!tiposPermitidos.includes(file.type)) {
                    showMessage('Solo se permiten archivos JPG, PNG, GIF y WebP.', 'error');
                    this.value = '';
                    return;
                }
                
                if (file.size > tamanoMaximo) {
                    showMessage('Uno o más archivos exceden el tamaño máximo de 5MB.', 'error');
                    this.value = '';
                    return;
                }
                
                if (file.size < tamanoMinimo) {
                    showMessage('Uno o más archivos son demasiado pequeños (mínimo 1KB).', 'error');
                    this.value = '';
                    return;
                }
            }
            
            if (currentImages + files.length > maxImages) {
                showMessage(`Solo se permiten un máximo de ${maxImages} fotos en total. Ya tienes ${currentImages} guardadas.`, 'error');
                this.value = '';
                return;
            }
            
            savingIndicator.style.display = 'block';
            
            const formData = new FormData(form);
            ajaxSend(formData, function(response) {
                if (response.success) {
                    showMessage('Imágenes subidas correctamente');
                    window.location.reload();
                } else {
                    showMessage(response.message || 'Error al subir las imágenes', 'error');
                }
            });
        });
        
        document.querySelectorAll('.remove-img').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (confirm('¿Estás seguro de que deseas eliminar esta imagen?')) {
                    const foto = this.getAttribute('data-foto');
                    document.getElementById('eliminar_foto').value = foto;
                    
                    savingIndicator.style.display = 'block';
                    
                    const formData = new FormData(form);
                    ajaxSend(formData, function(response) {
                        if (response.success) {
                            showMessage('Imagen eliminada correctamente');
                            window.location.reload();
                        } else {
                            showMessage(response.message || 'Error al eliminar la imagen', 'error');
                        }
                    });
                }
            });
        });
        

        let orderUpdateTimeout = null;
        
        const sortableGallery = document.getElementById('sortableGallery');
        if (sortableGallery) {
            let draggedItem = null;
            let galleryItems = Array.from(sortableGallery.querySelectorAll('.gallery-item'));
            
            function handleDragStart(e) {
                draggedItem = this;
                setTimeout(() => {
                    this.classList.add('dragging');
                }, 0);
            }
            
            function handleDragEnd(e) {
                this.classList.remove('dragging');
                draggedItem = null;
                
                updateItemOrders();
                
                autoSaveNewOrder();
            }
            
            function handleDragOver(e) {
                e.preventDefault();
                return false;
            }
            
            function handleDragEnter(e) {
                e.preventDefault();
            }
            
            function handleDragLeave() {
            }
            
            function handleDrop(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (draggedItem !== this) {
                    const allItems = Array.from(sortableGallery.querySelectorAll('.gallery-item'));
                    const draggedIndex = allItems.indexOf(draggedItem);
                    const targetIndex = allItems.indexOf(this);
                    
                    if (draggedIndex < targetIndex) {
                        this.parentNode.insertBefore(draggedItem, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedItem, this);
                    }
                }
                
                return false;
            }
            
            galleryItems.forEach(item => {
                item.setAttribute('draggable', true);
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragend', handleDragEnd);
                item.addEventListener('dragover', handleDragOver);
                item.addEventListener('dragenter', handleDragEnter);
                item.addEventListener('dragleave', handleDragLeave);
                item.addEventListener('drop', handleDrop);
            });
            
            function updateItemOrders() {
                const items = Array.from(sortableGallery.querySelectorAll('.gallery-item'));
                items.forEach((item, index) => {
                    const orderElement = item.querySelector('.gallery-item-order');
                    if (orderElement) {
                        orderElement.textContent = index + 1;
                    }
                });
            }
            
            function autoSaveNewOrder() {
                if (orderUpdateTimeout) {
                    clearTimeout(orderUpdateTimeout);
                }
                
                orderUpdateTimeout = setTimeout(() => {
                    const items = Array.from(sortableGallery.querySelectorAll('.gallery-item'));
                    const newOrder = items.map(item => parseInt(item.getAttribute('data-index')));
                    document.getElementById('reordenar_fotos').value = JSON.stringify(newOrder);
                    
                    savingIndicator.style.display = 'block';
                    
                    const formData = new FormData(form);
                    ajaxSend(formData, function(response) {
                        if (response.success) {
                            showMessage('Orden actualizado correctamente');
                        } else {
                            showMessage(response.message || 'Error al actualizar el orden', 'error');
                        }
                    });
                }, 500);
            }
        }
    </script>
</body>
</html>