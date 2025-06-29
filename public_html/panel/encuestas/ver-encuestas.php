<?php
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' cdnjs.cloudflare.com; font-src \'self\' cdnjs.cloudflare.com; img-src \'self\' data:;');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
    
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 3600) {
        session_destroy();
        session_start();
        $_SESSION['created'] = time();
    }
    
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    } elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_destroy();
        session_start();
        header('Location: ../../auth/login.php');
        exit;
    }
}

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";
require_once __DIR__ . "/../../src/obtener-negocios-premium-usuario.php";

// Manejar peticiones AJAX antes de generar HTML
if (isset($_GET['ajax']) && isset($_GET['detalle_id'])) {
    $detalle_id = validateInput($_GET['detalle_id'], 'int');
    
    if ($detalle_id) {
        try {
            $negocios = array_column($negocios_usuario, 'negocio_id');
            $negocios_nombres = array_column($negocios_usuario, 'nombre', 'negocio_id');
            
            $stmt = $pdo2->prepare("SELECT negocio_id, respuestas, fecha_respuesta FROM respuestas_encuestas WHERE id = ? AND negocio_id IN (" . implode(',', array_fill(0, count($negocios), '?')) . ")");
            $params = array_merge([$detalle_id], $negocios);
            $stmt->execute($params);
            $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($detalle) {
                echo "<div class='detalle-encuesta'>";
                echo "<h3><i class='fas fa-building'></i> " . htmlspecialchars($negocios_nombres[$detalle['negocio_id']] ?? 'Negocio no encontrado') . "</h3>";
                echo "<p class='fecha'><i class='fas fa-calendar'></i> " . date('d/m/Y', strtotime($detalle['fecha_respuesta'])) . "</p>";
                echo "<hr>";
                echo formatearRespuestas($detalle['respuestas']);
                echo "</div>";
            } else {
                echo "<div style='text-align: center; color: var(--color-red); padding: 20px;'>Encuesta no encontrada o sin permisos</div>";
            }
        } catch (PDOException $e) {
            error_log("Error al obtener detalle de encuesta: " . $e->getMessage());
            echo "<div style='text-align: center; color: var(--color-red); padding: 20px;'>Error al cargar los detalles</div>";
        }
    } else {
        echo "<div style='text-align: center; color: var(--color-red); padding: 20px;'>ID de encuesta inválido</div>";
    }
    
    exit;
}

function validateInput($input, $type, $maxLength = null) {
    if (is_null($input) || $input === '') {
        return $type === 'string' || $type === 'text' ? '' : false;
    }
    
    if (is_array($input)) {
        return false;
    }
    
    if (strlen($input) > 10000) {
        return false;
    }
    
    switch ($type) {
        case 'int':
            $value = filter_var($input, FILTER_VALIDATE_INT);
            return ($value !== false && $value >= 0) ? $value : false;
        case 'string':
        case 'text':
            $sanitized = trim($input);
            $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
            
            if (preg_match('/<script|javascript:|on\w+\s*=|eval\(|document\.|window\./i', $sanitized)) {
                return false;
            }
            
            if ($maxLength && mb_strlen($sanitized, 'UTF-8') > $maxLength) {
                return false;
            }
            return $sanitized;
        case 'date':
            $date = DateTime::createFromFormat('Y-m-d', $input);
            return ($date && $date->format('Y-m-d') === $input) ? $input : false;
        default:
            return false;
    }
}

function detectSuspiciousActivity($data) {
    $suspicious_patterns = [
        '/union.*select/i',
        '/drop.*table/i',
        '/insert.*into/i',
        '/update.*set/i',
        '/delete.*from/i',
        '/<script/i',
        '/javascript:/i',
        '/eval\(/i',
        '/base64_decode/i',
        '/exec\(/i',
        '/system\(/i'
    ];
    
    $data_string = is_array($data) ? json_encode($data) : (string)$data;
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $data_string)) {
            return true;
        }
    }
    
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (detectSuspiciousActivity($request_data)) {
        http_response_code(400);
        die('Solicitud rechazada por razones de seguridad');
    }
}

$negocios = array_column($negocios_usuario, 'negocio_id');
$negocios_nombres = array_column($negocios_usuario, 'nombre', 'negocio_id');

$filtro_negocio = validateInput($_GET['negocio'] ?? '', 'int');
$filtro_fecha_desde = validateInput($_GET['fecha_desde'] ?? '', 'date');
$filtro_fecha_hasta = validateInput($_GET['fecha_hasta'] ?? '', 'date');
$buscar = validateInput($_GET['buscar'] ?? '', 'string', 100);

$page = validateInput($_GET['page'] ?? 1, 'int');
$page = $page ?: 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = ["negocio_id IN (" . implode(',', array_fill(0, count($negocios), '?')) . ")"];
$params = $negocios;

if ($filtro_negocio && in_array($filtro_negocio, $negocios)) {
    $where_conditions = ["negocio_id = ?"];
    $params = [$filtro_negocio];
}

if ($filtro_fecha_desde) {
    $where_conditions[] = "DATE(fecha_respuesta) >= ?";
    $params[] = $filtro_fecha_desde;
}

if ($filtro_fecha_hasta) {
    $where_conditions[] = "DATE(fecha_respuesta) <= ?";
    $params[] = $filtro_fecha_hasta;
}

if ($buscar) {
    $where_conditions[] = "respuestas LIKE ?";
    $params[] = '%' . $buscar . '%';
}

$where_clause = "WHERE " . implode(' AND ', $where_conditions);

try {
    $count_sql = "SELECT COUNT(*) FROM respuestas_encuestas $where_clause";
    $count_stmt = $pdo2->prepare($count_sql);
    $count_stmt->execute($params);
    $total_encuestas = $count_stmt->fetchColumn();
    
    $total_pages = ceil($total_encuestas / $per_page);
    
    $sql = "SELECT id, negocio_id, respuestas, fecha_respuesta
            FROM respuestas_encuestas 
            $where_clause 
            ORDER BY fecha_respuesta DESC 
            LIMIT $per_page OFFSET $offset";
    
    $stmt = $pdo2->prepare($sql);
    $stmt->execute($params);
    $encuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats_sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN DATE(fecha_respuesta) = CURDATE() THEN 1 END) as hoy,
                    COUNT(CASE WHEN fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as semana,
                    COUNT(CASE WHEN fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as mes
                  FROM respuestas_encuestas 
                  WHERE negocio_id IN (" . implode(',', array_fill(0, count($negocios), '?')) . ")";
    
    $stats_stmt = $pdo2->prepare($stats_sql);
    $stats_stmt->execute($negocios);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error en ver-encuestas.php: " . $e->getMessage());
    $encuestas = [];
    $total_encuestas = 0;
    $total_pages = 0;
    $stats = ['total' => 0, 'hoy' => 0, 'semana' => 0, 'mes' => 0];
}

function formatearRespuestas($respuestas_json, $preview = false) {
    $respuestas = json_decode($respuestas_json, true);
    if (!$respuestas || !is_array($respuestas)) {
        return $preview ? 'Sin respuestas válidas' : '<p>Sin respuestas válidas</p>';
    }
    
    if ($preview) {
        $preview_text = '';
        foreach (array_slice($respuestas, 0, 2) as $respuesta) {
            if (isset($respuesta['pregunta'], $respuesta['respuesta'])) {
                $preview_text .= htmlspecialchars($respuesta['pregunta']) . ': ' . htmlspecialchars($respuesta['respuesta']) . ' | ';
            }
        }
        return rtrim($preview_text, ' | ') . (count($respuestas) > 2 ? '...' : '');
    }
    
    $html = '';
    foreach ($respuestas as $respuesta) {
        if (isset($respuesta['pregunta'], $respuesta['respuesta'])) {
            $pregunta = htmlspecialchars($respuesta['pregunta']);
            $respuesta_text = htmlspecialchars($respuesta['respuesta']);
            
            $html .= "<div class='respuesta-item'>";
            $html .= "<div class='pregunta'>{$pregunta}</div>";
            $html .= "<div class='respuesta'>{$respuesta_text}</div>";
            $html .= "</div>";
        }
    }
    
    return $html ?: '<p>Sin respuestas válidas</p>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Encuestas - Panel de Control</title>
    <link rel="stylesheet" href="css/ver-encuestas.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../../assets/includes/sidebar.php'; ?>
    
    <div class="content45">
        <div class="container">
            <div class="header">
                <h1><i></i> Respuestas de Encuestas</h1>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Encuestas</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-number"><?php echo number_format($stats['hoy']); ?></div>
                    <div class="stat-label">Hoy</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-number"><?php echo number_format($stats['semana']); ?></div>
                    <div class="stat-label">Esta Semana</div>
                </div>
                <div class="stat-card red">
                    <div class="stat-number"><?php echo number_format($stats['mes']); ?></div>
                    <div class="stat-label">Este Mes</div>
                </div>
            </div>

            <div class="filters">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="negocio">Negocio:</label>
                            <select name="negocio" id="negocio">
                                <option value="">Todos los negocios</option>
                                <?php foreach ($negocios_nombres as $id => $nombre): ?>
                                    <option value="<?php echo $id; ?>" <?php echo $filtro_negocio == $id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="fecha_desde">Desde:</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="fecha_hasta">Hasta:</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="buscar">Buscar en respuestas:</label>
                            <input type="text" name="buscar" id="buscar" placeholder="Buscar..." value="<?php echo htmlspecialchars($buscar); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="encuestas-table">
                
                <?php if (empty($encuestas)): ?>
                    <div class="no-data">
                        <i class="fas fa-inbox"></i>
                        <h3>No hay encuestas disponibles</h3>
                        <p>No se encontraron respuestas de encuestas con los filtros aplicados.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Negocio</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($encuestas as $encuesta): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($encuesta['id']); ?></td>
                                        <td class="negocio-name">
                                            <?php echo htmlspecialchars($negocios_nombres[$encuesta['negocio_id']] ?? 'Negocio no encontrado'); ?>
                                        </td>
                                        <td class="fecha">
                                            <?php echo date('d/m/Y', strtotime($encuesta['fecha_respuesta'])); ?>
                                        </td>
                                        <td>
                                            <button class="btn-ver-detalle" onclick="verDetalle(<?php echo $encuesta['id']; ?>)">
                                                <i class="fas fa-eye"></i> Ver Detalle
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="modalDetalle" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-clipboard-list"></i> Detalle de Encuesta</h2>
                <span class="close">&times;</span>
            </div>
            <div id="modalBody">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin"></i> Cargando...
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/sidebar.js"></script>
    <script>
        const modal = document.getElementById('modalDetalle');
        const closeBtn = document.querySelector('.close');
        const modalBody = document.getElementById('modalBody');

        function verDetalle(id) {
            modal.style.display = 'block';
            modalBody.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';
            
            fetch(`?ajax=1&detalle_id=${id}`)
                .then(response => response.text())
                .then(data => {
                    modalBody.innerHTML = data;
                })
                .catch(error => {
                    modalBody.innerHTML = '<div style="text-align: center; color: var(--color-red); padding: 20px;">Error al cargar los detalles</div>';
                });
        }

        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
