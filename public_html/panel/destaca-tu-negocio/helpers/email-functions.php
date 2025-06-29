<?php
/**
 * Funciones de envío de correos electrónicos
 */

/**
 * Envía correo de confirmación de pago destacado
 */
function enviarCorreoDeConfirmacion($user_id, $tipo_destacado, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, email, first_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            error_log("Usuario no encontrado con ID: $user_id");
            return false;
        }
        
        $email_destino = $usuario['email'];
        $nombre_destino = $usuario['first_name'] ?? '';
        
        $subject = '¡Pago confirmado! Tu negocio ya está destacado';
        $duracion_texto = $tipo_destacado === 'Anual' ? '12 meses' : '1 mes';
        
        $message = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pago confirmado</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0;">
  <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; margin: 0px auto;">
    <!-- Header -->
    <tr>
      <td align="center" style="padding: 20px; border-top-left-radius: 8px; border-top-right-radius: 8px;">
        <img src="https://buscounservicio.es/imagenes/recursos/logo-png-azul.png" alt="Logo Buscounservicio" style="max-width: 150px; display: block;">
      </td>
    </tr>
    <!-- Content -->
    <tr>
      <td align="center" style="padding: 30px; color: #333333; line-height: 1.6;">
        <h2 style="color: #2755d3; margin: 0 0 20px; font-size: 24px;">¡Tu negocio ya está destacado, {$nombre_destino}!</h2>
        <p style="margin: 0 0 20px; font-size: 16px;">Tu pago ha sido confirmado y tu negocio aparecerá destacado durante {$duracion_texto}.</p>
        <p style="margin: 0 0 20px; font-size: 16px;">Ahora tendrás mayor visibilidad y más oportunidades de conectar con clientes potenciales.</p>
        <p style="margin: 20px 0;">
        </p>
      </td>
    </tr>
    <!-- Footer -->
    <tr>
      <td align="center" style="background-color: #2755d3; padding: 30px; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; color: #ffffff;">
        <!-- Social Icons -->
        <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
          <tr>
            <td align="center">
              <a href="https://www.instagram.com/buscounservicio/" target="_blank">
                <img width="32" height="32" src="https://eoyhwey.stripocdn.email/content/assets/img/social-icons/circle-colored/instagram-circle-colored.png" alt="Instagram" style="border: 0;">
              </a>
            </td>
          </tr>
        </table>
        <!-- Support Text -->
        <p style="font-size: 16px; color: #ffffff; margin: 0 0 20px; line-height: 1.5;">
          Contacta con nosotros a través de nuestra <a href="https://buscounservicio.es/contacto/" target="_blank" style="color: #ffffff; text-decoration: underline;">página de soporte</a>
        </p>
        <!-- Security Text -->
        <p style="font-size: 16px; color: #ffffff; margin: 0 0 20px; line-height: 1.5;">
          Protegemos tu seguridad y privacidad. Nunca pediremos información personal (como contraseñas o números de tarjetas de crédito) en un correo electrónico.
        </p>
        <!-- Policy Links -->
        <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
          <tr>
            <td align="center" style="padding: 5px;">
              <a href="https://buscounservicio.es/politica-de-privacidad/" target="_blank" style="color: #ffffff; text-decoration: none; font-size: 14px;">Política de privacidad</a>
            </td>
            <td align="center" style="padding: 5px;">
              <a href="https://buscounservicio.es/terminos-y-condiciones/" target="_blank" style="color: #ffffff; text-decoration: none; font-size: 14px;">Términos y condiciones</a>
            </td>
            <td align="center" style="padding: 5px;">
              <a href="https://buscounservicio.es/configura-tus-cookies/" target="_blank" style="color: #ffffff; text-decoration: none; font-size: 14px;">Política de cookies</a>
            </td>
            <td align="center" style="padding: 5px;">
              <a href="https://buscounservicio.es/politica-de-devoluciones-y-reembolsos/" target="_blank" style="color: #ffffff; text-decoration: none; font-size: 14px;">Política de devoluciones</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        // Obtener API key de Brevo
        if (defined('BREVO_API_KEY')) {
            $apiKey = BREVO_API_KEY;
        } else if (isset($brevoApiKey)) {
            $apiKey = $brevoApiKey;
        } else {
            error_log('No se encontró la API key de Brevo');
            return false;
        }
        
        if ($apiKey) {
            $postData = [
                'sender' => [
                    'name' => 'buscounservicio',
                    'email' => 'info@buscounservicio.es',
                ],
                'to' => [
                    [ 'email' => $email_destino, 'name' => $nombre_destino ]
                ],
                'subject' => $subject,
                'htmlContent' => $message,
            ];
            
            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $apiKey,
                'content-type: application/json',
            ]);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($httpcode == 201) {
                error_log("Correo de confirmación enviado exitosamente a: $email_destino");
                return true;
            } else {
                error_log("Error enviando correo de confirmación. HTTP Code: $httpcode, Response: $response");
                return false;
            }
        }
        
    } catch (Exception $e) {
        error_log('Error enviando correo de confirmación: ' . $e->getMessage());
        return false;
    }
    
    return false;
}
?>