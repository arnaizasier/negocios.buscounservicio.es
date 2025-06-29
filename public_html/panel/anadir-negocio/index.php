<?php
require_once 'conexion.php';

// Iniciar sesión segura
if (session_status() === PHP_SESSION_NONE) {
    $cookie_params = session_get_cookie_params();
    session_set_cookie_params(
        $cookie_params["lifetime"],
        $cookie_params["path"], 
        $cookie_params["domain"], 
        true, // secure flag (HTTPS only)
        true  // httponly flag
    );
    session_start();
}

// Regenerar ID de sesión periódicamente
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Headers de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . $_SESSION['script_nonce'] . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';");

$auth = new \Delight\Auth\Auth($pdo);
$usuario_id = verificarUsuarioAutenticado($auth);

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Generar nonce para scripts inline
if (empty($_SESSION['script_nonce'])) {
    $_SESSION['script_nonce'] = bin2hex(random_bytes(16));
}

// Funciones de seguridad mejoradas
function sanitizarInputSeguro($data, $type = 'string') {
    if ($data === null || $data === '') return '';
    
    $data = trim($data);
    $data = stripslashes($data);
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

function validarNombreNegocio($nombre) {
    $errors = [];
    
    if (empty($nombre)) {
        $errors[] = 'El nombre del negocio es obligatorio';
    } elseif (strlen($nombre) < 2 || strlen($nombre) > 100) {
        $errors[] = 'El nombre debe tener entre 2 y 100 caracteres';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-\.\&]+$/u', $nombre)) {
        $errors[] = 'El nombre contiene caracteres no permitidos';
    }
    
    return $errors;
}

function validarDescripcion($descripcion) {
    $errors = [];
    
    if (!empty($descripcion)) {
        if (strlen($descripcion) > 2000) {
            $errors[] = 'La descripción no puede exceder los 2000 caracteres';
        }
        
        // Detectar posible spam/contenido malicioso
        $spam_patterns = [
            '/\b(?:viagra|cialis|casino|lottery|winner|prize)\b/i',
            '/\b(?:http|https|www\.)/i',
            '/[<>{}]/i' // Detectar posibles tags HTML/scripts
        ];
        
        foreach ($spam_patterns as $pattern) {
            if (preg_match($pattern, $descripcion)) {
                $errors[] = 'La descripción contiene contenido no permitido';
                break;
            }
        }
    }
    
    return $errors;
}

function checkRateLimit($action = 'add_business', $limit = 5, $timeframe = 300) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = $action . '_' . $ip;
    
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 0, 'time' => time()];
    }
    
    $rate_data = $_SESSION['rate_limit'][$key];
    
    if (time() - $rate_data['time'] > $timeframe) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'time' => time()];
        return true;
    }
    
    if ($rate_data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION['rate_limit'][$key]['count']++;
    return true;
}

function logSecurityEvent($message, $data = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'user_id' => $GLOBALS['usuario_id'] ?? null,
        'message' => $message,
        'data' => $data
    ];
    
    error_log("SECURITY [ADD_BUSINESS]: " . json_encode($logEntry));
}

function isValidUserAgent() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($user_agent) || strlen($user_agent) < 10) {
        return false;
    }
    
    $suspicious_patterns = [
        '/bot/i', '/crawler/i', '/spider/i', '/scraper/i',
        '/wget/i', '/curl/i', '/python/i', '/perl/i',
        '/script/i', '/automated/i', '/headless/i'
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return false;
        }
    }
    
    return true;
}

// Validaciones de seguridad iniciales
if (!isValidUserAgent()) {
    logSecurityEvent('Suspicious user agent detected', ['user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
    http_response_code(403);
    exit('Acceso denegado');
}

if (!checkRateLimit()) {
    logSecurityEvent('Rate limit exceeded for add business', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    http_response_code(429);
    exit('Demasiados intentos. Inténtalo más tarde.');
}

$negocio = null;
$negocio_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $negocio_id = intval($_GET['id']);
    
    $stmt = $pdoNegocios->prepare("SELECT * FROM negocios WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id");
    $stmt->execute([':negocio_id' => $negocio_id, ':usuario_id' => $usuario_id]);
    $negocio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$negocio) {
        logSecurityEvent('Unauthorized business access attempt', [
            'negocio_id' => $negocio_id,
            'user_id' => $usuario_id
        ]);
        header('Location: index.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logSecurityEvent('CSRF token validation failed', [
            'provided_token' => $_POST['csrf_token'] ?? 'none',
            'expected_token' => $_SESSION['csrf_token'] ?? 'none'
        ]);
        $error = "Error de verificación de seguridad. Recarga la página e inténtalo de nuevo.";
    }
    // Verificar honeypot (debe estar vacío)
    elseif (!empty($_POST['website'])) {
        logSecurityEvent('Honeypot field filled - possible bot', [
            'honeypot_value' => $_POST['website']
        ]);
        // Redirigir silenciosamente para confundir al bot
        header("Location: paso2?id=" . ($negocio_id ?: 'new'));
        exit();
    }
    // Verificar timestamp para prevenir envíos muy rápidos
    elseif (isset($_POST['form_timestamp']) && (time() - (int)$_POST['form_timestamp'] < 2)) {
        logSecurityEvent('Form submitted too quickly - possible bot', [
            'time_diff' => time() - (int)$_POST['form_timestamp']
        ]);
        $error = "Por favor, tómate un momento para revisar la información.";
    }
    else {
        $nombre = sanitizarInputSeguro($_POST['nombre'] ?? '');
        $descripcion = sanitizarInputSeguro($_POST['descripcion'] ?? '');
        
        // Validar nombre
        $nombre_errors = validarNombreNegocio($nombre);
        if (!empty($nombre_errors)) {
            $error = $nombre_errors[0];
            logSecurityEvent('Invalid business name provided', ['name' => $nombre, 'errors' => $nombre_errors]);
        }
        
        // Validar descripción
        if (empty($error)) {
            $descripcion_errors = validarDescripcion($descripcion);
            if (!empty($descripcion_errors)) {
                $error = $descripcion_errors[0];
                logSecurityEvent('Invalid business description provided', ['description' => substr($descripcion, 0, 100), 'errors' => $descripcion_errors]);
            }
        }
        
        // Verificar duplicados
        if (empty($error)) {
            $sql = "SELECT COUNT(*) FROM negocios WHERE nombre = :nombre";
            $params = [':nombre' => $nombre];
            
            if ($negocio_id !== null) {
                $sql .= " AND negocio_id != :negocio_id";
                $params[':negocio_id'] = $negocio_id;
            }
            
            $stmt = $pdoNegocios->prepare($sql);
            $stmt->execute($params);
            $nombreExiste = ($stmt->fetchColumn() > 0);
            
            if ($nombreExiste) {
                $error = "Ya existe un negocio con este nombre. Por favor, elige otro nombre.";
                logSecurityEvent('Duplicate business name attempt', ['name' => $nombre]);
            }
        }
        
        // Procesar si no hay errores
        if (empty($error)) {
            $url = generarURLAmigable($nombre);
            
            try {
                $pdoNegocios->exec("SET NAMES utf8mb4");
                
                if ($negocio_id !== null) {
                    $stmt = $pdoNegocios->prepare("UPDATE negocios SET nombre = :nombre, descripcion_negocio = :descripcion, url = :url 
                                              WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id");
                    $stmt->execute([
                        ':nombre' => $nombre,
                        ':descripcion' => $descripcion,
                        ':url' => $url,
                        ':negocio_id' => $negocio_id,
                        ':usuario_id' => $usuario_id
                    ]);
                    
                    logSecurityEvent('Business updated successfully', ['negocio_id' => $negocio_id]);
                } else {
                    $stmt = $pdoNegocios->prepare("INSERT INTO negocios (usuario_id, nombre, descripcion_negocio, url) 
                                              VALUES (:usuario_id, :nombre, :descripcion, :url)");
                    $stmt->execute([
                        ':usuario_id' => $usuario_id,
                        ':nombre' => $nombre,
                        ':descripcion' => $descripcion,
                        ':url' => $url
                    ]);
                    
                    $negocio_id = $pdoNegocios->lastInsertId();
                    logSecurityEvent('New business created successfully', ['negocio_id' => $negocio_id]);
                }
                
                // Regenerar token CSRF después del éxito
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                header("Location: paso2?id=$negocio_id");
                exit();
            } catch (PDOException $e) {
                $error = "Error al guardar los datos. Inténtalo de nuevo.";
                logSecurityEvent('Database error during business save', [
                    'error' => $e->getMessage(),
                    'negocio_id' => $negocio_id
                ]);
            }
        }
    }
}

function generarURLAmigable($texto) {
    setlocale(LC_ALL, 'es_ES.UTF-8');
    
    $texto = mb_strtolower($texto, 'UTF-8');
    
    $equivalencias = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ñ' => 'n', 'ç' => 'c',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
        'À' => 'a', 'È' => 'e', 'Ì' => 'i', 'Ò' => 'o', 'Ù' => 'u',
        'Ñ' => 'n', 'Ç' => 'c',
    ];
    
    foreach ($equivalencias as $especial => $normal) {
        $texto = str_replace($especial, $normal, $texto);
    }
    
    $texto = preg_replace('/[^a-z0-9\-]/', '-', $texto);
    
    $texto = preg_replace('/-+/', '-', $texto);
    
    $texto = trim($texto, '-');
    
    if (empty($texto)) {
        $texto = 'negocio';
    }
    
    return $texto;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Negocio - Información Básica</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="/assets/css/marca.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Información Básica</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="form-negocio">
                <!-- Token CSRF -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <!-- Timestamp para prevenir envíos muy rápidos -->
                <input type="hidden" name="form_timestamp" value="<?php echo time(); ?>">
                
                <!-- Honeypot field (debe permanecer vacío) -->
                <div style="position: absolute; left: -9999px; visibility: hidden;">
                    <label for="website">Website (no llenar):</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="nombre" class="required">Nombre del Negocio</label>
                    <input type="text" 
                           id="nombre" 
                           name="nombre" 
                           value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : htmlspecialchars($negocio['nombre'] ?? ''); ?>" 
                           required 
                           maxlength="100"
                           pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-\.\&]+"
                           title="Solo se permiten letras, números, espacios, guiones, puntos y el símbolo &">
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción del Negocio</label>
                    <textarea id="descripcion" 
                              name="descripcion" 
                              maxlength="2000"><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : htmlspecialchars($negocio['descripcion_negocio'] ?? ''); ?></textarea>
                    <div class="char-counter">
                        <span id="char-count">0</span>/2000 caracteres
                    </div>
                </div>
                
                <div class="btn-nav">
                    <div></div>
                    <button type="submit" id="submit-btn">Siguiente</button>
                </div>
            </form>
        </div>
    </div>
    
    <script nonce="<?php echo $_SESSION['script_nonce']; ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('form-negocio');
        const nombreInput = document.getElementById('nombre');
        const descripcionTextarea = document.getElementById('descripcion');
        const charCount = document.getElementById('char-count');
        const submitBtn = document.getElementById('submit-btn');
        
        // Contador de caracteres para la descripción
        function updateCharCount() {
            const count = descripcionTextarea.value.length;
            charCount.textContent = count;
            
            if (count > 2000) {
                charCount.style.color = 'red';
                submitBtn.disabled = true;
                         } else if (count > 1800) {
                 charCount.style.color = 'orange';
                 submitBtn.disabled = false;
            } else {
                charCount.style.color = 'green';
                submitBtn.disabled = false;
            }
        }
        
        // Actualizar contador al cargar y al escribir
        updateCharCount();
        descripcionTextarea.addEventListener('input', updateCharCount);
        
        // Validación del nombre en tiempo real
        nombreInput.addEventListener('input', function() {
            const value = this.value;
            const pattern = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-\.\&]*$/;
            
            if (!pattern.test(value)) {
                this.setCustomValidity('Solo se permiten letras, números, espacios, guiones, puntos y el símbolo &');
            } else if (value.length < 2) {
                this.setCustomValidity('El nombre debe tener al menos 2 caracteres');
            } else if (value.length > 100) {
                this.setCustomValidity('El nombre no puede exceder los 100 caracteres');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Validación de contenido malicioso en descripción
        descripcionTextarea.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const suspiciousPatterns = [
                'viagra', 'cialis', 'casino', 'lottery', 'winner', 'prize',
                'http://', 'https://', 'www.', '<script', '<iframe', 'javascript:'
            ];
            
            let hasSuspiciousContent = false;
            for (let pattern of suspiciousPatterns) {
                if (value.includes(pattern)) {
                    hasSuspiciousContent = true;
                    break;
                }
            }
            
            if (hasSuspiciousContent) {
                this.setCustomValidity('La descripción contiene contenido no permitido');
                submitBtn.disabled = true;
            } else {
                this.setCustomValidity('');
                submitBtn.disabled = false;
            }
        });
        
        // Prevenir envío múltiple
        let formSubmitted = false;
        form.addEventListener('submit', function(e) {
            // Verificar honeypot
            const honeypot = document.querySelector('input[name="website"]');
            if (honeypot && honeypot.value !== '') {
                e.preventDefault();
                return false;
            }
            
            // Prevenir doble envío
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            
            // Validar campos antes del envío
            if (!nombreInput.value.trim()) {
                alert('El nombre del negocio es obligatorio');
                e.preventDefault();
                return false;
            }
            
            if (nombreInput.value.length < 2 || nombreInput.value.length > 100) {
                alert('El nombre debe tener entre 2 y 100 caracteres');
                e.preventDefault();
                return false;
            }
            
            if (descripcionTextarea.value.length > 2000) {
                alert('La descripción no puede exceder los 2000 caracteres');
                e.preventDefault();
                return false;
            }
            
            // Marcar como enviado y deshabilitar botón
            formSubmitted = true;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Procesando...';
            
            // Reactivar después de 5 segundos en caso de error
            setTimeout(function() {
                formSubmitted = false;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Siguiente';
            }, 5000);
        });
        
        // Protección contra copiar/pegar contenido malicioso
        descripcionTextarea.addEventListener('paste', function(e) {
            setTimeout(function() {
                const value = descripcionTextarea.value.toLowerCase();
                const suspiciousPatterns = [
                    '<script', '<iframe', 'javascript:', 'vbscript:', 'onload=', 'onerror='
                ];
                
                for (let pattern of suspiciousPatterns) {
                    if (value.includes(pattern)) {
                        descripcionTextarea.value = descripcionTextarea.value.replace(new RegExp(pattern, 'gi'), '');
                        alert('Se ha detectado y eliminado contenido potencialmente peligroso');
                        break;
                    }
                }
                updateCharCount();
            }, 10);
        });
    });
    </script>
</body>
</html>