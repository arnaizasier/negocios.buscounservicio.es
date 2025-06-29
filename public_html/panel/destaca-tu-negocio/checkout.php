<?php
require_once __DIR__ . "/../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../src/rate-limiting.php";
require_once __DIR__ . "/../../src/headers-seguridad.php";
require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";

if (!isset($_SESSION['checkout_attempts'])) {
    $_SESSION['checkout_attempts'] = [];
}

$current_time = time();
$rate_limit_window = 300; 
$max_attempts = 5;

$_SESSION['checkout_attempts'] = array_filter($_SESSION['checkout_attempts'], function($timestamp) use ($current_time, $rate_limit_window) {
    return ($current_time - $timestamp) <= $rate_limit_window;
});

if (count($_SESSION['checkout_attempts']) >= $max_attempts) {
    error_log('Rate limit exceeded for checkout - User ID: ' . ($_SESSION['usuario_id'] ?? 'unknown') . ', IP: ' . $_SERVER['REMOTE_ADDR']);
    http_response_code(429);
    echo json_encode(['error' => 'Demasiados intentos de pago. Intente más tarde']);
    exit;
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!$csrf_token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$negocio_id = filter_input(INPUT_POST, 'negocio_id', FILTER_VALIDATE_INT);
$plan = filter_input(INPUT_POST, 'plan', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$negocio_id || !$plan) {
    error_log('Checkout attempt with missing data - User ID: ' . ($_SESSION['usuario_id'] ?? 'unknown') . ', IP: ' . $_SERVER['REMOTE_ADDR']);
    http_response_code(400);
    echo json_encode(['error' => 'Datos requeridos faltantes']);
    exit;
}

$allowed_plans = ['monthly', 'yearly'];
if (!in_array($plan, $allowed_plans)) {
    error_log('Invalid plan attempt - User ID: ' . ($_SESSION['usuario_id'] ?? 'unknown') . ', Plan: ' . $plan . ', IP: ' . $_SERVER['REMOTE_ADDR']);
    http_response_code(400);
    echo json_encode(['error' => 'Solicitud inválida']);
    exit;
}

try {
    $products = [
        'monthly' => 'price_1RXU54Rpyfnvpe3QYpfZufrH',
        'yearly' => 'price_1RXU5nRpyfnvpe3QcfaBqJFb'
    ];

    if (!isset($products[$plan])) {
        error_log('Invalid Stripe price ID attempt - User ID: ' . ($_SESSION['usuario_id'] ?? 'unknown') . ', Plan: ' . $plan);
        http_response_code(400);
        echo json_encode(['error' => 'Configuración de producto inválida']);
        exit;
    }

    session_regenerate_id(true);

    $usuario_id = $_SESSION['usuario_id'] ?? null;
    $email = $_SESSION['email'] ?? null;

    if (!$usuario_id) {
        error_log('Unauthenticated checkout attempt from IP: ' . $_SERVER['REMOTE_ADDR']);
        http_response_code(401);
        echo json_encode(['error' => 'Sesión no válida']);
        exit;
    }

    $stmt = $pdo2->prepare("SELECT negocio_id FROM negocios WHERE negocio_id = ? AND usuario_id = ?");
    $stmt->execute([$negocio_id, $usuario_id]);
    if (!$stmt->fetch()) {
        error_log('Unauthorized business access attempt - User ID: ' . $usuario_id . ', Business ID: ' . $negocio_id . ', IP: ' . $_SERVER['REMOTE_ADDR']);
        http_response_code(403);
        echo json_encode(['error' => 'Acceso denegado']);
        exit;
    }

    $metadata = [
        'user_id' => filter_var($usuario_id, FILTER_VALIDATE_INT),
        'negocio_id' => filter_var($negocio_id, FILTER_VALIDATE_INT),
        'tipo_destacado' => ($plan === 'yearly' ? 'Anual' : 'Mensual')
    ];

    foreach ($metadata as $key => $value) {
        if ($value === false || (is_string($value) && strlen($value) > 500)) {
            error_log('Invalid metadata detected - Key: ' . $key . ', User ID: ' . $usuario_id);
            http_response_code(400);
            echo json_encode(['error' => 'Datos inválidos']);
            exit;
        }
    }

    $success_url = 'https://gestion.buscounservicio.es/panel/perfil/index.php?negocio_id=' . urlencode($negocio_id) . '&payment_success=1';
    $cancel_url = 'https://gestion.buscounservicio.es/panel/destaca-tu-negocio/index.php?payment_cancelled=1';

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price' => $products[$plan],
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        'customer_email' => filter_var($email, FILTER_VALIDATE_EMAIL),
        'metadata' => $metadata
    ]);

    $_SESSION['checkout_attempts'][] = $current_time;
    
    error_log('Stripe checkout session created - User ID: ' . $usuario_id . ', Business ID: ' . $negocio_id . ', Plan: ' . $plan . ', Session ID: ' . $session->id);

    echo json_encode(['url' => $session->url]);
} catch (\Stripe\Exception\CardException $e) {
    error_log('Stripe card error - User ID: ' . ($_SESSION['usuario_id'] ?? 'unknown') . ', Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Error en el procesamiento del pago']);
} catch (\Stripe\Exception\RateLimitException $e) {
    error_log('Stripe rate limit - User ID: ' . ($_SESSION['usuario_id'] ?? 'unknown') . ', IP: ' . $_SERVER['REMOTE_ADDR']);
    http_response_code(429);
    echo json_encode(['error' => 'Demasiadas solicitudes. Intente más tarde']);
} catch (\Stripe\Exception\InvalidRequestException $e) {
    error_log('Stripe invalid request - User ID: ' . ($_SESSION['usuario_id'] ?? 'unknown') . ', Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Solicitud inválida']);
} catch (\Stripe\Exception\AuthenticationException $e) {
    error_log('Stripe authentication error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error de configuración del servicio']);
} catch (\Stripe\Exception\ApiConnectionException $e) {
    error_log('Stripe connection error: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Servicio temporalmente no disponible']);
} catch (Exception $e) {
    error_log('Unexpected checkout error - User ID: ' . ($_SESSION['usuario_id'] ?? 'unknown') . ', Error: ' . $e->getMessage() . ', File: ' . $e->getFile() . ', Line: ' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}