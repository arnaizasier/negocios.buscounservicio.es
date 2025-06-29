<?php include '../assets/includes/header.php'; ?>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preguntas frecuentes</title>
    <meta name="description" content="Resuelve tus dudas sobre el uso de nuestra plataforma. Encuentra respuestas a preguntas frecuentes tanto para negocios como para usuarios. ¡Consulta nuestra sección de ayuda ahora!">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<main class="custom-main">
    <div class="custom-content">
        <h2 class="custom-title">Preguntas frecuentes</h2>
        <p class="custom-text">Si tienes alguna duda sobre cómo usar nuestra plataforma, aquí podrás encontrar respuestas a las preguntas frecuentes.</p>
    </div>

    <div class="custom-button-container">
        <a href="https://buscounservicio.es/ayuda" class="custom-button">
            Preguntas para negocios
        </a>
        <button class="custom-button" onclick="location.href='https://buscounservicio.es/paginas/preguntas-frecuentes-para-usuarios'">
            Preguntas para usuarios
        </button>
    </div>
</main>

<style>
    /* Estilos para el contenido del main */
    .custom-main {
        padding: 50px;
    }
    .custom-content {
        margin-top: 30px;
        text-align: center;
    }
    .custom-title {
        font-size: 1.8em;
        color: #333;
        margin-bottom: 15px;
    }
    .custom-text {
        font-size: 1.2em;
        color: #666;
        margin-bottom: 30px;
    }

    /* Estilos para los botones */
    .custom-button-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-top: 20px;
    }
    .custom-button {
        width: 100%; /* Botón se ajusta al ancho disponible */
        max-width: 400px; /* Máximo ancho de 400px */
        padding: 15px;
        margin: 10px 0;
        font-size: 1.1em;
        color: #fff;
        background-color: #2755d3;
        border: none;
        border-radius: 30px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        transform: scale(1);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-decoration: none;
    }
    .custom-button:hover {
        background-color: #1a3f91;
        transform: scale(1.05);
        box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
    }
    .custom-button:active {
        transform: scale(0.95);
    }

    /* Media Queries para dispositivos móviles */
    @media (max-width: 768px) {
        .custom-title {
            font-size: 1.8em;
        }
        .custom-text {
            font-size: 1.1em;
        }
        .custom-button {
            font-size: 1.1em;
            padding: 12px;
        }
    }

    @media (max-width: 480px) {
        .custom-title {
            font-size: 1.6em;
        }
        .custom-text {
            font-size: 1em;
        }
        .custom-button {
            font-size: 1em;
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
