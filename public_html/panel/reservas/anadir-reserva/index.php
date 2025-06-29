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
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";

$stmt_negocios = $pdo2->prepare("SELECT negocio_id, nombre, url, telefono, tipo_reserva, horario_apertura, espacios_reservas, menu_servicios FROM negocios WHERE usuario_id = ?");
$stmt_negocios->execute([$user_id]);
$negocios_data = $stmt_negocios->fetchAll(PDO::FETCH_ASSOC);

if (empty($negocios_data)) {
    echo "<div class='alert alert-danger'>No tiene negocios registrados.</div>";
    exit; 
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Reserva</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container45">
        <?php include_once '../../../assets/includes/sidebar.php'; ?>
        
        <div class="content45" id="content45">
            <div class="content-wrapper">
                <h1 class="panel-title">Añadir Reserva Manual</h1>
                
                <form id="reservaForm" method="post" action="procesar-reserva.php">
                    <div class="form-container">
                        <h2 class="section-title">Selecciona el negocio</h2>
                        <div class="form-group">
                            <select id="negocio" name="negocio_id" required>
                                <option value="">Selecciona un negocio</option>
                                <?php foreach ($negocios_data as $negocio): ?>
                                    <option value="<?php echo $negocio['negocio_id']; ?>" 
                                            data-tipo="<?php echo htmlspecialchars($negocio['tipo_reserva'] ?? ''); ?>"
                                            data-horario='<?php echo htmlspecialchars($negocio['horario_apertura'] ?? '{}'); ?>'
                                            data-espacios='<?php echo htmlspecialchars($negocio['espacios_reservas'] ?? '{}'); ?>'
                                            data-servicios='<?php echo htmlspecialchars($negocio['menu_servicios'] ?? '{}'); ?>'>
                                        <?php echo htmlspecialchars($negocio['nombre'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="serviciosContainer" class="form-container" style="display: none;">
                        <h2 class="section-title">Selecciona los servicios</h2>
                        <div class="category-tabs" id="categoryTabs"></div>
                        <div class="servicios-lista" id="serviciosList"></div>
                        
                        <div class="servicios-seleccionados" id="serviciosSeleccionados">
                            <h3>Servicios seleccionados:</h3>
                            <div class="servicios-seleccionados-lista" id="serviciosSeleccionadosLista"></div>
                            <div class="total-resumen" id="totalResumen">
                                <strong>Total: <span id="totalPrecio">0</span> € | Duración total: <span id="totalDuracion">0</span> min</strong>
                            </div>
                        </div>
                        
                        <input type="hidden" id="serviciosMultiples" name="servicios_multiples" value="">
                        <input type="hidden" id="servicioSeleccionado" name="servicio" value="">
                        <input type="hidden" id="duracionSeleccionada" name="duracion" value="">
                        <input type="hidden" id="precioSeleccionado" name="precio" value="">
                    </div>
                    
                    <div id="trabajadorContainer" class="form-container" style="display: none;">
                        <h2 class="section-title">Selecciona el trabajador</h2>
                        <div class="trabajadores-lista" id="trabajadoresList">
                            <div class="mensaje-info"><i class="fas fa-spinner fa-spin"></i> Cargando trabajadores...</div>
                        </div>
                        
                        <input type="hidden" id="trabajadorSeleccionado" name="id_trabajador" value="">
                    </div>
                    
                    <div id="fechaHoraContainer" class="form-container" style="display: none;">
                        <h2 class="section-title">Selecciona fecha y hora</h2>
                        
                        <div class="date-carousel-container">
                            <div class="date-carousel" id="dateCarousel"></div>
                        </div>
                        
                        <input type="hidden" id="fechaSeleccionada" name="fecha" value="">
                        
                        <div class="time-slots-container">
                            <label>Horarios disponibles:</label>
                            <div class="time-slots" id="timeSlots"></div>
                        </div>
                        
                        <input type="hidden" id="horaSeleccionada" name="hora" value="">
                    </div>
                    
                    <div id="clienteContainer" class="form-container" style="display: none;">
                        <h2 class="section-title">Asignar Cliente</h2>
                        
                        <div class="cliente-opciones">
                            <div class="cliente-opcion" data-tipo="nuevo">
                                <div class="cliente-opcion-titulo">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Crear Nuevo Cliente</span>
                                </div>
                            </div>
                            
                            <div class="cliente-opcion" data-tipo="existente">
                                <div class="cliente-opcion-titulo">
                                    <i class="fas fa-users"></i>
                                    <span>Seleccionar Cliente</span>
                                </div>
                            </div>
                            
                            <div class="cliente-opcion" data-tipo="sin_cliente">
                                <div class="cliente-opcion-titulo">
                                    <i class="fas fa-user-slash"></i>
                                    <span>No asignar a ningún cliente</span>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="tipoClienteSeleccionado" name="tipo_cliente" value="">
                        <input type="hidden" id="clienteSeleccionado" name="cliente_id" value="">
                        <input type="hidden" id="usuarioIdSeleccionado" name="usuario_id" value="">
                        
                        <div id="nuevoClienteForm" class="cliente-form">
                            <div class="form-group">
                                <label for="nombre">Nombre:</label>
                                <input type="text" id="nombre" name="nombre">
                            </div>
                            
                            <div class="form-group">
                                <label for="apellidos">Apellidos:</label>
                                <input type="text" id="apellidos" name="apellidos">
                            </div>
                            
                            <div class="form-group">
                                <label for="telefono">Teléfono:</label>
                                <input type="tel" id="telefono" name="telefono">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email">
                            </div>
                        </div>
                        
                        <div id="clientesLista" class="cliente-lista">
                            <div class="cliente-search">
                                <input type="text" id="buscarCliente" placeholder="Buscar cliente por nombre, apellidos, teléfono o email..." class="form-control">
                            </div>
                            <div id="clientesItems"></div>
                        </div>

                        <div class="form-group" style="margin-top: 20px;">
                            <button type="submit" id="submitBtn">Confirmar y Añadir Reserva</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    const negocioSelect = document.getElementById('negocio');
    const serviciosContainer = document.getElementById('serviciosContainer');
    const trabajadorContainer = document.getElementById('trabajadorContainer');
    const fechaHoraContainer = document.getElementById('fechaHoraContainer');
    const clienteContainer = document.getElementById('clienteContainer');
    const categoryTabs = document.getElementById('categoryTabs');
    const serviciosList = document.getElementById('serviciosList');
    const trabajadoresList = document.getElementById('trabajadoresList');
    const dateCarousel = document.getElementById('dateCarousel');
    const timeSlots = document.getElementById('timeSlots');
    const fechaSeleccionada = document.getElementById('fechaSeleccionada');
    const horaSeleccionada = document.getElementById('horaSeleccionada');
    const serviciosMultiples = document.getElementById('serviciosMultiples');
    const servicioSeleccionado = document.getElementById('servicioSeleccionado');
    const duracionSeleccionada = document.getElementById('duracionSeleccionada');
    const precioSeleccionado = document.getElementById('precioSeleccionado');
    const trabajadorSeleccionado = document.getElementById('trabajadorSeleccionado');
    const tipoClienteSeleccionado = document.getElementById('tipoClienteSeleccionado');
    const clienteSeleccionado = document.getElementById('clienteSeleccionado');
    const usuarioIdSeleccionado = document.getElementById('usuarioIdSeleccionado');
    const nuevoClienteForm = document.getElementById('nuevoClienteForm');
    const clientesLista = document.getElementById('clientesLista');
    const clientesItems = document.getElementById('clientesItems');
    const buscarCliente = document.getElementById('buscarCliente');
    const submitBtn = document.getElementById('submitBtn');
    
    let selectedNegocio = null;
    let selectedDate = null;
    let selectedServices = [];
    let selectedTrabajador = null;
    let reservas = [];
    let clientes = []; 
    let trabajadores = [];
    let serviciosData = {};
    
    function cargarTrabajadores() {
        if (!selectedNegocio) return;
        
        trabajadoresList.innerHTML = '<div class="mensaje-info"><i class="fas fa-spinner fa-spin"></i> Cargando trabajadores...</div>';
        
        fetch(`obtener-trabajadores.php?negocio_id=${selectedNegocio.value}`)
            .then(response => {
                if (!response.ok) throw new Error('Error al obtener trabajadores');
                return response.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);
                trabajadores = data;
                mostrarTrabajadores();
            })
            .catch(error => {
                console.error('Error al cargar trabajadores:', error);
                trabajadoresList.innerHTML = '<div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> Error al cargar trabajadores.</div>';
                trabajadores = [];
                fechaHoraContainer.style.display = 'block';
                generarCalendario();
            });
    }
    
    function mostrarTrabajadores() {
        trabajadoresList.innerHTML = '';
        
        if (!Array.isArray(trabajadores) || trabajadores.length === 0) {
            trabajadoresList.innerHTML = '<div class="mensaje-info"><i class="fas fa-info-circle"></i> Este negocio no tiene trabajadores asignados. Continuando sin asignar trabajador específico.</div>';
            trabajadorSeleccionado.value = '';
            selectedTrabajador = null;
            fechaHoraContainer.style.display = 'block';
            generarCalendario();
            return;
        }
        
        trabajadores.forEach(trabajador => {
            const trabajadorItem = document.createElement('div');
            trabajadorItem.className = 'trabajador-item';
            trabajadorItem.dataset.trabajadorId = trabajador.id;
            
            const nombreCompleto = `${trabajador.nombre || ''} ${trabajador.apellido || ''}`.trim();
            
            trabajadorItem.innerHTML = `
                <div class="trabajador-info">
                    ${trabajador.url_foto ? `<img src="${htmlspecialchars(trabajador.url_foto)}" alt="${htmlspecialchars(nombreCompleto)}" class="trabajador-foto">` : '<div class="trabajador-foto-placeholder"><i class="fas fa-user"></i></div>'}
                    <div class="trabajador-detalles">
                        <div class="trabajador-nombre">${htmlspecialchars(nombreCompleto)}</div>
                        ${trabajador.rol ? `<div class="trabajador-rol">${htmlspecialchars(trabajador.rol)}</div>` : ''}
                    </div>
                </div>
                <div class="trabajador-color" style="background-color: ${trabajador.color_calendario || '#007bff'}"></div>
            `;
            
            trabajadorItem.addEventListener('click', function() {
                document.querySelectorAll('.trabajador-item').forEach(item => item.classList.remove('selected'));
                this.classList.add('selected');
                
                selectedTrabajador = trabajador;
                trabajadorSeleccionado.value = trabajador.id;
                
                fechaHoraContainer.style.display = 'block';
                generarCalendario();
            });
            
            trabajadoresList.appendChild(trabajadorItem);
        });
    }
    
    function generarCalendario() {
        dateCarousel.innerHTML = '';
        const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        
        const hoy = new Date();
        const primerDiaVisible = new Date(hoy);
        const ultimoDiaVisible = new Date(hoy);
        ultimoDiaVisible.setDate(1); 
        ultimoDiaVisible.setMonth(ultimoDiaVisible.getMonth() + 4); 
        ultimoDiaVisible.setDate(0); 
        
        let diaIteracion = primerDiaVisible;
        
        while (diaIteracion <= ultimoDiaVisible) {
            const fechaFormateada = diaIteracion.toISOString().split('T')[0];
            const diaSemana = diasSemana[diaIteracion.getDay()];
            const dia = diaIteracion.getDate();
            const mesAbrev = meses[diaIteracion.getMonth()].substring(0, 3);
            
            const dateCard = document.createElement('div');
            dateCard.className = 'date-card';
            dateCard.dataset.fecha = fechaFormateada;
            
            dateCard.innerHTML = `
                <span class="weekday">${diaSemana}</span>
                <span class="day">${dia}</span>
                <span class="month">${mesAbrev}</span>
            `;
            
            dateCard.addEventListener('click', function() {
                document.querySelectorAll('.date-card').forEach(card => card.classList.remove('selected'));
                this.classList.add('selected');
                selectedDate = this.dataset.fecha;
                fechaSeleccionada.value = selectedDate;
                obtenerReservasParaFecha(selectedDate);
            });
            
            dateCarousel.appendChild(dateCard);
            
            diaIteracion.setDate(diaIteracion.getDate() + 1);
        }
        
        const fechaHoyStr = hoy.toISOString().split('T')[0];
        const cardHoy = dateCarousel.querySelector(`.date-card[data-fecha="${fechaHoyStr}"]`);
        if (cardHoy) {
            cardHoy.click();

            setTimeout(() => {
                cardHoy.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }, 100);
        } else {
            const primeraFecha = dateCarousel.querySelector('.date-card');
            if (primeraFecha) primeraFecha.click();
        }
    }
    
    function obtenerReservasParaFecha(fecha) {
        timeSlots.innerHTML = '<div class="mensaje-info"><i class="fas fa-spinner fa-spin"></i> Cargando horarios...</div>';
        
        const trabajadorId = selectedTrabajador ? selectedTrabajador.id : '';
        
        fetch(`obtener-reservas.php?negocio_id=${selectedNegocio.value}&fecha=${fecha}&trabajador_id=${trabajadorId}`)
            .then(response => {
                if (!response.ok) throw new Error('Error al obtener reservas');
                return response.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);
                reservas = data;
                mostrarHorasDisponibles(fecha);
            })
            .catch(error => {
                console.error('Error en obtenerReservasParaFecha:', error);
                timeSlots.innerHTML = '<div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> Error al cargar horarios.</div>';
                reservas = [];
            });
    }
    
    function timeToMinutes(time) {
        if (!time) return 0;
        const [hours, minutes] = time.split(':').map(Number);
        return hours * 60 + minutes;
    }
    
    function minutesToTime(minutes) {
        const hours = Math.floor(minutes / 60).toString().padStart(2, '0');
        const mins = (minutes % 60).toString().padStart(2, '0');
        return `${hours}:${mins}`;
    }

    function mostrarHorasDisponibles(fecha) {
        timeSlots.innerHTML = '';
        if (!selectedNegocio || selectedServices.length === 0 || !duracionSeleccionada.value) {
            timeSlots.innerHTML = '<div class="mensaje-info"><i class="fas fa-info-circle"></i> Selecciona al menos un servicio para ver los horarios.</div>';
            return;
        }

        const tipoReserva = selectedNegocio.dataset.tipo;
        const diaSemana = new Date(fecha).toLocaleString('es-ES', { weekday: 'long' }).toLowerCase();
        const duracionServicio = parseInt(duracionSeleccionada.value);
        if (isNaN(duracionServicio) || duracionServicio <= 0) {
            timeSlots.innerHTML = '<div class="mensaje-error"><i class="fas fa-exclamation-triangle"></i> Duración de servicio inválida.</div>';
            return;
        }

        let horariosDelDia = [];
        try {
            if (selectedTrabajador && selectedTrabajador.horario) {
                const horarioTrabajador = JSON.parse(selectedTrabajador.horario);
                if (horarioTrabajador[diaSemana] && !horarioTrabajador[diaSemana].cerrado) {
                    const rangos = horarioTrabajador[diaSemana].rangos || [];
                    rangos.forEach(rango => {
                        horariosDelDia.push({ inicio: rango.inicio, fin: rango.fin });
                    });
                }
            } else {
                if (tipoReserva === 'normal') {
                    const horarioApertura = JSON.parse(selectedNegocio.dataset.horario);
                    if (horarioApertura[diaSemana] && !horarioApertura[diaSemana].cerrado) {
                        const rangos = horarioApertura[diaSemana].rangos || [];
                        rangos.forEach(rango => {
                            horariosDelDia.push({ inicio: rango.inicio, fin: rango.fin });
                        });
                    }
                } else if (tipoReserva === 'avanzado') {
                    const espaciosReservas = JSON.parse(selectedNegocio.dataset.espacios);
                    if (espaciosReservas[diaSemana]) {
                        const rangos = espaciosReservas[diaSemana] || [];
                        rangos.forEach(rango => {
                            horariosDelDia.push({ inicio: rango.inicio, fin: rango.fin });
                        });
                    }
                }
            }
        } catch (error) {
            console.error('Error al parsear horarios:', error);
            timeSlots.innerHTML = '<div class="mensaje-error"><i class="fas fa-exclamation-triangle"></i> Error en configuración de horarios.</div>';
            return;
        }
        
        if (horariosDelDia.length === 0) {
             timeSlots.innerHTML = '<div class="mensaje-info"><i class="fas fa-info-circle"></i> No hay horarios de atención configurados para este día.</div>';
            return;
        }

        let slotsPosibles = [];
        horariosDelDia.forEach(rango => {
            const inicioRangoMin = timeToMinutes(rango.inicio);
            const finRangoMin = timeToMinutes(rango.fin);
            for (let min = inicioRangoMin; min < finRangoMin; min += 15) {
                const slotFinMin = min + duracionServicio;
                if (slotFinMin <= finRangoMin) {
                    slotsPosibles.push(minutesToTime(min));
                }
            }
        });
        
        const slotsOcupados = new Set();
        reservas.forEach(reserva => {
            try {
                const reservaInicioMin = timeToMinutes(new Date(reserva.fecha_inicio).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }));
                const reservaFinMin = timeToMinutes(new Date(reserva.fecha_fin).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }));

                slotsPosibles.forEach(slot => {
                    const slotInicioMin = timeToMinutes(slot);
                    const slotFinMin = slotInicioMin + duracionServicio;
                    if (Math.max(slotInicioMin, reservaInicioMin) < Math.min(slotFinMin, reservaFinMin)) {
                        slotsOcupados.add(slot);
                    }
                });
            } catch(e) { console.error("Error procesando reserva para ocupado:", reserva, e); }
        });
        

        const slotsDisponibles = slotsPosibles.filter(slot => !slotsOcupados.has(slot));
        slotsDisponibles.sort(); 

        if (slotsDisponibles.length === 0) {
            timeSlots.innerHTML = '<div class="mensaje-error"><i class="fas fa-calendar-times"></i> No quedan horarios disponibles para este servicio en el día seleccionado.</div>';
            return;
        }

        slotsDisponibles.forEach(hora => {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            timeSlot.textContent = hora;
            timeSlot.dataset.hora = hora;
            timeSlot.addEventListener('click', function() {
                document.querySelectorAll('.time-slot').forEach(slot => slot.classList.remove('selected'));
                this.classList.add('selected');
                horaSeleccionada.value = this.dataset.hora;
                clienteContainer.style.display = 'block';
            });
            timeSlots.appendChild(timeSlot);
        });
    }
    
    function mostrarServicios() {
        categoryTabs.innerHTML = '';
        serviciosList.innerHTML = '';
        
        if (!selectedNegocio) return;
        
        try {
            serviciosData = JSON.parse(selectedNegocio.dataset.servicios || '{}');
            
            const categorias = Object.keys(serviciosData);
            if (categorias.length === 0) {
                serviciosContainer.innerHTML = '<div class="mensaje-info"><i class="fas fa-info-circle"></i> Este negocio aún no tiene servicios configurados.</div>';
                return;
            }
            
            categorias.forEach((catKey, index) => {
                const categoria = serviciosData[catKey];
                const tab = document.createElement('button');
                tab.className = 'category-tab';
                tab.textContent = categoria.nombre || 'Categoría sin nombre';
                tab.dataset.catKey = catKey;
                tab.type = 'button';
                
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    mostrarServiciosDeCategoria(this.dataset.catKey);
                });
                
                categoryTabs.appendChild(tab);
                
                if (index === 0) {
                    tab.classList.add('active');
                    mostrarServiciosDeCategoria(catKey);
                }
            });
            
        } catch (error) {
            console.error('Error al parsear servicios:', error);
            serviciosContainer.innerHTML = '<div class="mensaje-error"><i class="fas fa-exclamation-triangle"></i> Error al cargar servicios.</div>';
        }
    }

    function mostrarServiciosDeCategoria(catKey) {
        serviciosList.innerHTML = '';
        const categoria = serviciosData[catKey];
        if (!categoria || !categoria.servicios) return;
        
        const servicios = categoria.servicios;
        let serviciosEncontrados = false;

        const serviciosArray = Array.isArray(servicios) 
            ? servicios 
            : (typeof servicios === 'object' && servicios !== null ? Object.values(servicios) : []);
        
        serviciosArray.forEach(servicio => {
            if (servicio && servicio.nombre) {
                serviciosEncontrados = true;
                const servicioItem = document.createElement('div');
                servicioItem.className = 'servicio-item';
                
                servicioItem.innerHTML = `
                    <div class="servicio-nombre">${htmlspecialchars(servicio.nombre)}</div>
                    <div class="servicio-info">
                        <span class="servicio-duration"><i class="far fa-clock"></i> ${servicio.duracion ? servicio.duracion + ' min' : 'N/A'}</span>
                        <span class="servicio-price"><i class="fas fa-tag"></i> ${servicio.precio ? servicio.precio + ' €' : 'N/A'}</span>
                    </div>
                `;
                
                servicioItem.addEventListener('click', function() {
                    const servicioId = `serv_${Math.random().toString(36).substr(2, 9)}`;
                    const isSelected = this.classList.contains('selected');
                    
                    if (isSelected) {
                        this.classList.remove('selected');
                        selectedServices = selectedServices.filter(s => s.id !== this.dataset.servicioId);
                        delete this.dataset.servicioId;
                    } else {
                        this.classList.add('selected');
                        this.dataset.servicioId = servicioId;
                        selectedServices.push({
                            id: servicioId,
                            nombre: servicio.nombre,
                            precio: parseFloat(servicio.precio) || 0,
                            duracion: parseInt(servicio.duracion) || 60,
                            categoria: categoria.nombre
                        });
                    }
                    
                    actualizarServiciosSeleccionados();
                    
                    if (selectedServices.length > 0) {
                        trabajadorContainer.style.display = 'block';
                        cargarTrabajadores();
                    } else {
                        trabajadorContainer.style.display = 'none';
                        fechaHoraContainer.style.display = 'none';
                        clienteContainer.style.display = 'none';
                    }
                });
                
                serviciosList.appendChild(servicioItem);
            }
        });
        
        if (!serviciosEncontrados) {
            serviciosList.innerHTML = '<div class="mensaje-info"><i class="fas fa-info-circle"></i> No hay servicios definidos en esta categoría.</div>';
        }
    }
    
    function actualizarServiciosSeleccionados() {
        const serviciosSeleccionadosDiv = document.getElementById('serviciosSeleccionados');
        const serviciosSeleccionadosLista = document.getElementById('serviciosSeleccionadosLista');
        const totalPrecio = document.getElementById('totalPrecio');
        const totalDuracion = document.getElementById('totalDuracion');
        
        if (selectedServices.length === 0) {
            serviciosSeleccionadosDiv.style.display = 'none';
            serviciosMultiples.value = '';
            servicioSeleccionado.value = '';
            duracionSeleccionada.value = '';
            precioSeleccionado.value = '';
            return;
        }
        
        serviciosSeleccionadosDiv.style.display = 'block';
        serviciosSeleccionadosLista.innerHTML = '';
        
        let totalPrice = 0;
        let totalTime = 0;
        let nombresCombinados = [];
        
        selectedServices.forEach(servicio => {
            totalPrice += servicio.precio;
            totalTime += servicio.duracion;
            nombresCombinados.push(servicio.nombre);
            
            const servicioDiv = document.createElement('div');
            servicioDiv.className = 'servicio-seleccionado-item';
            servicioDiv.innerHTML = `
                <span class="servicio-nombre">${htmlspecialchars(servicio.nombre)}</span>
                <span class="servicio-detalles">${servicio.duracion} min - ${servicio.precio} €</span>
                <button type="button" class="btn-eliminar" onclick="eliminarServicioSeleccionado('${servicio.id}')">&times;</button>
            `;
            serviciosSeleccionadosLista.appendChild(servicioDiv);
        });
        
        totalPrecio.textContent = totalPrice.toFixed(2);
        totalDuracion.textContent = totalTime;
        
        serviciosMultiples.value = JSON.stringify(selectedServices);
        servicioSeleccionado.value = nombresCombinados.join(', ');
        duracionSeleccionada.value = totalTime;
        precioSeleccionado.value = totalPrice.toFixed(2);
    }
    
    window.eliminarServicioSeleccionado = function(servicioId) {
        selectedServices = selectedServices.filter(s => s.id !== servicioId);
        
        document.querySelectorAll('.servicio-item').forEach(item => {
            if (item.dataset.servicioId === servicioId) {
                item.classList.remove('selected');
                delete item.dataset.servicioId;
            }
        });
        
        actualizarServiciosSeleccionados();
        
        if (selectedServices.length === 0) {
            trabajadorContainer.style.display = 'none';
            fechaHoraContainer.style.display = 'none';
            clienteContainer.style.display = 'none';
        }
    }
    
    function cargarClientes() {
        if (!selectedNegocio) return;
        
        clientesItems.innerHTML = '<div class="mensaje-info"><i class="fas fa-spinner fa-spin"></i> Cargando clientes...</div>';
        
        fetch(`obtener-clientes.php?negocio_id=${selectedNegocio.value}`)
            .then(response => {
                if (!response.ok) throw new Error('Error al obtener clientes');
                return response.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);
                clientes = data; 
                mostrarClientes();
            })
            .catch(error => {
                console.error('Error al cargarClientes:', error);
                clientesItems.innerHTML = '<div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> Error al cargar la lista de clientes.</div>';
            });
    }
    
    function mostrarClientes(filtro = '') {
        clientesItems.innerHTML = '';
        const filtroLower = filtro.toLowerCase();
        
        if (!Array.isArray(clientes)) {
             clientesItems.innerHTML = '<div class="mensaje-error"><i class="fas fa-exclamation-circle"></i> Error: Datos de cliente inválidos.</div>';
             return;
        }
        
        const clientesFiltrados = filtroLower 
            ? clientes.filter(cliente => 
                (cliente.nombre && cliente.nombre.toLowerCase().includes(filtroLower)) || 
                (cliente.apellidos && cliente.apellidos.toLowerCase().includes(filtroLower)) ||
                (cliente.telefono && cliente.telefono.includes(filtroLower)) ||
                (cliente.email && cliente.email.toLowerCase().includes(filtroLower)))
            : clientes;
        
        if (clientesFiltrados.length === 0) {
            clientesItems.innerHTML = filtroLower 
                ? '<div class="mensaje-info"><i class="fas fa-search"></i> No se encontraron clientes con ese filtro.</div>' 
                : '<div class="mensaje-info"><i class="fas fa-users"></i> Aún no hay clientes registrados para este negocio. Puedes crear uno nuevo.</div>';
            return;
        }
        
        clientesFiltrados.forEach(cliente => {
            const clienteItem = document.createElement('div');
            clienteItem.className = 'cliente-item';
            clienteItem.dataset.clienteId = cliente.cliente_id || ''; 
            clienteItem.dataset.usuarioId = cliente.usuario_id || ''; 
            
            const nombreCompleto = cliente.apellidos 
                ? `${cliente.nombre || ''} ${cliente.apellidos || ''}`.trim() 
                : (cliente.nombre || 'Sin nombre');
            
            clienteItem.innerHTML = `
                <div>
                    <strong>${htmlspecialchars(nombreCompleto)}</strong> 
                    ${cliente.tiene_cuenta ? '<i class="fas fa-check-circle" title="Tiene cuenta registrada" style="color:var(--color-green); margin-left: 5px;"></i>' : ''}
                </div>
                <div><i class="fas fa-phone-alt" style="margin-right: 5px; color:#999;"></i> ${htmlspecialchars(cliente.telefono || 'N/A')}</div>
                <div><i class="fas fa-envelope" style="margin-right: 5px; color:#999;"></i> ${htmlspecialchars(cliente.email || 'N/A')}</div>
            `;
            
            clienteItem.addEventListener('click', function() {
                document.querySelectorAll('#clientesItems .cliente-item').forEach(item => item.classList.remove('selected'));
                this.classList.add('selected');
                clienteSeleccionado.value = this.dataset.clienteId;
                usuarioIdSeleccionado.value = this.dataset.usuarioId;
            });
            
            clientesItems.appendChild(clienteItem);
        });
    }
    
    function handleNegocioChange() {
        if (negocioSelect.value) {
            selectedNegocio = negocioSelect.options[negocioSelect.selectedIndex];
            serviciosContainer.style.display = 'block';
            trabajadorContainer.style.display = 'none';
            fechaHoraContainer.style.display = 'none';
            clienteContainer.style.display = 'none';
            selectedServices = [];
            selectedTrabajador = null;
            serviciosMultiples.value = '';
            servicioSeleccionado.value = '';
            duracionSeleccionada.value = '';
            precioSeleccionado.value = '';
            trabajadorSeleccionado.value = '';
            selectedDate = null;
            fechaSeleccionada.value = '';
            horaSeleccionada.value = '';
            
            document.querySelectorAll('.servicio-item').forEach(item => {
                item.classList.remove('selected');
                delete item.dataset.servicioId;
            });
            actualizarServiciosSeleccionados();
            
            mostrarServicios();
            cargarClientes();
        } else {
            serviciosContainer.style.display = 'none';
            trabajadorContainer.style.display = 'none';
            fechaHoraContainer.style.display = 'none';
            clienteContainer.style.display = 'none';
        }
    }
    
    negocioSelect.addEventListener('change', handleNegocioChange);
    
    if (negocioSelect.options.length > 1) { 
        negocioSelect.selectedIndex = 1;
        
        selectedNegocio = negocioSelect.options[negocioSelect.selectedIndex];
        handleNegocioChange();
    }
    
    buscarCliente.addEventListener('input', function() {
        mostrarClientes(this.value);
    });
    
    document.querySelectorAll('.cliente-opcion').forEach(opcion => {
        opcion.addEventListener('click', function() {
            document.querySelectorAll('.cliente-opcion').forEach(item => item.classList.remove('selected'));
            this.classList.add('selected');
            const tipo = this.dataset.tipo;
            tipoClienteSeleccionado.value = tipo;
            
            nuevoClienteForm.classList.toggle('visible', tipo === 'nuevo');
            clientesLista.classList.toggle('visible', tipo === 'existente');
            
            if (tipo !== 'existente') {
               document.querySelectorAll('#clientesItems .cliente-item.selected').forEach(i => i.classList.remove('selected'));
               clienteSeleccionado.value = '';
               usuarioIdSeleccionado.value = '';
            }
            if (tipo !== 'nuevo') {
                nuevoClienteForm.querySelectorAll('input').forEach(i => i.value = '');
            }
        });
    });
    
    const reservaForm = document.getElementById('reservaForm');
    reservaForm.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!fechaSeleccionada.value || !horaSeleccionada.value || selectedServices.length === 0) {
            Swal.fire('Campos incompletos', 'Por favor, completa la selección de negocio, servicios, fecha y hora.', 'warning');
            return;
        }
        const tipoCliente = tipoClienteSeleccionado.value;
        if (!tipoCliente) {
            Swal.fire('Selección pendiente', 'Debes seleccionar una opción en el Paso 5 (Asignar Cliente) antes de continuar.', 'warning');

            clienteContainer.scrollIntoView({ behavior: 'smooth' });
            return;
        }
        if (tipoCliente === 'nuevo') {
            const nombre = document.getElementById('nombre').value;
            const telefono = document.getElementById('telefono').value;
            if (!nombre || !telefono) {
                Swal.fire('Datos incompletos', 'Para crear un nuevo cliente, el nombre y el teléfono son obligatorios.', 'warning');
                return;
            }
        } else if (tipoCliente === 'existente') {
            if (!clienteSeleccionado.value && !usuarioIdSeleccionado.value) {
                Swal.fire('Selección pendiente', 'Por favor, selecciona un cliente de la lista.', 'warning');
                return;
            }
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        
        const formData = new FormData(reservaForm);
        
        formData.append('negocio_id', negocioSelect.value);
        formData.append('servicios_multiples', serviciosMultiples.value);
        formData.append('servicio', servicioSeleccionado.value);
        formData.append('duracion', duracionSeleccionada.value);
        formData.append('precio', precioSeleccionado.value);
        formData.append('id_trabajador', trabajadorSeleccionado.value);
        formData.append('fecha', fechaSeleccionada.value);
        formData.append('hora', horaSeleccionada.value);
        formData.append('tipo_cliente', tipoClienteSeleccionado.value);
        formData.append('cliente_id', clienteSeleccionado.value);
        formData.append('usuario_id', usuarioIdSeleccionado.value); 

        fetch('procesar-reserva.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Reserva Guardada!',
                    text: data.message,
                    timer: 2000, 
                    timerProgressBar: true,
                    showConfirmButton: false
                }).then(() => {

                    window.location.href = '/panel/reservas/calendario';
                });
            } else {

                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar',
                    text: data.message || 'Ocurrió un error inesperado.'
                });
            }
        })
        .catch(error => {
            console.error('Error en fetch:', error);
            Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor. Inténtalo de nuevo.', 'error');
        })
        .finally(() => {

            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Confirmar y Añadir Reserva';
        });
    });

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return str;
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return str.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
    </script>
</body>
</html>