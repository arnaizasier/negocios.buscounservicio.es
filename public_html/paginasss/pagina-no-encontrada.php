<?php include '../assets/includes/header.php'; ?>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<main>
    <h1 class="error-code">404</h1>
    <strong class="error-message">Página no encontrada</strong>
    <p class="error-description">Es posible que la dirección haya sido escrita incorrectamente o que la página ya no exista.</p>
    <a href="/" class="btn-home">Volver al inicio</a>
</main>


<style>

main {
    font-family: 'Poppins', sans-serif;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding-bottom: 80px;
}

.error-code {
    font-size: 9rem;
    color: #2755d3;
}


.error-message {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 10px 0;
    color: #333;
}

.error-description {
    font-family: 'Poppins', Arial, sans-serif;
    font-size: 1.6rem;
    margin: 10px 0;
    color: #333;
}


@media (max-width: 768px) {
    .error-description {
        font-size: 1.2rem; 
    }
}

.btn-home {
    display: inline-block;
    padding: 10px 20px;
    margin-top: 20px;
    background-color: #2755d3;
    color: white;
    text-decoration: none;
    border-radius: 25px;
    font-size: 1.1rem;
    transition: background-color 0.3s;
}

.btn-home:hover {
    background-color: #1f47a4;
}

</style>

<?php include '../footer2.php'; ?>
<script src="../header2.js"></script>
