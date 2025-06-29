<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/marca.css">
</head>
<main>
    <h1 class="error-code">404</h1>
    <strong class="error-message">Página no encontrada</strong>
    
    <div class="browser-container">
        <div class="browser-bar">
            <div class="browser-actions">
                <span class="browser-dot red"></span>
                <span class="browser-dot yellow"></span>
                <span class="browser-dot green"></span>
            </div>
            <div class="url-bar">
                <div class="url-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                    </svg>
                </div>
                <div class="url-text-container">
                    <span class="url-protocol">https://</span>
                    <span class="url-domain">buscounservicio.es</span>
                    <span class="url-path" id="entered-url">/idv</span>
                </div>
                <div class="url-actions">
                    <svg viewBox="0 0 24 24" width="16" height="16">
                        <path fill="currentColor" d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                    </svg>
                </div>
            </div>
            <div class="browser-menu">
                <svg viewBox="0 0 24 24" width="18" height="18">
                    <path fill="currentColor" d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                </svg>
            </div>
        </div>
    </div>
    
    <p class="url-check">¿Seguro que es correcta la URL?</p>
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
    padding: 40px;
    padding-bottom: 80px;
}
.error-code {
    font-size: 9rem;
    color: #2755d3;
    margin-bottom: 0;
    text-shadow: 3px 3px 0px rgba(39, 85, 211, 0.2);
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.03); }
    100% { transform: scale(1); }
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
.url-check {
    font-family: 'Poppins', Arial, sans-serif;
    font-size: 1.4rem;
    font-weight: 500;
    margin: 15px 0;
    color: #555;
}

/* Browser Container Styles */
.browser-container {
    margin: 30px 0;
    position: relative;
    width: 90%;
    max-width: 900px;
    perspective: 1000px;
    opacity: 1;
}

.browser-bar {
    height: 50px;
    background: #f0f0f0;
    border-radius: 10px;
    display: flex;
    align-items: center;
    padding: 0 10px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    position: relative;
    z-index: 2;
    border: 1px solid #e0e0e0;
}

.browser-actions {
    display: flex;
    gap: 6px;
    margin-right: 15px;
}

.browser-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.browser-dot.red {
    background-color: #ff5f56;
}

.browser-dot.yellow {
    background-color: #ffbd2e;
}

.browser-dot.green {
    background-color: #27c93f;
}

.url-bar {
    flex: 1;
    height: 30px;
    background: white;
    border-radius: 15px;
    display: flex;
    align-items: center;
    padding: 0 10px;
    color: #333;
    border: 1px solid #e0e0e0;
    position: relative;
    overflow: hidden;
}

.url-icon {
    margin-right: 8px;
    display: flex;
    align-items: center;
    color: #888;
}

.url-text-container {
    flex: 1;
    font-size: 14px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
}

.url-protocol {
    color: #888;
}

.url-domain {
    color: #333;
    font-weight: 500;
}

.url-path {
    color: #ff0000;
    font-weight: 700;
    position: relative;
    margin-left: 0;
    display: inline-block;
    transform-origin: left;
}

.url-actions {
    margin-left: 8px;
    color: #888;
}

.browser-menu {
    margin-left: 15px;
    color: #888;
}

.btn-home {
    display: inline-block;
    padding: 12px 25px;
    margin-top: 25px;
    margin-bottom: 30px;
    background: linear-gradient(135deg, #3366ff, #2755d3);
    color: white;
    text-decoration: none;
    border-radius: 25px;
    font-size: 1.1rem;
    transition: all 0.3s;
    box-shadow: 0 4px 8px rgba(39, 85, 211, 0.3);
}

.btn-home:hover {
    background: linear-gradient(135deg, #2755d3, #1f47a4);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(39, 85, 211, 0.4);
}

.btn-home:active {
    transform: translateY(1px);
    box-shadow: 0 2px 4px rgba(39, 85, 211, 0.3);
}

@media (max-width: 768px) {
    main {
        padding: 20px;
    }
    .error-code {
        font-size: 6rem;
    }
    .error-message {
        font-size: 1.5rem;
    }
    .error-description {
        font-size: 1.2rem;
    }
    .url-check {
        font-size: 1.1rem;
    }
    .browser-bar {
        height: 40px;
    }
    .url-bar {
        height: 26px;
    }
    .url-text-container {
        font-size: 12px;
    }
    .browser-dot {
        width: 10px;
        height: 10px;
    }
}
</style>
<script>
    // Mostrar solo la ruta de la URL en la barra de navegación
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('entered-url').textContent = window.location.pathname;
    });
</script>