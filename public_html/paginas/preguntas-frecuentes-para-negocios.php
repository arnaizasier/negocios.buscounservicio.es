<?php include '../assets/includes/header.php'; ?>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preguntas frecuentes para Negocios</title>
    <meta name="description" content="Resuelve tus dudas sobre el uso de nuestra plataforma. Encuentra respuestas a preguntas frecuentes de negocios. ¡Consulta nuestra sección de ayuda ahora!">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<main class="main-content">
    <div class="container">
        <h1>Preguntas Frecuentes para <span style="color: #2755d3;">Negocios</span></h1>
        
        <input type="text" id="search" placeholder="Buscar una pregunta...">
        
        <div class="faq-list">
            <div class="faq-item">
                <div class="faq-question">¿Cómo puedo añadir mi negocio?</div>
                <div class="faq-answer">
                    <p>Si ya tienes una cuenta de usuario creada, en el panel de tu perfil verás un apartado llamado "Cambiar rol" en el que cambiar al modo negocio. Si no tienes una cuenta creada, puedes añadir tu negocio registrándote, en el registro aparecerá un recuadro en el que podrás elegir el registro para negocios.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Es gratis el uso de buscounservicio?</div>
                <div class="faq-answer">
                    <p>Actualmente, todas nuestras funciones son gratuitas, no tenemos ningún tipo de suscripción o pago por usar nuestro software. Conoce todas nuestras funcionalidades en <a href="https://buscounservicio.es/paginas/todas-nuestras-funcionalidades" target="_blank">este enlace</a>.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Qué es iCal?</div>
                <div class="faq-answer">
                    <p>iCal es una forma en la que puedes conectar tu calendario de Google Calendar con el calendario de Buscounservicio.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Cómo importo mi calendario a Google Calendar?</div>
                <div class="faq-answer">
                    <p>Conecta Google Calendar con Buscounservicio mediante iCal. Ve a Mis direcciones, haz clic en iCal, selecciona Importar y copia la URL. En Google Calendar, entra en Configuración (arriba a la derecha), ve a Añadir calendario desde URL, pega la URL y listo. ¡Tus citas se importarán automáticamente!</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">Mis servicios no tienen una dirección</div>
                <div class="faq-answer">
                    <p>En caso de que ofrezcas servicios a domicilio, en la dirección debes poner la ciudad en donde prestas tus servicios.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">Necesito duplicar un negocio</div>
                <div class="faq-answer">
                    <p>Para poder duplicar un negocio, <a href="https://buscounservicio.es/paginas/contacto" target="_blank">contacta con nosotros</a> y lo solucionaremos en un abrir y cerrar de ojos.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Puedo aceptar pagos por adelantado en reservas?</div>
                <div class="faq-answer">
                    <p>Dispones de tres opciones: Con pago por adelantado, sin pago por adelantado y ofrecer las dos opciones. Podrás cambiar esta opción yendo a "Mis direcciones" - "Editar" - "Configuración de reservas" y en la sección "Opciones de pago".</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Cómo añado los gastos de envío?</div>
                <div class="faq-answer">
                    <p>Con el fin de ofrecer una mejor experiencia a los usuarios, no es posible cobrar los gastos de envío. Todos los productos se ofrecen como gastos de envío gratis.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Cómo recibo las notificaciones de nuevas reservas?</div>
                <div class="faq-answer">
                    <p>Recibirás una notificación por correo electrónico cada vez que un cliente reserve un servicio. También puedes verlas en tu panel de usuario.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Con qué métodos de pago pueden pagar mis clientes?</div>
                <div class="faq-answer">
                    <p>Actualmente, los métodos de pago que ofrecemos son: Tarjeta de crédito o débito, PayPal, Apple Pay y Google Pay.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Cómo puedo eliminar mi cuenta?</div>
                <div class="faq-answer">
                    <p>Para poder eliminar tu cuenta, escríbenos a nuestro correo <a href="mailto:info@buscounservicio.es">info@buscounservicio.es</a> indicándonos el correo de la cuenta que quieres eliminar y el nombre y apellido. Tus datos serán eliminados de forma permanente y no se podrán recuperar.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿No sale aquí tu pregunta?</div>
                <div class="faq-answer">
                    <p>Escríbenos en <a href="https://buscounservicio.es/paginas/contacto" target="_blank">nuestra página de contacto</a>.</p>
                </div>
            </div>
        </div>
        
        <div class="no-results">No se encontraron resultados para tu búsqueda.</div>
    </div>
</main>

<style>
    /* Estilos globales mínimos para evitar conflictos */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    /* Asegurar que el body no afecte a header ni footer */
    body {
        background-color: #fff;
        color: #333;
        line-height: 1.6;
        font-family: 'Poppins', sans-serif;
    }
    
    /* Aislar completamente los estilos del main */
    .main-content {
        padding-top: 80px;
        padding-bottom: 100px;
    }
    
    .main-content .container {
        max-width: 800px;
        margin: 0 auto;
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        padding: 30px;
    }
    
    .main-content h1 {
        text-align: center;
        margin-bottom: 25px;
        color: #2c3e50;
        font-size: 28px;
    }
    
    .main-content #search {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 30px;
        font-size: 16px;
        margin-bottom: 25px;
        transition: all 0.3s ease;
    }
    
    .main-content #search:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
    }
    
    .main-content .faq-item {
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
        padding-bottom: 20px;
    }
    
    .main-content .faq-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    
    .main-content .faq-question {
        cursor: pointer;
        font-weight: 600;
        font-size: 18px;
        color: #2c3e50;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: color 0.3s ease;
    }
    
    .main-content .faq-question:hover {
        color: #2755d3;
    }
    
    .main-content .faq-question::after {
        content: '+';
        font-size: 22px;
        transition: transform 0.3s ease;
    }
    
    .main-content .faq-item.active .faq-question::after {
        transform: rotate(45deg);
    }
    
    .main-content .faq-answer {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.5s ease, padding 0.5s ease;
        padding: 0 10px;
    }
    
    .main-content .faq-item.active .faq-answer {
        max-height: 300px;
        padding: 15px 10px 0;
    }
    
    .main-content .faq-answer p {
        color: #555;
        font-size: 16px;
    }
    
    .main-content .faq-answer a {
        color: #3498db;
        text-decoration: none;
    }
    
    .main-content .faq-answer a:hover {
        text-decoration: underline;
    }
    
    .main-content .no-results {
        text-align: center;
        padding: 20px;
        color: #7f8c8d;
        display: none;
    }
    
    @media (max-width: 768px) {
        .main-content .container {
            padding: 20px;
            margin: 30px;
        }
        
        .main-content h1 {
            font-size: 24px;
        }
        
        .main-content .faq-question {
            font-size: 16px;
        }
    }

    /* Asegurar que el header y footer no se vean afectados */
    header, footer {
        all: revert; /* Esto restablece los estilos a los valores por defecto del navegador */
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Comportamiento de acordeón para las preguntas
        const faqItems = document.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            
            question.addEventListener('click', () => {
                // Si ya está activo, desactivarlo
                if (item.classList.contains('active')) {
                    item.classList.remove('active');
                } else {
                    // Cerrar todos los demás
                    faqItems.forEach(otherItem => {
                        otherItem.classList.remove('active');
                    });
                    
                    // Activar el actual
                    item.classList.add('active');
                }
            });
        });
        
        // Funcionalidad de búsqueda
        const searchInput = document.getElementById('search');
        const noResults = document.querySelector('.no-results');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let hasVisibleItems = false;
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                
                // Verificar si la pregunta o respuesta contiene el término de búsqueda
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                    hasVisibleItems = true;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Mostrar o ocultar el mensaje de "No se encontraron resultados"
            if (hasVisibleItems || searchTerm === '') {
                noResults.style.display = 'none';
            } else {
                noResults.style.display = 'block';
            }
        });
    });
</script>

<?php include '../assets/includes/footer.php'; ?>
<script src="../assets/js/header.js"></script>

<script>
    document.querySelectorAll('.faq-item h3').forEach(i => i.addEventListener('click', () => i.parentElement.classList.toggle('active')));
    document.getElementById('search').addEventListener('input', function() {
        let f = this.value.toLowerCase();
        document.querySelectorAll('.faq-item').forEach(i => i.style.display = i.querySelector('h3').innerText.toLowerCase().includes(f) ? "block" : "none");
    });
</script>