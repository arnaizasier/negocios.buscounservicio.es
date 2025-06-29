<?php
require_once '../config.php';
use Delight\Auth\Auth;
session_start();
try {
    $auth = new Auth($pdo);
    
    if ($auth->isLoggedIn()) {
        header('Location: panel/mis-ubicaciones/');
        exit();
    }
} catch (Exception $e) {
    error_log("Error en verificación de sesión: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex">
    <title>Bienvenida</title>
    <link rel="stylesheet" href="assets/css/marca.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }
        
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            flex-direction: row;
        }
        
        .left {
            flex: 1;
            background-image: url('https://negocios.buscounservicio.es/imagenes/main.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
        }
        
        .right {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: var(--color-white);
            padding: 30px 20px 20px 20px;
            min-height: 100vh;
            position: relative;
        }
        
        .content-wrapper {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 25px;
            width: 100%;
            max-width: 400px;
            flex: 1;
            min-height: 0;
        }
        
        .right h1 {
            font-family: 'Poppins1', sans-serif;
            color: var(--color-title);
            font-size: clamp(24px, 5vw, 32px);
            margin: 0;
            text-align: center;
            line-height: 1.3;
            font-weight: 600;
        }
        
        .button {
            padding: 16px 32px;
            font-size: clamp(16px, 3vw, 20px);
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            text-decoration: none;
            color: var(--color-white);
            background-color: var(--color-primary);
            border-radius: var(--border-radius-btn);
            transition: all 0.3s ease;
            text-align: center;
            width: 100%;
            max-width: 280px;
            box-shadow: var(--box-shadow);
            display: block;
            border: none;
            cursor: pointer;
        }
        
        .button:hover {
            background-color: var(--hover-primary);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .footer-link {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(14px, 2.5vw, 16px);
            color: var(--color-primary);
            text-decoration: none;
            text-align: center;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
            margin-top: 20px;
            padding: 8px 16px;
            flex-shrink: 0;
        }
        
        .footer-link:hover {
            color: var(--hover-primary);
        }
        
        .footer-link i {
            font-size: clamp(14px, 2.5vw, 16px);
        }
        
        @media (max-width: 1200px) {
            .right {
                padding: 25px 20px;
            }
            
            .content-wrapper {
                max-width: 380px;
                gap: 20px;
            }
            
            .button {
                max-width: 260px;
                padding: 15px 30px;
            }
        }
        
        @media (max-width: 1000px) {
            .left {
                display: none;
            }
            
            .right {
                flex: 1;
                width: 100%;
                padding: 30px 25px;
            }
            
            .content-wrapper {
                gap: 25px;
            }
            
            .button {
                max-width: 300px;
                padding: 16px 32px;
            }
        }
        
        @media (max-width: 768px) {
            .right {
                padding: 20px 15px 15px 15px;
                min-height: 100vh;
            }
            
            .content-wrapper {
                gap: 20px;
                max-width: 90%;
                flex: 1;
            }
            
            .right h1 {
                font-size: clamp(22px, 4.5vw, 28px);
            }
            
            .button {
                padding: 14px 28px;
                max-width: 250px;
                font-size: clamp(15px, 2.8vw, 18px);
            }
            
            .footer-link {
                font-size: clamp(13px, 2.3vw, 15px);
                margin-top: 15px;
                padding: 8px 12px;
                border: 1px solid transparent;
                border-radius: 6px;
                backdrop-filter: blur(5px);
            }
            
            .footer-link i {
                font-size: clamp(13px, 2.3vw, 15px);
            }
        }
        
        @media (max-width: 600px) {
            .right {
                padding: 15px 12px 12px 12px;
                min-height: 100vh;
            }
            
            .content-wrapper {
                gap: 18px;
                flex: 1;
            }
            
            .right h1 {
                font-size: clamp(20px, 4vw, 24px);
            }
            
            .button {
                padding: 12px 24px;
                max-width: 220px;
                font-size: clamp(14px, 2.6vw, 16px);
            }
            
            .footer-link {
                font-size: 13px;
                margin-top: 12px;
                padding: 8px 12px;
                border-radius: 6px;
            }
            
            .footer-link i {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .right {
                padding: 12px 10px 10px 10px;
                min-height: 100vh;
            }
            
            .content-wrapper {
                gap: 15px;
                flex: 1;
            }
            
            .right h1 {
                font-size: clamp(18px, 3.8vw, 22px);
            }
            
            .button {
                padding: 12px 20px;
                max-width: 200px;
                font-size: clamp(13px, 2.5vw, 15px);
            }
            
            .footer-link {
                font-size: 12px;
                margin-top: 10px;
                padding: 6px 10px;
                border-radius: 5px;
                position: sticky;
                bottom: 10px;
            }
            
            .footer-link i {
                font-size: 12px;
            }
        }
        
        @media (max-width: 360px) {
            .right {
                padding: 10px 8px 8px 8px;
                min-height: 100vh;
            }
            
            .content-wrapper {
                gap: 12px;
                flex: 1;
            }
            
            .right h1 {
                font-size: clamp(16px, 3.5vw, 20px);
            }
            
            .button {
                padding: 10px 18px;
                max-width: 180px;
                font-size: clamp(12px, 2.3vw, 14px);
            }
            
            .footer-link {
                font-size: 11px;
                margin-top: 8px;
                padding: 6px 10px;
                border-radius: 4px;
                position: sticky;
                bottom: 8px;
                z-index: 10;
            }
            
            .footer-link i {
                font-size: 11px;
            }
        }
        
        @media (max-height: 500px) and (orientation: landscape) {
            .container {
                flex-direction: row;
            }
            
            .left {
                flex: 1;
                height: 100vh;
                display: block;
            }
            
            .right {
                flex: 1;
                padding: 15px 20px;
            }
            
            .content-wrapper {
                gap: 10px;
            }
            
            .right h1 {
                font-size: clamp(16px, 3vw, 20px);
            }
            
            .button {
                padding: 8px 16px;
                max-width: 180px;
                font-size: clamp(12px, 2vw, 14px);
            }
            
            .footer-link {
                font-size: 11px;
                margin-top: 8px;
            }
            
            .footer-link i {
                font-size: 11px;
            }
        }
        
        @media (min-width: 1201px) {
            .right {
                padding: 40px 30px;
            }
            
            .content-wrapper {
                max-width: 420px;
                gap: 30px;
            }
            
            .right h1 {
                font-size: clamp(28px, 5vw, 36px);
            }
            
            .button {
                max-width: 320px;
                padding: 18px 36px;
                font-size: clamp(18px, 3vw, 22px);
            }
            
            .footer-link {
                font-size: 16px;
            }
            
            .footer-link i {
                font-size: 16px;
            }
        }
        
        @media (max-height: 600px) {
            .right {
                padding: 15px 20px;
            }
            
            .content-wrapper {
                gap: 15px;
            }
            
            .footer-link {
                margin-top: 10px;
            }
        }
        
        @media (max-height: 500px) and (max-width: 768px) {
            .right {
                padding: 8px 12px 8px 12px;
                min-height: 100vh;
            }
            
            .content-wrapper {
                gap: 8px;
                flex: 1;
            }
            
            .footer-link {
                font-size: 11px;
                margin-top: 5px;
                padding: 5px 8px;
                background-color: rgba(255,255,255,0.95);
                border-radius: 4px;
                position: sticky;
                bottom: 5px;
                z-index: 10;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left"></div>
        <div class="right">
            <div class="content-wrapper">
                <h1>Inicia sesiónn o Regístrate</h1>
                <a href="https://negocios.buscounservicio.es/auth/login" class="button">Iniciar sesión</a>
                <a href="https://negocios.buscounservicio.es/auth/registro" class="button">Registrarse</a>
            </div>
            <a href="https://negocios.buscounservicio.es/ayuda/" class="footer-link">
                <i class="fas fa-question-circle"></i>
                <span>Centro de ayuda</span>
            </a>
        </div>
    </div>
</body>
</html>