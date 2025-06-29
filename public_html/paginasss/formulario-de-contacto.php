<?php
// contacto.php

require_once '../../config.php';

// Variables para almacenar mensajes
$mensaje_output = '';

// Configuración básica
$sitio_nombre = "buscounservicio";
$sitio_url = "https://buscounservicio.es";
$sitio_logo = "https://buscounservicio.es/imagen/logo-f-buscounservicio.webp";
$admin_email = "info@buscounservicio.es";


// Función para sanitizar entradas
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    // Configurar cookies de sesión seguras
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

// Regenerar ID de sesión para prevenir ataques de fijación de sesión
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Crear CSRF token si no existe
if (!isset($_SESSION['contacto_nonce'])) {
    $_SESSION['contacto_nonce'] = bin2hex(random_bytes(32)); // Aumentado a 32 bytes
}

// Rate limiting - protección contra spam
$rate_limit_period = 300; // 5 minutos en segundos
$max_submissions = 3; // máximo número de envíos en el periodo

// Implementar rate limiting
if (!isset($_SESSION['form_submissions'])) {
    $_SESSION['form_submissions'] = [];
}

// Limpiar envíos antiguos
$now = time();
$_SESSION['form_submissions'] = array_filter($_SESSION['form_submissions'], 
    function($timestamp) use ($now, $rate_limit_period) {
        return ($now - $timestamp) <= $rate_limit_period;
    }
);

// Función para enviar email usando la API de Brevo
function send_email_brevo($to_email, $to_name, $subject, $html_content, $from_email, $from_name, $reply_to_email = null, $reply_to_name = null) {
    global $brevo_api_key;
    $brevo_api_key = BREVO_API_KEY; 
    
    // Preparar datos para la API
    $url = 'https://api.brevo.com/v3/smtp/email';
    
    $sender = [
        'name' => $from_name,
        'email' => $from_email
    ];
    
    $to = [
        [
            'email' => $to_email,
            'name' => $to_name
        ]
    ];
    
    $data = [
        'sender' => $sender,
        'to' => $to,
        'subject' => $subject,
        'htmlContent' => $html_content
    ];
    
    // Agregar reply-to si se especifica
    if ($reply_to_email !== null) {
        $data['replyTo'] = [
            'email' => $reply_to_email,
            'name' => $reply_to_name ?? $reply_to_email
        ];
    }
    
    // Convertir datos a JSON
    $payload = json_encode($data);
    
    // Configurar petición cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'api-key: ' . $brevo_api_key
    ]);
    
    // Ejecutar petición
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Verificar respuesta
    return ($http_code >= 200 && $http_code < 300);
}

// Procesar el formulario si se ha enviado
if (isset($_POST['contacto_submit'])) {
    // Verificar CSRF token
    if (!isset($_POST['contacto_nonce']) || !hash_equals($_SESSION['contacto_nonce'], $_POST['contacto_nonce'])) {
        $mensaje_output = '<div class="contacto-error"><i class="fas fa-exclamation-circle"></i> Error de seguridad. Por favor, intenta nuevamente.</div>';
    } 
    // Verificar rate limiting
    elseif (count($_SESSION['form_submissions']) >= $max_submissions) {
        $mensaje_output = '<div class="contacto-error"><i class="fas fa-exclamation-circle"></i> Has enviado demasiados formularios en poco tiempo. Por favor, espera unos minutos antes de intentarlo de nuevo.</div>';
    } 
    else {
        // Sanitizar y validar los datos - más estricto
        $nombre = isset($_POST['nombre']) ? sanitize_input($_POST['nombre']) : '';
        $email = isset($_POST['email']) ? filter_var(sanitize_input($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
        $mensaje = isset($_POST['mensaje']) ? sanitize_input($_POST['mensaje']) : '';
        
        $errores = array();
        
        // Validación mejorada
        if (empty($nombre)) {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (strlen($nombre) > 100) {
            $errores[] = 'El nombre no puede exceder los 100 caracteres.';
        } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre)) {
            $errores[] = 'El nombre solo puede contener letras y espacios.';
        }
        
        if (empty($email)) {
            $errores[] = 'El email es obligatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no es válido.';
        } elseif (strlen($email) > 100) {
            $errores[] = 'El email no puede exceder los 100 caracteres.';
        }
        
        if (empty($mensaje)) {
            $errores[] = 'El mensaje es obligatorio.';
        } elseif (strlen($mensaje) > 2000) {
            $errores[] = 'El mensaje no puede exceder los 2000 caracteres.';
        }
        
        // Validación anti-spam simple
        $spam_words = ['viagra', 'casino', 'lottery', 'prize', 'winner', 'cialis', 'http://', 'https://', 'www.'];
        foreach ($spam_words as $word) {
            if (stripos($mensaje, $word) !== false) {
                $errores[] = 'Tu mensaje ha sido identificado como posible spam.';
                break;
            }
        }
        
        // Si no hay errores, procesar el formulario
        if (empty($errores)) {
            // Registrar el envío para rate limiting
            $_SESSION['form_submissions'][] = time();
            
            $fecha = date('d/m/Y');
            $enviado_admin = false;
            $enviado_user = false;

            // Preparar correo para el administrador
            $body_admin = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
                    .header { background-color: #2563EB; padding: 20px; text-align: center; }
                    .logo { max-width: 200px; max-height: 80px; }
                    .content { padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; }
                    .footer { text-align: center; font-size: 12px; color: #777; padding: 10px; }
                    h1 { color: #2563EB; }
                    .mensaje-box { background-color: #fff; border-left: 4px solid #2563EB; padding: 15px; margin: 15px 0; }
                    .info-contacto { background-color: #f0f0f0; padding: 10px; border-radius: 5px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="content">
                    <h1>Nuevo mensaje de contacto</h1>
                    <p>Has recibido un nuevo mensaje a través del formulario de contacto de tu sitio web.</p>
                    <div class="info-contacto">
                        <p><strong>Fecha:</strong> ' . htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') . '</p>
                        <p><strong>Nombre:</strong> ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</p>
                        <p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>
                    </div>
                    <h2>Mensaje:</h2>
                    <div class="mensaje-box">
                        ' . nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')) . '
                    </div>
                    <p>Puedes responder directamente a este correo para contestar al remitente.</p>
                </div>
            </body>
            </html>';
            
            // Enviar correo al administrador usando la API de Brevo
            $enviado_admin = send_email_brevo(
                $admin_email, 
                $sitio_nombre, 
                'Nuevo mensaje de contacto de ' . $nombre, 
                $body_admin, 
                $admin_email, 
                $sitio_nombre, 
                $email, 
                $nombre
            );

            // Enviar correo al usuario solo si el primero fue exitoso
            if ($enviado_admin) {
                // Preparar correo para el usuario
                $body_user = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        body {
                            font-family: "Helvetica Neue", Arial, sans-serif;
                            line-height: 1.6;
                            color: #333;
                            margin: 0;
                            padding: 0;
                            background-color: #f4f4f4;
                        }
                        .container {
                            max-width: 600px;
                            margin: 20px auto;
                            background-color: #ffffff;
                            border-radius: 8px;
                            overflow: hidden;
                            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                        }
                        .header {
                            background-color: #fff;
                            padding: 30px 20px;
                            text-align: center;
                        }
                        .logo {
                            max-width: 200px;
                            max-height: 80px;
                            display: block;
                            margin: 0 auto;
                        }
                        .content {
                            padding: 30px;
                            background-color: #ffffff;
                        }
                        h1 {
                            color: #2563EB;
                            font-size: 24px;
                            margin: 0 0 20px;
                            text-align: center;
                        }
                        p {
                            margin: 0 0 15px;
                            font-size: 16px;
                        }
                        .mensaje-box {
                            background-color: #f9f9f9;
                            border-left: 4px solid #2563EB;
                            padding: 15px;
                            margin: 20px 0;
                            border-radius: 4px;
                            font-size: 15px;
                            color: #444;
                        }
                        .button {
                            display: inline-block;
                            background-color: #2563EB;
                            color: #ffffff !important;
                            text-decoration: none;
                            padding: 12px 25px;
                            border-radius: 5px;
                            font-size: 16px;
                            font-weight: 600;
                            text-align: center;
                            margin: 20px 0;
                        }
                        .button:hover {
                            background-color: #1d4ed8;
                        }
                        .footer {
                            background-color: #f4f4f4;
                            text-align: center;
                            font-size: 12px;
                            color: #777;
                            padding: 20px;
                            border-top: 1px solid #e5e7eb;
                        }
                        .footer a {
                            color: #2563EB;
                            text-decoration: none;
                        }
                        .footer a:hover {
                            text-decoration: underline;
                        }
                        @media (max-width: 600px) {
                            .container { width: 100%; margin: 10px; }
                            .content { padding: 20px; }
                            h1 { font-size: 20px; }
                            p { font-size: 14px; }
                            .button { font-size: 14px; padding: 10px 20px; }
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="content">
                            <h1>¡Gracias por contactarnos!</h1>
                            <p>Hola <strong>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
                            <p>Hemos recibido tu mensaje correctamente. Te responderemos lo antes posible.</p>
                            <h2 style="font-size: 18px; color: #333;">Resumen de tu mensaje:</h2>
                            <div class="mensaje-box">
                                ' . nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')) . '
                            </div>
                            <p>Si tienes alguna otra consulta o detalles adicionales, puede responder este correo o contactarnos nuevamente.</p>
                            <div style="text-align: center;">
                                <a href="' . htmlspecialchars($sitio_url, ENT_QUOTES, 'UTF-8') . '" class="button">Visitar nuestra web</a>
                            </div>
                        </div>
                    </div>
                </body>
                </html>';
                
                // Enviar correo al usuario usando la API de Brevo
                $enviado_user = send_email_brevo(
                    $email, 
                    $nombre, 
                    'Confirmación de tu mensaje - ' . $sitio_nombre, 
                    $body_user, 
                    $admin_email, 
                    $sitio_nombre, 
                    'info@buscounservicio.es', 
                    $sitio_nombre
                );
            }

            // Verificar resultados
            if ($enviado_admin && $enviado_user) {
                $mensaje_output = '<div class="contacto-exito"><i class="fas fa-check-circle"></i> ¡Gracias! Tu mensaje ha sido enviado correctamente. Hemos enviado una confirmación a tu correo electrónico.</div>';
                // Limpiar campos de formulario tras envío exitoso
                $_POST = array();
            } elseif ($enviado_admin && !$enviado_user) {
                $mensaje_output = '<div class="contacto-error"><i class="fas fa-exclamation-circle"></i> Tu mensaje fue recibido, pero no pudimos enviarte la confirmación. Por favor, contáctanos si necesitas asistencia.</div>';
            } elseif (!$enviado_admin) {
                $mensaje_output = '<div class="contacto-error"><i class="fas fa-exclamation-circle"></i> Ha ocurrido un error al procesar tu mensaje. Por favor, intenta nuevamente más tarde.</div>';
            }
            
            // Generar nuevo token CSRF para prevenir reutilización
            $_SESSION['contacto_nonce'] = bin2hex(random_bytes(32));
        } else {
            $mensaje_output = '<div class="contacto-error">';
            $mensaje_output .= '<i class="fas fa-exclamation-circle"></i> Por favor, corrige los siguientes errores:';
            $mensaje_output .= '<ul>';
            foreach ($errores as $error) {
                $mensaje_output .= '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $mensaje_output .= '</ul></div>';
        }
    }
}
?>


<?php include '../assets/includes/header.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <title>Formulario de Contacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self'; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' https://buscounservicio.es;">
    <style>
        .formulario-contacto-profesional {
            max-width: 800px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .titulo-formulario {
            color: #2563EB;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .campo-grupo {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .campo-grupo {
                flex-direction: column;
                gap: 0;
            }
        }
        
        .campo-formulario {
            margin-bottom: 20px;
            width: 100%;
        }
        
        .campo-formulario label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .campo-formulario input[type="text"],
        .campo-formulario input[type="email"],
        .campo-formulario textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: #fff;
        }
        
        .campo-formulario input[type="text"]:focus,
        .campo-formulario input[type="email"]:focus,
        .campo-formulario textarea:focus {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
            outline: none;
            background-color: #fff;
        }
        
        .boton-enviar {
            background-color: #2563EB;
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        
        .boton-enviar:hover {
            background-color: #1d4ed8;
        }
        
        .requerido {
            color: #ef4444;
        }
        
        .contacto-exito {
            background-color: #ecfdf5;
            color: #065f46;
            padding: 15px;
            margin-top: 25px;
            border-radius: 5px;
            border-left: 4px solid #10b981;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .contacto-error {
            background-color: #fef2f2;
            color: #991b1b;
            padding: 15px;
            margin-top: 25px;
            border-radius: 5px;
            border-left: 4px solid #ef4444;
            font-weight: 500;
        }
        
        .contacto-error ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }
        
        .politica-privacidad {
            font-size: 14px;
            color: #333;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        
        .politica-privacidad a {
            color: #2563EB;
            text-decoration: none;
        }
        
        .politica-privacidad a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="formulario-contacto-profesional">
        <h2 class="titulo-formulario"><i></i> Formulario de Contacto</h2>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" id="formulario-contacto">
            <input type="hidden" name="contacto_nonce" value="<?php echo $_SESSION['contacto_nonce']; ?>">
            
            <div class="campo-grupo">
                <div class="campo-formulario">
                    <label for="nombre">
                        <i class="nombre"></i> Nombre <span class="requerido">*</span>
                    </label>
                    <input type="text" name="nombre" id="nombre" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre'], ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="Tu nombre" required maxlength="100">
                </div>
                
                <div class="campo-formulario">
                    <label for="email">
                        <i class="correo"></i> Email <span class="requerido">*</span>
                    </label>
                    <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="tu@email.com" required maxlength="100">
                </div>
            </div>
            
            <div class="campo-formulario">
                <label for="mensaje">
                    <i class="mensaje"></i> Mensaje <span class="requerido">*</span>
                </label>
                <textarea name="mensaje" id="mensaje" rows="6" placeholder="¿En qué podemos ayudarte?" required maxlength="2000"><?php echo isset($_POST['mensaje']) ? htmlspecialchars($_POST['mensaje'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
            </div>
            
            <div class="campo-formulario">
                <button type="submit" name="contacto_submit" class="boton-enviar">
                    <i></i> Enviar mensaje
                </button>
            </div>
            
            <div class="politica-privacidad">
                <p>
                    <i class="fas fa-info-circle"></i> Usamos tus datos con tu consentimiento, solo para ponernos en contacto contigo. Al enviarlo, aceptas nuestra 
                    <a href="<?php echo htmlspecialchars($sitio_url . '/politica-privacidad', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">política de privacidad</a>.
                </p>
            </div>
        </form>
        
        <?php 
        if (!empty($mensaje_output)) {
            echo $mensaje_output;
        }
        ?>
    </div>

<?php include '../assets/includes/footer.php'; ?>

<script src="../../assets/js/header.js"></script>
</body>
</html>