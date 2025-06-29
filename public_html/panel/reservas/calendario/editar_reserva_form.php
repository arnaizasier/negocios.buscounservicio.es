<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-publica.php';
require_once '../../../../db-venta_productos.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID de reserva no proporcionado.</div>";
    exit;
}

$id_reserva = intval($_GET['id']);

$sql = "SELECT * FROM reservas WHERE id_reserva = :id_reserva";
$stmt = $pdo5->prepare($sql);
$stmt->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);
$stmt->execute();
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    echo "<div class='alert alert-danger'>Reserva no encontrada.</div>";
    exit;
}

$sql_negocio = "SELECT * FROM negocios WHERE negocio_id = ? AND usuario_id = ?";
$stmt_negocio = $pdo2->prepare($sql_negocio);
$stmt_negocio->execute([$reserva['id_negocio'], $user_id]);
$negocio = $stmt_negocio->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    echo "<div class='alert alert-danger'>No tienes permiso para editar esta reserva.</div>";
    exit;
}

$fecha_inicio = new DateTime($reserva['fecha_inicio']);
$fecha_fin = new DateTime($reserva['fecha_fin']);
$fecha_actual = $fecha_inicio->format('Y-m-d');
$hora_actual = $fecha_inicio->format('H:i');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reserva | BuscoUnServicio</title>
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="editar-reserva.css">
    <link rel="stylesheet" href="/panel/reservas/anadir-reserva/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container45">
        <?php include_once '../../../assets/includes/sidebar.php'; ?>
        
        <div class="content45" id="content45">
            <div class="content-wrapper">
                <h1 class="panel-title">Editar Reserva #<?php echo $id_reserva; ?></h1>
                
                <form id="reservaForm" method="post" action="procesar_edicion_reserva.php">
                    <input type="hidden" name="id_reserva" value="<?php echo $id_reserva; ?>">
                    <input type="hidden" name="negocio_id" value="<?php echo $reserva['id_negocio']; ?>">
                    
                    <div id="serviciosContainer" class="form-container">
                        <h2 class="section-title">Selecciona el servicio</h2>
                        <div class="category-tabs" id="categoryTabs"></div>
                        <div class="servicios-lista" id="serviciosList"></div>
                        
                        <input type="hidden" id="servicioSeleccionado" name="servicio" value="">
                        <input type="hidden" id="duracionSeleccionada" name="duracion" value="0">
                        <input type="hidden" id="precioSeleccionado" name="precio" value="0">
                        
                        <div class="selected-services-container" style="margin-top: 20px;">
                            <h3>Servicios seleccionados:</h3>
                            <div id="selectedServicesList" class="selected-services-list" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                    
                    <div id="trabajadoresContainer" class="form-container">
                        <h2 class="section-title"><i></i>Selecciona el trabajador</h2>
                        <div class="trabajadores-lista" id="trabajadoresList"></div>
                        <input type="hidden" id="trabajadorSeleccionado" name="id_trabajador" value="<?php echo $reserva['id_trabajador'] ?? ''; ?>">
                    </div>
                    
                    <div id="fechaHoraContainer" class="form-container">
                        <h2 class="section-title">Selecciona fecha y hora</h2>
                        
                        <div class="date-carousel-container">
                            <div class="carousel-nav prev" id="prevDates">&#10094;</div>
                            <div class="date-carousel" id="dateCarousel"></div>
                            <div class="carousel-nav next" id="nextDates">&#10095;</div>
                        </div>
                        
                        <input type="hidden" id="fechaSeleccionada" name="fecha" value="<?php echo $fecha_actual; ?>">
                        
                        <div class="time-slots-container">
                            <label>Horarios disponibles:</label>
                            <div class="time-slots" id="timeSlots"></div>
                        </div>
                        
                        <input type="hidden" id="horaSeleccionada" name="hora" value="<?php echo $hora_actual; ?>">
                    </div>
                    
                    <div class="form-group actions-container">
                        <button type="submit" id="submitBtn">Guardar Cambios</button>
                        <a href="javascript:history.back()" class="btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="/assets/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const negocio_id = <?php echo $reserva['id_negocio']; ?>;
            const serviciosContainer = document.getElementById('serviciosContainer');
            const trabajadoresContainer = document.getElementById('trabajadoresContainer');
            const fechaHoraContainer = document.getElementById('fechaHoraContainer');
            const categoryTabs = document.getElementById('categoryTabs');
            const serviciosList = document.getElementById('serviciosList');
            const trabajadoresList = document.getElementById('trabajadoresList');
            const dateCarousel = document.getElementById('dateCarousel');
            const timeSlots = document.getElementById('timeSlots');
            const fechaSeleccionada = document.getElementById('fechaSeleccionada');
            const horaSeleccionada = document.getElementById('horaSeleccionada');
            const servicioSeleccionado = document.getElementById('servicioSeleccionado');
            const duracionSeleccionada = document.getElementById('duracionSeleccionada');
            const precioSeleccionado = document.getElementById('precioSeleccionado');
            const trabajadorSeleccionado = document.getElementById('trabajadorSeleccionado');
            const selectedServicesList = document.getElementById('selectedServicesList');
            const submitBtn = document.getElementById('submitBtn');
            
            let selectedDate = '<?php echo $fecha_actual; ?>';
            let selectedTime = '<?php echo $hora_actual; ?>';
            let selectedServices = [];
            let totalDuration = 0;
            let totalPrice = 0;
            let reservas = [];
            let serviciosData = {};
            let trabajadores = [];
            let selectedTrabajador = null;
            let horarioNegocio = {};
            let fechaInicialCalendario = new Date();
            
            Promise.all([
                fetch(`obtener_datos_negocio.php?negocio_id=${negocio_id}`),
                fetch(`obtener_trabajadores.php?id_negocio=${negocio_id}`)
            ])
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(([datosNegocio, datosTrabajadores]) => {
                if (datosNegocio.error) {
                    showError(datosNegocio.error);
                    return;
                }
                
                const negocio = datosNegocio.negocio;
                
                try {
                    const menuServiciosStr = negocio.menu_servicios || '{}';
                    serviciosData = JSON.parse(menuServiciosStr);
                    console.log('Servicios cargados:', serviciosData);
                } catch (error) {
                    console.error('Error al parsear JSON de servicios:', error);
                    serviciosData = {};
                    showError('Error al cargar los servicios. Por favor, contacte con el administrador.');
                }
                
                try {
                    const horarioAperturaStr = negocio.horario_apertura || '{}';
                    horarioNegocio = JSON.parse(horarioAperturaStr);
                    console.log('Horario del negocio cargado:', horarioNegocio);
                } catch (error) {
                    console.error('Error al parsear horario del negocio:', error);
                    horarioNegocio = {};
                }
                
                if (datosTrabajadores.success) {
                    trabajadores = datosTrabajadores.trabajadores || [];
                    console.log('Trabajadores cargados:', trabajadores);
                } else {
                    console.error('Error al cargar trabajadores:', datosTrabajadores.error);
                    trabajadores = [];
                }
                
                mostrarCategorias(serviciosData);
                mostrarTrabajadores();
                actualizarListaServiciosSeleccionados();
                generarCalendario();
                buscarReservasParaFecha(selectedDate);
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error al cargar datos del negocio.');
            });
            
            function generarCalendario() {
                dateCarousel.innerHTML = '';
                const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                const mesesAbrev = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                
                const numDiasAMostrar = 10;
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);

                for (let i = 0; i < numDiasAMostrar; i++) {
                    const currentDate = new Date(fechaInicialCalendario);
                    currentDate.setDate(currentDate.getDate() + i);
                    
                    if (currentDate < hoy) {
                        continue;
                    }
                    
                    const dateISO = currentDate.toISOString().split('T')[0];
                    const dayNumber = currentDate.getDate();
                    const dayName = diasSemana[currentDate.getDay()];
                    const monthAbbr = mesesAbrev[currentDate.getMonth()];
                    
                    const dateItem = document.createElement('div');
                    dateItem.className = 'date-item';
                    dateItem.setAttribute('data-date', dateISO);
                    
                    if (dateISO === selectedDate) {
                        dateItem.classList.add('selected');
                    }
                    
                    const dayNameElem = document.createElement('div');
                    dayNameElem.className = 'day-name';
                    dayNameElem.textContent = dayName;
                    
                    const dayNumberElem = document.createElement('div');
                    dayNumberElem.className = 'day-number';
                    dayNumberElem.textContent = dayNumber;
                    
                    const monthAbbrElem = document.createElement('div');
                    monthAbbrElem.className = 'month-abbr';
                    monthAbbrElem.textContent = monthAbbr;
                    
                    dateItem.appendChild(dayNameElem);
                    dateItem.appendChild(dayNumberElem);
                    dateItem.appendChild(monthAbbrElem);
                    
                    dateItem.addEventListener('click', function() {
                        document.querySelectorAll('.date-item.selected').forEach(item => {
                            item.classList.remove('selected');
                        });
                        this.classList.add('selected');
                        
                        const fechaClick = this.getAttribute('data-date');
                        selectedDate = fechaClick;
                        fechaSeleccionada.value = fechaClick;
                        
                        buscarReservasParaFecha(fechaClick);
                    });
                    
                    dateCarousel.appendChild(dateItem);
                }
                
                actualizarControlesCarousel();
            }
            
            function actualizarControlesCarousel() {
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                
                const prevBtn = document.getElementById('prevDates');
                const nextBtn = document.getElementById('nextDates');
                
                if (fechaInicialCalendario <= hoy) {
                    prevBtn.style.opacity = '0.3';
                    prevBtn.style.pointerEvents = 'none';
                } else {
                    prevBtn.style.opacity = '1';
                    prevBtn.style.pointerEvents = 'auto';
                }
            }
            
            document.getElementById('prevDates').addEventListener('click', function() {
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                
                const nuevaFecha = new Date(fechaInicialCalendario);
                nuevaFecha.setDate(nuevaFecha.getDate() - 9);
                
                if (nuevaFecha >= hoy) {
                    fechaInicialCalendario = nuevaFecha;
                    generarCalendario();
                }
            });
            
            document.getElementById('nextDates').addEventListener('click', function() {
                fechaInicialCalendario.setDate(fechaInicialCalendario.getDate() + 9); 
                generarCalendario();
            });
            
            function buscarReservasParaFecha(fecha) {
                let url = `/panel/reservas/anadir-reserva/obtener-reservas.php?negocio_id=${negocio_id}&fecha=${fecha}`;
                
                if (selectedTrabajador && selectedTrabajador.id) {
                    url += `&trabajador_id=${selectedTrabajador.id}`;
                }
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        reservas = data;
                        mostrarHorariosDisponibles(fecha);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
            
            function mostrarHorariosDisponibles(fecha) {
                timeSlots.innerHTML = '';
                
                const currentReservaId = <?php echo $id_reserva; ?>;
                const duracionParaMostrar = totalDuration > 0 ? totalDuration : 15;
                
                const diaSemana = new Date(fecha).toLocaleDateString('es-ES', { weekday: 'long' }).toLowerCase();
                const diaSemanaKey = getDiaSemanaKey(diaSemana);
                
                let horariosDelDia = [];
                
                if (selectedTrabajador && selectedTrabajador.horario && selectedTrabajador.horario !== 'null') {
                    try {
                        const horarioTrabajador = JSON.parse(selectedTrabajador.horario);
                        if (horarioTrabajador[diaSemanaKey] && !horarioTrabajador[diaSemanaKey].cerrado) {
                            const rangos = horarioTrabajador[diaSemanaKey].rangos || [];
                            rangos.forEach(rango => {
                                horariosDelDia.push({ inicio: rango.inicio, fin: rango.fin });
                            });
                        }
                    } catch (error) {
                        console.error('Error al parsear horario del trabajador:', error);
                        horariosDelDia = getHorarioNegocio(diaSemanaKey);
                    }
                } else {
                    horariosDelDia = getHorarioNegocio(diaSemanaKey);
                }
                
                if (horariosDelDia.length === 0) {
                    timeSlots.innerHTML = '<div class="mensaje-info"><i class="fas fa-info-circle"></i> No hay horarios disponibles para este día.</div>';
                    return;
                }
                
                const intervalo = 15;
                
                horariosDelDia.forEach(horario => {
                    const [horaInicio, minutoInicio] = horario.inicio.split(':').map(Number);
                    const [horaFin, minutoFin] = horario.fin.split(':').map(Number);
                    
                    const minutosInicio = horaInicio * 60 + minutoInicio;
                    const minutosFin = horaFin * 60 + minutoFin;
                    
                    for (let minutos = minutosInicio; minutos < minutosFin; minutos += intervalo) {
                        const hora = Math.floor(minutos / 60);
                        const minuto = minutos % 60;
                        
                        const horaStr = hora.toString().padStart(2, '0');
                        const minutoStr = minuto.toString().padStart(2, '0');
                        const timeStr = `${horaStr}:${minutoStr}`;
                        
                        const horarioDisponible = esHorarioDisponible(fecha, timeStr, duracionParaMostrar, currentReservaId);
                        
                        if (horarioDisponible) {
                            const timeSlot = document.createElement('div');
                            timeSlot.className = 'time-slot';
                            timeSlot.textContent = timeStr;
                            
                            if (timeStr === selectedTime) {
                                timeSlot.classList.add('selected');
                            }
                            
                            timeSlot.addEventListener('click', function() {
                                document.querySelectorAll('.time-slot.selected').forEach(slot => {
                                    slot.classList.remove('selected');
                                });
                                this.classList.add('selected');
                                selectedTime = timeStr;
                                horaSeleccionada.value = timeStr;
                            });
                            
                            timeSlots.appendChild(timeSlot);
                        }
                    }
                });
            }
            
            function getDiaSemanaKey(diaSemana) {
                const mapaDias = {
                    'lunes': 'lunes',
                    'martes': 'martes',
                    'miércoles': 'miercoles',
                    'jueves': 'jueves',
                    'viernes': 'viernes',
                    'sábado': 'sabado',
                    'domingo': 'domingo'
                };
                return mapaDias[diaSemana] || diaSemana;
            }
            
            function getHorarioNegocio(diaSemanaKey) {
                let horariosDelDia = [];
                
                if (horarioNegocio && horarioNegocio[diaSemanaKey] && !horarioNegocio[diaSemanaKey].cerrado) {
                    const rangos = horarioNegocio[diaSemanaKey].rangos || [];
                    rangos.forEach(rango => {
                        horariosDelDia.push({ inicio: rango.inicio, fin: rango.fin });
                    });
                } else {
                    horariosDelDia.push({ inicio: '09:00', fin: '18:00' });
                }
                
                return horariosDelDia;
            }
            
            function esHorarioDisponible(fecha, hora, duracion, currentReservaId) {
                const fechaHoraInicio = new Date(`${fecha}T${hora}`);
                const fechaHoraFin = new Date(fechaHoraInicio.getTime() + duracion * 60000);
                
                const conflictoReservas = reservas.some(reserva => {
                    if (reserva.id_reserva && parseInt(reserva.id_reserva) === currentReservaId) {
                        return false;
                    }
                    
                    if (selectedTrabajador && reserva.id_trabajador && parseInt(reserva.id_trabajador) !== parseInt(selectedTrabajador.id)) {
                        return false;
                    }
                    
                    const reservaInicio = new Date(reserva.fecha_inicio);
                    const reservaFin = new Date(reserva.fecha_fin);
                    
                    return (fechaHoraInicio < reservaFin && fechaHoraFin > reservaInicio);
                });
                
                return !conflictoReservas;
            }
            

            
            function mostrarCategorias(serviciosData) {
                categoryTabs.innerHTML = '';
                let isFirst = true;
                
                if (!serviciosData || typeof serviciosData !== 'object' || Object.keys(serviciosData).length === 0) {
                    console.error('No hay categorías de servicios disponibles:', serviciosData);
                    serviciosList.innerHTML = '<div class="alert alert-warning">No hay servicios disponibles. Por favor, configure los servicios en la configuración del negocio.</div>';
                    return;
                }
                
                for (const categoriaKey in serviciosData) {
                    const categoriaData = serviciosData[categoriaKey];
                    
                    if (!categoriaData.nombre) {
                        continue;
                    }
                    
                    const tab = document.createElement('div');
                    tab.className = 'category-tab';
                    if (isFirst) {
                        tab.classList.add('active');
                        mostrarServicios(categoriaKey, categoriaData);
                        isFirst = false;
                    }
                    tab.textContent = categoriaData.nombre;
                    tab.setAttribute('data-categoria', categoriaKey);
                    
                    tab.addEventListener('click', function() {
                        document.querySelectorAll('.category-tab').forEach(tab => {
                            tab.classList.remove('active');
                        });
                        this.classList.add('active');
                        
                        const categoriaSeleccionada = this.getAttribute('data-categoria');
                        mostrarServicios(categoriaSeleccionada, serviciosData[categoriaSeleccionada]);
                    });
                    
                    categoryTabs.appendChild(tab);
                }
            }
            
            function mostrarServicios(categoria, categoriaData) {
                serviciosList.innerHTML = '';
                
                if (!categoriaData || !categoriaData.servicios || typeof categoriaData.servicios !== 'object') {
                    console.error('Los servicios para la categoría ' + categoria + ' no tienen el formato esperado:', categoriaData);
                    serviciosList.innerHTML = '<div class="alert alert-warning">No hay servicios disponibles en esta categoría. Por favor, configure los servicios en la configuración del negocio.</div>';
                    return;
                }
                
                Object.keys(categoriaData.servicios).forEach(servicioKey => {
                    const servicio = categoriaData.servicios[servicioKey];
                    
                    const servicioItem = document.createElement('div');
                    servicioItem.className = 'servicio-item';
                    
                    const isSelected = selectedServices.some(s => s.nombre === servicio.nombre);
                    if (isSelected) {
                        servicioItem.classList.add('selected');
                    }
                    
                    const nombre = document.createElement('div');
                    nombre.className = 'servicio-nombre';
                    nombre.textContent = servicio.nombre;
                    
                    const info = document.createElement('div');
                    info.className = 'servicio-info';
                    
                    const duracion = document.createElement('span');
                    duracion.className = 'servicio-duracion';
                    duracion.innerHTML = `<i class="far fa-clock"></i> ${servicio.duracion} min`;
                    
                    const precio = document.createElement('span');
                    precio.className = 'servicio-precio';
                    precio.innerHTML = `<i class="fas fa-euro-sign"></i> ${Number(servicio.precio).toFixed(2)}`;
                    
                    info.appendChild(duracion);
                    info.appendChild(precio);
                    
                    servicioItem.appendChild(nombre);
                    servicioItem.appendChild(info);
                    
                    servicioItem.addEventListener('click', function() {

                        const isAlreadySelected = selectedServices.some(s => s.nombre === servicio.nombre);
                        
                        if (isAlreadySelected) {

                            this.classList.remove('selected');
                            selectedServices = selectedServices.filter(s => s.nombre !== servicio.nombre);
                        } else {

                            this.classList.add('selected');
                            selectedServices.push({
                                nombre: servicio.nombre,
                                duracion: parseInt(servicio.duracion) || 0,
                                precio: parseFloat(servicio.precio) || 0
                            });
                        }
                        

                        actualizarTotales();
                        

                        actualizarListaServiciosSeleccionados();
                        

                        buscarReservasParaFecha(selectedDate);
                    });
                    
                    serviciosList.appendChild(servicioItem);
                });
            }
            
            function actualizarTotales() {
                totalDuration = selectedServices.reduce((sum, servicio) => sum + servicio.duracion, 0);
                totalPrice = selectedServices.reduce((sum, servicio) => sum + servicio.precio, 0);
                
                duracionSeleccionada.value = totalDuration;
                precioSeleccionado.value = totalPrice;
                
                const serviciosNombres = selectedServices.map(s => s.nombre).join(', ');
                servicioSeleccionado.value = serviciosNombres;
            }
            
            function mostrarTrabajadores() {
                trabajadoresList.innerHTML = '';
                
                const trabajadorActualId = <?php echo $reserva['id_trabajador'] ?? 'null'; ?>;
                
                if (!Array.isArray(trabajadores) || trabajadores.length === 0) {
                    const mensaje = document.createElement('div');
                    mensaje.className = 'mensaje-info';
                    mensaje.innerHTML = '<i class="fas fa-info-circle"></i> Este negocio no tiene trabajadores registrados.';
                    trabajadoresList.appendChild(mensaje);
                    return;
                }
                
                let trabajadorSeleccionadoEncontrado = false;
                
                trabajadores.forEach((trabajador, index) => {
                    const trabajadorItem = document.createElement('div');
                    trabajadorItem.className = 'trabajador-item';
                    trabajadorItem.dataset.trabajadorId = trabajador.id;
                    
                    const esTrabajadorActual = trabajadorActualId && parseInt(trabajador.id) === parseInt(trabajadorActualId);
                    const esPrimero = index === 0 && !trabajadorActualId;
                    
                    if (esTrabajadorActual || esPrimero) {
                        trabajadorItem.classList.add('selected');
                        selectedTrabajador = trabajador;
                        trabajadorSeleccionado.value = trabajador.id;
                        trabajadorSeleccionadoEncontrado = true;
                    }
                    
                    const nombreCompleto = `${trabajador.nombre || ''} ${trabajador.apellido || ''}`.trim();
                    
                    trabajadorItem.innerHTML = `
                        <div class="trabajador-info">
                            ${trabajador.url_foto ? 
                                `<img src="${trabajador.url_foto}" alt="${nombreCompleto}" class="trabajador-foto">` : 
                                '<div class="trabajador-foto-placeholder"><i class="fas fa-user"></i></div>'
                            }
                            <div class="trabajador-detalles">
                                <div class="trabajador-nombre">${nombreCompleto}</div>
                                ${trabajador.rol ? `<div class="trabajador-rol">${trabajador.rol}</div>` : ''}
                            </div>
                        </div>
                        <div class="trabajador-color" style="background-color: ${trabajador.color_calendario || '#007bff'}"></div>
                    `;
                    
                    trabajadorItem.addEventListener('click', function() {
                        document.querySelectorAll('.trabajador-item').forEach(item => {
                            item.classList.remove('selected');
                        });
                        this.classList.add('selected');
                        
                        selectedTrabajador = trabajador;
                        trabajadorSeleccionado.value = trabajador.id;
                        buscarReservasParaFecha(selectedDate);
                    });
                    
                    trabajadoresList.appendChild(trabajadorItem);
                });
            }
            
            function actualizarListaServiciosSeleccionados() {
                selectedServicesList.innerHTML = '';
                
                if (selectedServices.length === 0) {
                    selectedServicesList.innerHTML = '<div class="empty-selection">No hay servicios seleccionados</div>';
                    return;
                }
                
                selectedServices.forEach((servicio, index) => {
                    const serviceItem = document.createElement('div');
                    serviceItem.className = 'selected-service-item';
                    
                    const serviceName = document.createElement('span');
                    serviceName.className = 'selected-service-name';
                    serviceName.textContent = servicio.nombre;
                    
                    const removeBtn = document.createElement('span');
                    removeBtn.className = 'remove-service';
                    removeBtn.innerHTML = '&times;';
                    removeBtn.addEventListener('click', function() {
                        selectedServices.splice(index, 1);
                        
                        actualizarTotales();
                        
                        actualizarListaServiciosSeleccionados();
                        
                        const servicioItems = document.querySelectorAll('.servicio-item');
                        servicioItems.forEach(item => {
                            const nombreServicio = item.querySelector('.servicio-nombre').textContent;
                            if (nombreServicio === servicio.nombre) {
                                item.classList.remove('selected');
                            }
                        });
                        
                        buscarReservasParaFecha(selectedDate);
                    });
                    
                    serviceItem.appendChild(serviceName);
                    serviceItem.appendChild(removeBtn);
                    selectedServicesList.appendChild(serviceItem);
                });
            }
            
            document.getElementById('reservaForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (selectedServices.length === 0) {
                    showError('Por favor, selecciona al menos un servicio.');
                    return;
                }
                
                if (!fechaSeleccionada.value || !horaSeleccionada.value) {
                    showError('Por favor, selecciona fecha y hora.');
                    return;
                }
                
                const formData = new FormData(this);
                
                fetch('procesar_edicion_reserva.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Reserva actualizada!',
                            text: 'La reserva ha sido actualizada correctamente.',
                            confirmButtonColor: '#3085d6',
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            window.location.href = 'https://negocios.buscounservicio.es/panel/reservas/calendario/';
                        });
                    } else {
                        showError(data.message || 'Error al actualizar la reserva.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Error de conexión al actualizar la reserva.');
                });
            });
            
            function showError(message) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message,
                    confirmButtonColor: '#d33'
                });
            }
        });
    </script>
</body>
</html> 