<?php

function enviarCorreoBrevo($destinatario, $asunto, $mensaje) {
    global $brevoApiKey;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.sendinblue.com/v3/smtp/email");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'sender' => ['name' => 'Buscounservicio', 'email' => 'info@buscounservicio.es'],
        'to' => [['email' => $destinatario]],
        'subject' => $asunto,
        'htmlContent' => $mensaje
    ]));

    $headers = [];
    $headers[] = "Accept: application/json";
    $headers[] = "Content-Type: application/json";
    $headers[] = "Api-Key: $brevoApiKey";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Error de cURL al enviar correo: ' . curl_error($ch));
        return false;
    }
    curl_close($ch);
    return true;
}

function generarCorreoHTML($nombre, $apellido, $resetLink) {
    return <<<EOD
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invitación a Buscounservicio</title>
        <style>
            @media only screen and (max-width: 600px) {
                .container { width: 100% !important; }
                .content { padding: 20px !important; }
                .header img { width: 120px !important; }
                .footer p { font-size: 14px !important; }
            }
        </style>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: 'Inter', Arial, sans-serif;">
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 0px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <tr>
                <td class="content" style="padding: 40px;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td style="text-align: center; margin-bottom: 20px;">
                                <img src="https://buscounservicio.es/imagenes/recursos/logo-png-azul.png" alt="Buscounservicio" style="width: 250px; height: 48px; margin-bottom: 15px;">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h2 style="color: #2755d3; font-size: 24px; margin: 0 0 20px; text-align: center;">¡Has sido invitado!</h2>
                                <p style="font-size: 16px; color: #4a4a4a; line-height: 1.6; margin: 0 0 20px; text-align: center;">
                                    Hola $nombre $apellido,
                                </p>
                                <p style="font-size: 16px; color: #4a4a4a; line-height: 1.6; margin: 0 0 20px; text-align: center;">
                                    Has sido invitado a unirte al equipo en Buscounservicio como trabajador. Para activar tu cuenta y establecer tu contraseña, haz clic en el siguiente enlace:
                                </p>
                                <div style="text-align: center; margin: 30px 0;">
                                    <a href="$resetLink" style="background-color: #2755d3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
                                        Establecer Contraseña
                                    </a>
                                </div>
                                <p style="font-size: 14px; color: #666; line-height: 1.6; margin: 0 0 20px; text-align: center;">
                                    Este enlace expirará en 24 horas por motivos de seguridad.
                                </p>
                                <p style="font-size: 14px; color: #666; line-height: 1.6; margin: 0 0 20px; text-align: center;">
                                    Si no esperabas esta invitación, puedes ignorar este mensaje de forma segura.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding: 0 40px;">
                    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0;">
                </td>
            </tr>
            <tr>
                <td bgcolor="#2755d3" style="padding: 40px 30px; text-align: center; border-top-left-radius: 20px; border-top-right-radius: 20px;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 25px;">
                        <tr>
                            <td align="center">
                                <a href="https://www.instagram.com/buscounservicio/" target="_blank" style="display: inline-block; margin: 0 10px;">
                                    <img width="38" height="38" src="https://eoyhwey.stripocdn.email/content/assets/img/social-icons/circle-colored/instagram-circle-colored.png" alt="Instagram" style="border: 0; display: block;" />
                                </a>
                            </td>
                        </tr>
                    </table>
                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 35px;">
                        <tr>
                            <td align="center">
                                <p style="font-family: 'Inter', Arial, sans-serif; font-size: 16px; color: #ffffff; margin: 0; font-weight: 500;">
                                    Contacta con nosotros a través de nuestra
                                    <a href="https://buscounservicio.es/contacto/" target="_blank" style="color: #ffffff; text-decoration: none; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.4); padding-bottom: 1px; font-family: 'Inter', Arial, sans-serif;">
                                        página de soporte
                                    </a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td>
                                <a href="https://buscounservicio.es/politica-de-privacidad/" target="_blank" style="color: rgba(255,255,255,0.85); text-decoration: none; font-family: 'Inter', Arial, sans-serif; font-size: 14px; padding: 0 10px;">
                                    Política de privacidad
                                </a>
                            </td>
                            <td>
                                <a href="https://buscounservicio.es/terminos-y-condiciones/" target="_blank" style="color: rgba(255,255,255,0.85); text-decoration: none; font-family: 'Inter', Arial, sans-serif; font-size: 14px; padding: 0 10px;">
                                    Términos y condiciones
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    EOD;
} 