<?php
require_once __DIR__ . "/../../../src/sesiones-seguras.php";

session_start();

require_once __DIR__ . "/../../../src/rate-limiting.php";
require_once __DIR__ . "/../../../src/headers-seguridad.php";


require_once '../../../../config.php';
require_once '../../../../db-publica.php';

use Delight\Auth\Auth;
$auth = new Auth($pdo);
$user_id = $auth->getUserId();

require_once __DIR__ . "/../../../src/verificar-logeado.php";
require_once __DIR__ . "/../../../src/verificar-rol-negocio.php";


$stmt_negocios = $pdo2->prepare("SELECT negocio_id, nombre, reservas, tipo_reserva, pago_reservas, espacios_reservas FROM negocios WHERE usuario_id = ?");
$stmt_negocios->bindParam(1, $user_id, PDO::PARAM_INT);
$stmt_negocios->execute();
$negocios_data = $stmt_negocios->fetchAll(PDO::FETCH_ASSOC);

if (empty($negocios_data)) {
    header("Location: /panel/anade-tu-negocio.php");
    exit; 
}

$negocio_id = isset($_POST['negocio_id']) ? (int)$_POST['negocio_id'] : (int)$negocios_data[0]['negocio_id'];
$negocio_seleccionado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_ajustes'])) {
    try {
        $reservas = isset($_POST['reservas']) ? 1 : 0;
        $tipo_reserva = filter_input(INPUT_POST, 'tipo_reserva', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $pago_reservas = filter_input(INPUT_POST, 'pago_reservas', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        $espacios_reservas = '{}';
        if ($tipo_reserva === 'avanzado' && isset($_POST['slots'])) {
            $slots = $_POST['slots'];
            $espacios_json = [];
            
            $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            
            foreach ($dias as $dia) {
                $espacios_json[$dia] = [];
                
                if (isset($slots[$dia]) && is_array($slots[$dia])) {
                    foreach ($slots[$dia] as $index => $slot) {
                        if (!empty($slot['inicio']) && !empty($slot['fin'])) {
                            $espacios_json[$dia][] = [
                                'inicio' => $slot['inicio'],
                                'fin' => $slot['fin']
                            ];
                        }
                    }
                }
            }
            
            $espacios_reservas = json_encode($espacios_json, JSON_UNESCAPED_UNICODE);
        }
        
        $stmt_verificar = $pdo2->prepare("SELECT negocio_id FROM negocios WHERE negocio_id = ? AND usuario_id = ?");
        $stmt_verificar->bindParam(1, $negocio_id, PDO::PARAM_INT);
        $stmt_verificar->bindParam(2, $user_id, PDO::PARAM_INT);
        $stmt_verificar->execute();
        
        if ($stmt_verificar->rowCount() > 0) {
            $stmt_update = $pdo2->prepare("UPDATE negocios SET reservas = ?, tipo_reserva = ?, pago_reservas = ?, espacios_reservas = ? WHERE negocio_id = ?");
            $stmt_update->bindParam(1, $reservas, PDO::PARAM_INT);
            $stmt_update->bindParam(2, $tipo_reserva, PDO::PARAM_STR);
            $stmt_update->bindParam(3, $pago_reservas, PDO::PARAM_STR);
            $stmt_update->bindParam(4, $espacios_reservas, PDO::PARAM_STR);
            $stmt_update->bindParam(5, $negocio_id, PDO::PARAM_INT);
            $stmt_update->execute();
            
            $notification = [
                'type' => 'success',
                'message' => 'Ajustes guardados correctamente.'
            ];
            
            foreach ($negocios_data as &$negocio) {
                if ($negocio['negocio_id'] == $negocio_id) {
                    $negocio['reservas'] = $reservas;
                    $negocio['tipo_reserva'] = $tipo_reserva;
                    $negocio['pago_reservas'] = $pago_reservas;
                    $negocio['espacios_reservas'] = $espacios_reservas;
                }
            }
        } else {
            $notification = [
                'type' => 'error',
                'message' => 'No tienes permiso para modificar este negocio.'
            ];
        }
    } catch (Exception $e) {
        $notification = [
            'type' => 'error',
            'message' => 'Error al guardar los ajustes: ' . $e->getMessage()
        ];
    }
}

foreach ($negocios_data as $negocio) {
    if ($negocio['negocio_id'] == $negocio_id) {
        $negocio_seleccionado = $negocio;
        break;
    }
}

$timeslots = [];
if ($negocio_seleccionado && !empty($negocio_seleccionado['espacios_reservas'])) {
    $timeslots = json_decode($negocio_seleccionado['espacios_reservas'], true);
}
if (empty($timeslots)) {
    $timeslots = [
        'lunes' => [], 'martes' => [], 'miercoles' => [],
        'jueves' => [], 'viernes' => [], 'sabado' => [], 'domingo' => []
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes de Reservas</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container45">
        <?php include_once '../../../assets/includes/sidebar.php'; ?>
        
        <div id="content45" class="content45">
            <div class="ajustes-reservas-container">
                <h1>Ajustes de Reservas</h1>
                
                <form method="POST" action="" id="ajustes-form">
                    <?php if(count($negocios_data) > 1): ?>
                    <div class="seccion">
                        <h2>Seleccionar Negocio</h2>
                        <div class="campo">
                            <label for="negocio_id">Negocio:</label>
                            <select name="negocio_id" id="negocio_id" onchange="this.form.submit()">
                                <?php foreach($negocios_data as $negocio): ?>
                                    <option value="<?php echo htmlspecialchars($negocio['negocio_id']); ?>" <?php echo ($negocio_id == $negocio['negocio_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($negocio['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="negocio_id" value="<?php echo htmlspecialchars($negocio_seleccionado['negocio_id']); ?>">
                    <?php endif; ?>
                    
                    <div class="seccion">
                        <h2>Estado de Reservas</h2>
                        <div class="campo switch-container">
                            <span>Activar reservas:</span>
                            <label class="switch">
                                <input type="checkbox" name="reservas" id="reservas" <?php echo (isset($negocio_seleccionado['reservas']) && $negocio_seleccionado['reservas'] == 1) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="seccion">
                        <h2>Tipo de Reserva</h2>
                        <div class="campo radio-grupo">
                            <div class="radio-option">
                                <input type="radio" name="tipo_reserva" id="tipo_normal" value="normal" <?php echo (!isset($negocio_seleccionado['tipo_reserva']) || $negocio_seleccionado['tipo_reserva'] == 'normal') ? 'checked' : ''; ?>>
                                <label for="tipo_normal">Normal</label>
                                <p class="descripcion">Los clientes pueden reservar a cualquier hora dentro del horario de apertura.</p>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="tipo_reserva" id="tipo_avanzado" value="avanzado" <?php echo (isset($negocio_seleccionado['tipo_reserva']) && $negocio_seleccionado['tipo_reserva'] == 'avanzado') ? 'checked' : ''; ?>>
                                <label for="tipo_avanzado">Avanzado</label>
                                <p class="descripcion">Define horarios específicos (slots) para las reservas.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="seccion" id="seccion-slots" style="<?php echo (isset($negocio_seleccionado['tipo_reserva']) && $negocio_seleccionado['tipo_reserva'] == 'avanzado') ? '' : 'display: none;'; ?>">
                        <h2>Configuración de Horarios Disponibles</h2>
                        
                        <div class="copy-slots-container">
                            <h3><i class="fas fa-clone"></i> Copiar horarios entre días</h3>
                            
                            <div class="copy-slots-selectors">
                                <div class="campo">
                                    <label for="origen-dia">Selecciona el día de origen:</label>
                                    <select id="origen-dia" class="form-select">
                                        <option value="lunes">Lunes</option>
                                        <option value="martes">Martes</option>
                                        <option value="miercoles">Miércoles</option>
                                        <option value="jueves">Jueves</option>
                                        <option value="viernes">Viernes</option>
                                        <option value="sabado">Sábado</option>
                                        <option value="domingo">Domingo</option>
                                    </select>
                                </div>
                                
                                <div class="campo dias-destino-container">
                                    <label>Selecciona los días de destino:</label>
                                    <div class="checkbox-grupo">
                                        <input type="checkbox" id="dia-lunes" class="dia-destino" value="lunes">
                                        <label for="dia-lunes">Lunes</label>
                                        
                                        <input type="checkbox" id="dia-martes" class="dia-destino" value="martes">
                                        <label for="dia-martes">Martes</label>
                                        
                                        <input type="checkbox" id="dia-miercoles" class="dia-destino" value="miercoles">
                                        <label for="dia-miercoles">Miércoles</label>
                                        
                                        <input type="checkbox" id="dia-jueves" class="dia-destino" value="jueves">
                                        <label for="dia-jueves">Jueves</label>
                                        
                                        <input type="checkbox" id="dia-viernes" class="dia-destino" value="viernes">
                                        <label for="dia-viernes">Viernes</label>
                                        
                                        <input type="checkbox" id="dia-sabado" class="dia-destino" value="sabado">
                                        <label for="dia-sabado">Sábado</label>
                                        
                                        <input type="checkbox" id="dia-domingo" class="dia-destino" value="domingo">
                                        <label for="dia-domingo">Domingo</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="copiar-btn-container">
                                <button type="button" id="copiar-slots" class="boton">
                                    <i class="fas fa-copy"></i> Copiar Horarios
                                </button>
                            </div>
                        </div>
                        
                        <div class="tabs-container">
                            <div class="tabs">
                                <button type="button" class="tab-btn active" data-dia="lunes">Lunes</button>
                                <button type="button" class="tab-btn" data-dia="martes">Martes</button>
                                <button type="button" class="tab-btn" data-dia="miercoles">Miércoles</button>
                                <button type="button" class="tab-btn" data-dia="jueves">Jueves</button>
                                <button type="button" class="tab-btn" data-dia="viernes">Viernes</button>
                                <button type="button" class="tab-btn" data-dia="sabado">Sábado</button>
                                <button type="button" class="tab-btn" data-dia="domingo">Domingo</button>
                            </div>
                            
                            <div class="tab-content">
                                <?php foreach(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'] as $i => $dia): ?>
                                    <div class="tab-pane <?php echo $dia === 'lunes' ? 'active' : ''; ?>" id="<?php echo $dia; ?>-tab">
                                        <div class="slots-container" id="<?php echo $dia; ?>-slots">
                                            <?php if(!empty($timeslots[$dia])): ?>
                                                <?php foreach($timeslots[$dia] as $j => $slot): ?>
                                                    <div class="slot-row">
                                                        <div class="campo inicio-container">
                                                            <label>Inicio:</label>
                                                            <input type="time" name="slots[<?php echo $dia; ?>][<?php echo $j; ?>][inicio]" value="<?php echo htmlspecialchars($slot['inicio']); ?>">
                                                        </div>
                                                        <div class="campo">
                                                            <label>Fin:</label>
                                                            <input type="time" name="slots[<?php echo $dia; ?>][<?php echo $j; ?>][fin]" value="<?php echo htmlspecialchars($slot['fin']); ?>">
                                                        </div>
                                                        <button type="button" class="eliminar-slot boton-icono">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="slot-row">
                                                    <div class="campo inicio-container">
                                                        <label>Inicio:</label>
                                                        <input type="time" name="slots[<?php echo $dia; ?>][0][inicio]">
                                                    </div>
                                                    <div class="campo">
                                                        <label>Fin:</label>
                                                        <input type="time" name="slots[<?php echo $dia; ?>][0][fin]">
                                                    </div>
                                                    <button type="button" class="eliminar-slot boton-icono">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="agregar-slot boton boton-outline" data-dia="<?php echo $dia; ?>">
                                            <i class="fas fa-plus"></i> Agregar Slot
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="seccion">
                        <h2>Opciones de Pago</h2>
                        <div class="campo radio-grupo">
                            <div class="radio-option">
                                <input type="radio" name="pago_reservas" id="pago_si" value="si" <?php echo (isset($negocio_seleccionado['pago_reservas']) && $negocio_seleccionado['pago_reservas'] == 'si') ? 'checked' : ''; ?>>
                                <label for="pago_si">Pago por adelantado</label>
                                <p class="descripcion">Los clientes deben pagar en el momento de hacer la reserva.</p>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="pago_reservas" id="pago_no" value="no" <?php echo (isset($negocio_seleccionado['pago_reservas']) && $negocio_seleccionado['pago_reservas'] == 'no') ? 'checked' : ''; ?>>
                                <label for="pago_no">Pago en establecimiento</label>
                                <p class="descripcion">Los clientes pagan directamente en el establecimiento.</p>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="pago_reservas" id="pago_ambas" value="ambas" <?php echo (isset($negocio_seleccionado['pago_reservas']) && $negocio_seleccionado['pago_reservas'] == 'ambas') ? 'checked' : ''; ?>>
                                <label for="pago_ambas">Ambas opciones</label>
                                <p class="descripcion">Los clientes pueden elegir entre pagar por adelantado o en el establecimiento.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="seccion botones">
                        <button type="submit" name="guardar_ajustes" class="boton boton-primary">
                            <i></i> Guardar Ajustes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/assets/js/sidebar.js"></script>
    <script>
        const notification = <?php echo isset($notification) ? json_encode($notification) : 'null'; ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            if (notification) {
                Swal.fire({
                    icon: notification.type,
                    title: notification.type === 'success' ? 'Éxito' : 'Error',
                    text: notification.message,
                    showConfirmButton: true,
                    timer: 3000
                });
            }

            const tipoNormal = document.getElementById('tipo_normal');
            const tipoAvanzado = document.getElementById('tipo_avanzado');
            const seccionSlots = document.getElementById('seccion-slots');
            
            function actualizarVisibilidadSlots() {
                if (tipoAvanzado.checked) {
                    seccionSlots.style.display = '';
                } else {
                    seccionSlots.style.display = 'none';
                }
            }
            
            tipoNormal.addEventListener('change', actualizarVisibilidadSlots);
            tipoAvanzado.addEventListener('change', actualizarVisibilidadSlots);
            
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const dia = this.getAttribute('data-dia');
                    
                    tabBtns.forEach(b => b.classList.remove('active'));
                    tabPanes.forEach(p => p.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(dia + '-tab').classList.add('active');
                });
            });
            
            document.querySelectorAll('.agregar-slot').forEach(btn => {
                btn.addEventListener('click', function() {
                    const dia = this.getAttribute('data-dia');
                    const slotsContainer = document.getElementById(dia + '-slots');
                    const slotCount = slotsContainer.querySelectorAll('.slot-row').length;
                    
                    let horaInicio = '';
                    let horaFin = '';
                    
                    if (slotCount > 0) {
                        const ultimoSlot = slotsContainer.querySelectorAll('.slot-row')[slotCount - 1];
                        const ultimaHoraFin = ultimoSlot.querySelector('input[name*="[fin]"]').value;
                        
                        if (ultimaHoraFin) {
                            horaInicio = ultimaHoraFin;
                            
                            try {
                                const [horas, minutos] = ultimaHoraFin.split(':').map(Number);
                                let nuevaHora = horas + 1;
                                if (nuevaHora > 23) nuevaHora = 23;
                                horaFin = `${nuevaHora.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}`;
                            } catch(e) {
                                horaFin = '';
                            }
                        }
                    }
                    
                    const newSlot = document.createElement('div');
                    newSlot.className = 'slot-row';
                    newSlot.innerHTML = `
                        <div class="campo inicio-container">
                            <label>Inicio:</label>
                            <input type="time" name="slots[${dia}][${slotCount}][inicio]" value="${horaInicio}">
                        </div>
                        <div class="campo">
                            <label>Fin:</label>
                            <input type="time" name="slots[${dia}][${slotCount}][fin]" value="${horaFin}">
                        </div>
                        <button type="button" class="eliminar-slot boton-icono">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                    
                    slotsContainer.appendChild(newSlot);
                    
                    newSlot.querySelector('.eliminar-slot').addEventListener('click', eliminarSlot);
                });
            });
            
            function eliminarSlot() {
                const slotRow = this.closest('.slot-row');
                const slotsContainer = slotRow.parentNode;
                
                if (slotsContainer.querySelectorAll('.slot-row').length > 1) {
                    slotRow.remove();
                    
                    const dia = slotsContainer.id.replace('-slots', '');
                    const slots = slotsContainer.querySelectorAll('.slot-row');
                    
                    slots.forEach((slot, index) => {
                        slot.querySelectorAll('input').forEach(input => {
                            const name = input.name;
                            const newName = name.replace(/\[\d+\]/, `[${index}]`);
                            input.name = newName;
                        });
                    });
                } else {
                    slotRow.querySelectorAll('input').forEach(input => {
                        input.value = '';
                    });
                }
            }
            
            document.querySelectorAll('.eliminar-slot').forEach(btn => {
                btn.addEventListener('click', eliminarSlot);
            });
            
            document.getElementById('copiar-slots').addEventListener('click', function() {
                const diaOrigen = document.getElementById('origen-dia').value;
                const slotsOrigen = document.getElementById(diaOrigen + '-slots');
                
                const diasDestino = Array.from(document.querySelectorAll('.dia-destino:checked'))
                    .map(checkbox => checkbox.value);
                
                if (diasDestino.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atención',
                        text: 'Selecciona al menos un día de destino',
                        showConfirmButton: true
                    });
                    return;
                }
                
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `¿Quieres copiar los slots del ${diaOrigen} a los días seleccionados?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, copiar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        diasDestino.forEach(diaDestino => {
                            if (diaDestino !== diaOrigen) {
                                const slotsDestino = document.getElementById(diaDestino + '-slots');
                                slotsDestino.innerHTML = '';
                                
                                Array.from(slotsOrigen.querySelectorAll('.slot-row')).forEach((slot, index) => {
                                    const newSlot = document.createElement('div');
                                    newSlot.className = 'slot-row';
                                    
                                    const inicioValor = slot.querySelector('input[name*="[inicio]"]').value;
                                    const finValor = slot.querySelector('input[name*="[fin]"]').value;
                                    
                                    newSlot.innerHTML = `
                                        <div class="campo inicio-container">
                                            <label>Inicio:</label>
                                            <input type="time" name="slots[${diaDestino}][${index}][inicio]" value="${inicioValor}">
                                        </div>
                                        <div class="campo">
                                            <label>Fin:</label>
                                            <input type="time" name="slots[${diaDestino}][${index}][fin]" value="${finValor}">
                                        </div>
                                        <button type="button" class="eliminar-slot boton-icono">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    `;
                                    
                                    slotsDestino.appendChild(newSlot);
                                    newSlot.querySelector('.eliminar-slot').addEventListener('click', eliminarSlot);
                                });
                            }
                        });
                        
                        Swal.fire({
                            icon: 'success',
                            title: '¡Copiado!',
                            text: 'Los slots se han copiado correctamente',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    }
                });
            });
            
            document.getElementById('negocio_id')?.addEventListener('change', function(e) {
                const form = document.getElementById('ajustes-form');
                form.querySelector('button[name="guardar_ajustes"]').style.display = 'none';
                form.submit();
            });
        });
    </script>
</body>
</html>