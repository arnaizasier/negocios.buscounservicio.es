<?php
require_once '../../../config.php';

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
        echo 'Error de cURL: ' . curl_error($ch);
    }
    curl_close($ch);
}

function generarCorreoHTML($titulo, $mensajePrincipal) {
    return <<<EOD
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>$titulo</title>
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
                                <img src="https://buscounservicio.es/imagenes/recursos/logo-png-azul.png" alt="Check" style="width: 250px; height: 48px; margin-bottom: 15px;">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <p style="font-size: 16px; color: #4a4a4a; line-height: 1.6; margin: 0 0 20px; text-align: center;">
                                    $mensajePrincipal
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
                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 35px;">
                        <tr>
                            <td align="center">
                                <p style="font-family: 'Inter', Arial, sans-serif; font-size: 15px; color: rgba(255,255,255,0.9); margin: 0; line-height: 1.6; max-width: 500px;">
                                    Protegemos tu seguridad y tu privacidad. Nunca pediremos información personal (como contraseñas o números de tarjetas de crédito) en un correo electrónico.
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
                        <tr>
                            <td height="15"></td>
                        </tr>
                        <tr>
                            <td>
                                <a href="https://buscounservicio.es/configura-tus-cookies/" target="_blank" style="color: rgba(255,255,255,0.85); text-decoration: none; font-family: 'Inter', Arial, sans-serif; font-size: 14px; padding: 0 10px;">
                                    Política de cookies
                                </a>
                            </td>
                            <td>
                                <a href="https://buscounservicio.es/politica-de-devoluciones-y-reembolsos/" target="_blank" style="color: rgba(255,255,255,0.85); text-decoration: none; font-family: 'Inter', Arial, sans-serif; font-size: 14px; padding: 0 10px;">
                                    Política de devoluciones
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

function enviarCorreoCancelacionPlan($correoUsuario, $user_id) {
    $mensajeCorreo = generarCorreoHTML(
        'Cancelación de Plan',
        'Tu plan ha sido cancelado con éxito. Gracias por haber confiado en Buscounservicio.'
    );
    enviarCorreoBrevo($correoUsuario, 'Cancelación de Plan', $mensajeCorreo);
    enviarCorreoBrevo('buscounservicio@gmail.com', 'Cancelación de Plan', 'El usuario con ID ' . $user_id . ' ha cancelado su plan.');
}

function enviarCorreoCancelacionDestacado($correoUsuario, $user_id) {
    $mensajeCorreo = generarCorreoHTML(
        'Cancelación de Destacado',
        'El destacado de tu negocio ha sido cancelado con éxito. Gracias por haber confiado en Buscounservicio.'
    );
    enviarCorreoBrevo($correoUsuario, 'Cancelación de Destacado', $mensajeCorreo);
    enviarCorreoBrevo('buscounservicio@gmail.com', 'Cancelación de Destacado', 'El usuario con ID ' . $user_id . ' ha cancelado el destacado de su negocio.');
}
?>
