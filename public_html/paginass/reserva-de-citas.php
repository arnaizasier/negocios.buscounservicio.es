<?php include '../assets/includes/header.php'; ?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plataforma gratuita para profesionales: crea tu perfil, gestiona citas online y aumenta tu visibilidad. ¡Empieza ahora y conecta con más clientes!">
    <meta name="keywords" content="sistema de reserva de citas, gestion de citas online, programa para reserva de citas">
    <meta name="robots" content="index, follow">
    <link rel="preload" href="https://buscounservicio.es/imagenes/recursos/ofrece-a-tus-clientes-un-perfil-todo-en-uno-ahora.webp" as="image">
    <link rel="stylesheet" href="../assets/css/styles.css">
    
    <!-- Open Graph para redes sociales -->
    <meta property="og:title" content="Sistema de Reserva de citas online gratis">
    <meta property="og:description" content="Plataforma gratuita para profesionales: crea tu perfil, gestiona citas online y aumenta tu visibilidad. ¡Empieza ahora y conecta con más clientes!">
    <meta property="og:image" content="imagenes/recursosofrece-a-tus-clientes-un-perfil-todo-en-uno-ahora.webp">
    <meta property="og:url" content="https://buscounservicio.es.es/paginas/reserva-de-citas.php">
    <meta property="og:type" content="article">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Sistema de Reserva de citas online gratis">
    <meta name="twitter:description" content="Plataforma gratuita para profesionales: crea tu perfil, gestiona citas online y aumenta tu visibilidad. ¡Empieza ahora y conecta con más clientes!">
    <meta name="twitter:image" content="imagenes/recursos/ofrece-a-tus-clientes-un-perfil-todo-en-uno-ahora.webp">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Sistema de Reserva de citas online gratis</title>
</head>

<main>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #ffffff;
            color: #333;
            line-height: 1.6;
        }

        .hero-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            margin: 0 auto;
            max-width: 1300px;
            min-height: 400px;
            
        }

        .unete-ordenador {
            font-size: 55px;
            line-height: 1.2;
            margin-bottom: 15px;
            margin-top: 50px;
            background: linear-gradient(to right, #4a90e2, #6c63ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
            font-family: 'Poppins', sans-serif;
        }

        .texto-descriptivo {
            color: #000;
            font-size: 18px;
            font-family: 'Poppins', sans-serif;
            max-width: 600px;
            margin-bottom: 20px;
        }

        .button-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 30px;
        }

        .btn {
            padding: 12px 25px;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primaryy {
            background: linear-gradient(to right, #4a90e2, #6c63ff);
            color: white;
        }

        .btn-secondaryy {
            background: #fff;
            color: #4a90e2;
            border: 2px solid #4a90e2;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .profile-image {
            max-width: 100%;
            height: auto;
            display: block;
            margin-bottom: 20px;
        }

        .profile-image, .categoria img, .ola {
            width: 100%;
            height: auto;
            aspect-ratio: 16/9; /* Mantiene proporción consistente */
        }

        /* Estilos para las olas */
        .ola-contenedor {
            width: 100%;
            height: 40px;
            position: relative;
            margin: 0;
            padding: 0;
            background-color: white;
            overflow: hidden;
            display: block;
            line-height: 0;
        }

        .ola-contenedor.inferior {
            background-color: white;
            margin-top: -1px;
        }

        .ola {
            width: 100%;
            height: 100%;
            position: absolute;
            bottom: 0;
            left: 0;
            margin: 0;
            padding: 0;
            display: block;
        }

        .ola.ola-inferior {
            top: 0;
            bottom: auto;
        }

        .seccion-contenido {
            background-color: #A6C8F7;
            padding: 30px 15px 40px;
            width: 100%;
            box-sizing: border-box;
            margin-top: -1px;
            position: relative;
            z-index: 1;
        }

        .seccion-titulo {
            color: white;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 30px 0;
        }

        .tarjetas-contenedor {
            display: flex;
            justify-content: center;
            gap: 50px;
            max-width: 1200px;
            margin: 30px auto 10px;
            flex-wrap: nowrap;
        }

        .tarjeta {
            background: #ffffff;
            border-radius: 16px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            font-family: 'Poppins', sans-serif;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            width: 300px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .tarjeta:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(166, 200, 247, 0.5);
        }

        .tarjeta-texto {
            flex: 1;
        }

        .tarjeta-titulo {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 0;
            color: #2755d3;
        }

        .tarjeta-descripcion {
            font-size: 16px;
            color: #666;
            line-height: 1.7;
        }

        /* Nueva sección de reservas */
        .reservas-container {
            max-width: 1100px;
            margin: 0 auto;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }

        .reservas-titulo {
            text-align: center;
            color: #2563eb;
            font-size: 32px;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 15px;
            margin-top: 20px;
        }

        .reservas-descripcion {
            text-align: center;
            color: #64748b;
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .reservas-tarjetas {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            justify-content: center;
        }

        .reserva-tarjeta {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            width: 500px;
            padding: 25px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 350px;
            flex: 0 1 500px;
        }

        .reserva-tarjeta.duracion {
            border-top: 5px solid #2563eb;
        }

        .reserva-tarjeta.franjas {
            border-top: 5px solid #2755d3;
        }

        .reserva-emoji {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .reserva-titulo {
            font-size: 22px;
            margin: 0 0 15px;
        }

        .reserva-titulo.duracion {
            color: #2563eb;
        }

        .reserva-titulo.franjas {
            color: #2755d3;
        }

        .reserva-descripcion {
            color: #64748b;
            font-size: 15px;
            line-height: 1.6;
            margin: 0 0 20px;
        }

        .reserva-lista {
            text-align: left;
            color: #64748b;
            padding-left: 20px;
            margin-bottom: 25px;
        }

        .reserva-btn {
            display: inline-block;
            padding: 10px 25px;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
        }

        .reserva-btn.duracion {
            background: #2563eb;
        }

        .reserva-btn.franjas {
            background: #2755d3;
        }

        /* Estilos para la sección de funcionalidades */
        .funcionalidades-section {
            text-align: center;
            max-width: 1500px;
            width: 100%;
            animation: contactFadeIn 1s ease-in-out;
            background-color: #ffffff;
            margin: 80px auto 0;
        }

        .funcionalidades-title {
            font-family: "Poppins", sans-serif;
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 40px;
            text-align: center;
        }

        .funcionalidades {
            background-color: #ffffff !important;
            padding: 5% 3%;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .funcion {
            background: #ffffff;
            padding: 18px 15px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            height: 100%;
        }

        .funcion:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .icono {
            font-size: 1.8rem;
            color: #2755d3;
            margin-bottom: 8px;
            transition: color 0.3s;
            display: inline-block;
            line-height: 1;
        }

        .funcion:hover .icono {
            color: #1a3f91;
        }

        .funcion h3 {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2755d3;
            margin: 0 0 6px 0;
            line-height: 1.3;
        }

        .funcion p {
            font-size: 0.9rem;
            color: #555;
            margin: 0;
            line-height: 1.4;
        }

        .funcionalidades-section .icono i {
            font-family: "Font Awesome 6 Free" !important;
            font-weight: 900 !important;
        }

        /* Estilos para la sección de contacto */
        .contact-section {
            background-color: #FF8C32;
            text-align: center;
            padding: 60px 20px;
            color: white;
        }

        .contact-section h2 {
            font-size: 45px;
            font-weight: bold;
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
        }

        .contact-section p {
            font-size: 16px;
            margin-bottom: 30px;
            font-family: 'Poppins', sans-serif;
        }

        .contact-section a {
            display: inline-block;
            padding: 12px 24px;
            margin: 8px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .whatsapp-btn {
            background-color: #25D366;
            color: white;
        }

        .other-method-btn {
            background-color: white;
            color: #2755d3;
        }

        .contact-section a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Estilos para la sección de FAQ */
        .faq-content {
            padding-top: 80px;
            padding-bottom: 100px;
            margin: 20px;
        }

        .faq-content .hero-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .faq-content h1 {
            text-align: center;
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 28px;
            font-family: 'Poppins', sans-serif;
        }

        .faq-content .faq-item {
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        .faq-content .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .faq-content .faq-question {
            cursor: pointer;
            font-weight: 600;
            font-size: 18px;
            color: #2c3e50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: color 0.3s ease;
        }

        .faq-content .faq-question:hover {
            color: #2755d3;
        }

        .faq-content .faq-question::after {
            content: '+';
            font-size: 22px;
            transition: transform 0.3s ease;
        }

        .faq-content .faq-item.active .faq-question::after {
            transform: rotate(45deg);
        }

        .faq-content .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease, padding 0.5s ease;
            padding: 0 10px;
        }

        .faq-content .faq-item.active .faq-answer {
            max-height: 300px;
            padding: 15px 10px 0;
        }

        .faq-content .faq-answer p {
            color: #555;
            font-size: 16px;
        }

        .faq-content .faq-answer a {
            color: #3498db;
            text-decoration: none;
        }

        .faq-content .faq-answer a:hover {
            text-decoration: underline;
        }

        /* Estilos para la sección de categorías */
        .categorias-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            max-width: 100%;
            padding: 20px;
            margin-bottom: 50px;
            min-height: 420px;
        }

        .categoria {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            height: 200px;
            cursor: pointer;
        }

        .categoria img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease-in-out;
        }

        .categoria:hover img {
            transform: scale(1.05);
        }

        .categoria .titulo {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            padding: 10px 0;
            font-family: 'Poppins', sans-serif;
        }

        .categorias-titulo {
            font-size: 15px;
            margin-bottom: 10px;
            text-align: center;
            padding: 20px 0;
        }

        .categorias-titulo h1 {
            font-family: 'Poppins', sans-serif;
            color: #333;
            font-weight: 700;
        }

        /* Media Queries para responsive */
        @media (max-width: 1200px) {
            .reservas-tarjetas {
                flex-direction: row;
                justify-content: center;
            }

            .reserva-tarjeta {
                flex: 0 1 45%;
                max-width: 500px;
            }
        }

        @media (max-width: 1024px) {
            .grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 12px;
            }
            
            .funcion {
                padding: 15px 12px;
            }
            
            .icono {
                font-size: 1.7rem;
            }
        }

        @media (max-width: 768px) {
            .unete-ordenador {
                font-size: 40px;
            }
            
            .texto-descriptivo {
                font-size: 16px;
            }

            .btn {
                font-size: 14px;
                padding: 10px 20px;
            }

            .ola-contenedor {
                height: 30px;
            }

            .tarjetas-contenedor {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }

            .tarjeta {
                width: 100%;
                max-width: 400px;
                padding: 20px;
            }

            .reservas-tarjetas {
                flex-direction: column;
                align-items: center;
            }

            .reserva-tarjeta {
                width: 100%;
                max-width: 400px;
            }

            .funcionalidades-title {
                font-size: 30px;
                font-weight: 700;
                margin-bottom: 28px;
                margin-top: 50px;
            }

            .grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 10px;
            }
            
            .funcionalidades {
                padding: 30px 12px;
            }
            
            .icono {
                font-size: 1.6rem;
                margin-bottom: 6px;
            }
            
            .funcion h3 {
                font-size: 1.1rem;
                margin-bottom: 4px;
            }
            
            .funcion p {
                font-size: 0.85rem;
            }

            .contact-section h2 {
                font-size: 35px;
            }

            .contact-section p {
                font-size: 14px;
            }

            .contact-section a {
                font-size: 14px;
                padding: 10px 20px;
            }

            .faq-content .hero-container {
                padding: 20px;
                margin: 30px;
                min-height: 300px;
            }

            .faq-content h1 {
                font-size: 24px;
            }

            .faq-content .faq-question {
                font-size: 16px;
            }

            .categorias-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .categoria {
                height: 140px;
            }

            .categorias-titulo {
                font-size: 30px; 
            }

            .categorias-titulo h1 {
                font-size: 30px;
            }
        }

        @media (max-width: 480px) {
            .unete-ordenador {
                font-size: 28px;
            }
            
            .texto-descriptivo {
                font-size: 14px;
            }

            .btn {
                font-size: 12px;
                padding: 8px 15px;
            }

            .reservas-titulo {
                font-size: 24px;
            }

            .reserva-titulo {
                font-size: 18px;
            }

            .funcionalidades-section {
                margin-top: 0px;
            }

            .funcionalidades-title {
                font-size: 30px;
                font-weight: 700;
                margin-bottom: 20px;
                margin-top: 50px;
            }

            .grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .funcion {
                padding: 12px 10px;
                margin-bottom: 10px;
            }
            
            .icono {
                font-size: 1.4rem;
            }
            
            .funcion h3 {
                font-size: 1rem;
            }
            
            .funcion p {
                font-size: 0.8rem;
            }

            .contact-section {
                padding: 40px 15px;
                margin-top: 80px;
            }

            .contact-section h2 {
                font-size: 32px;
            }

            .contact-section p {
                font-size: 13px;
                margin-bottom: 20px;
            }

            .contact-section a {
                font-size: 13px;
                padding: 8px 16px;
                margin: 5px;
            }

            .faq-content .hero-container {
                padding: 15px;
                margin: 15px;
            }

            .faq-content h1 {
                font-size: 20px;
            }

            .faq-content .faq-question {
                font-size: 14px;
            }

            .faq-content .faq-answer p {
                font-size: 14px;
            }

            .categorias-titulo {
                font-size: 30px;
            }

            .categorias-titulo h1 {
                font-size: 30px;
            }

            .categoria .titulo {
                font-size: 14px;
                padding: 8px 0;
            }
        }

        @media (max-width: 350px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .categorias-grid {
                grid-template-columns: 1fr;
            }
        }

        .whatsapp-float {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background-color: #25D366;
    color: white;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    text-decoration: none;
    transition: transform 0.3s ease, background-color 0.3s ease;
    z-index: 1000; 
}

.whatsapp-float:hover {
    transform: scale(1.1);
    background-color: #20b354;
}

.seccion-contenido, .reservas-container, .funcionalidades-section, .faq-content {
    content-visibility: auto;
    contain-intrinsic-size: 1000px; /* Estimación del tamaño */
}

    </style>
</head>
<body>
    <div class="hero-container">
        <h1 class="unete-ordenador">¡Ofrece a tus clientes citas online!</h1>
        <p class="texto-descriptivo">La plataforma ideal para profesionales que ofrecen servicios.</p>
        <div class="button-container">
            <a href="https://buscounservicio.es/panel/anadir-negocio/" class="btn btn-primaryy">Crear perfil Gratis</a>
            <a href="https://buscounservicio.es/negocio/fisioterapia-castillo" class="btn btn-secondaryy">Ver Perfil de Ejemplo</a>
        </div>
        <img src="https://buscounservicio.es/imagenes/recursos/ofrece-a-tus-clientes-un-perfil-todo-en-uno-ahora.webp" alt="Perfil todo en uno" class="profile-image">
    </div>

    <!-- Ola Superior -->
    <div class="ola-contenedor">
        <svg class="ola" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path fill="#A6C8F7" d="M0,128L48,122.7C96,117,192,107,288,122.7C384,139,480,181,576,176C672,171,768,117,864,101.3C960,85,1056,107,1152,133.3C1248,160,1344,192,1392,208L1440,224L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>
    </div>

    <div class="seccion-contenido">
        <h2 class="seccion-titulo">¿Por qué elegirnos?</h2>
        <div class="tarjetas-contenedor">
            <div class="tarjeta">
                <div class="tarjeta-texto">
                    <div class="tarjeta-titulo">¡Es gratis!</div>
                    <div class="tarjeta-descripcion">No tienes que pagar por usar nuestro software de citas ni por tener un perfil</div>
                </div>
            </div>
            <div class="tarjeta">
                <div class="tarjeta-texto">
                    <div class="tarjeta-titulo">Mayor visibilidad y clientes</div>
                    <div class="tarjeta-descripcion">Podras crear un perfil para estar en nuestro buscador</div>
                </div>
            </div>
            <div class="tarjeta">
                <div class="tarjeta-texto">
                    <div class="tarjeta-titulo">Todo en uno</div>
                    <div class="tarjeta-descripcion">No necesitas una pagina web, en el perfil tendras toda la informacion</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ola Inferior -->
    <div class="ola-contenedor inferior">
        <svg class="ola ola-inferior" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path fill="#A6C8F7" d="M0,0L48,10.7C96,21,192,43,288,53.3C384,64,480,64,576,80C672,96,768,128,864,122.7C960,117,1056,75,1152,64C1248,53,1344,75,1392,85.3L1440,96L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path>
        </svg>
    </div>

    <!-- Sección de Reservas -->
    <div class="reservas-container">
        <h2 class="reservas-titulo">Sistemas de Reservas Inteligentes</h2>
        <p class="reservas-descripcion">Optimiza tu agenda y aumenta la eficiencia de tu negocio con nuestros dos tipos de reservas.</p>
        <div class="reservas-tarjetas">
            <div class="reserva-tarjeta duracion">
                <div>
                    <div class="reserva-emoji">📅</div>
                    <h3 class="reserva-titulo duracion">Ajustada a la duración</h3>
                    <p class="reserva-descripcion">El sistema calculará la disponibilidad teniendo en cuenta tu horario y la duración de cada cita, asegurando una gestión precisa de tu agenda.</p>
                    <ul class="reserva-lista">
                        <li>Optimización automática de horarios</li>
                        <li>Recordatorio de citas</li>
                    </ul>
                </div>
                <a href="https://buscounservicio.es/panel/anadir-negocio/" class="reserva-btn duracion">Empezar</a>
            </div>
            <div class="reserva-tarjeta franjas">
                <div>
                    <div class="reserva-emoji">⏳</div>
                    <h3 class="reserva-titulo franjas">Franjas horarias</h3>
                    <p class="reserva-descripcion">Ideal para optimizar al máximo tu agenda. Establece franjas horarias fijas para evitar huecos libres y maximizar la eficiencia.</p>
                    <ul class="reserva-lista">
                        <li>Franjas personalizables</li>
                        <li>Recordatorio de citas</li>
                    </ul>
                </div>
                <a href="https://buscounservicio.es/panel/anadir-negocio/" class="reserva-btn franjas">Empezar</a>
            </div>
        </div>
    </div>

    <!-- Tercera sección: Funcionalidades -->
    <div class="funcionalidades-section">
        <h2 class="funcionalidades-title">Una herramienta más allá de las reservas</h2>
        <section class="funcionalidades">
            <div class="grid">
                <div class="funcion">
                    <div class="icono"><i class="fas fa-calendar"></i></div>
                    <h3>Reserva de citas</h3>
                    <p>Permite a tus usuarios reservar citas desde el perfil.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-star"></i></div>
                    <h3>Enlaces sociales</h3>
                    <p>Añade tu página web, WhatsApp, Instagram, correo...</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-link"></i></div>
                    <h3>Reseñas</h3>
                    <p>Da credibilidad mostrando las opiniones de los usuarios.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-cart-shopping"></i></div>
                    <h3>Venta de productos</h3>
                    <p>Vende productos relacionados con tu categoría.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-clipboard"></i></div>
                    <h3>Carta de servicios</h3>
                    <p>Muestra los servicios que ofreces de manera clara.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-image"></i></div>
                    <h3>Fotos</h3>
                    <p>Haz tu perfil más personal con imágenes de ti o tu equipo.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-circle-question"></i></div>
                    <h3>Preguntas frecuentes</h3>
                    <p>Resuelve las dudas comunes de tus clientes.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-chart-line"></i></div>
                    <h3>Gestión</h3>
                    <p>Gestiona fácilmente las finanzas y los clientes con nuestras herramientas.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-location-dot"></i></div>
                    <h3>Dirección</h3>
                    <p>Añade la dirección donde prestas tus servicios.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-clock"></i></div>
                    <h3>Descuentos</h3>
                    <p>Ofrece descuentos especiales a tus clientes.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-comment"></i></div>
                    <h3>Chat con los clientes</h3>
                    <p>Habla con los clientes una vez han reservado una cita.</p>
                </div>
                <div class="funcion">
                    <div class="icono"><i class="fas fa-tag"></i></div>
                    <h3>Horario de apertura</h3>
                    <p>Indica los horarios en los que atiendes a tus clientes.</p>
                </div>
            </div>
        </section>
    </div>

    <!-- Sección de Contacto -->
    <div class="contact-section">
        <h2>¿Tienes alguna duda?</h2>
        <p>Queremos resolvértela, contacta con nosotros</p>
        <a href="http://wa.me/34644002339" class="whatsapp-btn">WhatsApp</a>
        <a href="https://buscounservicio.es/contacto" class="other-method-btn">Otro método</a>
    </div>

    <!-- Sección de Preguntas Frecuentes -->
    <main class="faq-content">
        <div class="hero-container">
            <h1>Preguntas <span style="color: #2755d3;">Frecuentes</span></h1>
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question">¿Cómo funciona?</div>
                    <div class="faq-answer">
                        <p>Somos una combinación entre tu página web, las páginas amarillas y una red social. En buscounservicio, cada negocio tiene su propio perfil con toda la información necesaria para los clientes. Además, los usuarios pueden encontrarte fácilmente a través de nuestro buscador y conectar contigo.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">¿Cómo puedo registrar mi negocio?</div>
                    <div class="faq-answer">
                        <p>El registro es rápido y sencillo. Solo tienes que crear una cuenta, completar el formulario con la información de tu empresa y seguir los pasos indicados.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">¿No sale aquí tu pregunta?</div>
                    <div class="faq-answer">
                        <p>Escríbenos a través de nuestra <a href="https://buscounservicio.es/contacto" target="_blank">página de contacto</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Sección de Categorías -->
    <div class="categorias-titulo">
        <h1>Empieza ya, sin importar tu categoría</h1>
    </div>

    <div class="categorias-grid">
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Barberias.webp" alt="Barberías" loading="lazy">
            <div class="titulo">Barberías</div>
        </a>
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Dentistas.webp" alt="Dentistas" loading="lazy">
            <div class="titulo">Dentistas</div>
        </a>
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Fotogragos.webp" alt="Fotógrafos" loading="lazy">
            <div class="titulo">Fotógrafos</div>
        </a>
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Masajistas.webp" alt="Masajistas" loading="lazy">
            <div class="titulo">Masajistas</div>
        </a>
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Entrenadores.webp" alt="Entrenadores" loading="lazy">
            <div class="titulo">Entrenadores</div>
        </a>
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Psicologos.webp" alt="Psicólogos" loading="lazy">
            <div class="titulo">Psicólogos</div>
        </a>
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Nutricionistas.webp" alt="Nutricionistas" loading="lazy">
            <div class="titulo">Nutricionistas</div>
        </a>
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Fisioterapeutas.webp" alt="Fisioterapeutas" loading="lazy">
            <div class="titulo">Fisioterapeutas</div>
        </a>
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Autoescuelas.webp" alt="Autoescuelas" loading="lazy">
            <div class="titulo">Autoescuelas</div>
        </a>
        <a href="https://buscounservicio.es/panel/anadir-negocio/" target="_blank" class="categoria">
            <img src="https://buscounservicio.es/imagenes/recursos/1Centrosdebelleza.webp" alt="Centros de Belleza" loading="lazy">
            <div class="titulo">Centros de Belleza</div>
        </a>
    </div>

    <!-- Botón de WhatsApp -->
<a href="https://wa.me/34644002339" class="whatsapp-float" target="_blank">
    <i class="fab fa-whatsapp"></i>
</a>

    <!-- Script para el acordeón de FAQ -->
    <script>
        document.querySelectorAll('.faq-question').forEach(item => {
            item.addEventListener('click', () => {
                const parent = item.parentElement;
                parent.classList.toggle('active');
            });
        });
    </script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/js/all.min.js"></script>
</body>

<?php include '../assets/includes/footer.php'; ?>
<script src="../assets/js/header.js"></script>

<script>
    document.querySelectorAll('.faq-item h3').forEach(i => i.addEventListener('click', () => i.parentElement.classList.toggle('active')));
    document.getElementById('search').addEventListener('input', function() {
        let f = this.value.toLowerCase();
        document.querySelectorAll('.faq-item').forEach(i => i.style.display = i.querySelector('h3').innerText.toLowerCase().includes(f) ? "block" : "none");
    });
</script>