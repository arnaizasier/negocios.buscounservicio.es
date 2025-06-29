<?php
function enviarCorreoRecuperacion($email, $selector, $token) {
    $url = 'https://negocios.buscounservicio.es/auth/cambiar-contrasena?selector=' . urlencode($selector) . '&token=' . urlencode($token);
    
    $subject = 'Recuperación de contraseña - buscounservicio';
    $message = '
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
                color: #024ddf;
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
                border-left: 4px solid #024ddf;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
                font-size: 15px;
                color: #444;
            }
            .button {
                display: inline-block;
                background-color: #024ddf;
                color: #ffffff !important;
                text-decoration: none;
                padding: 12px 25px;
                border-radius: 25px;
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
                color: #024ddf;
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
                <h1>Recuperación de Contraseña</h1>
                <p>Hola!</p>
                <p>Hemos recibido una solicitud para restablecer la contraseña asociada a esta dirección de correo electrónico. Si no ha realizado esta solicitud, puede ignorar este mensaje.</p>
                
                <div class="mensaje-box">
                    Para restablecer su contraseña, haga clic en el botón a continuación. Este enlace expirará en 2 horas por seguridad.
                </div>
                
                <div style="text-align: center;">
                    <a href="' . $url . '" class="button">Restablecer Contraseña</a>
                </div>
                
                <p>O copie y pegue la siguiente URL en su navegador:</p>
                <p>' . $url . '</p>
                
                <p>Buen día,<br>El equipo de buscounservicio</p>
            </div>
        </div>
    </body>
    </html>';

    $apiKey = BREVO_API_KEY;
    $apiUrl = 'https://api.brevo.com/v3/smtp/email';
    
    $data = [
        'sender' => [
            'name' => 'buscounservicio',
            'email' => 'info@buscounservicio.es'
        ],
        'to' => [
            [
                'email' => $email
            ]
        ],
        'subject' => $subject,
        'htmlContent' => $message
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'api-key: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 201) {
        error_log('Error al enviar correo con Brevo: ' . $response);
        throw new Exception('No se pudo enviar el correo de recuperación');
    }
    
    return true;
}
?>
