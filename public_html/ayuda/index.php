<?php
ini_set('display_errors', 0);
error_reporting(0);
session_start();
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

require_once __DIR__ . '/../../db-publica.php';

function obtenerCategorias($pdo2) {
    try {
        $sql = "SELECT DISTINCT categoria FROM preguntas_frecuentes ORDER BY categoria";
        $stmt = $pdo2->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        return [];
    }
}

$categorias = obtenerCategorias($pdo2);

$iconosCategorias = [
    'Reservas' => '📅',
    'Tienda' => '🏪',
    'Finanzas' => '💰',
    'Clientes' => '👥',
    'Añadir negocio' => '➕',
    'Destacado de negocio' => '⭐',
    'Cupones' => '🎫',
    'Cartera' => '💳',
    'Planes' => '📋'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';">
    <title>Preguntas Frecuentes - Centro de Ayuda para negocios</title>
    <link rel="stylesheet" href="../assets/css/marca.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--color-title);
        }

        .faq-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .faq-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .faq-title {
            font-family: 'Poppins1', sans-serif;
            font-size: 2.5rem;
            color: var(--color-primary);
            margin-bottom: 15px;
        }

        .faq-subtitle {
            font-size: 1.2rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-box {
            max-width: 500px;
            margin: 30px auto;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: var(--border-radius-field);
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(2, 77, 223, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.2rem;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .category-card {
            background: var(--color-white);
            border-radius: 15px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: rgba(0, 0, 0, 0.15) 0px 25px 35px -5px, rgba(0, 0, 0, 0.08) 0px 15px 15px -5px;
        }

        .category-header {
            background: white;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .category-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .category-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .category-icon {
            font-size: 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px;
            border-radius: 50%;
        }

        .category-name {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .category-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
            display: none;
        }

        .no-results.show {
            display: block;
        }

        @media (max-width: 768px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .faq-title {
                font-size: 2rem;
            }
            
            .category-name {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="faq-container">
        <div class="faq-header">
            <h1 class="faq-title">Centro de Ayuda</h1>
            <p class="faq-subtitle">Encuentra respuestas rápidas a las preguntas más frecuentes</p>
        </div>
        
        <div class="search-box">
            <input type="text" class="search-input" id="searchInput" placeholder="Buscar en preguntas frecuentes...">
            <span class="search-icon">🔍</span>
        </div>

        <div class="categories-grid" id="categoriesGrid">
            <?php foreach ($categorias as $categoria): ?>
            <div class="category-card" data-category="<?php echo strtolower(htmlspecialchars($categoria)); ?>">
                <div class="category-header">
                    <a href="categoria?cat=<?php echo urlencode(htmlspecialchars($categoria)); ?>" class="category-link">
                        <div class="category-info">
                            <span class="category-icon"><?php echo htmlspecialchars($iconosCategorias[$categoria] ?? '📝'); ?></span>
                            <span class="category-name"><?php echo htmlspecialchars($categoria); ?></span>
                        </div>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="no-results" id="noResults">
            <h3>No se encontraron resultados</h3>
            <p>Intenta con otros términos de búsqueda o contacta con nuestro soporte</p>
        </div>
    </div>

    <script nonce="<?php echo bin2hex(random_bytes(16)); ?>">
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const categories = document.querySelectorAll('.category-card');
            const noResults = document.getElementById('noResults');
            let hasResults = false;

            if (searchTerm === '') {
                categories.forEach(category => {
                    category.style.display = 'block';
                });
                noResults.classList.remove('show');
                return;
            }

            categories.forEach(category => {
                const categoryName = category.getAttribute('data-category');
                
                if (categoryName.includes(searchTerm)) {
                    category.style.display = 'block';
                    hasResults = true;
                } else {
                    category.style.display = 'none';
                }
            });

            if (hasResults) {
                noResults.classList.remove('show');
            } else {
                noResults.classList.add('show');
            }
        });
    </script>
</body>
</html>