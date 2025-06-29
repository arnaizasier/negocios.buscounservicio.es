<?php include '../assets/includes/header.php'; ?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<main>
    <div class="contact-page">
        <h1>¿Por dónde quieres hablarnos?</h1>
        <p>El tiempo de respuesta es inferior a 24 horas.</p>
        <div class="button-container">
            <button onclick="location.href='https://wa.me/34644002339'">
                <i class="fab fa-whatsapp"></i> Chat de WhatsApp
            </button>
            <button onclick="location.href='https://buscounservicio.es/paginas/formulario-de-contacto/'">
                <i class="fas fa-envelope"></i> Formulario
            </button>
            <button onclick="location.href='mailto:info@buscounservicio.es'">
                <i></i> info@buscounservicio.es
            </button>
            <button onclick="location.href='https://buscounservicio.es/paginas/preguntas-frecuentes'">
                <i class="fas fa-question-circle"></i> Preguntas frecuentes
            </button>
        </div>
    </div>
</main>


<style>
        /* Estilos encapsulados dentro de la clase .contact-page */
        .contact-page {
            font-family: 'Poppins', Arial, sans-serif;
            text-align: center;
            margin: 0;
            background-color: #fff;
            min-height: 80vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding-top: 50px;
            padding-bottom: 80px;
            box-sizing: border-box;
        }
        .contact-page h1 {
            font-size: 2em;
            margin-top: 0;
            color: #333;
        }
        .contact-page p {
            font-size: 1em;
            color: #666;
            margin-bottom: 30px;
        }
        .contact-page .button-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        .contact-page .button-container button {
            width: 100%;
            padding: 15px;
            margin: 10px 0;
            font-size: 1.1em;
            color: #fff;
            background-color: #2755d3;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: scale(1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .contact-page .button-container button:hover {
            background-color: #1a3f91;
            transform: scale(1.05);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
        .contact-page .button-container button i {
            margin-right: 10px;
        }
        .contact-page .button-container button:active {
            transform: scale(0.95);
        }

        /* Media Queries para diseño responsive */
        @media (max-width: 768px) {
            .contact-page h1 {
                font-size: 1.5em;
            }
            .contact-page p {
                font-size: 0.9em;
            }
            .contact-page .button-container button {
                font-size: 1em;
                padding: 12px;
            }
        }

        @media (max-width: 480px) {
            .contact-page h1 {
                font-size: 2em;
            }
            .contact-page p {
                font-size: 0.8em;
            }
            .contact-page .button-container button {
                font-size: 0.9em;
                padding: 10px;
            }
        }
</style>

<?php include '../assets/includes/footer.php'; ?>
<script src="../assets/js/header.js"></script>
<script>
    document.querySelectorAll('.faq-item h3').forEach(i => i.addEventListener('click', () => i.parentElement.classList.toggle('active')));
    document.getElementById('search').addEventListener('input', function() {
        let f = this.value.toLowerCase();
        document.querySelectorAll('.faq-item').forEach(i => i.style.display = i.querySelector('h3').innerText.toLowerCase().includes(f) ? "block" : "none");
    });
</script>