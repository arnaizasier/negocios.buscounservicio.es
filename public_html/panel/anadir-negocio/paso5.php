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

// Obtener trabajadores del negocio
$trabajadores = [];
try {
    $stmt_trabajadores = $pdo2->prepare("SELECT id, nombre, apellido, rol FROM trabajadores WHERE negocio_id = :negocio_id AND admin_id = :usuario_id ORDER BY nombre ASC");
    $stmt_trabajadores->execute([':negocio_id' => $negocio_id, ':usuario_id' => $usuario_id]);
    $trabajadores = $stmt_trabajadores->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener trabajadores: " . $e->getMessage());
}

$menu_servicios = [];
if (!empty($negocio['menu_servicios'])) {
    $menu_servicios = json_decode($negocio['menu_servicios'], true) ?: [];
}

$servicio_a_editar = null;
$categoria_servicio_editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['agregar_categoria']) && !empty($_POST['nombre_categoria'])) {
        $nombre_categoria = sanitizarInput($_POST['nombre_categoria']);
        
        $categoria_id = uniqid('cat_');
        
        $menu_servicios[$categoria_id] = [
            'nombre' => $nombre_categoria,
            'servicios' => []
        ];
        
        actualizarMenuServicios($pdo2, $negocio_id, $menu_servicios);
        header("Location: paso5?id=$negocio_id&success=1");
        exit();
    }
    
    else if (isset($_POST['eliminar_categoria']) && !empty($_POST['categoria_id'])) {
        $categoria_id = sanitizarInput($_POST['categoria_id']);
        
        if (isset($menu_servicios[$categoria_id])) {
            unset($menu_servicios[$categoria_id]);
            
            actualizarMenuServicios($pdo2, $negocio_id, $menu_servicios);
            header("Location: paso5?id=$negocio_id&success=1");
            exit();
        }
    }
    
    else if (isset($_POST['agregar_servicio']) && !empty($_POST['nombre_servicio'])) {
        $categoria_id = sanitizarInput($_POST['servicio_categoria']);
        $nombre_servicio = sanitizarInput($_POST['nombre_servicio']);
        $descripcion_servicio = sanitizarInput($_POST['descripcion_servicio'] ?? '');
        $precio_servicio = sanitizarInput($_POST['precio_servicio'] ?? '');
        $duracion_servicio = sanitizarInput($_POST['duracion_servicio'] ?? '');
        $trabajadores_asignados = $_POST['trabajadores_servicio'] ?? [];
        
        // Validar y sanitizar trabajadores asignados
        $trabajadores_validados = [];
        if (is_array($trabajadores_asignados)) {
            foreach ($trabajadores_asignados as $trabajador_id) {
                if ($trabajador_id === 'todos') {
                    $trabajadores_validados[] = 'todos';
                } else {
                    $trabajador_id = filter_var($trabajador_id, FILTER_VALIDATE_INT);
                    if ($trabajador_id) {
                        $trabajadores_validados[] = $trabajador_id;
                    }
                }
            }
        }
        
        $servicio_id = uniqid('serv_');
        
        if (empty($categoria_id)) {
            $categoria_id = 'sin_categoria';
            if (!isset($menu_servicios[$categoria_id])) {
                $menu_servicios[$categoria_id] = [
                    'nombre' => 'Sin Categoría',
                    'servicios' => []
                ];
            }
        }
        
        if (isset($menu_servicios[$categoria_id])) {
            $menu_servicios[$categoria_id]['servicios'][$servicio_id] = [
                'nombre' => $nombre_servicio,
                'descripcion' => $descripcion_servicio,
                'precio' => $precio_servicio === '' ? null : $precio_servicio,
                'duracion' => $duracion_servicio,
                'trabajadores' => $trabajadores_validados,
                'orden' => count($menu_servicios[$categoria_id]['servicios'])
            ];
            
            actualizarMenuServicios($pdo2, $negocio_id, $menu_servicios);
            header("Location: paso5?id=$negocio_id&success=1");
            exit();
        }
    }
    
    else if (isset($_POST['actualizar_servicio']) && !empty($_POST['servicio_id']) && !empty($_POST['categoria_id'])) {
        $categoria_id = sanitizarInput($_POST['categoria_id']);
        $servicio_id = sanitizarInput($_POST['servicio_id']);
        $nuevo_nombre = sanitizarInput($_POST['nombre_servicio']);
        $nueva_descripcion = sanitizarInput($_POST['descripcion_servicio'] ?? '');
        $nuevo_precio = sanitizarInput($_POST['precio_servicio'] ?? '');
        $nueva_duracion = sanitizarInput($_POST['duracion_servicio'] ?? '');
        $nueva_categoria_id = sanitizarInput($_POST['servicio_categoria']);
        $trabajadores_asignados = $_POST['trabajadores_servicio'] ?? [];
        
        // Validar y sanitizar trabajadores asignados
        $trabajadores_validados = [];
        if (is_array($trabajadores_asignados)) {
            foreach ($trabajadores_asignados as $trabajador_id) {
                if ($trabajador_id === 'todos') {
                    $trabajadores_validados[] = 'todos';
                } else {
                    $trabajador_id = filter_var($trabajador_id, FILTER_VALIDATE_INT);
                    if ($trabajador_id) {
                        $trabajadores_validados[] = $trabajador_id;
                    }
                }
            }
        }
        
        if (empty($nueva_categoria_id)) {
            $nueva_categoria_id = 'sin_categoria';
            if (!isset($menu_servicios[$nueva_categoria_id])) {
                $menu_servicios[$nueva_categoria_id] = [
                    'nombre' => 'Sin Categoría',
                    'servicios' => []
                ];
            }
        }
        
        if ($nueva_categoria_id !== $categoria_id) {
            $servicio_actual = $menu_servicios[$categoria_id]['servicios'][$servicio_id];
            $servicio_actual['nombre'] = $nuevo_nombre;
            $servicio_actual['descripcion'] = $nueva_descripcion;
            $servicio_actual['precio'] = $nuevo_precio === '' ? null : $nuevo_precio;
            $servicio_actual['duracion'] = $nueva_duracion;
            $servicio_actual['trabajadores'] = $trabajadores_validados;
            $servicio_actual['orden'] = count($menu_servicios[$nueva_categoria_id]['servicios']);
            
            unset($menu_servicios[$categoria_id]['servicios'][$servicio_id]);
            
            $menu_servicios[$nueva_categoria_id]['servicios'][$servicio_id] = $servicio_actual;
        } else {
            $menu_servicios[$categoria_id]['servicios'][$servicio_id]['nombre'] = $nuevo_nombre;
            $menu_servicios[$categoria_id]['servicios'][$servicio_id]['descripcion'] = $nueva_descripcion;
            $menu_servicios[$categoria_id]['servicios'][$servicio_id]['precio'] = $nuevo_precio === '' ? null : $nuevo_precio;
            $menu_servicios[$categoria_id]['servicios'][$servicio_id]['duracion'] = $nueva_duracion;
            $menu_servicios[$categoria_id]['servicios'][$servicio_id]['trabajadores'] = $trabajadores_validados;
        }
        
        actualizarMenuServicios($pdo2, $negocio_id, $menu_servicios);
        header("Location: paso5?id=$negocio_id&success=1");
        exit();
    }
    
    else if (isset($_POST['eliminar_servicio']) && 
             !empty($_POST['categoria_id']) && 
             !empty($_POST['servicio_id'])) {
        
        $categoria_id = sanitizarInput($_POST['categoria_id']);
        $servicio_id = sanitizarInput($_POST['servicio_id']);
        
        if (isset($menu_servicios[$categoria_id]['servicios'][$servicio_id])) {
            unset($menu_servicios[$categoria_id]['servicios'][$servicio_id]);
            
            actualizarMenuServicios($pdo2, $negocio_id, $menu_servicios);
            header("Location: paso5?id=$negocio_id&success=1");
            exit();
        }
    }
    
    else if (isset($_POST['mover_servicio']) && 
             !empty($_POST['categoria_id']) && 
             !empty($_POST['servicio_id']) && 
             isset($_POST['direccion'])) {
        
        $categoria_id = sanitizarInput($_POST['categoria_id']);
        $servicio_id = sanitizarInput($_POST['servicio_id']);
        $direccion = sanitizarInput($_POST['direccion']);
        
        if (isset($menu_servicios[$categoria_id]['servicios'][$servicio_id])) {
            $servicios = $menu_servicios[$categoria_id]['servicios'];
            
            $ids_servicios = array_keys($servicios);
            
            $posicion_actual = array_search($servicio_id, $ids_servicios);
            
            if ($direccion === 'arriba' && $posicion_actual > 0) {
                $id_anterior = $ids_servicios[$posicion_actual - 1];
                
                $orden_actual = $servicios[$servicio_id]['orden'] ?? $posicion_actual;
                $orden_anterior = $servicios[$id_anterior]['orden'] ?? ($posicion_actual - 1);
                
                $servicios[$servicio_id]['orden'] = $orden_anterior;
                $servicios[$id_anterior]['orden'] = $orden_actual;
                
                uasort($servicios, function($a, $b) {
                    $orden_a = $a['orden'] ?? 999;
                    $orden_b = $b['orden'] ?? 999;
                    return $orden_a - $orden_b;
                });
                
                $menu_servicios[$categoria_id]['servicios'] = $servicios;
                
                actualizarMenuServicios($pdo2, $negocio_id, $menu_servicios);
                header("Location: paso5?id=$negocio_id&success=1");
                exit();
            }
            else if ($direccion === 'abajo' && $posicion_actual < count($ids_servicios) - 1) {
                $id_siguiente = $ids_servicios[$posicion_actual + 1];
                
                $orden_actual = $servicios[$servicio_id]['orden'] ?? $posicion_actual;
                $orden_siguiente = $servicios[$id_siguiente]['orden'] ?? ($posicion_actual + 1);
                
                $servicios[$servicio_id]['orden'] = $orden_siguiente;
                $servicios[$id_siguiente]['orden'] = $orden_actual;
                
                uasort($servicios, function($a, $b) {
                    $orden_a = $a['orden'] ?? 999;
                    $orden_b = $b['orden'] ?? 999;
                    return $orden_a - $orden_b;
                });
                
                $menu_servicios[$categoria_id]['servicios'] = $servicios;
                
                actualizarMenuServicios($pdo2, $negocio_id, $menu_servicios);
                header("Location: paso5?id=$negocio_id&success=1");
                exit();
            }
        }
    }
    
    else if (isset($_POST['editar_servicio']) && 
             !empty($_POST['categoria_id']) && 
             !empty($_POST['servicio_id'])) {
        
        $categoria_id = sanitizarInput($_POST['categoria_id']);
        $servicio_id = sanitizarInput($_POST['servicio_id']);
        
        if (isset($menu_servicios[$categoria_id]['servicios'][$servicio_id])) {
            $servicio_a_editar = $menu_servicios[$categoria_id]['servicios'][$servicio_id];
            $servicio_a_editar['id'] = $servicio_id;
            $categoria_servicio_editar = $categoria_id;
        }
    }
    
    else if (isset($_POST['siguiente'])) {
        header("Location: paso6?id=$negocio_id");
        exit();
    }
}

function actualizarMenuServicios($pdo, $negocio_id, $menu_servicios) {
    $menu_servicios_json = json_encode($menu_servicios);
    
    $stmt = $pdo->prepare("UPDATE negocios SET menu_servicios = :menu_servicios WHERE negocio_id = :negocio_id");
    $stmt->execute([
        ':menu_servicios' => $menu_servicios_json,
        ':negocio_id' => $negocio_id
    ]);
}

foreach ($menu_servicios as $categoria_id => &$categoria) {
    if (isset($categoria['servicios']) && !empty($categoria['servicios'])) {
        uasort($categoria['servicios'], function($a, $b) {
            $orden_a = $a['orden'] ?? 999;
            $orden_b = $b['orden'] ?? 999;
            return $orden_a - $orden_b;
        });
    }
}
unset($categoria);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Negocio - Servicios</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/paso5.css">
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Servicios</h2>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">Los cambios se han guardado correctamente.</div>
            <?php endif; ?>
            
            <div class="formulario-servicios">
                <h3 class="titulo-formulario">
                    <?php echo $servicio_a_editar ? 'Editar Servicio' : 'Añadir Nuevo Servicio'; ?>
                </h3>
                
                <form method="POST" action="">
                    <?php if ($servicio_a_editar): ?>
                        <input type="hidden" name="servicio_id" value="<?php echo $servicio_a_editar['id']; ?>">
                        <input type="hidden" name="categoria_id" value="<?php echo $categoria_servicio_editar; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="servicio_categoria">Categoría</label>
                        <select id="servicio_categoria" name="servicio_categoria" onchange="manejarSeleccionCategoria(this)">
                            <option value="">Sin categoría</option>
                            <?php foreach ($menu_servicios as $categoria_id => $categoria): ?>
                                <?php if ($categoria_id !== 'sin_categoria'): ?>
                                    <option value="<?php echo $categoria_id; ?>" <?php echo ($categoria_servicio_editar == $categoria_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <option value="__agregar_nueva__" style="font-style: italic; color: #024ddf;">+ Agregar nueva categoría</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="trabajadores_servicio">Trabajadores que realizan este servicio</label>
                        <div class="trabajadores-selector">
                            <?php if (empty($trabajadores)): ?>
                                <div class="alert-info" style="padding: 10px; background: #e8f4fd; border: 1px solid #bee5eb; border-radius: 4px; margin-bottom: 15px;">
                                    <p style="margin: 0; color: #0c5460;">
                                        <i class="fas fa-info-circle"></i> No hay trabajadores registrados en este negocio. 
                                        <a href="../equipo/gestion-trabajador" style="color: #0c5460; text-decoration: underline;">Añadir trabajadores</a>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="trabajador-option">
                                    <label>
                                        <input type="checkbox" name="trabajadores_servicio[]" value="todos" 
                                               <?php echo ($servicio_a_editar && isset($servicio_a_editar['trabajadores']) && in_array('todos', $servicio_a_editar['trabajadores'])) ? 'checked' : ''; ?>>
                                        <strong>Todos los trabajadores</strong>
                                    </label>
                                </div>
                                <div class="trabajadores-individuales">
                                    <?php foreach ($trabajadores as $trabajador): ?>
                                        <div class="trabajador-option">
                                            <label>
                                                <input type="checkbox" name="trabajadores_servicio[]" value="<?php echo $trabajador['id']; ?>"
                                                       <?php echo ($servicio_a_editar && isset($servicio_a_editar['trabajadores']) && in_array($trabajador['id'], $servicio_a_editar['trabajadores'])) ? 'checked' : ''; ?>>
                                                <?php echo htmlspecialchars($trabajador['nombre'] . ' ' . $trabajador['apellido']); ?>
                                                <?php if (!empty($trabajador['rol'])): ?>
                                                    <span class="trabajador-rol">(<?php echo htmlspecialchars($trabajador['rol']); ?>)</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="help-text">Selecciona qué trabajadores pueden realizar este servicio</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre_servicio">Nombre del Servicio</label>
                        <input type="text" id="nombre_servicio" name="nombre_servicio" value="<?php echo $servicio_a_editar ? htmlspecialchars($servicio_a_editar['nombre']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion_servicio">Descripción</label>
                        <textarea id="descripcion_servicio" name="descripcion_servicio"><?php echo $servicio_a_editar ? htmlspecialchars($servicio_a_editar['descripcion']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="precio_servicio">Precio</label>
                        <input type="text" id="precio_servicio" name="precio_servicio" value="<?php echo $servicio_a_editar ? htmlspecialchars($servicio_a_editar['precio']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="duracion_servicio">Duración (minutos)</label>
                        <input type="number" id="duracion_servicio" name="duracion_servicio" min="1" value="<?php echo $servicio_a_editar ? htmlspecialchars($servicio_a_editar['duracion']) : ''; ?>" class="form-control">
                    </div>
                    
                    <?php if ($servicio_a_editar): ?>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="actualizar_servicio" style="flex-grow: 1;">Actualizar Servicio</button>
                            <a href="paso5?id=<?php echo $negocio_id; ?>" class="button-secondary" style="flex-grow: 1; text-align: center; padding: 10px;">Cancelar</a>
                        </div>
                    <?php else: ?>
                        <button type="submit" name="agregar_servicio">Añadir Servicio</button>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="seccion-container" id="seccion-categorias">
                <h3>Categorías</h3>
                <form method="POST" action="" class="inline-form">
                    <input type="text" id="nombre_categoria" name="nombre_categoria" placeholder="Nombre de la categoría" required>
                    <button type="submit" name="agregar_categoria">Añadir</button>
                </form>
            </div>
            
            <div class="seccion-container">
                <h3>Listado de Servicios por Categoría</h3>
                
                <?php if (empty($menu_servicios)): ?>
                    <p>No hay categorías ni servicios. Añade una categoría y servicios para comenzar.</p>
                <?php else: ?>
                    <?php foreach ($menu_servicios as $categoria_id => $categoria): ?>
                        <div class="categoria-container">
                            <div class="categoria-header">
                                <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                <?php if ($categoria_id !== 'sin_categoria'): ?>
                                <form method="POST" action="" style="margin: 0;">
                                    <input type="hidden" name="categoria_id" value="<?php echo $categoria_id; ?>">
                                    <button type="submit" name="eliminar_categoria" class="button-secondary" style="padding: 5px 10px;">
                                        <i></i> Eliminar
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <?php if (empty($categoria['servicios'])): ?>
                                    <p>No hay servicios en esta categoría.</p>
                                <?php else: ?>
                                    <?php foreach ($categoria['servicios'] as $servicio_id => $servicio): ?>
                                        <div class="servicio-item">
                                            <div class="servicio-header">
                                                <h4>
                                                    <?php echo htmlspecialchars($servicio['nombre']); ?>
                                                </h4>
                                                <div class="servicio-acciones">
                                                    <form method="POST" action="" style="margin: 0; display: inline-block;">
                                                        <input type="hidden" name="categoria_id" value="<?php echo $categoria_id; ?>">
                                                        <input type="hidden" name="servicio_id" value="<?php echo $servicio_id; ?>">
                                                        <button type="submit" name="editar_servicio" class="accion-editar">
                                                            <i></i> Editar
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="POST" action="" style="margin: 0; display: inline-block;">
                                                        <input type="hidden" name="categoria_id" value="<?php echo $categoria_id; ?>">
                                                        <input type="hidden" name="servicio_id" value="<?php echo $servicio_id; ?>">
                                                        <input type="hidden" name="direccion" value="arriba">
                                                        <button type="submit" name="mover_servicio" class="button-secondary" style="padding: 5px; margin-right: 5px;">
                                                            <i class="fas fa-arrow-up"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="POST" action="" style="margin: 0; display: inline-block;">
                                                        <input type="hidden" name="categoria_id" value="<?php echo $categoria_id; ?>">
                                                        <input type="hidden" name="servicio_id" value="<?php echo $servicio_id; ?>">
                                                        <input type="hidden" name="direccion" value="abajo">
                                                        <button type="submit" name="mover_servicio" class="button-secondary" style="padding: 5px; margin-right: 5px;">
                                                            <i class="fas fa-arrow-down"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="POST" action="" style="margin: 0; display: inline-block;">
                                                        <input type="hidden" name="categoria_id" value="<?php echo $categoria_id; ?>">
                                                        <input type="hidden" name="servicio_id" value="<?php echo $servicio_id; ?>">
                                                        <button type="submit" name="eliminar_servicio" class="button-secondary" style="padding: 5px;">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <div class="servicio-detalles">
                                                <?php if (!empty($servicio['descripcion'])): ?>
                                                    <p><?php echo htmlspecialchars($servicio['descripcion']); ?></p>
                                                <?php endif; ?>
                                                
                                                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                                    <?php if (isset($servicio['precio'])): ?>
                                                        <span><strong>Precio:</strong> <?php echo ($servicio['precio'] == 0) ? 'Gratis' : htmlspecialchars($servicio['precio']) . '€'; ?></span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($servicio['duracion'])): ?>
                                                        <span><strong>Duración:</strong> <?php echo htmlspecialchars($servicio['duracion']); ?> min</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($servicio['trabajadores'])): ?>
                                                    <div class="trabajadores-asignados" style="margin-top: 10px;">
                                                        <strong>Trabajadores asignados:</strong>
                                                        <?php if (in_array('todos', $servicio['trabajadores'])): ?>
                                                            <span class="badge-trabajador">Todos los trabajadores</span>
                                                        <?php else: ?>
                                                            <?php 
                                                            $trabajadores_nombres = [];
                                                            foreach ($trabajadores as $trabajador) {
                                                                if (in_array($trabajador['id'], $servicio['trabajadores'])) {
                                                                    $trabajadores_nombres[] = htmlspecialchars($trabajador['nombre'] . ' ' . $trabajador['apellido']);
                                                                }
                                                            }
                                                            if (!empty($trabajadores_nombres)):
                                                            ?>
                                                                <div class="lista-trabajadores">
                                                                    <?php foreach ($trabajadores_nombres as $nombre): ?>
                                                                        <span class="badge-trabajador"><?php echo $nombre; ?></span>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="badge-trabajador sin-trabajadores">Sin trabajadores asignados</span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif (!empty($trabajadores)): ?>
                                                    <div class="trabajadores-asignados" style="margin-top: 10px;">
                                                        <strong>Trabajadores asignados:</strong>
                                                        <span class="badge-trabajador sin-trabajadores">Sin trabajadores asignados</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="btn-nav">
                <a href="paso4?id=<?php echo $negocio_id; ?>" class="button-secondary">Anterior</a>
                <form method="POST" style="margin: 0;">
                    <button type="submit" name="siguiente">Siguiente</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function manejarSeleccionCategoria(select) {
        if (select.value === '__agregar_nueva__') {
            select.value = '';
            
            document.getElementById('seccion-categorias').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            
            setTimeout(() => {
                document.getElementById('nombre_categoria').focus();
            }, 500);
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxTodos = document.querySelector('input[name="trabajadores_servicio[]"][value="todos"]');
        const checkboxesIndividuales = document.querySelectorAll('input[name="trabajadores_servicio[]"]:not([value="todos"])');
        
        if (checkboxTodos) {
            checkboxTodos.addEventListener('change', function() {
                if (this.checked) {
                    checkboxesIndividuales.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                }
            });
            
            checkboxesIndividuales.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        checkboxTodos.checked = false;
                    }
                });
            });
        }
    });
    </script>
</body>
</html>