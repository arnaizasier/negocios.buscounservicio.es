<?php
function enviarCorreoBienvenida($negocio, $email_usuario, $pdoNegocios) {
    $nombre_negocio = htmlspecialchars($negocio['nombre']);
    $url_negocio = htmlspecialchars($negocio['url']);
    $email_usuario = htmlspecialchars($email_usuario);
    
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode("https://buscounservicio.es/negocio/" . $url_negocio);
    
    $listing_url = "https://buscounservicio.es/negocio/" . $url_negocio;
    
    $asunto = "¡Bienvenido a BuscoUnServicio! Tu negocio ha sido registrado";
    
    $mensaje = '<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a buscounservicio</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!--[if mso]>
    <style type="text/css">
        table {border-collapse: collapse; border-spacing: 0; margin: 0;}
        div, td {padding: 0;}
        div {margin: 0 !important;}
    </style>
    <noscript>
    <xml>
        <o:OfficeDocumentSettings>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f9; font-family: \'Inter\', Arial, sans-serif; -webkit-font-smoothing: antialiased; font-size: 16px; line-height: 1.4; color: #333333; width: 100%;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; border-radius: 20px; overflow: hidden; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1); background-color: #ffffff;">
                    <tr>
                        <td align="center" bgcolor="#2755d3" style="padding: 40px 30px; text-align: center; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;">
                            <h2 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.5px; font-family: \'Inter\', Arial, sans-serif;">¡Bienvenido a buscounservicio!</h2>
                            <p style="color: #ffffff; opacity: 0.9; margin: 12px 0 0 0; font-size: 18px; font-weight: 500; font-family: \'Inter\', Arial, sans-serif;">' . $nombre_negocio . ' ya se encuentra publicado</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px; background-color: #ffffff;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-bottom: 30px; border-radius: 16px; background-color: #eef5ff; border-left: 8px solid #2755d3;">
                                <tr>
                                    <td style="padding: 30px;">
                                        <h3 style="color: #1a202c; margin: 0 0 15px 0; text-align: center; font-size: 24px; font-weight: 600; font-family: \'Inter\', Arial, sans-serif;">Todo lo que tus clientes necesitan saber, en una única web.</h3>
                                        <p style="color: #4a5568; margin: 0 0 25px 0; text-align: center; line-height: 1.7; font-size: 16px; font-family: \'Inter\', Arial, sans-serif;">
                                            No olvides personalizar tu perfil para que sea aún más atractivo para los usuarios. Si necesitas ayuda o más información,
                                            <a style="color: #2755d3; text-decoration: none; font-weight: 600; font-family: \'Inter\', Arial, sans-serif;" href="https://buscounservicio.es/contacto/">
                                                contáctanos
                                            </a>
                                            y estaremos encantados de ayudarte.
                                        </p>
                                        <table border="0" cellpadding="0" cellspacing="0" align="center" style="margin: 0 auto;">
                                            <tr>
                                                <td align="center" bgcolor="#2755d3" style="border-radius: 12px; padding: 16px 36px; box-shadow: 0 4px 12px rgba(39, 85, 211, 0.25);">
                                                    <a href="' . $listing_url . '" style="font-family: \'Inter\', Arial, sans-serif; display: inline-block; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600;">
                                                        Ver mi perfil
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-bottom: 30px; border-radius: 16px; background-color: #ffffff; box-shadow: 0 2px 12px rgba(0,0,0,0.04); border: 1px solid #e0eaff;">
                                <tr>
                                    <td style="padding: 30px; text-align: center;">
                                        <h3 style="color: #1a202c; margin: 0 0 20px 0; font-size: 22px; font-weight: 600; font-family: \'Inter\', Arial, sans-serif;">
                                            Imprímelo y colócalo en tu local para que accedan a tu perfil en segundos
                                        </h3>
                                        <img src="' . $qr_url . '" alt="QR Code" style="max-width: 150px; height: auto;" />
                                    </td>
                                </tr>
                            </table>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; border-radius: 16px; background-color: #ffffff; box-shadow: 0 2px 12px rgba(0,0,0,0.04); border: 1px solid #e0eaff;">
                                <tr>
                                    <td style="padding: 30px;">
                                        <h3 style="color: #1a202c; margin: 0 0 20px 0; text-align: center; font-size: 22px; font-weight: 600; font-family: \'Inter\', Arial, sans-serif;">
                                            Comparte a tus clientes que ya estás en buscounservicio:
                                        </h3>
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 25px;">
                                            <tr>
                                                <td align="center">
                                                    <table border="0" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td align="center" bgcolor="#1877f2" style="border-radius: 10px; padding: 14px 28px; margin-right: 10px;">
                                                                <a href="https://www.facebook.com/sharer/sharer.php?u=' . urlencode($listing_url) . '" target="_blank" rel="noopener" style="color: #ffffff; text-decoration: none; font-weight: 600; font-family: \'Inter\', Arial, sans-serif; font-size: 15px; display: inline-block;">
                                                                    Facebook
                                                                </a>
                                                            </td>
                                                            <td width="15"></td>
                                                            <td align="center" bgcolor="#e1306c" style="border-radius: 10px; padding: 14px 28px;">
                                                                <a href="https://www.instagram.com" target="_blank" rel="noopener" style="color: #ffffff; text-decoration: none; font-weight: 600; font-family: \'Inter\', Arial, sans-serif; font-size: 15px; display: inline-block;">
                                                                    Instagram
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
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
                                        <p style="font-family: \'Inter\', Arial, sans-serif; font-size: 16px; color: #ffffff; margin: 0; font-weight: 500;">
                                            Contacta con nosotros a través de nuestra
                                            <a href="https://buscounservicio.es/contacto/" target="_blank" style="color: #ffffff; text-decoration: none; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.4); padding-bottom: 1px; font-family: \'Inter\', Arial, sans-serif;">
                                                página de soporte
                                            </a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 35px;">
                                <tr>
                                    <td align="center">
                                        <p style="font-family: \'Inter\', Arial, sans-serif; font-size: 15px; color: rgba(255,255,255,0.9); margin: 0; line-height: 1.6; max-width: 500px;">
                                            Protegemos tu seguridad y tu privacidad. Nunca pediremos información personal (como contraseñas o números de tarjetas de crédito) en un correo electrónico.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                            <tr>
                                                <td>
                                                    <a href="https://buscounservicio.es/politica-de-privacidad/" target="_blank" style="color: rgba(255,255,255,0.85); text-decoration: none; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; padding: 0 10px;">
                                                        Política de privacidad
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="https://buscounservicio.es/terminos-y-condiciones/" target="_blank" style="color: rgba(255,255,255,0.85); text-decoration: none; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; padding: 0 10px;">
                                                        Términos y condiciones
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td height="15"></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <a href="https://buscounservicio.es/configura-tus-cookies/" target="_blank" style="color: rgba(255,255,255,0.85); text-decoration: none; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; padding: 0 10px;">
                                                        Política de cookies
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="https://buscounservicio.es/politica-de-devoluciones-y-reembolsos/" target="_blank" style="color: rgba(255,255,255,0.85); text-decoration: none; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; padding: 0 10px;">
                                                        Política de devoluciones
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.brevo.com/v3/smtp/email");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json",
        "api-key: " . BREVO_API_KEY,
        "content-type: application/json"
    ]);

    $data = [
        "sender" => ["name" => "BuscoUnServicio", "email" => "info@buscounservicio.es"],
        "to" => [["email" => $email_usuario]],
        "subject" => $asunto,
        "htmlContent" => $mensaje
    ];

    $json_data = json_encode($data);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 201) {
        $stmt = $pdoNegocios->prepare("UPDATE negocios SET email_bienvenida = 'no' WHERE negocio_id = :negocio_id");
        $stmt->execute([':negocio_id' => $negocio['negocio_id']]);
        return true;
    }
    
    return false;
}
?>
