<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Negocio sin plan Premium</title>
    <link href="../assets/css/marca.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--color-title);
            background: var(--color-white);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            max-width: 90%;
            width: 100%;
            margin: 2rem auto;
            background: var(--color-white);
            padding: 2.5rem;
            border-radius: 1rem;
            text-align: center;
        }

        .container h1 {
            font-family: 'Poppins1', sans-serif;
            font-size: clamp(2rem, 8vw, 3.5rem);
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 1.5rem;
        }

        .container .message {
            font-size: clamp(1.2rem, 5vw, 2rem);
            line-height: 1.6;
            color: var(--color-dark);
            margin-bottom: 1.5rem;
        }

        .container .message .bold {
            font-weight: 700;
        }

        .container .message .small {
            font-size: clamp(0.9rem, 4vw, 1.2rem);
            display: block;
            margin-top: 0.5rem;
        }

        .container .btn-group {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
        }

        .container a {
            display: inline-block;
            padding: 0.8rem 2rem;
            background: var(--color-primary);
            color: var(--color-white);
            text-decoration: none;
            border-radius: var(--border-radius-btn);
            font-size: clamp(0.9rem, 4vw, 1.2rem);
            font-weight: 500;
            box-shadow: var(--box-shadow);
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .container a:hover {
            background: var(--hover-primary);
            transform: translateY(-2px);
        }

        .container a.secondary-btn {
            background: var(--color-white);
            border: 2px solid var(--color-secondary);
            color: var(--color-secondary);
        }

        .container a.secondary-btn:hover {
            background: var(--color-light);
            border-color: var(--hover-secondary);
            transform: translateY(-2px);
        }

        /* Media Queries para diferentes tamaños de pantalla */
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                margin: 1rem;
            }

            .container h1 {
                font-size: clamp(1.8rem, 7vw, 2.5rem);
            }

            .container .message {
                font-size: clamp(1rem, 4.5vw, 1.5rem);
            }

            .container .message .small {
                font-size: clamp(0.8rem, 3.5vw, 1rem);
            }

            .container a {
                padding: 0.7rem 1.5rem;
                font-size: clamp(0.85rem, 3.5vw, 1rem);
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
                margin: 0.5rem;
            }

            .container h1 {
                font-size: clamp(1.5rem, 6vw, 2rem);
            }

            .container .message {
                font-size: clamp(0.9rem, 4vw, 1.2rem);
            }

            .container .message .small {
                font-size: clamp(0.7rem, 3vw, 0.9rem);
            }

            .container a {
                padding: 0.6rem 1.2rem;
                font-size: clamp(0.8rem, 3vw, 0.95rem);
                width: 100%;
                text-align: center;
            }

            .container .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>¡Vaya!</h1>
        <div class="message">
            <span class="bold">No tienes ningún negocio con el plan Premium.</span>
            <span class="small">Pásate a Premium para disfrutar de esta herramienta y muchas funciones más.</span>
        </div>
        <div class="btn-group">
            <a href="https://buscounservicio.es/panel/perfil">Pasar a Premium</a>
            <a href="https://buscounservicio.es/panel/mis-ubicaciones" class="secondary-btn">Volver al Panel</a>
        </div>
    </div>
</body>
</html>