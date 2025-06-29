<?php include '../../assets/includes/header.php'; ?>
<meta name="robots" content="noindex">


<link rel="stylesheet" href="../../assets/css/styles.css">

<div class="form-container">
    <h2>Actualizar tus preferencias</h2>
    <form id="unsubscribeForm">
        <div class="form-group">
            <label for="estado">Preferencia:</label>
            <select id="estado" name="estado" required>
                <option value="">Seleccione una opción</option>
                <option value="baja_permanente">Darse de baja para siempre</option>
                <option value="mensual">Recibir solo un correo al mes</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="correo">Correo electrónico:</label>
            <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" required>
            <div id="emailError" class="form-error">Por favor, introduce un correo electrónico válido.</div>
        </div>
        
        <button type="submit" id="submitBtn" class="form-button">Confirmar</button>
        <div id="loader" class="form-loader"></div>
    </form>
    
    <div id="successMessage" class="form-success">
        Tu solicitud ha sido procesada correctamente.
    </div>
    
    <div id="errorMessage" class="form-error" style="display: none;">
        Ha ocurrido un error al procesar tu solicitud. Por favor, inténtalo de nuevo.
    </div>
</div>

<style>
    .form-container {
        border: 1px solid #ddd;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        max-width: 400px;
        margin: 170px auto;
    }
    
    .form-container h2 {
        color: #333;
        margin-top: 0;
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .form-group select, 
    .form-group input[type="email"] {
        width: 100%;
        padding: 10px;
        margin-bottom: 5px;
        border: 1px solid #ddd;
        border-radius: 10px;
        box-sizing: border-box;
    }
    
    .form-button {
        background-color: #007bff;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        font-size: 16px;
    }
    
    .form-button:hover {
        background-color: #45a049;
    }
    
    .form-error {
        color: red;
        font-size: 14px;
        margin-bottom: 15px;
        display: none;
    }
    
    .form-success {
        color: green;
        font-weight: bold;
        margin-top: 20px;
        display: none;
    }
    
    .form-loader {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        animation: spin 2s linear infinite;
        display: none;
        margin: 0 auto;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<?php
// Configuración de seguridad
ini_set('display_errors', 0); // Ocultar errores en producción
error_reporting(0);

// Validar si el formulario fue enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Token CSRF
    session_start();
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['error' => 'Error de seguridad: Token CSRF inválido']));
    }

    // Sanitización de inputs
    $estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_STRING);
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);

    // Validaciones
    if (!$estado || !$correo) {
        die(json_encode(['error' => 'Todos los campos son obligatorios']));
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['error' => 'Correo electrónico inválido']));
    }

    // Validar opciones permitidas
    $opciones_validas = ['baja_permanente', 'mensual'];
    if (!in_array($estado, $opciones_validas)) {
        die(json_encode(['error' => 'Opción inválida']));
    }

    // Configuración del correo
    $to = 'info@buscounservicio.es'; // Dirección de destino
    $subject = 'Solicitud de gestión de preferencias';
    
    // Cuerpo del mensaje con protección contra inyección
    $message = "Nueva solicitud de preferencias:\n\n";
    $message .= "Correo: " . htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') . "\n";
    $message .= "Preferencia: " . ($estado === 'baja_permanente' ? 'Baja permanente' : 'Correo mensual') . "\n";
    $message .= "Fecha: " . date('d/m/Y H:i:s') . "\n";
    $message .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";

    // Cabeceras seguras
    $headers = "From: no-reply@buscounservicio.es\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // Protección contra inyección en cabeceras
    if (preg_match("/[\r\n]/", $correo)) {
        die(json_encode(['error' => 'Datos inválidos']));
    }

    // Registro en log para auditoría
    $log_message = date('Y-m-d H:i:s') . " - Solicitud: $correo - $estado - IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    file_put_contents(__DIR__ . '/logs/unsubscribe.log', $log_message, FILE_APPEND | LOCK_EX);

    // Enviar correo
    if (mail($to, $subject, $message, $headers)) {
        echo json_encode(['success' => 'Solicitud procesada correctamente']);
    } else {
        echo json_encode(['error' => 'Error al procesar la solicitud']);
    }
    exit;
}
?>

<script>
// Generar token CSRF
const csrfToken = '<?php echo bin2hex(random_bytes(32)); ?>';
<?php $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>

document.getElementById('unsubscribeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Mostrar loader
    const loader = document.getElementById('loader');
    const submitBtn = document.getElementById('submitBtn');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    const emailError = document.getElementById('emailError');
    
    loader.style.display = 'block';
    submitBtn.disabled = true;
    successMessage.style.display = 'none';
    errorMessage.style.display = 'none';
    emailError.style.display = 'none';

    // Validación del lado del cliente
    const email = document.getElementById('correo').value;
    const estado = document.getElementById('estado').value;
    
    if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        emailError.style.display = 'block';
        loader.style.display = 'none';
        submitBtn.disabled = false;
        return;
    }

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `estado=${encodeURIComponent(estado)}&correo=${encodeURIComponent(email)}&csrf_token=${csrfToken}`
        });

        const result = await response.json();
        
        if (result.success) {
            successMessage.style.display = 'block';
            this.reset();
        } else {
            errorMessage.textContent = result.error || 'Error al procesar la solicitud';
            errorMessage.style.display = 'block';
        }
    } catch (error) {
        errorMessage.textContent = 'Error de conexión. Por favor, intenta de nuevo.';
        errorMessage.style.display = 'block';
    } finally {
        loader.style.display = 'none';
        submitBtn.disabled = false;
    }
});
</script>

<?php include '../../assets/includes/footer.php'; ?>
<script src="../../assets/js/header.js"></script>
<script>
    document.querySelectorAll('.faq-item h3').forEach(i => i.addEventListener('click', () => i.parentElement.classList.toggle('active')));
    document.getElementById('search').addEventListener('input', function() {
        let f = this.value.toLowerCase();
        document.querySelectorAll('.faq-item').forEach(i => i.style.display = i.querySelector('h3').innerText.toLowerCase().includes(f) ? "block" : "none");
    });
</script>