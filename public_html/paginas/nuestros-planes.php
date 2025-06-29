<?php include '../assets/includes/header.php'; ?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Conoce lo que ofrecen nuestros planes y consulta los precios.">
    <title>Descubre nuestros planes</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

    <main>
        <div class="pricing-container">
            <h1>Planes y Precios</h1>
            <p class="sub-pricing">Comience con el plan Gratis o elija Premium para acceder a herramientas avanzadas.</p>
            <p class="pricing-subtitle"></p>

            <div class="pricing-plans">
                <div class="plan-free">
                    <h2>Gratis</h2>
                    <p>Comienza y eleva tu negocio.</p>
                    <p class="plan-price" id="free-price">0€ <span>/MES</span></p>
                    <p class="plan-guarantee">No se requiere tarjeta de crédito para este plan.</p>
                    <a href="https://buscounservicio.es/anadir-negocio/" class="plan-button">Comenzar</a>
                    <ul>
                        <li>Fotos</li>
                        <li>Reseñas</li>
                        <li>Reserva de citas</li>
                        <li>Venta de productos</li>
                        <li>Horario, Ubicación</li>
                        <li>Descripción y enlaces</li>
                    </ul>
                </div>

                <div class="plan-premium">
                    <h2>PREMIUM</h2>
                    <p>Para los más exigentes.</p>
                    <p class="plan-price" id="premium-price">10€ <span>/MES</span></p>
                    <p class="plan-guarantee">30 días de devolución, sin preguntas ni condiciones.</p>
                    <a href="https://buscounservicio.es/anadir-negocio/" class="plan-button">Comenzar</a>
                    <ul>
                        <li>Todo lo del plan gratis</li>
                        <li>Cupones</li>
                        <li>Clientes ilimitados</li>
                        <li>Finanzas ilimitados</li>
                        <li>Google reviews</li>
                        <li>Soporte prioritario</li>
                    </ul>
                </div>
            </div>

            <a href="https://buscounservicio.es/paginas/todas-nuestras-funcionalidades.php" 
                style="display: inline-block; padding: 10px 20px; margin-top: 50px; background-color: #2755d3; color: white; 
                    text-decoration: none; border-radius: 25px; font-size: 16px; font-family: Poppins, sans-serif;">
                Conoce todas nuestras herramientas
            </a>

            <div class="pricing-contact">
                <h3>¿Tienes alguna pregunta?</h3>
                <p>Escríbenos, nos encantaría ayudarte.</p>
                <div class="contact-options">
                    <a href="http://wa.me/34644002339" class="contact-whatsapp" target="_blank">WhatsApp</a>
                    <a href="https://buscounservicio.es/contacto/" class="contact-alternative" target="_blank">Otro método</a>
                </div>
            </div>
        </div>
    </main>

    <style>
        main {
            font-family: "Poppins", sans-serif;
            background-color: #fff;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .pricing-container {
            text-align: center;
            width: 80%;
            max-width: 800px;
            padding-bottom: 40px;
            padding-top: 50px;
        }

        h1 {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 40px;
        }

        .pricing-subtitle {
            font-size: 1em;
            color: #666;
            margin-bottom: 60px;
        }

        .sub-pricing {
            font-family: "Montserrat"
            font-size: 1em;
            color: #333;
        }

        .pricing-toggle {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
        }

        .pricing-toggle span {
            font-size: 1em;
            color: #666;
            margin: 0 10px;
        }

        .pricing-toggle input[type="checkbox"] {
            display: none;
        }

        .pricing-toggle label {
            background-color: #ddd;
            width: 60px;
            height: 30px;
            border-radius: 15px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .pricing-toggle label::before {
            content: '';
            position: absolute;
            width: 26px;
            height: 26px;
            background-color: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }

        .pricing-toggle input:checked + label {
            background-color: #4caf50;
        }

        .pricing-toggle input:checked + label::before {
            transform: translateX(30px);
        }

        .pricing-toggle .pricing-discount {
            color: #4caf50;
            font-size: 0.9em;
            margin-left: 10px;
        }

        .pricing-plans {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .plan-free, .plan-premium {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 300px;
            padding: 20px;
            text-align: left;
        }

.plan-premium {
    position: relative;
    padding: 20px;
    border-radius: 12px;
    background: white;
    z-index: 1;
}

.plan-premium::before {
    content: "";
    position: absolute;
    inset: 0; /* Se ajusta al tamaño del contenedor */
    background: linear-gradient(45deg, #ff00ff, #0088ff, #00ff88, #ff8800);
    border-radius: 12px;
    padding: 2px; /* Grosor del borde */
    -webkit-mask: 
        linear-gradient(white 0 0) content-box, 
        linear-gradient(white 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    z-index: -1;
}

        .plan-free h2, .plan-premium h2 {
            font-size: 1.5em;
            color: #333;
            margin: 0;
        }

        .plan-free p, .plan-premium p {
            color: #666;
            margin: 5px 0 20px;
        }

        .plan-price {
            font-size: 2em;
            color: #333;
            margin: 10px 0;
        }

        .plan-price span {
            font-size: 0.5em;
            color: #666;
        }

        .plan-guarantee {
            font-size: 0.8em;
            color: #666;
            text-align: center;
            margin: 10px 0;
        }

        .plan-button {
            display: block;
            text-align: center;
            padding: 10px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            margin: 10px 0;
            margin-bottom: 40px;
        }

        .plan-free .plan-button {
            background-color: #333;
            color: white;
        }

        .plan-premium .plan-button {
            background-color: #2755d3;
            color: white;
        }

        .plan-free ul, .plan-premium ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .plan-free ul li, .plan-premium ul li {
            font-size: 0.9em;
            color: #666;
            margin: 10px 0;
            position: relative;
            padding-left: 25px;
        }

        .plan-free ul li::before, .plan-premium ul li::before {
            content: '✔';
            color: #2755d3;
            position: absolute;
            left: 0;
            font-size: 1.2em;
        }

        .pricing-contact {
            margin-top: 80px;
            margin-bottom: 50px;
            text-align: center;

        }

        .pricing-contact h3 {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 10px;
        }

        .pricing-contact p {
            font-size: 1em;
            color: #666;
            margin-bottom: 20px;
        }

        .contact-options {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .contact-options a {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1em;
        }

        .contact-whatsapp {
            background-color: #25D366;
            color: white;
        }

        .contact-alternative {
            background-color: #2755d3;
            color: white;
        }

        /* Media Query para pantallas menores a 440px */
        @media (max-width: 440px) {
            .pricing-container {
                width: 90%;
                padding: 20px 0;
            }

            .pricing-plans {
                flex-direction: column; /* Cambia a disposición vertical */
                align-items: center; /* Centra los planes */
            }

            .plan-free, .plan-premium {
                width: 100%; /* Ocupa todo el ancho disponible */
                max-width: 300px; /* Limita el ancho máximo */
                margin-bottom: 20px; /* Espacio entre planes */
            }

            h1 {
                font-size: 2em; /* Reduce el tamaño del título */
            }

            .pricing-toggle {
                flex-wrap: wrap; /* Permite que los elementos se envuelvan */
                gap: 10px; /* Espacio entre elementos */
            }

            .pricing-toggle span {
                font-size: 0.9em; /* Reduce ligeramente el tamaño del texto */
            }

            .pricing-contact h3 {
                font-size: 1.2em; /* Reduce el tamaño del título */
            }

            .contact-options {
                flex-direction: column; /* Botones de contacto en columna */
                gap: 10px;
            }

            .contact-options a {
                width: 100%; /* Botones ocupan todo el ancho */
                max-width: 200px; /* Limita el ancho máximo */
                margin: 0 auto; /* Centra los botones */
            }
        }
    </style>

    <script>
        function updatePrices() {
            const toggle = document.getElementById('toggle');
            const freePrice = document.getElementById('free-price');
            const premiumPrice = document.getElementById('premium-price');

            if (toggle.checked) {
                freePrice.innerHTML = '0€ <span>/AÑO</span>';
                premiumPrice.innerHTML = '8€ <span>/AÑO (96€)</span>';
            } else {
                freePrice.innerHTML = '0€ <span>/MES</span>';
                premiumPrice.innerHTML = '10€ <span>/MES</span>';
            }
        }
    </script>

<?php include '../assets/includes/footer.php'; ?>
<script src="../assets/js/header.js"></script>