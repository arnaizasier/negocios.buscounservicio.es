<?php
/**
 * Email Functions for Cartera Module
 * Contains all email-related functionality for withdrawal notifications
 */

/**
 * Send email using Brevo API
 * 
 * @param string $destinatario Email recipient
 * @param string $nombreDestinatario Recipient name
 * @param string $asunto Email subject
 * @param string $contenidoHTML Email HTML content
 * @return bool Success status
 */
function enviarCorreoBrevo($destinatario, $nombreDestinatario, $asunto, $contenidoHTML) {
    $url = 'https://api.brevo.com/v3/smtp/email';
    
    $datos = [
        'sender' => [
            'name' => 'BuscoUnServicio',
            'email' => 'info@buscounservicio.es'
        ],
        'to' => [
            [
                'email' => $destinatario,
                'name' => $nombreDestinatario
            ]
        ],
        'subject' => $asunto,
        'htmlContent' => $contenidoHTML
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'api-key: ' . BREVO_API_KEY
    ]);
    
    $respuesta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Generate HTML content for user withdrawal notification email
 * 
 * @param string $nombre_negocio Business name
 * @param float $cantidad Amount to withdraw
 * @param string $metodo_pago Payment method
 * @return string HTML content
 */
function generarEmailUsuarioRetiro($nombre_negocio, $cantidad, $metodo_pago) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; padding: 20px 0; }
            .header h1 { color: #2755d3; }
            .content { background-color: #f5f5f5; padding: 20px; border-radius: 5px; }
            .content h2 { color: #2755d3; }
            .details { margin-bottom: 20px; }
            .details p { margin: 8px 0; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Solicitud de retiro de fondos</h1>
            </div>
            <div class='content'>
                <h2>¡Tu solicitud ha sido recibida!</h2>
                <div class='details'>
                    <p><strong>Negocio:</strong> {$nombre_negocio}</p>
                    <p><strong>Cantidad:</strong> {$cantidad}€</p>
                    <p><strong>Método de pago:</strong> {$metodo_pago}</p>
                    <p><strong>Fecha de solicitud:</strong> " . date('d/m/Y H:i') . "</p>
                    <p><strong>Estado:</strong> Pendiente</p>
                </div>
                <p>Tu solicitud está siendo procesada. Te notificaremos cuando los fondos hayan sido transferidos.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " BuscoUnServicio.</p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Generate HTML content for admin withdrawal notification email
 * 
 * @param int $id_retiro Withdrawal ID
 * @param string $nombre_negocio Business name
 * @param int $id_negocio Business ID
 * @param string $nombre_usuario User name
 * @param int $user_id User ID
 * @param float $cantidad Amount to withdraw
 * @param string $metodo_pago Payment method
 * @param string $datos_pago Payment details
 * @return string HTML content
 */
function generarEmailAdminRetiro($id_retiro, $nombre_negocio, $id_negocio, $nombre_usuario, $user_id, $cantidad, $metodo_pago, $datos_pago) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; padding: 20px 0; }
            .header h1 { color: #2755d3; }
            .content { background-color: #f5f5f5; padding: 20px; border-radius: 5px; }
            .details { margin-bottom: 20px; }
            .details p { margin: 8px 0; }
            .button { display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Nueva solicitud de retiro</h1>
            </div>
            <div class='content'>
                <div class='details'>
                    <p><strong>ID de retiro:</strong> {$id_retiro}</p>
                    <p><strong>Negocio:</strong> {$nombre_negocio} (ID: {$id_negocio})</p>
                    <p><strong>Usuario:</strong> {$nombre_usuario} (ID: {$user_id})</p>
                    <p><strong>Cantidad:</strong> {$cantidad}€</p>
                    <p><strong>Método de pago:</strong> {$metodo_pago}</p>
                    <p><strong>Datos de pago:</strong> {$datos_pago}</p>
                    <p><strong>Fecha de solicitud:</strong> " . date('d/m/Y H:i') . "</p>
                </div>
                <p>Se ha recibido una nueva solicitud de retiro que requiere tu aprobación.</p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Send withdrawal notification emails to both user and admin
 * 
 * @param array $user User data array
 * @param string $nombre_negocio Business name
 * @param int $id_negocio Business ID
 * @param int $user_id User ID
 * @param float $cantidad Amount to withdraw
 * @param string $metodo_pago Payment method
 * @param string $datos_pago Payment details
 * @param int $id_retiro Withdrawal ID
 * @return array Array with success status for both emails
 */
function enviarNotificacionesRetiro($user, $nombre_negocio, $id_negocio, $user_id, $cantidad, $metodo_pago, $datos_pago, $id_retiro) {
    $nombre_usuario = $user['first_name'] . ' ' . $user['last_name'];
    
    // Generate email content
    $asuntoUsuario = "Solicitud de retiro de fondos - BuscoUnServicio";
    $contenidoUsuario = generarEmailUsuarioRetiro($nombre_negocio, $cantidad, $metodo_pago);
    
    $asuntoAdmin = "Nueva solicitud de retiro - {$nombre_negocio}";
    $contenidoAdmin = generarEmailAdminRetiro($id_retiro, $nombre_negocio, $id_negocio, $nombre_usuario, $user_id, $cantidad, $metodo_pago, $datos_pago);
    
    // Send emails
    $enviado1 = enviarCorreoBrevo($user['email'], $nombre_usuario, $asuntoUsuario, $contenidoUsuario);
    $enviado2 = enviarCorreoBrevo('buscounservicio@gmail.com', 'Administrador', $asuntoAdmin, $contenidoAdmin);
    
    return [
        'user_email_sent' => $enviado1,
        'admin_email_sent' => $enviado2
    ];
} 