<?php include '../assets/includes/header.php'; ?>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preguntas frecuentes para Usuarios</title>
    <meta name="description" content="Resuelve tus dudas sobre el uso de nuestra plataforma. Encuentra respuestas a preguntas frecuentes de usuarios. ¡Consulta nuestra sección de ayuda ahora!">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<main class="main-content">
    <div class="container">
        <h1>Preguntas Frecuentes para <span style="color: #2755d3;">Usuarios</span></h1>
        
        <input type="text" id="search" placeholder="Buscar una pregunta...">
        
        <div class="faq-list">
            <div class="faq-item">
                <div class="faq-question">¿Puedo cancelar mi cita?</div>
                <div class="faq-answer">
                    <p>Puede cancelar una cita siempre que falten más de 24 horas de la fecha de inicio de la cita programada. Si no es así, el negocio se reserva el derecho a no reembolsar el dinero.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Cómo cancelo una cita?</div>
                <div class="faq-answer">
                    <p>Para cancelar una cita, dirígete a tu perfil en el apartado «Mis reservas» y contacta con el negocio indicando que deseas cancelar la cita. Si tienes más de una cita con ese negocio, asegúrate de especificar cuál. La hora de envío del mensaje será la referencia para calcular las 24 horas previas a la cita y determinar si es posible un reembolso.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">Tengo una incidencia con la reserva</div>
                <div class="faq-answer">
                    <p>Si tienes una incidencia con una de tus reservas y no la has podido solucionar con el negocio, ponte en contacto con nosotros para resolver la situación. Escríbenos a nuestro correo <a href="mailto:reservas@buscounservicio.es">reservas@buscounservicio.es</a> o a través de nuestra <a href="https://buscounservicio.es/paginas/contacto" target="_blank">página de contacto</a>.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿Cómo puedo eliminar mi cuenta?</div>
                <div class="faq-answer">
                    <p>Para eliminar tu cuenta, escríbenos a <a href="mailto:info@buscounservicio.es">info@buscounservicio.es</a> indicándonos el correo de la cuenta que deseas eliminar, junto con tu nombre y apellido. Ten en cuenta que la eliminación es permanente y los datos no podrán recuperarse.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">¿No sale aquí tu pregunta?</div>
                <div class="faq-answer">
                    <p>Escríbenos a través de nuestra <a href="https://buscounservicio.es/paginas/contacto" target="_blank">página de contacto</a>.</p>
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