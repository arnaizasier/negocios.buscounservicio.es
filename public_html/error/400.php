
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud incorrecta</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/marca.css">
</head>
<main>
    <h1 class="error-code">400</h1>
    <strong class="error-message">Solicitud incorrecta</strong>
    <p class="error-description">El servidor no pudo procesar la solicitud debido a un error del cliente.</p>
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
    padding: 40px;
    padding-bottom: 80px;
}

.error-code {
    font-size: 9rem;
    color: #2755d3;
}


.error-message {
    font-family: 'Poppins1', Arial, sans-serif;
    font-size: 2rem;
    font-weight: 700;
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
    main {
        padding: 20px;
    }
    .error-description {
        font-size: 1.2rem; 
    }
}

.btn-home {
    display: inline-block;
    padding: 10px 20px;
    margin-top: 20px;
    margin-bottom: 30px;
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