<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '/home/customer/www/asiera1.sg-host.com/config.php';
require_once '/home/customer/www/asiera1.sg-host.com/db-publica.php';

use Delight\Auth\Auth;

$auth = new Auth($pdo);

// Verificar si el usuario está logeado
if (!$auth->isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit;
}

// Obtener el ID del usuario
$user_id = $auth->getUserId();

// Generar código de referido dinámico
$codigo_referido = '4984-' . $user_id . '446';

// Depuración: Verificar que el código se genera
if (empty($user_id)) {
    error_log("Error: user_id no está definido.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitaministrador a un Negocio</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        /* Configuración base específica para la página */
        .content45 {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            line-height: 1.6;
            color: #333;
            flex-grow: 1;
            padding: 2rem;
            transition: margin-left 0.3s ease;
            padding-top: 11rem;
        }

        /* Contenedor del formulario */
        .main-container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        /* Títulos */
        .main-container h1 {
            font-size: 2.2rem;
            color: #1a1a1a;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 700;
        }

        .main-container h2 {
            font-size: 1.5rem;
            color: #2755d3;
            margin-bottom: 1rem;
        }

        /* Texto */
        .main-container p {
            color: #666;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        /* Sección de código de referido */
        .main-container .referral-code {
            background: linear-gradient(135deg, #e0f7fa 0%, #e6f0fa 100%);
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            margin: 1.5rem 0;
            transition: transform 0.2s ease;
        }

        .main-container .referral-code:hover {
            transform: translateY(-5px);
        }

        /* Caja del código */
        .main-container .code-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: #2755d3;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .main-container .code {
            font-size: 1.2rem;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: 2px;
            display: inline-block;
        }

        /* Botón */
        .main-container button {
            background: #ff8728;
            color: #ffffff;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .main-container button:hover {
            background: #e06b24;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .main-container button:active {
            transform: translateY(0);
        }

        /* Estilos para el aviso de copia */
        .copy-notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: #ffffff;
            padding: 1rem 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            font-size: 1rem;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 1000;
        }

        .copy-notification.show {
            opacity: 1;
            transform: translateX(-50%) translateY(-10px);
        }

        .copy-notification.error {
            background: #dc3545;
        }

        /* Media Queries para responsividad */
        @media (max-width: 768px) {
            .content45 {
                padding: 1.5rem;
                padding-top: 5rem;
            }

            .main-container {
                padding: 1.5rem;
            }

            .main-container h1 {
                font-size: 1.8rem;
            }

            .main-container h2 {
                font-size: 1.3rem;
            }

            .main-container .code-box {
                flex-direction: column;
                gap: 0.8rem;
            }

            .main-container button {
                width: 100%;
                padding: 0.8rem;
            }

            .copy-notification {
                width: 90%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .main-container h1 {
                font-size: 1.5rem;
            }

            .main-container .referral-code {
                padding: 1.5rem;
            }

            .main-container .code {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container45">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar.php'; ?>
        <div class="content45" id="content45">
            <div class="main-container">
                <h1>¡INVITA Y GANA!</h1>
                <p>Por cada negocio que invites, recibirás un destacado mensual GRATIS para tu negocio</p>
                <div class="referral-code">
                    <h2>¡TU CÓDIGO DE REFERIDO!</h2>
                    <p>Comparte este código cuando se registren.</p>
                    <div class="code-box">
                        <span class="code" id="referralCode"><?php echo htmlspecialchars($codigo_referido, ENT_QUOTES, 'UTF-8'); ?></span>
                        <button onclick="copyCode()">Copiar Código</button>
                    </div>
                    <p>Copia este código y compártelo donde quieras.</p>
                </div>
            </div>
            <!-- Contenedor para el aviso de copia -->
            <div class="copy-notification" id="copyNotification"></div>
        </div>
    </div>
    <script>
        function copyCode() {
            const codeElement = document.getElementById('referralCode');
            const notification = document.getElementById('copyNotification');
            const code = codeElement.textContent.trim();

            if (!code) {
                showNotification('Error: No se encontró el código de referido.', true);
                return;
            }

            // Intentar usar navigator.clipboard
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code)
                    .then(() => {
                        showNotification(`Código copiado: ${code}`);
                    })
                    .catch((err) => {
                        console.error('Error al copiar con clipboard: ', err);
                        // Método alternativo
                        fallbackCopyCode(code);
                    });
            } else {
                // Método alternativo para navegadores antiguos
                fallbackCopyCode(code);
            }
        }

        function fallbackCopyCode(code) {
            const textarea = document.createElement('textarea');
            textarea.value = code;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showNotification(`Código copiado: ${code}`);
            } catch (err) {
                console.error('Error al copiar con execCommand: ', err);
                showNotification('Error al copiar el código. Intenta de nuevo.', true);
            }
            document.body.removeChild(textarea);
        }

        function showNotification(message, isError = false) {
            const notification = document.getElementById('copyNotification');
            notification.textContent = message;
            notification.classList.toggle('error', isError);
            notification.classList.add('show');
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000); // Ocultar después de 3 segundos
        }
    </script>
    <!-- Sidebar JS -->
    <script src="../../assets/js/sidebar.js"></script>
</body>
</html>