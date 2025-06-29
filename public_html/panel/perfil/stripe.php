<?php
session_start();
require_once __DIR__ . "/../../../config.php";
use Delight\Auth\Auth;
use Stripe\Stripe;
use Stripe\Checkout\Session;

$auth = new Auth($pdo); 
if (!$auth->isLoggedIn()) {
    $redirect_url = $_SERVER['REQUEST_URI'];
    $_SESSION['redirect_url'] = $redirect_url;
    header('Location: /auth/login.php');
    exit;
}

$user_id = $auth->getUserId();
$email = $auth->getEmail();
$negocio_id = filter_input(INPUT_GET, 'negocio_id', FILTER_VALIDATE_INT);

if (!$negocio_id) {
    die('No se ha proporcionado un negocio_id válido.');
}

defined('STRIPE_SECRET_KEY') or die('Clave de Stripe no definida');
Stripe::setApiKey(STRIPE_SECRET_KEY);

$product_id = 'prod_SSNwK8x16UOOyq';
$price_id = 'price_1RXTAERpyfnvpe3QHJ9KBIQE';

// URL de éxito modificada para incluir negocio_id
$success_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
    '://' . $_SERVER['HTTP_HOST'] . '/panel/perfil/index?negocio_id=' . $negocio_id;

$cancel_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
    '://' . $_SERVER['HTTP_HOST'] . '/panel/perfil/index?checkout=cancel';

try {
    $session = Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price' => $price_id,
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        'client_reference_id' => $user_id,
        'customer_email' => $email,
        'subscription_data' => [
            'metadata' => [
                'user_id' => $user_id,
                'negocio_id' => $negocio_id
            ]
        ]
    ]);
    
    header('Location: ' . $session->url);
    exit;
} catch (Exception $e) {
    echo 'Error al iniciar el checkout de Stripe: ' . $e->getMessage();
}
?>