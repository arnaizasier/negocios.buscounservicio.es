<?php

function enviarCorreoNuevoRegistro($email, $nombre, $apellido, $role) {
    if (!defined('BREVO_API_KEY')) {
        require_once '/home/u898735099/domains/negocios.buscounservicio.es/config.php';
        if (!defined('BREVO_API_KEY')) {
            error_log("Error: BREVO_API_KEY no está definida");
            return false;
        }
    }
    
    try {
        $curl = curl_init();
        
        $datosCorreo = [
            'sender' => [
                'name' => "Sistema de Registro",
                'email' => 'info@buscounservicio.es'
            ],
            'to' => [
                [
                    'email' => 'buscounservicio@gmail.com',
                    'name' => 'Administrador'
                ]
            ],
            'subject' => "Nuevo registro en la plataforma",
            'textContent' => "Nuevo registro de $email\n\nNombre: $nombre $apellido\nRol: $role\n\nFecha: " . date('Y-m-d H:i:s')
        ];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.brevo.com/v3/smtp/email",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($datosCorreo),
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "api-key: " . BREVO_API_KEY,
                "content-type: application/json"
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);
        
        if ($err) {
            error_log("Error al enviar correo de nuevo registro: " . $err);
            return false;
        }
        
        if ($httpCode !== 201) {
            error_log("Error HTTP al enviar correo de nuevo registro. Código: $httpCode. Respuesta: $response");
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error al enviar correo de nuevo registro: " . $e->getMessage());
        return false;
    }
}

?>
