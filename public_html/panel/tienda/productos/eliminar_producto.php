<?php

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'');

session_start();

// Regenerar ID de sesión para prevenir session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

require_once '../../../../config.php';
require_once '../../../../db-publica.php';

use Delight\Auth\Auth;

try {
    $auth = new Auth($pdo);
} catch (Exception $e) {
    error_log("Error de autenticación: " . $e->getMessage());
    http_response_code(500);
    exit('Error del servidor');
}

// Verificar si el usuario está logeado
if (!$auth->isLoggedIn()) {
    // Limpiar y validar la URL de redirección
    $redirect_url = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
    
    // Validar que la URL pertenece al dominio actual
    $parsed_url = parse_url($redirect_url);
    if ($parsed_url && !isset($parsed_url['host'])) {
        $_SESSION['redirect_url'] = $redirect_url;
    }
    
    header('Location: /auth/login.php');
    exit;
}

// Obtener y validar el ID del usuario
$user_id = $auth->getUserId();
if (!is_numeric($user_id) || $user_id <= 0) {
    error_log("ID de usuario inválido: " . $user_id);
    header('Location: /auth/login.php');
    exit;
}

// Verificar rol del usuario con prepared statement
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['role'] !== 'negocio') {
        error_log("Acceso denegado para usuario ID: " . $user_id);
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error de base de datos: " . $e->getMessage());
    http_response_code(500);
    exit('Error del servidor');
}

// Verificar token CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        error_log("Token CSRF inválido para usuario: " . $user_id);
        http_response_code(403);
        exit('Token de seguridad inválido');
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

// Procesar eliminación de producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['producto_id'])) {
    
    // Validar y sanitizar producto_id
    $producto_id = filter_var($_POST['producto_id'], FILTER_VALIDATE_INT);
    if ($producto_id === false || $producto_id <= 0) {
        error_log("ID de producto inválido: " . $_POST['producto_id']);
        http_response_code(400);
        exit('ID de producto inválido');
    }
    
    try {
        // Iniciar transacción
        $pdo2->beginTransaction();
        
        // Verificar que el producto pertenece al usuario
        $stmt = $pdo2->prepare("SELECT usuario_id, url_imagenes FROM productos WHERE producto_id = ? AND usuario_id = ? LIMIT 1");
        $stmt->execute([$producto_id, $user_id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            $pdo2->rollBack();
            error_log("Intento de eliminar producto no autorizado. Usuario: " . $user_id . ", Producto: " . $producto_id);
            http_response_code(403);
            exit('No autorizado');
        }
        
        // Eliminar imágenes de forma segura
        if ($producto['url_imagenes']) {
            $imagenes = explode(',', $producto['url_imagenes']);
            foreach ($imagenes as $imagen) {
                $imagen = trim($imagen);
                if (empty($imagen)) continue;
                
                if (strpos($imagen, CLOUDFLARE_R2_CDN_URL . '/productos/') === 0) {
                    if (!deleteFromCloudflareR2($imagen)) {
                        error_log("No se pudo eliminar la imagen de R2: " . $imagen);
                    }
                } else {
                    if (strpos($imagen, '..') !== false || strpos($imagen, '//') !== false) {
                        error_log("Ruta de imagen sospechosa: " . $imagen);
                        continue;
                    }
                    
                    $base_path = realpath('../../../');
                    $file_path = $base_path . '/' . ltrim($imagen, '/');
                    
                    if (strpos(realpath(dirname($file_path)), $base_path) === 0 && file_exists($file_path)) {
                        if (!unlink($file_path)) {
                            error_log("No se pudo eliminar la imagen local: " . $file_path);
                        }
                    }
                }
            }
        }
        
        // Eliminar producto de la base de datos
        $stmt = $pdo2->prepare("DELETE FROM productos WHERE producto_id = ? AND usuario_id = ?");
        $result = $stmt->execute([$producto_id, $user_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            $pdo2->commit();
            error_log("Producto eliminado exitosamente. ID: " . $producto_id . ", Usuario: " . $user_id);
        } else {
            $pdo2->rollBack();
            error_log("No se pudo eliminar el producto. ID: " . $producto_id);
        }
        
    } catch (PDOException $e) {
        $pdo2->rollBack();
        error_log("Error al eliminar producto: " . $e->getMessage());
        http_response_code(500);
        exit('Error del servidor');
    } catch (Exception $e) {
        $pdo2->rollBack();
        error_log("Error inesperado: " . $e->getMessage());
        http_response_code(500);
        exit('Error del servidor');
    }
}

// Limpiar el buffer de salida antes de redirigir
if (ob_get_level()) {
    ob_end_clean();
}

header('Location: index.php');
exit;
?>