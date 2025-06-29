<?php
ini_set('display_errors', 0);
error_reporting(0);

session_start();
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

require_once __DIR__ . '/../../db-publica.php';


$categoria = isset($_GET['cat']) ? filter_var(urldecode($_GET['cat']), FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';

function obtenerPreguntasDeCategoria($pdo2, $categoria) {
    try {
        $sql = "SELECT pregunta, respuesta FROM preguntas_frecuentes WHERE categoria = :categoria ORDER BY id";
        $stmt = $pdo2->prepare($sql);
        $stmt->execute(['categoria' => $categoria]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        return [];
    }
}

function categoriaValida($pdo2, $categoria) {
    try {
        $sql = "SELECT COUNT(*) FROM preguntas_frecuentes WHERE categoria = :categoria";
        $stmt = $pdo2->prepare($sql);
        $stmt->execute(['categoria' => $categoria]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        return false;
    }
}

$preguntas = [];
if ($categoria && categoriaValida($pdo2, $categoria)) {
    $preguntas = obtenerPreguntasDeCategoria($pdo2, $categoria);
} else {
    $categoria = '';
}

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
    <title><?php echo htmlspecialchars($categoria ?: 'Preguntas Frecuentes'); ?> - Preguntas Frecuentes</title>
    <link rel="stylesheet" href="../assets/css/marca.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-white);
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

        .category-header {
            background: var(--color-white);
            padding: 20px;
            border-radius: 15px;
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
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

        .faq-item {
            background: var(--color-white);
            border-radius: 15px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .faq-question {
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
        }

        .faq-question:hover {
            background-color: #f8f9fa;
        }

        .question-text {
            font-weight: 500;
            color: var(--color-title);
            flex: 1;
            margin-right: 15px;
        }

        .question-icon {
            color: var(--color-primary);
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background-color: #f8f9fa;
        }

        .faq-answer.active {
            max-height: 500px;
        }

        .answer-content {
            padding: 20px;
            color: #666;
            line-height: 1.7;
        }

        .no-questions {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
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
            <a href="/ayuda/" class="back-link">← Volver al Centro de Ayuda</a>
            <h1 class="faq-title"><?php echo htmlspecialchars($categoria ?: 'Preguntas Frecuentes'); ?></h1>
            <p class="faq-subtitle">Preguntas frecuentes sobre <?php echo htmlspecialchars($categoria ?: 'todas las categorías'); ?></p>
        </div>

        <div class="category-header">
            <div class="category-info">
                <span class="category-icon"><?php echo htmlspecialchars($iconosCategorias[$categoria] ?? '📝'); ?></span>
                <span class="category-name"><?php echo htmlspecialchars($categoria ?: 'Sin categoría'); ?></span>
            </div>
        </div>

        <?php if (empty($preguntas)): ?>
            <div class="no-questions">
                <h3>No se encontraron preguntas</h3>
                <p>No hay preguntas frecuentes disponibles para esta categoría.</p>
            </div>
        <?php else: ?>
            <?php foreach ($preguntas as $index => $faq): ?>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ('<?php echo htmlspecialchars($categoria . '-' . $index); ?>')">
                    <span class="question-text"><?php echo htmlspecialchars($faq['pregunta']); ?></span>
                    <span class="question-icon" id="icon-<?php echo htmlspecialchars($categoria . '-' . $index); ?>">+</span>
                </div>
                <div class="faq-answer" id="answer-<?php echo htmlspecialchars($categoria . '-' . $index); ?>">
                    <div class="answer-content">
                        <?php echo nl2br(htmlspecialchars($faq['respuesta'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script nonce="<?php echo bin2hex(random_bytes(16)); ?>">
        function toggleFAQ(id) {
            const answer = document.getElementById('answer-' + id);
            const icon = document.getElementById('icon-' + id);
            
            if (answer.classList.contains('active')) {
                answer.classList.remove('active');
                icon.textContent = '+';
            } else {
                answer.classList.add('active');
                icon.textContent = '−';
            }
        }
    </script>
</body>
</html>