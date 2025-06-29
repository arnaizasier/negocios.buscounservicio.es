<?php
require_once 'conexion.php';

$auth = new \Delight\Auth\Auth($pdo);

$usuario_id = verificarUsuarioAutenticado($auth);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index');
    exit();
}

$negocio_id = intval($_GET['id']);

$stmt = $pdo2->prepare("SELECT * FROM negocios WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id");
$stmt->execute([':negocio_id' => $negocio_id, ':usuario_id' => $usuario_id]);
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    header('Location: index');
    exit();
}

$horario_apertura = [];
if (!empty($negocio['horario_apertura'])) {
    $horario_apertura = json_decode($negocio['horario_apertura'], true) ?: [];
}

$dias = [
    'lunes' => 'Lunes',
    'martes' => 'Martes',
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes',
    'sabado' => 'Sábado',
    'domingo' => 'Domingo'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['no_horario'])) {
        $stmt = $pdo2->prepare("UPDATE negocios SET horario_apertura = NULL WHERE negocio_id = :negocio_id");
        $stmt->execute([':negocio_id' => $negocio_id]);
        
        header("Location: paso7?id=$negocio_id");
        exit();
    } elseif (isset($_POST['horario'])) {
        $horarios = $_POST['horario'];
        $horario_nuevo = [];
        
        foreach ($horarios as $dia => $datos) {
            $cerrado = isset($datos['cerrado']) && $datos['cerrado'] === 'true';
            
            if ($cerrado) {
                $horario_nuevo[$dia] = ['cerrado' => true];
            } else {
                if (isset($datos['rangos']) && is_array($datos['rangos'])) {
                    $rangos = [];
                    foreach ($datos['rangos'] as $rango) {
                        if (!empty($rango['inicio']) && !empty($rango['fin'])) {
                            $rangos[] = [
                                'inicio' => sanitizarInput($rango['inicio']),
                                'fin' => sanitizarInput($rango['fin'])
                            ];
                        }
                    }
                    
                    if (!empty($rangos)) {
                        $horario_nuevo[$dia] = ['rangos' => $rangos];
                    }
                }
            }
        }
        
        $horario_json = json_encode($horario_nuevo);
        
        $stmt = $pdo2->prepare("UPDATE negocios SET horario_apertura = :horario_apertura WHERE negocio_id = :negocio_id");
        $stmt->execute([
            ':horario_apertura' => $horario_json,
            ':negocio_id' => $negocio_id
        ]);
        
        header("Location: paso7?id=$negocio_id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Negocio - Horario</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/paso6.css">
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Horario</h2>
            
            <form method="POST" action="">
                <button type="submit" name="no_horario" class="btn-no-horario">No tengo horario</button>
                <div class="horario-container">
                    <?php foreach ($dias as $dia_key => $dia_nombre): ?>
                    <div class="horario-dia" id="horario-<?php echo $dia_key; ?>">
                        <div class="dia-header">
                            <h4><i class="far fa-calendar-alt"></i> <?php echo $dia_nombre; ?></h4>
                            <div class="dia-controles">
                                <label for="abierto-<?php echo $dia_key; ?>" class="switch">
                                    <input type="checkbox" id="abierto-<?php echo $dia_key; ?>" class="toggle-abierto" 
                                           <?php echo (!isset($horario_apertura[$dia_key]['cerrado']) || $horario_apertura[$dia_key]['cerrado'] !== true) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="toggle-label">Abierto</span>
                            </div>
                        </div>
                        
                        <div class="horario-rangos" id="rangos-<?php echo $dia_key; ?>" 
                             <?php echo (isset($horario_apertura[$dia_key]['cerrado']) && $horario_apertura[$dia_key]['cerrado'] === true) ? 'style="display:none;"' : ''; ?>>
                            
                            <?php 
                            $rangos = [];
                            if (isset($horario_apertura[$dia_key]['rangos']) && is_array($horario_apertura[$dia_key]['rangos'])) {
                                $rangos = $horario_apertura[$dia_key]['rangos'];
                            } elseif (!isset($horario_apertura[$dia_key]['cerrado']) || $horario_apertura[$dia_key]['cerrado'] !== true) {
                                $rangos = [['inicio' => '09:00', 'fin' => '18:00']];
                            }
                            
                            if (!empty($rangos)): 
                                foreach ($rangos as $index => $rango): 
                            ?>
                            <div class="rango-horario">
                                <select class="hora-inicio" name="horario[<?php echo $dia_key; ?>][rangos][<?php echo $index; ?>][inicio]">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                        <?php for ($m = 0; $m < 60; $m += 15): ?>
                                            <?php 
                                                $hora = sprintf('%02d:%02d', $h, $m);
                                                $selected = ($hora === $rango['inicio']) ? 'selected' : '';
                                            ?>
                                            <option value="<?php echo $hora; ?>" <?php echo $selected; ?>><?php echo $hora; ?></option>
                                        <?php endfor; ?>
                                    <?php endfor; ?>
                                </select>
                                <span class="separador">—</span>
                                <select class="hora-fin" name="horario[<?php echo $dia_key; ?>][rangos][<?php echo $index; ?>][fin]">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                        <?php for ($m = 0; $m < 60; $m += 15): ?>
                                            <?php 
                                                $hora = sprintf('%02d:%02d', $h, $m);
                                                $selected = ($hora === $rango['fin']) ? 'selected' : '';
                                            ?>
                                            <option value="<?php echo $hora; ?>" <?php echo $selected; ?>><?php echo $hora; ?></option>
                                        <?php endfor; ?>
                                    <?php endfor; ?>
                                </select>
                                <button type="button" class="btn-eliminar-rango">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php 
                                endforeach; 
                            else: 
                            ?>
                            <div class="rango-horario">
                                <select class="hora-inicio" name="horario[<?php echo $dia_key; ?>][rangos][0][inicio]">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                        <?php for ($m = 0; $m < 60; $m += 15): ?>
                                            <?php 
                                                $hora = sprintf('%02d:%02d', $h, $m);
                                                $selected = ($hora === '09:00') ? 'selected' : '';
                                            ?>
                                            <option value="<?php echo $hora; ?>" <?php echo $selected; ?>><?php echo $hora; ?></option>
                                        <?php endfor; ?>
                                    <?php endfor; ?>
                                </select>
                                <span class="separador">—</span>
                                <select class="hora-fin" name="horario[<?php echo $dia_key; ?>][rangos][0][fin]">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                        <?php for ($m = 0; $m < 60; $m += 15): ?>
                                            <?php 
                                                $hora = sprintf('%02d:%02d', $h, $m);
                                                $selected = ($hora === '18:00') ? 'selected' : '';
                                            ?>
                                            <option value="<?php echo $hora; ?>" <?php echo $selected; ?>><?php echo $hora; ?></option>
                                        <?php endfor; ?>
                                    <?php endfor; ?>
                                </select>
                                <button type="button" class="btn-eliminar-rango">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-agregar-rango" data-dia="<?php echo $dia_key; ?>">
                                <i class="fas fa-plus"></i> Añadir rango horario
                            </button>
                        </div>
                        
                        <input type="hidden" class="estado-cerrado" name="horario[<?php echo $dia_key; ?>][cerrado]" 
                               value="<?php echo (isset($horario_apertura[$dia_key]['cerrado']) && $horario_apertura[$dia_key]['cerrado'] === true) ? 'true' : 'false'; ?>">
                        
                        <div class="sugerencia-horario">
                            <i class="fas fa-lightbulb"></i>
                            <p>¿Quieres copiar este horario para otros días? <a class="btn-copiar-horario" data-dia="<?php echo $dia_key; ?>">Sí, copiar</a></p>
                        </div>
                        <div class="menu-copiar" id="menu-copiar-dias-<?php echo $dia_key; ?>">
                            <h5 class="modal-title">Copiar horario de <?php echo $dias[$dia_key]; ?></h5>
                            <p class="modal-subtitle">Selecciona los días donde quieres aplicar este horario:</p>
                            <div class="opciones-dias">
                                <?php foreach ($dias as $dia_option_key => $dia_option_nombre): ?>
                                    <?php if ($dia_option_key !== $dia_key): ?>
                                        <label><input type="checkbox" value="<?php echo $dia_option_key; ?>"> <?php echo $dia_option_nombre; ?></label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="modal-buttons">
                                <button type="button" class="btn-aplicar-copia" data-dia="<?php echo $dia_key; ?>">Aplicar</button>
                                <button type="button" class="btn-cancelar-copia" data-dia="<?php echo $dia_key; ?>">Cancelar</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="btn-nav">
                    <a href="paso5?id=<?php echo $negocio_id; ?>" class="button-secondary" style="display: inline-block; padding: 10px 20px; text-decoration: none;">Anterior</a>
                    <button type="submit">Siguiente</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="modal-overlay" id="modal-overlay"></div>
    
    <template id="template-rango-horario">
        <div class="rango-horario">
            <select class="hora-inicio" name="horario[{DIA}][rangos][{INDEX}][inicio]">
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <?php for ($m = 0; $m < 60; $m += 15): ?>
                        <?php $hora = sprintf('%02d:%02d', $h, $m); ?>
                        <option value="<?php echo $hora; ?>"><?php echo $hora; ?></option>
                    <?php endfor; ?>
                <?php endfor; ?>
            </select>
            <span class="separador">—</span>
            <select class="hora-fin" name="horario[{DIA}][rangos][{INDEX}][fin]">
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <?php for ($m = 0; $m < 60; $m += 15): ?>
                        <?php $hora = sprintf('%02d:%02d', $h, $m); ?>
                        <option value="<?php echo $hora; ?>"><?php echo $hora; ?></option>
                    <?php endfor; ?>
                <?php endfor; ?>
            </select>
            <button type="button" class="btn-eliminar-rango">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </template>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const templateRangoHorario = document.getElementById('template-rango-horario').innerHTML;
            
            document.querySelectorAll('.toggle-abierto').forEach(function(toggle) {
                toggle.addEventListener('change', function() {
                    const diaId = this.id.replace('abierto-', '');
                    const rangosContainer = document.getElementById('rangos-' + diaId);
                    const estadoCerrado = document.querySelector(`#horario-${diaId} .estado-cerrado`);
                    
                    if (this.checked) {
                        rangosContainer.style.display = 'block';
                        estadoCerrado.value = 'false';
                    } else {
                        rangosContainer.style.display = 'none';
                        estadoCerrado.value = 'true';
                    }
                });
            });
            
            document.querySelectorAll('.btn-agregar-rango').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const dia = this.getAttribute('data-dia');
                    const rangosContainer = this.parentElement;
                    const rangosExistentes = rangosContainer.querySelectorAll('.rango-horario');
                    const nuevoIndex = rangosExistentes.length;
                    
                    const nuevoRango = templateRangoHorario
                        .replace(/{DIA}/g, dia)
                        .replace(/{INDEX}/g, nuevoIndex);
                        
                    this.insertAdjacentHTML('beforebegin', nuevoRango);
                });
            });
            
            document.querySelector('.horario-container').addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-eliminar-rango') || e.target.parentElement.classList.contains('btn-eliminar-rango')) {
                    const btn = e.target.classList.contains('btn-eliminar-rango') ? e.target : e.target.parentElement;
                    const rangoHorario = btn.closest('.rango-horario');
                    const horarioDia = rangoHorario.closest('.horario-dia');
                    const rangosContainer = rangoHorario.parentElement;
                    
                    const rangos = rangosContainer.querySelectorAll('.rango-horario');
                    if (rangos.length > 1) {
                        rangoHorario.remove();
                        actualizarIndicesRangos(horarioDia);
                    } else {
                        alert('Debe haber al menos un rango horario para los días abiertos.');
                    }
                }
            });
            
            document.querySelectorAll('.btn-copiar-horario').forEach(function(btnCopiarHorario) {
                btnCopiarHorario.addEventListener('click', function(e) {
                    e.preventDefault();
                    const diaOrigen = this.getAttribute('data-dia');
                    const menuCopiarDias = document.getElementById('menu-copiar-dias-' + diaOrigen);
                    const overlay = document.getElementById('modal-overlay');
                    
                    menuCopiarDias.classList.toggle('visible');
                    overlay.classList.toggle('visible');
                });
            });
            
            document.querySelectorAll('.btn-aplicar-copia').forEach(function(btnAplicarCopia) {
                btnAplicarCopia.addEventListener('click', function(e) {
                    e.preventDefault();
                    const diaOrigen = this.getAttribute('data-dia');
                    const menuCopiarDias = document.getElementById('menu-copiar-dias-' + diaOrigen);
                    const overlay = document.getElementById('modal-overlay');
                    const diasSeleccionados = [];
                    const checkboxes = menuCopiarDias.querySelectorAll('input[type="checkbox"]:checked');
                    
                    checkboxes.forEach(function(checkbox) {
                        diasSeleccionados.push(checkbox.value);
                    });
                    
                    if (diasSeleccionados.length > 0) {
                        copiarHorario(diaOrigen, diasSeleccionados);
                        menuCopiarDias.classList.remove('visible');
                        overlay.classList.remove('visible');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = false;
                        });
                    }
                });
            });
            
            document.querySelectorAll('.btn-cancelar-copia').forEach(function(btnCancelarCopia) {
                btnCancelarCopia.addEventListener('click', function(e) {
                    e.preventDefault();
                    const diaOrigen = this.getAttribute('data-dia');
                    const menuCopiarDias = document.getElementById('menu-copiar-dias-' + diaOrigen);
                    const overlay = document.getElementById('modal-overlay');
                    const checkboxes = menuCopiarDias.querySelectorAll('input[type="checkbox"]');
                    
                    menuCopiarDias.classList.remove('visible');
                    overlay.classList.remove('visible');
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = false;
                    });
                });
            });
            
            function actualizarIndicesRangos(horarioDia) {
                const dia = horarioDia.id.replace('horario-', '');
                const rangos = horarioDia.querySelectorAll('.rango-horario');
                
                rangos.forEach(function(rango, index) {
                    const selectInicio = rango.querySelector('.hora-inicio');
                    const selectFin = rango.querySelector('.hora-fin');
                    
                    selectInicio.name = `horario[${dia}][rangos][${index}][inicio]`;
                    selectFin.name = `horario[${dia}][rangos][${index}][fin]`;
                });
            }

            function copiarHorario(diaOrigen, diasDestino) {
                const origenAbierto = document.getElementById(`abierto-${diaOrigen}`).checked;
                const origenRangos = document.getElementById(`rangos-${diaOrigen}`);
                const rangosOrigen = origenRangos.querySelectorAll('.rango-horario');

                diasDestino.forEach(function(dia) {
                    const toggleDia = document.getElementById(`abierto-${dia}`);
                    const rangosDia = document.getElementById(`rangos-${dia}`);
                    const estadoCerrado = document.querySelector(`#horario-${dia} .estado-cerrado`);

                    toggleDia.checked = origenAbierto;

                    if (origenAbierto) {
                        rangosDia.style.display = 'block';
                        estadoCerrado.value = 'false';

                        const rangosActuales = rangosDia.querySelectorAll('.rango-horario');
                        rangosActuales.forEach(rango => rango.remove());

                        rangosOrigen.forEach((rango, index) => {
                            const nuevoRango = rango.cloneNode(true);
                            nuevoRango.querySelector('.hora-inicio').name = `horario[${dia}][rangos][${index}][inicio]`;
                            nuevoRango.querySelector('.hora-fin').name = `horario[${dia}][rangos][${index}][fin]`;

                            nuevoRango.querySelector('.hora-inicio').value = rango.querySelector('.hora-inicio').value;
                            nuevoRango.querySelector('.hora-fin').value = rango.querySelector('.hora-fin').value;

                            const btnAgregarRango = rangosDia.querySelector('.btn-agregar-rango');
                            btnAgregarRango.parentNode.insertBefore(nuevoRango, btnAgregarRango);
                        });

                        const btnAgregarRango = rangosDia.querySelector('.btn-agregar-rango');
                        if (btnAgregarRango) {
                            btnAgregarRango.setAttribute('data-dia', dia);
                        }
                    } else {
                        rangosDia.style.display = 'none';
                        estadoCerrado.value = 'true';
                    }
                });
            }
            
            document.addEventListener('click', function(e) {
                const overlay = document.getElementById('modal-overlay');
                
                if (e.target === overlay) {
                    document.querySelectorAll('.menu-copiar.visible').forEach(function(menuCopiarDias) {
                        menuCopiarDias.classList.remove('visible');
                        const checkboxes = menuCopiarDias.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = false;
                        });
                    });
                    overlay.classList.remove('visible');
                }
                
                document.querySelectorAll('.menu-copiar.visible').forEach(function(menuCopiarDias) {
                    const diaId = menuCopiarDias.id.replace('menu-copiar-dias-', '');
                    const btnCopiarHorario = document.querySelector(`.btn-copiar-horario[data-dia="${diaId}"]`);
                    
                    if (!menuCopiarDias.contains(e.target) && e.target !== btnCopiarHorario && !btnCopiarHorario.contains(e.target) && e.target !== overlay) {
                        menuCopiarDias.classList.remove('visible');
                        overlay.classList.remove('visible');
                        const checkboxes = menuCopiarDias.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = false;
                        });
                    }
                });
            });
        });
    </script>
</body>
</html> 