<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-publica.php';
require_once '../../../../db-venta_productos.php';
require_once '../../../../db-crm.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-trabajador.php";

// Obtener datos del trabajador actual
$worker_data = requireWorkerRole();
$current_worker_id = $worker_data['id'];
$worker_negocio_id = $worker_data['negocio_id'];
$worker_permissions = $worker_data['permisos'];

// El negocio es fijo - el del trabajador
$id_negocio = $worker_negocio_id;

// Filtro de trabajador: solo si tiene permisos 2 (ver/editar todas las reservas)
$id_trabajador = null;
if ($worker_permissions == 2) {
    // Puede ver reservas de todos los trabajadores
    $id_trabajador = isset($_GET['id_trabajador']) && $_GET['id_trabajador'] !== '' ? intval($_GET['id_trabajador']) : null;
} else {
    // Solo puede ver sus propias reservas
    $id_trabajador = $current_worker_id;
}

// Obtener trabajadores del mismo negocio (solo si tiene permisos 2)
$trabajadores_data = [];
if ($worker_permissions == 2) {
    $stmt_trabajadores = $pdo2->prepare("
        SELECT id, nombre, apellido, rol, color_calendario 
        FROM trabajadores 
        WHERE negocio_id = ? 
        ORDER BY nombre ASC, apellido ASC
    ");
    $stmt_trabajadores->execute([$id_negocio]);
    $trabajadores_data = $stmt_trabajadores->fetchAll(PDO::FETCH_ASSOC);
}

$sql_all_reservas = "SELECT r.* FROM reservas r 
        WHERE r.id_negocio = :id_negocio 
        AND r.estado_reserva != 'Cancelada'";

if ($id_trabajador !== null) {
    $sql_all_reservas .= " AND r.id_trabajador = :id_trabajador";
}

$sql_all_reservas .= " ORDER BY r.fecha_inicio ASC";

$stmt_all_reservas = $pdo5->prepare($sql_all_reservas);
$stmt_all_reservas->bindParam(':id_negocio', $id_negocio, PDO::PARAM_INT);
if ($id_trabajador !== null) {
    $stmt_all_reservas->bindParam(':id_trabajador', $id_trabajador, PDO::PARAM_INT);
}
$stmt_all_reservas->execute();
$all_reservas_data = $stmt_all_reservas->fetchAll(PDO::FETCH_ASSOC);

$clientes = [];
$clientes_crm = [];

$trabajadores = [];
$ids_trabajadores = array_filter(array_unique(array_column($all_reservas_data, 'id_trabajador')), function($id) {
    return !empty($id) && $id > 0;
});

if (!empty($ids_trabajadores)) {
    $params_trabajadores = [];
    $placeholders_trabajadores_arr = [];
    foreach ($ids_trabajadores as $index => $id) {
        $param_name = ":trabajador_id_$index";
        $placeholders_trabajadores_arr[] = $param_name;
        $params_trabajadores[$param_name] = $id;
    }
    $placeholders_trabajadores_str = implode(',', $placeholders_trabajadores_arr);
    $sql_trabajadores = "SELECT id, nombre, apellido, rol, color_calendario FROM trabajadores WHERE id IN ($placeholders_trabajadores_str)";
    $stmt_trabajadores_reservas = $pdo2->prepare($sql_trabajadores);
    $stmt_trabajadores_reservas->execute($params_trabajadores);
    $trabajadores_reservas_data = $stmt_trabajadores_reservas->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trabajadores_reservas_data as $trabajador) {
        $trabajadores[$trabajador['id']] = $trabajador;
    }
}

$ids_clientes = array_filter(array_unique(array_column($all_reservas_data, 'id_cliente')), function($id) {
    return !empty($id) && $id > 0;
});

if (!empty($ids_clientes)) {
    $params_clientes = [];
    $placeholders_clientes_arr = [];
    foreach ($ids_clientes as $index => $id) {
        $param_name = ":cliente_id_$index";
        $placeholders_clientes_arr[] = $param_name;
        $params_clientes[$param_name] = $id;
    }
    $placeholders_clientes_str = implode(',', $placeholders_clientes_arr);
    $sql_clientes = "SELECT id, first_name, last_name, email, phone FROM users WHERE id IN ($placeholders_clientes_str)";
    $stmt_clientes = $pdo->prepare($sql_clientes);
    $stmt_clientes->execute($params_clientes);
    $clientes_data = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
    foreach ($clientes_data as $cliente) {
        $clientes[$cliente['id']] = $cliente;
    }
}

// Obtener clientes del CRM
$ids_clientes_crm = array_filter(array_unique(array_column($all_reservas_data, 'id_cliente_crm')), function($id) {
    return !empty($id);
});

if (!empty($ids_clientes_crm)) {
    $params_clientes_crm = [];
    $placeholders_clientes_crm_arr = [];
    foreach ($ids_clientes_crm as $index => $id) {
        $param_name = ":cliente_crm_id_$index";
        $placeholders_clientes_crm_arr[] = $param_name;
        $params_clientes_crm[$param_name] = $id;
    }
    $placeholders_clientes_crm_str = implode(',', $placeholders_clientes_crm_arr);
    $sql_clientes_crm = "SELECT cliente_id, nombre, apellidos, email, telefono FROM crm WHERE cliente_id IN ($placeholders_clientes_crm_str)";
    $stmt_clientes_crm = $pdo6->prepare($sql_clientes_crm);
    $stmt_clientes_crm->execute($params_clientes_crm);
    $clientes_crm_data = $stmt_clientes_crm->fetchAll(PDO::FETCH_ASSOC);
    foreach ($clientes_crm_data as $cliente_crm) {
        $clientes_crm[$cliente_crm['cliente_id']] = $cliente_crm;
    }
}

$calendar_events = [];
foreach ($all_reservas_data as $reserva) {
    $cliente_nombre_completo = 'Cliente Desconocido';
    
    // Primero intentar obtener del sistema de usuarios
    if (!empty($reserva['id_cliente']) && $reserva['id_cliente'] > 0 && isset($clientes[$reserva['id_cliente']])) {
        $cliente_info = $clientes[$reserva['id_cliente']];
        $cliente_nombre_completo = trim(($cliente_info['first_name'] ?? '') . ' ' . ($cliente_info['last_name'] ?? ''));
    }
    // Si no se encontró en users, buscar en CRM
    elseif (!empty($reserva['id_cliente_crm']) && isset($clientes_crm[$reserva['id_cliente_crm']])) {
        $cliente_crm_info = $clientes_crm[$reserva['id_cliente_crm']];
        $cliente_nombre_completo = trim(($cliente_crm_info['nombre'] ?? '') . ' ' . ($cliente_crm_info['apellidos'] ?? ''));
    }
    
    // Si el nombre sigue vacío, usar valor por defecto
    if (empty(trim($cliente_nombre_completo))) {
        $cliente_nombre_completo = 'Cliente Desconocido';
    }

    $estado_pago = strtolower($reserva['estado_pago'] ?? 'pendiente');
    $estado_reserva_val = strtolower($reserva['estado_reserva'] ?? 'confirmada');

    // Obtener color del trabajador
    $color_trabajador = '#024ddf'; // Color por defecto
    $trabajador_nombre = '';
    if (!empty($reserva['id_trabajador']) && isset($trabajadores[$reserva['id_trabajador']])) {
        $trabajador_info = $trabajadores[$reserva['id_trabajador']];
        $trabajador_nombre = ' (' . trim($trabajador_info['nombre'] . ' ' . $trabajador_info['apellido']) . ')';
        
        // Usar el color del trabajador si está disponible
        if (!empty($trabajador_info['color_calendario'])) {
            $color_trabajador = $trabajador_info['color_calendario'];
        }
    }

    $event_title = ($reserva['servicio'] ?? 'Reserva sin servicio') . ' - ' . $cliente_nombre_completo . $trabajador_nombre;

    $start_time = $reserva['fecha_inicio'] ?? null;
    $end_time = $reserva['fecha_fin'] ?? null;
    $id_reserva_val = $reserva['id_reserva'] ?? null;

    if ($id_reserva_val && $start_time && $end_time) {
        $calendar_events[] = [
            'id' => $id_reserva_val, 
            'title' => $event_title,
            'start' => $start_time,
            'end' => $end_time,
            'allDay' => false,
            'backgroundColor' => $color_trabajador,
            'borderColor' => $color_trabajador,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'id_reserva' => $id_reserva_val,
                'estado_pago' => $estado_pago,
                'estado_reserva' => $estado_reserva_val,
                'id_trabajador' => $reserva['id_trabajador'] ?? null
            ]
        ];
    }
}

$events_json = json_encode($calendar_events);

$initial_date_fc = date('Y-m-d');

$id_trabajador_param_filtro = isset($_GET['id_trabajador']) && $_GET['id_trabajador'] !== '' ? '&id_trabajador=' . $_GET['id_trabajador'] : '';
$filtros_params = $id_trabajador_param_filtro;

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Reservas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <script>
        if (typeof FullCalendar === 'undefined') {
            console.error("FullCalendar library not loaded! Trying alternative source...");
            
            const mainScript = document.createElement('script');
            mainScript.src = 'https://unpkg.com/fullcalendar@6.1.10/index.global.min.js';
            document.head.appendChild(mainScript);
            
            const cssLink = document.createElement('link');
            cssLink.rel = 'stylesheet';
            cssLink.href = 'https://unpkg.com/fullcalendar@6.1.10/main.min.css';
            document.head.appendChild(cssLink);
            
            document.addEventListener('DOMContentLoaded', function() {
                const calendarContainer = document.querySelector('.calendar-container');
                if (calendarContainer) {
                    calendarContainer.innerHTML = `
                        <div class="alert-warning">
                            <strong>Cargando calendario desde fuente alternativa...</strong>
                            <div class="spinner">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    ` + calendarContainer.innerHTML;
                }
            });
        }
    </script>
</head>
<body>
<div class="container45">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar-trabajadores.php'; ?>
    <div id="content45" class="content45 container-fluid mt-4">
        <div class="main-container">
            <div class="calendario-header mb-4">
                <div class="calendario-header-row">
                    <h1 class="mb-0">Calendario</h1>
                    
                    <button id="mobileFilterBtn" class="filter-button">
                        <i class="fas fa-filter"></i> Filtros
                    </button>
                    
                    <div class="desktop-filters">
                    <?php
                    if (count($trabajadores_data) > 1) {
                        echo "<form method='get' id='filtroTrabajadorForm' class='trabajador-filtro-form'>";
                        echo "<label for='trabajadorSelect' style='margin-right:8px;'>Filtrar por trabajador:</label>";
                        echo "<select name='id_trabajador' id='trabajadorSelect' class='form-select' onchange=\"document.getElementById('filtroTrabajadorForm').submit();\">";
                        echo "<option value=''>Todos los trabajadores</option>";
                        foreach ($trabajadores_data as $trabajador) {
                            $t_id = $trabajador['id'];
                            $sel = ($id_trabajador == $t_id) ? ' selected' : '';
                            $nombre_trabajador = htmlspecialchars(trim($trabajador['nombre'] . ' ' . $trabajador['apellido']));
                            echo "<option value='{$t_id}'{$sel}>{$nombre_trabajador}</option>";
                        }
                        echo "</select>";
                        echo "</form>";
                    }
                    ?>
                    </div>
                </div>
            </div>
            
            <div id="filterModal" class="modal">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Filtros del Calendario</h5>
                            <button type="button" class="close-button" id="filterModalCloseBtn">&times;</button>
                        </div>
                        <div class="modal-body">
                            <?php
                            $hay_filtros = (count($trabajadores_data) > 1);
                            
                            if ($hay_filtros) {
                                echo "<form method='get' id='modalFilterForm' class='modal-filter-form'>";
                                
                                if (count($trabajadores_data) > 1) {
                                    echo "<div class='form-group'>";
                                    echo "<label for='modalTrabajadorSelect'><i></i> Filtrar por trabajador:</label>";
                                    echo "<select name='id_trabajador' id='modalTrabajadorSelect' class='form-control'>";
                                    echo "<option value=''>Todos los trabajadores</option>";
                                    foreach ($trabajadores_data as $trabajador) {
                                        $t_id = $trabajador['id'];
                                        $sel = ($id_trabajador == $t_id) ? ' selected' : '';
                                        $nombre_trabajador = htmlspecialchars(trim($trabajador['nombre'] . ' ' . $trabajador['apellido']));
                                        $rol = !empty($trabajador['rol']) ? ' - ' . htmlspecialchars($trabajador['rol']) : '';
                                        echo "<option value='{$t_id}'{$sel}>{$nombre_trabajador}{$rol}</option>";
                                    }
                                    echo "</select>";
                                    echo "</div>";
                                }
                                
                                echo "<div class='form-group' style='margin-bottom: 0;'>";
                                echo "<button type='submit' class='btn-primary' style='width: 100%;'><i class='fas fa-check'></i> Aplicar filtros</button>";
                                echo "</div>";
                                echo "</form>";
                            } else {
                                echo "<div class='alert-info'>";
                                echo "<i class='fas fa-info-circle'></i> No hay filtros disponibles para este calendario.";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($all_reservas_data)): ?>
            <div class="alert-info">
                No hay reservas para mostrar para este negocio y fechas seleccionadas.
            </div>
            <?php endif; ?>
            
            <div class="calendar-container">
                <div id='calendar'></div>
            </div>
            
            <div class="footer-space"></div>
        </div>
    </div>
    
    <div class="modal" id="reservaModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reservaModalLabel">Detalles de la Reserva</h5>
                    <button type="button" class="close-button" id="modalCloseBtn" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" id="reservaDetails">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="modalCloseBtn2">Cerrar</button>
                    <a href="#" id="btnEditar" class="btn-primary">Editar</a>
                    <button type="button" id="btnCancelar" class="btn-danger">Cancelar Reserva</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/sidebar.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <script>
    window.Swal=function(){throw new Error("SweetAlert2 no está completamente embebido. Usa el CDN para todas las funcionalidades.")};
    var сладкоеОповещениеScript=document.createElement('script');
    сладкоеОповещениеScript.src='https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js';
    document.head.appendChild(сладкоеОповещениеScript);
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileFilterBtn = document.getElementById('mobileFilterBtn');
            const filterModal = document.getElementById('filterModal');
            const filterModalCloseBtn = document.getElementById('filterModalCloseBtn');
            
            if (mobileFilterBtn && filterModal) {
                mobileFilterBtn.addEventListener('click', function() {
                    filterModal.style.display = 'block';
                    filterModal.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop';
                    document.body.appendChild(backdrop);
                });
            }
            
            if (filterModalCloseBtn) {
                filterModalCloseBtn.addEventListener('click', function() {
                    filterModal.style.display = 'none';
                    filterModal.classList.remove('show');
                    document.body.classList.remove('modal-open');
                    
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.parentNode.removeChild(backdrop);
                    }
                });
            }
            
            if (typeof bootstrap === 'undefined') {
            }
            
            const calendarEl = document.getElementById('calendar');
            
            const eventsData = <?php echo $events_json ?: '[]'; ?>;
            
            const reservaModalEl = document.getElementById('reservaModal');
            const reservaModal = {
                show: function() {
                    reservaModalEl.style.display = 'block';
                    reservaModalEl.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop';
                    document.body.appendChild(backdrop);
                },
                hide: function() {
                    reservaModalEl.style.display = 'none';
                    reservaModalEl.classList.remove('show');
                    document.body.classList.remove('modal-open');
                    
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.parentNode.removeChild(backdrop);
                    }
                }
            };
            
            document.getElementById('modalCloseBtn').addEventListener('click', function() {
                reservaModal.hide();
            });
            document.getElementById('modalCloseBtn2').addEventListener('click', function() {
                reservaModal.hide();
            });
            
            let reservaActualId = null;

                            try {
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    locale: 'es',
                    initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
                    initialDate: '<?php echo $initial_date_fc; ?>',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    views: {
                        timeGridDay: {
                            titleFormat: { month: 'short', day: 'numeric' }
                        },
                        timeGridWeek: {
                            titleFormat: { month: 'short', day: 'numeric' }
                        }
                    },
                    buttonText: {
                        today: 'Hoy',
                        month: 'Mes',
                        week: 'Semana',
                        day: 'Día',
                        list: 'Lista'
                    },
                    events: eventsData,
                    slotMinTime: "08:00:00",
                    slotMaxTime: "22:00:00",
                    allDaySlot: false,
                    height: 'auto',
                    eventDidMount: function(info) {
                        // Agregar atributos de datos para indicadores de estado
                        if (info.event.extendedProps.estado_pago) {
                            info.el.setAttribute('data-estado-pago', info.event.extendedProps.estado_pago);
                        }
                        if (info.event.extendedProps.estado_reserva) {
                            info.el.setAttribute('data-estado-reserva', info.event.extendedProps.estado_reserva);
                        }
                    },
                    eventClick: function(info) {
                        reservaActualId = info.event.extendedProps.id_reserva;
                        const estadoReserva = info.event.extendedProps.estado_reserva;
                        
                        const btnCancelar = document.getElementById('btnCancelar');
                        const btnEditar = document.getElementById('btnEditar');
                        
                        btnCancelar.setAttribute('data-reserva-id', reservaActualId);
                        btnEditar.setAttribute('data-reserva-id', reservaActualId);
                        
                        if (estadoReserva && estadoReserva.toLowerCase() === 'cancelada') {
                            btnCancelar.style.display = 'none';
                            btnEditar.style.display = 'none';
                        } else {
                            btnCancelar.style.display = 'inline-block';
                            btnEditar.style.display = 'inline-block';
                        }
                        
                        fetch('obtener_detalles_reserva.php?id=' + reservaActualId)
                            .then(response => response.text())
                            .then(data => {
                                document.getElementById('reservaDetails').innerHTML = data;
                                reservaModal.show();
                                attachMarcarPagadoListener();
                            })
                            .catch(error => {
                                document.getElementById('reservaDetails').innerHTML = '<p class="text-danger">Error al cargar los detalles de la reserva.</p>';
                                reservaModal.show();
                            });
                    },
                    datesSet: function(dateInfo) {
                        // Mantener solo los filtros actuales sin parámetros de fecha
                        const idTrabajadorActual = document.getElementById('trabajadorSelect') ? document.getElementById('trabajadorSelect').value : '<?php echo $id_trabajador ?? ''; ?>';
                        
                        const currentUrl = new URL(window.location.href);
                        // Eliminar parámetros de fecha para que siempre se use "hoy" como predeterminado
                        currentUrl.searchParams.delete('semana');
                        currentUrl.searchParams.delete('anio');
                        
                        // Mantener solo los filtros
                        if (idTrabajadorActual) {
                            currentUrl.searchParams.set('id_trabajador', idTrabajadorActual);
                        }
                        
                        window.history.pushState({path:currentUrl.href},'',currentUrl.href);
                    }
                });
                
                calendar.render();

                const btnCancelar = document.getElementById('btnCancelar');
                const btnEditar = document.getElementById('btnEditar');

                function attachMarcarPagadoListener() {
                    const btnMarcarPagado = document.getElementById('btnMarcarPagado');
                    if (btnMarcarPagado) {
                        
                        const newBtn = btnMarcarPagado.cloneNode(true);
                        btnMarcarPagado.parentNode.replaceChild(newBtn, btnMarcarPagado);

                        newBtn.addEventListener('click', function() {
                            const reservaId = this.getAttribute('data-id'); 
                            Swal.fire({
                                title: '¿Marcar como pagado?',
                                text: 'Esta acción marcará la reserva como pagada.',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonColor: '#28a745',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, marcar',
                                cancelButtonText: 'No, volver'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    const formData = new FormData();
                                    formData.append('id_reserva', reservaId);
                                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
                                    fetch('marcar_pagado.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            Swal.fire('Pagado', data.message, 'success')
                                                .then(() => {
                                                    reservaModal.hide();
                                                    calendar.refetchEvents();
                                                });
                                        } else {
                                            Swal.fire('Error', data.message, 'error');
                                        }
                                    })
                                    .catch(() => {
                                        Swal.fire('Error', 'Error al marcar como pagado.', 'error');
                                    });
                                }
                            });
                        });
                    }
                }
                
                btnCancelar.addEventListener('click', function() {
                    const reservaId = this.getAttribute('data-reserva-id');
                    if (!reservaId) {
                        Swal.fire('Error', 'No se pudo obtener el ID de la reserva para cancelar.', 'error');
                        return;
                    }
                    
                    Swal.fire({
                        title: 'Motivo de cancelación',
                        html: `
                            <div style="text-align: left;">
                                <p style="margin-bottom: 15px;">Selecciona el motivo de cancelación para mostrar al cliente:</p>
                                <div style="margin-bottom: 10px;">
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="radio" name="motivo" value="Cita reservada por error" style="margin-right: 8px;">
                                        Cita reservada por error
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="radio" name="motivo" value="Cancelación solicitada por cliente" style="margin-right: 8px;">
                                        Cancelación solicitada por cliente
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="radio" name="motivo" value="Problemas técnicos o de equipo" style="margin-right: 8px;">
                                        Problemas técnicos o de equipo
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="radio" name="motivo" value="Emergencia del personal" style="margin-right: 8px;">
                                        Emergencia del personal
                                    </label>
                                    <label style="display: block; margin-bottom: 15px;">
                                        <input type="radio" name="motivo" value="otro" style="margin-right: 8px;">
                                        Otro motivo
                                    </label>
                                </div>
                                <div id="otroMotivoDiv" style="display: none; margin-top: 10px;">
                                    <label for="otroMotivoText" style="display: block; margin-bottom: 5px; font-weight: bold;">Especifica el motivo:</label>
                                    <textarea id="otroMotivoText" placeholder="Escribe aquí el motivo de cancelación..." style="width: 100%; height: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; resize: vertical;" maxlength="255"></textarea>
                                    <small style="color: #666;">Máximo 255 caracteres</small>
                                </div>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Cancelar reserva',
                        cancelButtonText: 'Volver',
                        preConfirm: () => {
                            const selectedMotivo = document.querySelector('input[name="motivo"]:checked');
                            if (!selectedMotivo) {
                                Swal.showValidationMessage('Por favor, selecciona un motivo de cancelación');
                                return false;
                            }
                            
                            let motivoFinal = selectedMotivo.value;
                            if (selectedMotivo.value === 'otro') {
                                const otroMotivoText = document.getElementById('otroMotivoText').value.trim();
                                if (!otroMotivoText || otroMotivoText.length < 3) {
                                    Swal.showValidationMessage('Por favor, especifica el motivo (mínimo 3 caracteres)');
                                    return false;
                                }
                                if (otroMotivoText.length > 255) {
                                    Swal.showValidationMessage('El motivo no puede exceder 255 caracteres');
                                    return false;
                                }
                                if (/<[^>]*>|javascript:|onload=|onerror=|<script|[<>"']/.test(otroMotivoText)) {
                                    Swal.showValidationMessage('El motivo contiene caracteres no permitidos');
                                    return false;
                                }
                                motivoFinal = otroMotivoText;
                            }
                            
                            return motivoFinal;
                        },
                        didOpen: () => {
                            const radioButtons = document.querySelectorAll('input[name="motivo"]');
                            const otroMotivoDiv = document.getElementById('otroMotivoDiv');
                            
                            radioButtons.forEach(radio => {
                                radio.addEventListener('change', function() {
                                    if (this.value === 'otro') {
                                        otroMotivoDiv.style.display = 'block';
                                        document.getElementById('otroMotivoText').focus();
                                    } else {
                                        otroMotivoDiv.style.display = 'none';
                                    }
                                });
                            });
                        }
                    }).then((result) => {
                        if (result.isConfirmed && result.value) {
                            const formData = new FormData();
                            formData.append('id', reservaId);
                            formData.append('motivo_cancelacion', result.value);
                            
                            fetch('cancelar_reserva.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('Cancelada', data.message || 'La reserva ha sido cancelada correctamente.', 'success')
                                        .then(() => {
                                            reservaModal.hide();
                                            calendar.refetchEvents();
                                        });
                                } else {
                                    Swal.fire('Error', data.message || 'Error al cancelar la reserva.', 'error');
                                }
                            })
                            .catch(() => {
                                Swal.fire('Error', 'Error de conexión al cancelar la reserva.', 'error');
                            });
                        }
                    });
                });

                btnEditar.addEventListener('click', function() {
                    const reservaId = this.getAttribute('data-reserva-id');
                    if (!reservaId) {
                        Swal.fire('Error', 'No se pudo obtener el ID de la reserva para editar.', 'error');
                        return;
                    }
                    window.location.href = 'editar_reserva_form.php?id=' + reservaId;
                });

            } catch (error) {
                const calendarContainer = document.querySelector('.calendar-container');
                if (calendarContainer) {
                    calendarContainer.innerHTML += `
                        <div class="alert-danger">
                            <strong>Error al inicializar el calendario:</strong> ${error.message}<br>
                            <small>Revise la consola del navegador para más detalles.</small>
                        </div>
                    `;
                }
            }
        });
    </script>
</body>
</html>