<?php
session_start();

// Regenerar ID de sesión periódicamente para prevenir ataques de fijación de sesión
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Generar token CSRF seguro
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

// Función para validar token CSRF
function validar_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Verificar autenticación y rol
if (!$auth->isLoggedIn()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: /auth/login.php');
    exit;
}

// Verificar rol de negocio
$stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt_role->execute([$user_id]);
$user_role = $stmt_role->fetch(PDO::FETCH_ASSOC);

if (!$user_role || $user_role['role'] !== 'negocio') {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Acceso denegado. No tienes permisos para acceder a esta página.'
    ];
    header('Location: /error/403.php');
    exit;
}

$_SESSION['usuario_id'] = $user_id; 
$email = $auth->getEmail();
$_SESSION['email'] = $email;

// Sanitizar y validar parámetro plan de forma moderna
$plan_raw = $_GET['plan'] ?? 'monthly';
$plan = trim(strip_tags($plan_raw));
$plan = htmlspecialchars($plan, ENT_QUOTES, 'UTF-8');
$allowed_plans = ['monthly', 'yearly'];
if (!in_array($plan, $allowed_plans)) {
    $plan = 'monthly';
}

// Verificar que el usuario existe y está activo
$stmt_user = $pdo->prepare("SELECT status, verified FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user_data || !$user_data['verified']) {
    header('Location: /auth/login.php');
    exit;
}

$stmt = $pdo2->prepare("SELECT negocio_id, nombre FROM negocios WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$negocios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar que el usuario tiene al menos un negocio
if (empty($negocios)) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'No tienes negocios activos registrados.'
    ];
    header('Location: ../mis-ubicaciones/');
    exit;
}

$plan_nombre = $plan === 'yearly' ? 'anual' : 'mensual';

$nonce = base64_encode(random_bytes(16));

// Headers de seguridad con CSP completo y mejorado
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; media-src 'self'; object-src 'none'; child-src 'none'; worker-src 'self'; manifest-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests; block-all-mixed-content;");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Negocio | BuscoUnServicio</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="css/seleccionar-negocio.css">
</head>

<body>
    <main>
        <div class="container">
            <div class="header">
                <h1>Selecciona tu negocio</h1>
                <p class="subtitle">Elige el negocio que deseas destacar con un plan <span class="plan-badge"><?php echo htmlspecialchars(ucfirst($plan_nombre), ENT_QUOTES, 'UTF-8'); ?></span></p>
            </div>
            
            <form id="negocioForm" action="checkout" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="plan" value="<?php echo htmlspecialchars($plan, ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="business-options">
                    <?php if (count($negocios) > 0): ?>
                        <?php foreach ($negocios as $index => $negocio): ?>
                        <div class="business-option" data-id="<?php echo (int)$negocio['negocio_id']; ?>">
                            <div class="business-radio">
                                <input type="radio" id="negocio_<?php echo (int)$negocio['negocio_id']; ?>" 
                                       name="negocio_id" 
                                       value="<?php echo (int)$negocio['negocio_id']; ?>" 
                                       required
                                       <?php echo ($index === 0) ? 'checked' : ''; ?>>
                                <span class="radio-custom"></span>
                            </div>
                            <label class="business-label" for="negocio_<?php echo (int)$negocio['negocio_id']; ?>">
                                <?php echo htmlspecialchars($negocio['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No tienes negocios registrados. Por favor, <a href="../panel/mis-direcciones">crea un negocio</a> primero.</p>
                    <?php endif; ?>
                </div>
                
                <?php if (count($negocios) > 0): ?>
                <button type="submit" class="btn-primary">
                    Continuar al pago
                </button>
                <?php endif; ?>
            </form>
            
            <div class="loading" id="loadingIndicator">
                <div class="spinner"></div>
                <span>Procesando tu solicitud...</span>
            </div>
        </div>
    </main>

    <script nonce="<?php echo htmlspecialchars(
        $nonce,
        ENT_QUOTES,
        'UTF-8'
    ); ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const businessOptions = document.querySelectorAll('.business-option');
        const loadingIndicator = document.getElementById('loadingIndicator');
        const submitButton = document.querySelector('.btn-primary');
        
        businessOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            
            if (radio.checked) {
                option.classList.add('selected');
            }
            
            option.addEventListener('click', function() {
                businessOptions.forEach(opt => {
                    opt.classList.remove('selected');
                    opt.querySelector('input[type="radio"]').checked = false;
                });
                
                option.classList.add('selected');
                radio.checked = true;
            });
        });
        
        document.getElementById('negocioForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Verificar que hay un negocio seleccionado
            const selectedNegocio = document.querySelector('input[name="negocio_id"]:checked');
            if (!selectedNegocio) {
                alert('Por favor, selecciona un negocio antes de continuar.');
                return;
            }
            
            submitButton.style.display = 'none';
            loadingIndicator.style.display = 'flex';
            
            const formData = new FormData(this);
            
            // Verificar que el token CSRF está presente
            if (!formData.get('csrf_token')) {
                alert('Error de seguridad. Por favor, recarga la página.');
                submitButton.style.display = 'flex';
                loadingIndicator.style.display = 'none';
                return;
            }
            
            fetch('checkout', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': formData.get('csrf_token')
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.url) {
                        window.location.href = data.url;
                    } else {
                        alert('Error al procesar el pago: ' + (data.error || 'Error desconocido'));
                        submitButton.style.display = 'flex';
                        loadingIndicator.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar el pago. Por favor, inténtalo de nuevo.');
                    submitButton.style.display = 'flex';
                    loadingIndicator.style.display = 'none';
                });
        });
    });
    </script>
</body>
</html>