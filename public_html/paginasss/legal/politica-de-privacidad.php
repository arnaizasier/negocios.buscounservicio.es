<?php include '../../assets/includes/header.php'; ?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de privacidad</title>
    <meta name="description" content="Contactactanos por WhatsApp, email o formulario. Respuesta en menos de 24 horas. ¡Estamos aquí para ayudarte!">
    <link rel="stylesheet" href="../../../assets/css/styles.css">
</head>

<main>
    <h1>Política de privacidad</h1>
    
    <h2>1. Introducción</h2>
    <p>En buscounservicio, estamos comprometidos con la protección de tu privacidad. Esta Política de Privacidad detalla cómo recopilamos, utilizamos, compartimos y protegemos tu información personal cuando utilizas nuestro directorio web.</p>
    
    <h2>2. Información que recopilamos</h2>
    <p>Podemos recopilar la siguiente información personal cuando interactúas con nuestro sitio web:</p>
    <ul>
        <li><strong>Datos de contacto:</strong> Nombre, dirección de correo electrónico.</li>
        <li><strong>Datos de navegación:</strong> Dirección IP, tipo de navegador, sistema operativo, páginas visitadas, enlaces seguidos.</li>
        <li><strong>Información de la cuenta:</strong> Si te registras, almacenaremos tu nombre de usuario y contraseña forma cifrada para proteger tu seguridad.</li>
    </ul>
    
    <h2>3. Cómo utilizamos tu información</h2>
    <p>Utilizamos tu información personal para:</p>
    <ul>
        <li><strong>Proporcionar y mejorar nuestros servicios:</strong> Personalizar tu experiencia, responder a tus consultas y mejorar nuestro directorio web.</li>
        <li><strong>Comunicarnos contigo:</strong> Enviarte actualizaciones, noticias y ofertas relacionadas con nuestros servicios.</li>
        <li><strong>Cumplir con nuestras obligaciones legales:</strong> Mantener registros y cumplir con las leyes y regulaciones aplicables.</li>
    </ul>
    
    <h2>4. Compartir tu información</h2>
    <p>No vendemos ni alquilamos tu información personal a terceros. Podemos compartir tus datos con:</p>
    <ul>
        <li><strong>Proveedores de servicios confiables:</strong> Empresas que nos ayudan a gestionar nuestro sitio web y prestar nuestros servicios (por ejemplo, proveedores de alojamiento web, herramientas de análisis).</li>
        <li><strong>Autoridades competentes:</strong> Si estamos obligados a hacerlo por ley o para proteger nuestros derechos.</li>
    </ul>
    
    <h2>5. Seguridad de tus datos</h2>
    <p>Implementamos medidas de seguridad técnicas y organizativas adecuadas para proteger tu información personal de pérdidas, accesos no autorizados, divulgación, alteración o destrucción.</p>
    <ul>
        <li>Encriptación de datos sensibles.</li>
        <li>Control de acceso a la información personal.</li>
        <li>Actualización regular de nuestras medidas de seguridad.</li>
    </ul>
    
    <h2>6. Tus derechos</h2>
    <p>Tienes derecho a:</p>
    <ul>
        <li><strong>Acceder a tu información:</strong> Solicitar una copia de los datos personales que tenemos sobre ti.</li>
        <li><strong>Rectificar tu información:</strong> Corregir cualquier información inexacta o incompleta.</li>
        <li><strong>Suprimir tu información:</strong> Solicitar la eliminación de tus datos personales.</li>
        <li><strong>Oponerte al tratamiento:</strong> Oponerte al tratamiento de tus datos personales en determinadas circunstancias.</li>
        <li><strong>Limitar el tratamiento:</strong> Solicitar la restricción del tratamiento de tus datos personales.</li>
        <li><strong>Portabilidad de los datos:</strong> Recibir tus datos personales en un formato estructurado, de uso común y legible por máquina.</li>
    </ul>
    
    <h2>7. Cookies</h2>
    <p>Para obtener más información sobre cómo utilizamos las cookies, consulta nuestra <a href="#">Política de Cookies</a>.</p>
    
    <h2>8. Cambios en esta política</h2>
    <p>Podemos actualizar esta Política de Privacidad periódicamente para reflejar cambios en nuestras prácticas o en la legislación. Te notificaremos cualquier cambio significativo y te recomendamos revisar esta página regularmente para estar al tanto de las actualizaciones.</p>
    
    <h2>9. Contacto</h2>
    <p>Para obtener más información sobre nuestras prácticas de privacidad, si tiene alguna pregunta o si desea presentar una queja, contáctenos por correo electrónico a <a href="mailto:dpo@buscounservicio.es">dpo@buscounservicio.es</a>.</p>
</main>

<style>
    main {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        font-family: "Poppins";
        line-height: 1.6;
    }

    h1 {
        font-size: 2em;
        color: #333;
        margin-bottom: 20px;
        text-align: center;
        font-family: "Poppins";
    }

    h2 {
        font-size: 1.5em;
        color: #000;
        margin-top: 30px;
        margin-bottom: 15px;
        font-family: "Poppins";
    }

    p {
        font-size: 1em;
        color: #666;
        margin-bottom: 15px;
        font-family: "Poppins";
    }

    ul {
        list-style-type: disc;
        padding-left: 20px;
        margin-bottom: 20px;
        font-family: "Poppins";
    }

    li {
        font-size: 1em;
        color: #666;
        margin-bottom: 10px;
        font-family: "Poppins";
    }

    a {
        color: #007BFF;
        text-decoration: none;
        font-family: "Poppins";
    }

    a:hover {
        text-decoration: underline;
    }

    strong {
        color: #333;
        font-family: "Poppins";
    }

    @media (max-width: 600px) {
        main {
            padding: 10px;
        }

        h1 {
            font-size: 1.5em;
        }

        h2 {
            font-size: 1.2em;
        }

        p, li {
            font-size: 0.9em;
        }
    }
</style>

<?php include '../../assets/includes/footer.php'; ?>
<script src="../../assets/js/header.js"></script>
<script>
    document.querySelectorAll('.faq-item h3').forEach(i => i.addEventListener('click', () => i.parentElement.classList.toggle('active')));
    document.getElementById('search').addEventListener('input', function() {
        let f = this.value.toLowerCase();
        document.querySelectorAll('.faq-item').forEach(i => i.style.display = i.querySelector('h3').innerText.toLowerCase().includes(f) ? "block" : "none");
    });
</script>