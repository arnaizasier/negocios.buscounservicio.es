<?php
require_once __DIR__ . "/../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../src/rate-limiting.php";
require_once __DIR__ . "/../../src/headers-seguridad.php";

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";
require_once __DIR__ . "/../../../db-crm.php";


use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../src/verificar-logeado.php";
require_once __DIR__ . "/../../src/verificar-rol-negocio.php";

if (!function_exists('obtenerConexionPublica')) {
    function obtenerConexionPublica() {
        global $pdo2;
        
        return $pdo2;
    }
}

function verificarSesion() {
    global $auth;
    
    if (!$auth->isLoggedIn()) {
        header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    
    return $auth->getUserId();
}

$cache_negocios = [];

function obtenerNegociosUsuario($usuario_id) {
    global $cache_negocios;
    
    $cache_key = "negocios_usuario_$usuario_id";
    if (isset($cache_negocios[$cache_key])) {
        return $cache_negocios[$cache_key];
    }
    
    $pdo2 = obtenerConexionPublica();
    $stmt = $pdo2->prepare("
        SELECT negocio_id, nombre 
        FROM negocios 
        WHERE usuario_id = :usuario_id AND plan = 'Premium'
    ");
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cache_negocios[$cache_key] = $resultado;
    
    return $resultado;
}

require_once __DIR__ . "/../../src/obtener-negocios-premium-usuario.php";

if (!function_exists('obtenerConexionCRM')) {
    function obtenerConexionCRM() {
        global $pdo6;
        
        return $pdo6;
    }
}

function validarYObtenerNegocio($negocio_id, $usuario_id) {
    global $cache_negocios;
    
    $cache_key = "negocio_{$negocio_id}_{$usuario_id}";
    if (isset($cache_negocios[$cache_key])) {
        return $cache_negocios[$cache_key];
    }
    
    $pdo = obtenerConexionPublica();
    $stmt = $pdo->prepare("
        SELECT negocio_id, nombre, plan
        FROM negocios 
        WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id
        LIMIT 1
    ");
    $stmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $cache_negocios[$cache_key] = $resultado;
    
    return $resultado;
}

function validarNegocioUsuario($negocio_id, $usuario_id) {
    $negocio = validarYObtenerNegocio($negocio_id, $usuario_id);
    return $negocio !== false;
}

function obtenerNombreNegocio($negocio_id, $usuario_id = null) {
    global $cache_negocios;
    
    if ($usuario_id !== null) {
        $negocio = validarYObtenerNegocio($negocio_id, $usuario_id);
        return $negocio ? $negocio['nombre'] : '';
    }
    
    $cache_key = "nombre_negocio_$negocio_id";
    if (isset($cache_negocios[$cache_key])) {
        return $cache_negocios[$cache_key];
    }
    
    $pdo = obtenerConexionPublica();
    $stmt = $pdo->prepare("
        SELECT nombre 
        FROM negocios 
        WHERE negocio_id = :negocio_id
    ");
    $stmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $nombre = $resultado ? $resultado['nombre'] : '';
    
    $cache_negocios[$cache_key] = $nombre;
    
    return $nombre;
}

function limpiarCacheNegocios() {
    global $cache_negocios;
    $cache_negocios = [];
}

function agregarRegistroFinanciero($datos) {
    $pdo = obtenerConexionCRM();
    
    $campos_requeridos = ['negocio_id', 'usuario_id', 'tipo', 'cantidad', 'iva_porcentaje', 'categoria', 'fecha'];
    foreach ($campos_requeridos as $campo) {
        if (empty($datos[$campo])) {
            if ($campo == 'fecha') {
                $datos['fecha'] = date('Y-m-d');
            } else {
                throw new Exception("El campo $campo es obligatorio");
            }
        }
    }
    
    $negocio_id = filter_var($datos['negocio_id'], FILTER_SANITIZE_NUMBER_INT);
    $usuario_id = filter_var($datos['usuario_id'], FILTER_SANITIZE_NUMBER_INT);
    $tipo = in_array($datos['tipo'], ['Gasto', 'Beneficio']) ? $datos['tipo'] : 'Gasto';
    $cantidad = filter_var($datos['cantidad'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $iva_porcentaje = in_array($datos['iva_porcentaje'], [0, 4, 10, 21]) ? $datos['iva_porcentaje'] : 21;
    $categoria = htmlspecialchars($datos['categoria']);
    $descripcion = isset($datos['descripcion']) ? substr(htmlspecialchars($datos['descripcion']), 0, 50) : null;
    $fecha = $datos['fecha'];
    
    $stmt = $pdo->prepare("
        INSERT INTO finanzas 
        (negocio_id, usuario_id, tipo, cantidad, iva_porcentaje, categoria, descripcion, fecha) 
        VALUES 
        (:negocio_id, :usuario_id, :tipo, :cantidad, :iva_porcentaje, :categoria, :descripcion, :fecha)
    ");
    
    $stmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->bindParam(':cantidad', $cantidad);
    $stmt->bindParam(':iva_porcentaje', $iva_porcentaje, PDO::PARAM_INT);
    $stmt->bindParam(':categoria', $categoria, PDO::PARAM_STR);
    $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindParam(':fecha', $fecha);
    
    return $stmt->execute();
}

function obtenerRegistrosFinancieros($negocio_id, $usuario_id, $filtros = [], $pagina = 1, $por_pagina = 20) {
    $pdo = obtenerConexionCRM();
    
    $consulta = "
        SELECT * FROM finanzas 
        WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id
    ";
    
    $params = [
        ':negocio_id' => $negocio_id,
        ':usuario_id' => $usuario_id
    ];
    
    if (!empty($filtros['tipo']) && in_array($filtros['tipo'], ['Gasto', 'Beneficio'])) {
        $consulta .= " AND tipo = :tipo";
        $params[':tipo'] = $filtros['tipo'];
    }
    
    if (!empty($filtros['mes']) && !empty($filtros['año'])) {
        $consulta .= " AND MONTH(fecha) = :mes AND YEAR(fecha) = :anio";
        $params[':mes'] = $filtros['mes'];
        $params[':anio'] = $filtros['año'];
    }
    
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM ($consulta) as subquery");
    foreach ($params as $key => $value) {
        $stmtTotal->bindValue($key, $value);
    }
    $stmtTotal->execute();
    $total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
    
    $consulta .= " ORDER BY fecha DESC, id DESC";
    $consulta .= " LIMIT :offset, :limit";
    
    $offset = ($pagina - 1) * $por_pagina;
    $params[':offset'] = $offset;
    $params[':limit'] = $por_pagina;
    
    $stmt = $pdo->prepare($consulta);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $por_pagina, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        if ($key !== ':offset' && $key !== ':limit') {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
    }
    
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'registros' => $registros,
        'total' => $total,
        'paginas' => ceil($total / $por_pagina),
        'pagina_actual' => $pagina
    ];
}

function calcularBalance($negocio_id, $usuario_id, $periodo = 'total') {
    $pdo = obtenerConexionCRM();
    
    $consulta = "
        SELECT 
            SUM(CASE WHEN tipo = 'Beneficio' THEN cantidad ELSE 0 END) as beneficio_total,
            SUM(CASE WHEN tipo = 'Gasto' THEN cantidad ELSE 0 END) as gasto_total,
            SUM(CASE WHEN tipo = 'Beneficio' THEN cantidad_sin_iva ELSE 0 END) as beneficio_sin_iva,
            SUM(CASE WHEN tipo = 'Gasto' THEN cantidad_sin_iva ELSE 0 END) as gasto_sin_iva
        FROM finanzas 
        WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id
    ";
    
    $params = [
        ':negocio_id' => $negocio_id,
        ':usuario_id' => $usuario_id
    ];
    
    if ($periodo !== 'total') {
        switch ($periodo) {
            case 'mensual':
                $consulta .= " AND fecha >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
                break;
            case 'trimestral':
                $consulta .= " AND fecha >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)";
                break;
            case 'semestral':
                $consulta .= " AND fecha >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)";
                break;
            case 'anual':
                $consulta .= " AND fecha >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)";
                break;
        }
    }
    
    $stmt = $pdo->prepare($consulta);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $balance = [
        'beneficio_total' => $resultado['beneficio_total'] ?? 0,
        'gasto_total' => $resultado['gasto_total'] ?? 0,
        'beneficio_sin_iva' => $resultado['beneficio_sin_iva'] ?? 0,
        'gasto_sin_iva' => $resultado['gasto_sin_iva'] ?? 0,
        'balance_con_iva' => ($resultado['beneficio_total'] ?? 0) - ($resultado['gasto_total'] ?? 0),
        'balance_sin_iva' => ($resultado['beneficio_sin_iva'] ?? 0) - ($resultado['gasto_sin_iva'] ?? 0)
    ];
    
    return $balance;
}

function obtenerDatosGrafico($negocio_id, $usuario_id, $periodo = 'anual') {
    $pdo = obtenerConexionCRM();
    
    switch ($periodo) {
        case 'mensual':
            $intervalo = "DAY";
            $formato = "%Y-%m-%d";
            $dias = 30;
            break;
        case 'trimestral':
            $intervalo = "WEEK";
            $formato = "%Y-%U";
            $dias = 90;
            break;
        case 'semestral':
            $intervalo = "MONTH";
            $formato = "%Y-%m";
            $dias = 180;
            break;
        case 'anual':
        default:
            $intervalo = "MONTH";
            $formato = "%Y-%m";
            $dias = 365;
            break;
    }
    
    $consulta = "
        SELECT 
            DATE_FORMAT(fecha, '$formato') as periodo,
            SUM(CASE WHEN tipo = 'Beneficio' THEN cantidad ELSE 0 END) as beneficio,
            SUM(CASE WHEN tipo = 'Gasto' THEN cantidad ELSE 0 END) as gasto
        FROM finanzas 
        WHERE 
            negocio_id = :negocio_id AND 
            usuario_id = :usuario_id AND
            fecha >= DATE_SUB(CURRENT_DATE, INTERVAL $dias DAY)
        GROUP BY DATE_FORMAT(fecha, '$formato')
        ORDER BY fecha
    ";
    
    $stmt = $pdo->prepare($consulta);
    $stmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerCategorias() {
    return [
        'Gasto' => [
            'Gastos básicos',
            'Nóminas',
            'Impuestos',
            'Materiales y suministros',
            'Transporte y viajes',
            'Productos',
            'Mantenimiento y arreglos',
            'Publicidad y marketing',
            'Otros gastos'
        ],
        'Beneficio' => [
            'Servicio puntual',
            'Bono',
            'Suscripciones',
            'Venta de productos',
            'Reembolsos',
            'Otros ingresos'
        ]
    ];
}