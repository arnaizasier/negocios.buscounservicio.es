<?php
/**
 * Módulo de Finanzas
 * 
 * Permite a los negocios gestionar sus finanzas, añadir gastos e ingresos,
 * visualizar los registros y ver balance y gráficos financieros.
 */

// Incluir archivo de funciones
require_once 'functions.php';

// Verificar sesión del usuario
$usuario_id = verificarSesion();

// Procesar selección de negocio
$negocio_id = null;
$nombre_negocio = '';
$mensaje = '';
$tipo_mensaje = '';

// Si se envió el formulario de selección de negocio
if (isset($_POST['seleccionar_negocio'])) {
    $negocio_id = filter_input(INPUT_POST, 'negocio_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Validar que el negocio pertenezca al usuario
    if (validarNegocioUsuario($negocio_id, $usuario_id)) {
        $_SESSION['finanzas_negocio_id'] = $negocio_id;
        $nombre_negocio = obtenerNombreNegocio($negocio_id);
    } else {
        $mensaje = 'El negocio seleccionado no te pertenece.';
        $tipo_mensaje = 'error';
        $negocio_id = null;
    }
// Si ya hay un negocio seleccionado en la sesión
} elseif (isset($_SESSION['finanzas_negocio_id'])) {
    $negocio_id = $_SESSION['finanzas_negocio_id'];
    
    // Validar que el negocio guardado en sesión siga perteneciendo al usuario
    if (validarNegocioUsuario($negocio_id, $usuario_id)) {
        $nombre_negocio = obtenerNombreNegocio($negocio_id);
    } else {
        unset($_SESSION['finanzas_negocio_id']);
        $negocio_id = null;
        $mensaje = 'El negocio seleccionado ya no está disponible.';
        $tipo_mensaje = 'error';
    }
}

// Obtener lista de negocios del usuario
$negocios = obtenerNegociosUsuario($usuario_id);

// Si solo hay un negocio, seleccionarlo automáticamente
if (count($negocios) === 1 && !$negocio_id) {
    $negocio_id = $negocios[0]['negocio_id'];
    $_SESSION['finanzas_negocio_id'] = $negocio_id;
    $nombre_negocio = obtenerNombreNegocio($negocio_id);
}

// Si no hay un negocio seleccionado, seleccionar el primero de la lista
if (!$negocio_id && count($negocios) > 0) {
    $negocio_id = $negocios[0]['negocio_id'];
    $_SESSION['finanzas_negocio_id'] = $negocio_id;
    $nombre_negocio = obtenerNombreNegocio($negocio_id);
}

// Procesar formulario de añadir registro financiero
if (isset($_POST['agregar_registro']) && $negocio_id) {
    try {
        // Recopilar datos del formulario
        $datos = [
            'negocio_id' => $negocio_id,
            'usuario_id' => $usuario_id,
            'tipo' => $_POST['tipo'],
            'cantidad' => $_POST['cantidad'],
            'iva_porcentaje' => $_POST['iva_porcentaje'] ?? 21,
            'categoria' => $_POST['categoria'],
            'descripcion' => $_POST['descripcion'] ?? null,
            'fecha' => $_POST['fecha'] ?? date('Y-m-d')
        ];
        
        // Intentar agregar el registro
        if (agregarRegistroFinanciero($datos)) {
            $mensaje = 'Registro financiero añadido correctamente.';
            $tipo_mensaje = 'exito';
        } else {
            $mensaje = 'No se pudo añadir el registro.';
            $tipo_mensaje = 'error';
        }
    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Configuración para lista de registros
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$filtros = [
    'tipo' => $_GET['filtro_tipo'] ?? '',
    'mes' => $_GET['filtro_mes'] ?? '',
    'año' => $_GET['filtro_anio'] ?? ''
];

// Determinar la pestaña activa, por defecto "anadir" a menos que existan filtros aplicados
$tab_activa = 'anadir';
if (isset($_GET['tab'])) {
    $tab_activa = $_GET['tab'];
} elseif (isset($_GET['filtro_tipo']) || isset($_GET['filtro_mes']) || isset($_GET['filtro_anio'])) {
    $tab_activa = 'lista';
} elseif (isset($_GET['periodo_balance'])) {
    $tab_activa = 'balance';
}

// Obtener registros financieros si hay un negocio seleccionado
$datos_lista = [];
if ($negocio_id) {
    $datos_lista = obtenerRegistrosFinancieros($negocio_id, $usuario_id, $filtros, $pagina_actual);
}

// Configuración para balance y gráficos
$periodo_balance = $_GET['periodo_balance'] ?? 'total';
$balance = [];
$datos_grafico = [];

if ($negocio_id) {
    $balance = calcularBalance($negocio_id, $usuario_id, $periodo_balance);
    $datos_grafico = obtenerDatosGrafico($negocio_id, $usuario_id, $periodo_balance);
}

// Categorías disponibles
$categorias = obtenerCategorias();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanzas</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="styles.css">
    
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Contenedor principal con clases del primer código -->
    <div class="container45">
        <!-- Incluir sidebar -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar.php'; ?>
        
        <!-- Contenedor de contenido -->
        <div class="content45" id="content45">
            <div class="main-container">
                <h1 class="titulo-seccion">Gestión de Finanzas</h1>
                
                <?php if ($mensaje): ?>
                    <div class="alerta alerta-<?php echo $tipo_mensaje; ?>">
                        <?php echo $mensaje; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (count($negocios) > 1): ?>
                <!-- Selector de negocio (solo se muestra si hay más de un negocio) -->
                <div class="selector-negocio">
                    <h2>Selecciona un negocio</h2>
                    <form method="post" action="">
                        <div class="form-group">
                            <select name="negocio_id" id="negocio_id" required>
                                <option value="">Seleccionar negocio</option>
                                <?php foreach ($negocios as $negocio): ?>
                                    <option value="<?php echo htmlspecialchars($negocio['negocio_id']); ?>" 
                                        <?php echo ($negocio_id == $negocio['negocio_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($negocio['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="seleccionar_negocio">Seleccionar</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <?php if ($negocio_id): ?>
                    <!-- Tabs de navegación -->
                    <div class="tabs">
                        <div class="tab <?php echo ($tab_activa === 'anadir') ? 'active' : ''; ?>" data-tab="anadir">Añadir Datos</div>
                        <div class="tab <?php echo ($tab_activa === 'lista') ? 'active' : ''; ?>" data-tab="lista">Lista de Registros</div>
                        <div class="tab <?php echo ($tab_activa === 'balance') ? 'active' : ''; ?>" data-tab="balance">Balance y Gráficos</div>
                    </div>
                    
                    <!-- Sección: Añadir Datos -->
                    <div class="seccion <?php echo ($tab_activa === 'anadir') ? 'active' : ''; ?>" id="anadir">
                        <div class="panel">
                            <h3 class="subtitulo">Añadir Registro Financiero</h3>
                            
                            <form method="post" action="" class="formulario">
                                <div class="form-group">
                                    <label for="tipo">Tipo*:</label>
                                    <select name="tipo" id="tipo" required onchange="actualizarCategorias()">
                                        <option value="Beneficio">Beneficio</option>
                                        <option value="Gasto">Gasto</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cantidad">Cantidad (€)*:</label>
                                    <input type="number" name="cantidad" id="cantidad" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="iva_porcentaje">IVA (%):</label>
                                    <select name="iva_porcentaje" id="iva_porcentaje">
                                        <option value="21">21%</option>
                                        <option value="10">10%</option>
                                        <option value="4">4%</option>
                                        <option value="0">0%</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="categoria">Categoría:</label>
                                    <select name="categoria" id="categoria">
                                        <!-- Opciones se cargarán con JavaScript -->
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="descripcion">Descripción (máx. 50 caracteres):</label>
                                    <input type="text" name="descripcion" id="descripcion" maxlength="50">
                                </div>
                                
                                <div class="form-group">
                                    <label for="fecha">Fecha:</label>
                                    <input type="date" name="fecha" id="fecha" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <button type="submit" name="agregar_registro">Guardar Registro</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Sección: Lista de Registros -->
                    <div class="seccion <?php echo ($tab_activa === 'lista') ? 'active' : ''; ?>" id="lista">
                        <div class="panel">
                            <h3 class="subtitulo">Lista de Registros Financieros</h3>
                            
                            <!-- Filtros -->
                            <form method="get" action="" class="filtros">
                                <input type="hidden" name="pagina" value="1">
                                <input type="hidden" name="tab" value="lista">
                                
                                <div class="form-group">
                                    <label for="filtro_tipo">Tipo:</label>
                                    <select name="filtro_tipo" id="filtro_tipo">
                                        <option value="">Todos</option>
                                        <option value="Beneficio" <?php echo ($filtros['tipo'] === 'Beneficio') ? 'selected' : ''; ?>>Beneficios</option>
                                        <option value="Gasto" <?php echo ($filtros['tipo'] === 'Gasto') ? 'selected' : ''; ?>>Gastos</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="filtro_mes">Mes:</label>
                                    <select name="filtro_mes" id="filtro_mes">
                                        <option value="">Todos</option>
                                        <?php 
                                        $meses = [
                                            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
                                            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
                                            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                                        ];
                                        for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ($filtros['mes'] == $i) ? 'selected' : ''; ?>>
                                                <?php echo $meses[$i]; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="filtro_anio">Año:</label>
                                    <select name="filtro_anio" id="filtro_anio">
                                        <option value="">Todos</option>
                                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ($filtros['año'] == $i) ? 'selected' : ''; ?>>
                                                <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label> </label>
                                    <button type="submit">Filtrar</button>
                                </div>
                            </form>
                            
                            <!-- Tabla de registros -->
                            <div class="tabla-container">
                                <table class="tabla">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tipo</th>
                                            <th>Categoría</th>
                                            <th>Descripción</th>
                                            <th>Cantidad</th>
                                            <th>IVA</th>
                                            <th>Importe sin IVA</th>
                                            <th>Importe con IVA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($datos_lista['registros']) && !empty($datos_lista['registros'])): ?>
                                            <?php foreach ($datos_lista['registros'] as $registro): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($registro['fecha'])); ?></td>
                                                    <td class="<?php echo strtolower($registro['tipo']); ?>">
                                                        <?php echo $registro['tipo']; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($registro['categoria']); ?></td>
                                                    <td><?php echo htmlspecialchars($registro['descripcion'] ?? ''); ?></td>
                                                    <td><?php echo number_format($registro['cantidad'], 2, ',', '.'); ?> €</td>
                                                    <td><?php echo $registro['iva_porcentaje']; ?>%</td>
                                                    <td><?php echo number_format($registro['cantidad_sin_iva'], 2, ',', '.'); ?> €</td>
                                                    <td><?php echo number_format($registro['cantidad'], 2, ',', '.'); ?> €</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" style="text-align: center;">No hay registros disponibles.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginación -->
                            <?php if (isset($datos_lista['paginas']) && $datos_lista['paginas'] > 1): ?>
                                <div class="paginacion">
                                    <?php if ($pagina_actual > 1): ?>
                                        <a href="?pagina=1<?php echo isset($_GET['filtro_tipo']) ? '&filtro_tipo=' . $_GET['filtro_tipo'] : ''; ?><?php echo isset($_GET['filtro_mes']) ? '&filtro_mes=' . $_GET['filtro_mes'] : ''; ?><?php echo isset($_GET['filtro_anio']) ? '&filtro_anio=' . $_GET['filtro_anio'] : ''; ?>">
                                            «
                                        </a>
                                        <a href="?pagina=<?php echo $pagina_actual - 1; ?><?php echo isset($_GET['filtro_tipo']) ? '&filtro_tipo=' . $_GET['filtro_tipo'] : ''; ?><?php echo isset($_GET['filtro_mes']) ? '&filtro_mes=' . $_GET['filtro_mes'] : ''; ?><?php echo isset($_GET['filtro_anio']) ? '&filtro_anio=' . $_GET['filtro_anio'] : ''; ?>">
                                            ‹
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $pagina_actual - 2); $i <= min($datos_lista['paginas'], $pagina_actual + 2); $i++): ?>
                                        <a href="?pagina=<?php echo $i; ?><?php echo isset($_GET['filtro_tipo']) ? '&filtro_tipo=' . $_GET['filtro_tipo'] : ''; ?><?php echo isset($_GET['filtro_mes']) ? '&filtro_mes=' . $_GET['filtro_mes'] : ''; ?><?php echo isset($_GET['filtro_anio']) ? '&filtro_anio=' . $_GET['filtro_anio'] : ''; ?>" 
                                           class="<?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($pagina_actual < $datos_lista['paginas']): ?>
                                        <a href="?pagina=<?php echo $pagina_actual + 1; ?><?php echo isset($_GET['filtro_tipo']) ? '&filtro_tipo=' . $_GET['filtro_tipo'] : ''; ?><?php echo isset($_GET['filtro_mes']) ? '&filtro_mes=' . $_GET['filtro_mes'] : ''; ?><?php echo isset($_GET['filtro_anio']) ? '&filtro_anio=' . $_GET['filtro_anio'] : ''; ?>">
                                            ›
                                        </a>
                                        <a href="?pagina=<?php echo $datos_lista['paginas']; ?><?php echo isset($_GET['filtro_tipo']) ? '&filtro_tipo=' . $_GET['filtro_tipo'] : ''; ?><?php echo isset($_GET['filtro_mes']) ? '&filtro_mes=' . $_GET['filtro_mes'] : ''; ?><?php echo isset($_GET['filtro_anio']) ? '&filtro_anio=' . $_GET['filtro_anio'] : ''; ?>">
                                            »
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sección: Balance y Gráficos -->
                    <div class="seccion <?php echo ($tab_activa === 'balance') ? 'active' : ''; ?>" id="balance">
                        <div class="panel">
                            <h3 class="subtitulo">Balance Financiero</h3>
                            
                            <!-- Selector de periodo -->
                            <form method="get" action="" class="filtros">
                                <input type="hidden" name="tab" value="balance">
                                <div class="form-group">
                                    <label for="periodo_balance">Periodo:</label>
                                    <select name="periodo_balance" id="periodo_balance" onchange="this.form.submit()">
                                        <option value="total" <?php echo ($periodo_balance === 'total') ? 'selected' : ''; ?>>Total</option>
                                        <option value="mensual" <?php echo ($periodo_balance === 'mensual') ? 'selected' : ''; ?>>Último mes</option>
                                        <option value="trimestral" <?php echo ($periodo_balance === 'trimestral') ? 'selected' : ''; ?>>Trimestral</option>
                                        <option value="semestral" <?php echo ($periodo_balance === 'semestral') ? 'selected' : ''; ?>>Semestral</option>
                                        <option value="anual" <?php echo ($periodo_balance === 'anual') ? 'selected' : ''; ?>>Anual</option>
                                    </select>
                                </div>
                            </form>
                            
                            <!-- Tarjetas de balance -->
                            <div class="balance-cards">
                                <div class="card">
                                    <div class="card-title">Beneficios Totales</div>
                                    <div class="card-valor positivo">
                                        <?php echo number_format($balance['beneficio_total'] ?? 0, 2, ',', '.'); ?> €
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-title">Gastos Totales</div>
                                    <div class="card-valor negativo">
                                        <?php echo number_format($balance['gasto_total'] ?? 0, 2, ',', '.'); ?> €
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-title">Balance con IVA</div>
                                    <div class="card-valor <?php echo (($balance['balance_con_iva'] ?? 0) >= 0) ? 'positivo' : 'negativo'; ?>">
                                        <?php echo number_format($balance['balance_con_iva'] ?? 0, 2, ',', '.'); ?> €
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-title">Balance sin IVA</div>
                                    <div class="card-valor <?php echo (($balance['balance_sin_iva'] ?? 0) >= 0) ? 'positivo' : 'negativo'; ?>">
                                        <?php echo number_format($balance['balance_sin_iva'] ?? 0, 2, ',', '.'); ?> €
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gráfico -->
                            <div class="grafico-container">
                                <canvas id="grafico-finanzas"></canvas>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="/assets/js/sidebar.js"></script>
    
    <script>
        // Datos para el gráfico
        const datosGrafico = <?php echo json_encode($datos_grafico ?? []); ?>;
        
        // Datos para las categorías
        const categorias = <?php echo json_encode($categorias); ?>;
        
        // Función para actualizar categorías según el tipo seleccionado
        function actualizarCategorias() {
            const tipoSelect = document.getElementById('tipo');
            const categoriaSelect = document.getElementById('categoria');
            const tipo = tipoSelect.value;
            
            // Limpiar opciones actuales
            categoriaSelect.innerHTML = '';
            
            // Añadir nuevas opciones según el tipo
            if (categorias[tipo]) {
                categorias[tipo].forEach(categoria => {
                    const option = document.createElement('option');
                    option.value = categoria;
                    option.textContent = categoria;
                    categoriaSelect.appendChild(option);
                });
            }
        }
        
        // Inicializar categorías al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar categorías
            actualizarCategorias();
            
            // Manejar cambios de tabs
            const tabs = document.querySelectorAll('.tab');
            const secciones = document.querySelectorAll('.seccion');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    // Desactivar todas las tabs y secciones
                    tabs.forEach(t => t.classList.remove('active'));
                    secciones.forEach(s => s.classList.remove('active'));
                    
                    // Activar la tab y sección seleccionada
                    tab.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                    
                    // Actualizar parámetro en la URL sin recargar
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.pushState({}, '', url);
                    
                    // Inicializar gráfico si estamos en la sección de balance
                    if (tabId === 'balance') {
                        inicializarGrafico();
                    }
                });
            });
            
            // Inicializar gráfico si la tab de balance está activa
            if (document.querySelector('.tab[data-tab="balance"]').classList.contains('active')) {
                inicializarGrafico();
            }
        });
        
        // Inicializar gráfico
        function inicializarGrafico() {
            if (datosGrafico.length === 0) return;
            
            const ctx = document.getElementById('grafico-finanzas').getContext('2d');
            
            // Preparar datos para el gráfico
            const periodos = datosGrafico.map(item => item.periodo);
            const beneficios = datosGrafico.map(item => parseFloat(item.beneficio));
            const gastos = datosGrafico.map(item => parseFloat(item.gasto));
            
            // Crear gráfico
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: periodos,
                    datasets: [
                        {
                            label: 'Beneficios',
                            data: beneficios,
                            backgroundColor: 'rgba(34, 191, 70, 0.7)',
                            borderColor: 'rgba(34, 191, 70, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Gastos',
                            data: gastos,
                            backgroundColor: 'rgba(250, 27, 27, 0.7)',
                            borderColor: 'rgba(250, 27, 27, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' €';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw.toFixed(2) + ' €';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>