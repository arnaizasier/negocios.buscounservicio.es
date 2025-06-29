<?php
session_start();

require_once '../../vendor/autoload.php';
require_once '/home/u898735099/domains/buscounservicio.es/config.php';
require_once '/home/u898735099/domains/buscounservicio.es/db-publica.php';

// Obtener el slug del negocio
$negocio_slug = isset($_GET['negocio']) ? trim($_GET['negocio']) : '';

// Verificar que tengamos un valor válido
if (empty($negocio_slug)) {
    header('Location: /');
    exit;
}

// Consultar información del negocio
$stmt = $pdo2->prepare("
    SELECT * FROM negocios 
    WHERE url LIKE ?
");
$stmt->execute(['%' . $negocio_slug . '%']);
$negocio = $stmt->fetch();

// Si no existe el negocio, redirigir a la página principal
if (!$negocio) {
    header('Location: /');
    exit;
}

// Consultar todos los productos del negocio
$stmt = $pdo2->prepare("
    SELECT * FROM productos 
    WHERE negocio_id = ? AND visible = 1 AND unidades > 0
    ORDER BY nombre ASC
");
$stmt->execute([$negocio['negocio_id']]);
$productos = $stmt->fetchAll();

// Generar el título de la página
$titulo_pagina = 'Tienda de ' . htmlspecialchars($negocio['nombre']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?></title>
    <meta name="description" content="Explora todos los productos de <?php echo htmlspecialchars($negocio['nombre']); ?>">
    
    <!-- Font Awesome para íconos -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Estilos del sitio -->
    <link href="/assets/css/styles.css" rel="stylesheet">
    
    <style>
        /* Estilos específicos para la página de tienda */
        .tienda-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .tienda-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .tienda-titulo {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .tienda-descripcion {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .productos-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        
        .producto-card {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .producto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .producto-card-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .producto-card-img.sin-imagen {
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #aaa;
        }
        
        .producto-card-img.sin-imagen i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        
        .producto-card-body {
            padding: 1rem;
        }
        
        .producto-card-title {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .producto-card-price {
            font-weight: bold;
            color: #024ddf;
            margin-bottom: 0;
        }
        
        .sin-productos {
            text-align: center;
            padding: 3rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            color: #666;
        }
        
        .sin-productos i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .productos-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .productos-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tienda-titulo {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .productos-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/header.php'; ?>
    
    <main>
        <div class="tienda-container">
            <div class="tienda-header">
                <h1 class="tienda-titulo">Tienda de <?php echo htmlspecialchars($negocio['nombre']); ?></h1>
                <?php if (!empty($negocio['descripcion'])): ?>
                    <p class="tienda-descripcion"><?php echo nl2br(htmlspecialchars($negocio['descripcion'])); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($productos)): ?>
                <div class="productos-grid">
                    <?php foreach ($productos as $producto): ?>
                        <?php 
                            // Obtener la primera imagen del producto
                            $imagen = '';
                            if (!empty($producto['url_imagenes'])) {
                                $imagenes = explode(',', $producto['url_imagenes']);
                                $imagen = $imagenes[0];
                            }
                            
                            // Formatear el precio
                            $precio_formateado = number_format($producto['precio'], 2, ',', '.');
                            
                            // Extraer el slug del producto para la URL
                            $prod_url_parts = explode('/', $producto['url_producto']);
                            $prod_slug = end($prod_url_parts);
                        ?>
                        <a href="/<?php echo htmlspecialchars($negocio_slug); ?>/producto/<?php echo htmlspecialchars($prod_slug); ?>" class="producto-card">
                            <?php if (!empty($imagen)): ?>
                                <img src="/<?php echo htmlspecialchars($imagen); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" class="producto-card-img">
                            <?php else: ?>
                                <div class="producto-card-img sin-imagen">
                                    <i class="fas fa-image"></i>
                                    <p>Sin imagen</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="producto-card-body">
                                <h3 class="producto-card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                                <p class="producto-card-price"><?php echo $precio_formateado; ?> €</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sin-productos">
                    <i class="fas fa-shopping-basket"></i>
                    <h2>No hay productos disponibles</h2>
                    <p>Este negocio aún no tiene productos en su tienda o todos están agotados.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/footer.php'; ?>
    
    <!-- Script para alternar el menú en móviles -->
    <script>
        function toggleMenu() {
            const nav = document.getElementById('busMainNav');
            const overlay = document.getElementById('busMenuOverlay');
            
            nav.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Bloquear scroll cuando el menú está abierto
            document.body.classList.toggle('menu-open');
        }
    </script>
</body>
</html> 