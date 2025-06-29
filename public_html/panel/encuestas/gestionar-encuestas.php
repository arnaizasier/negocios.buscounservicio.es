<?php
session_start();

// Enhanced security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' cdnjs.cloudflare.com; font-src \'self\' cdnjs.cloudflare.com; img-src \'self\' data:;');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Session security
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

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 1800) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           isset($_SESSION['csrf_token_time']) &&
           (time() - $_SESSION['csrf_token_time']) <= 1800 &&
           hash_equals($_SESSION['csrf_token'], $token);
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
            return ($value !== false && $value > 0) ? $value : false;
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
        default:
            return false;
    }
}

function checkRateLimit($action, $user_id, $limit = 10, $timeframe = 300) {
    $key = 'rate_limit_' . $action . '_' . $user_id;
    $current_time = time();
    
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => [], 'blocked_until' => 0];
    }
    
    if (!isset($_SESSION[$key]['blocked_until'])) {
        $_SESSION[$key]['blocked_until'] = 0;
    }
    
    if (!isset($_SESSION[$key]['attempts']) || !is_array($_SESSION[$key]['attempts'])) {
        $_SESSION[$key]['attempts'] = [];
    }
    
    if ($current_time < $_SESSION[$key]['blocked_until']) {
        return false;
    }
    
    $_SESSION[$key]['attempts'] = array_filter($_SESSION[$key]['attempts'], function($timestamp) use ($current_time, $timeframe) {
        return ($current_time - $timestamp) < $timeframe;
    });
    
    $_SESSION[$key]['attempts'][] = $current_time;
    
    if (count($_SESSION[$key]['attempts']) > $limit) {
        $_SESSION[$key]['blocked_until'] = $current_time + ($timeframe * 2);
        return false;
    }
    
    return true;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (detectSuspiciousActivity($_POST)) {
        http_response_code(400);
        die('Solicitud rechazada por razones de seguridad');
    }
}

$negocios = array_column($negocios_usuario, 'negocio_id');
$negocios_nombres = array_column($negocios_usuario, 'nombre', 'negocio_id');

$id_negocio = null;
$mostrar_modal = true;

if (isset($_GET['id_negocio'])) {
    $id_negocio_solicitado = validateInput($_GET['id_negocio'], 'int');
    if ($id_negocio_solicitado !== false && in_array($id_negocio_solicitado, $negocios)) {
        $id_negocio = $id_negocio_solicitado;
        $mostrar_modal = false;
    }
} elseif (isset($_POST['id_negocio_seleccionado'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }
    
    $id_negocio_seleccionado = validateInput($_POST['id_negocio_seleccionado'], 'int');
    if ($id_negocio_seleccionado !== false && in_array($id_negocio_seleccionado, $negocios)) {
        $id_negocio = $id_negocio_seleccionado;
        $mostrar_modal = false;
    }
}

$mensaje = '';
$error = '';
$encuesta_existente = false;
$recompensa_existente = '';
$regalo_existente = '';
$preguntas_existentes = [];

if ($id_negocio) {
    $stmt_check = $pdo2->prepare("SELECT recompensa, regalo, preguntas FROM encuestas WHERE negocio_id = ? LIMIT 1");
    $stmt_check->execute([$id_negocio]);
    $encuesta_data = $stmt_check->fetch(PDO::FETCH_ASSOC);
    $encuesta_existente = !empty($encuesta_data);

    if ($encuesta_existente) {
        $recompensa_existente = $encuesta_data['recompensa'] ?? '';
        $regalo_existente = $encuesta_data['regalo'] ?? '';
        $preguntas_json = json_decode($encuesta_data['preguntas'], true);
        $preguntas_existentes = is_array($preguntas_json) ? $preguntas_json : [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preguntas'])) {
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            $error = "Token de seguridad inválido.";
        } 
        elseif (!checkRateLimit('encuesta_submit', $user_id, 3, 600)) {
            $error = "Demasiadas solicitudes. Espere 10 minutos.";
        }
        elseif (!checkRateLimit('global_submit', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 10, 3600)) {
            $error = "Límite de solicitudes globales excedido. Intente más tarde.";
        }
        elseif (strlen(json_encode($_POST)) > 50000) {
            $error = "Solicitud demasiado grande.";
        }
        elseif (!empty($_POST['website'])) {
            $error = "Error de validación.";
        }
        elseif (isset($_POST['form_start_time'])) {
            $form_start_time = validateInput($_POST['form_start_time'], 'int');
            if ($form_start_time === false) {
                $error = "Datos de formulario inválidos.";
            } else {
                $time_spent = time() - $form_start_time;
                if ($time_spent < 5) {
                    $error = "Formulario enviado demasiado rápido. Espere unos segundos.";
                } elseif ($time_spent > 3600) {
                    $error = "Sesión expirada. Recargue la página.";
                } else {
            try {
                $pdo2->beginTransaction();
                
                $recompensa = validateInput($_POST['recompensa'] ?? '', 'text', 500);
                if ($recompensa === false) {
                    throw new Exception("Recompensa inválida o demasiado larga.");
                }
                
                $regalo = validateInput($_POST['regalo'] ?? '', 'text', 500);
                if ($regalo === false) {
                    throw new Exception("Regalo inválido o demasiado largo.");
                }
                
                if (!is_array($_POST['preguntas']) || !is_array($_POST['tipos']) || !is_array($_POST['opciones'])) {
                    throw new Exception("Datos de formulario inválidos.");
                }
                
                if (count($_POST['preguntas']) !== count($_POST['tipos']) || count($_POST['preguntas']) !== count($_POST['opciones'])) {
                    throw new Exception("Inconsistencia en los datos del formulario.");
                }
                
                foreach ($_POST as $key => $value) {
                    if (is_array($value) && count($value) > 50) {
                        throw new Exception("Demasiados elementos en el formulario.");
                    }
                }
                
                $preguntas = $_POST['preguntas'];
                $tipos = $_POST['tipos'];
                $opciones = $_POST['opciones'];
                
                if (empty($preguntas) || count($preguntas) === 0) {
                    throw new Exception("Debe agregar al menos una pregunta.");
                }
                
                if (count($preguntas) > 10) {
                    throw new Exception("Máximo 10 preguntas permitidas.");
                }
                
                $preguntas_array = [];
                $tipos_validos = ['si_no', 'selector', 'texto'];
                
                foreach ($preguntas as $index => $enunciado) {
                    $enunciado = validateInput($enunciado, 'text', 1000);
                    if ($enunciado === false || empty($enunciado)) {
                        continue;
                    }
                    
                    $tipo = $tipos[$index] ?? '';
                    if (!in_array($tipo, $tipos_validos)) {
                        throw new Exception("Tipo de pregunta inválido.");
                    }
                    
                    $pregunta_opciones = [];
                    
                    if ($tipo === 'selector') {
                        $opciones_raw = validateInput($opciones[$index] ?? '', 'text', 2000);
                        if ($opciones_raw === false) {
                            throw new Exception("Opciones inválidas para pregunta de selección.");
                        }
                        
                        if (substr_count($opciones_raw, ',') > 19) {
                            throw new Exception("Demasiadas opciones en una pregunta.");
                        }
                        
                        $opciones_array = array_filter(array_map(function($opcion) {
                             $opcion_limpia = validateInput(trim($opcion), 'text', 200);
                             if ($opcion_limpia === false || empty($opcion_limpia)) {
                                 return null;
                             }
                             if (detectSuspiciousActivity($opcion_limpia)) {
                                 return null;
                             }
                             return $opcion_limpia;
                         }, explode(',', $opciones_raw)));
                        
                        if (empty($opciones_array)) {
                            throw new Exception("Las preguntas de tipo selector deben tener opciones válidas.");
                        }
                        
                        if (count($opciones_array) > 20) {
                            throw new Exception("Máximo 20 opciones por pregunta de selección.");
                        }
                        
                        $pregunta_opciones = $opciones_array;
                    }
                    
                    $preguntas_array[] = [
                        'enunciado' => $enunciado,
                        'tipo' => $tipo,
                        'opciones' => $pregunta_opciones
                    ];
                }
                
                if (empty($preguntas_array)) {
                    throw new Exception("No se pudo procesar ninguna pregunta válida.");
                }
                
                $preguntas_json = json_encode($preguntas_array, JSON_UNESCAPED_UNICODE);
                
                if ($encuesta_existente) {
                    $stmt_update = $pdo2->prepare("UPDATE encuestas SET recompensa = ?, regalo = ?, preguntas = ? WHERE negocio_id = ?");
                    $stmt_update->execute([$recompensa, $regalo, $preguntas_json, $id_negocio]);
                } else {
                    $stmt_insert = $pdo2->prepare("INSERT INTO encuestas (negocio_id, recompensa, regalo, preguntas) VALUES (?, ?, ?, ?)");
                    $stmt_insert->execute([$id_negocio, $recompensa, $regalo, $preguntas_json]);
                }
                
                $pdo2->commit();
                $mensaje = $encuesta_existente ? "Encuesta actualizada correctamente." : "Encuesta creada correctamente.";
                $encuesta_existente = true;
                $preguntas_existentes = $preguntas_array;
                $recompensa_existente = $recompensa;
                $regalo_existente = $regalo;

                unset($_SESSION['csrf_token']);
                
            } catch (Exception $e) {
                $pdo2->rollback();
                $error = "Error: " . htmlspecialchars($e->getMessage());
            }
                }
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuestas - <?= $id_negocio ? htmlspecialchars($negocios_nombres[$id_negocio]) : 'Seleccionar Negocio' ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/gestionar-encuestas.css">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <link href="../../assets/css/marca.css" rel="stylesheet">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sidebar.php'; ?>
    
    <?php if ($mostrar_modal): ?>
    <div class="modal-overlay" id="modal-negocio">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i></i> Seleccionar Negocio</h4>
                <p>Elige el negocio para el cual deseas crear o gestionar la encuesta</p>
            </div>
            <div class="modal-body">
                <form method="POST" id="form-seleccionar-negocio">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="negocios-grid">
                        <?php foreach ($negocios_usuario as $negocio): ?>
                            <div class="negocio-card" data-id="<?= intval($negocio['negocio_id']) ?>">
                                <div class="negocio-info">
                                    <h5><?= htmlspecialchars($negocio['nombre']) ?></h5>
                                </div>
                                <button type="button" class="btn-seleccionar" onclick="seleccionarNegocio(<?= intval($negocio['negocio_id']) ?>)">
                                    <i class="fas fa-arrow-right"></i> Seleccionar
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="id_negocio_seleccionado" id="id_negocio_seleccionado">
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-container">
        <?php if ($id_negocio): ?>
        <div class="card">
            <div class="card-header">
                <div class="cambiar-negocio">
                    <a href="index.php" class="btn-cambiar">
                        <i class="fas fa-exchange-alt"></i> Cambiar negocio
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="form-encuesta">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="id_negocio_seleccionado" value="<?= intval($id_negocio) ?>">
                    <input type="hidden" name="form_start_time" value="<?= time() ?>">
                    <input type="text" name="website" style="display:none !important; position: absolute; left: -9999px;" tabindex="-1" autocomplete="off">
                    
                    <div class="recompensa-section">
                        <h5><i class="fas fa-gift"></i> Recompensa (opcional)</h5>
                        <p>Ofrece algo a cambio por completar la encuesta y aumenta la participación</p>
                        <input type="text" 
                               name="recompensa" 
                               class="form-control" 
                               maxlength="500"
                               placeholder="Ej: Descuento del 10% para tu proxima visita"
                               value="<?= htmlspecialchars($recompensa_existente) ?>">
                        <small class="text-muted d-block mt-2"><i class="fas fa-info-circle"></i> Se mostrará antes de comenzar la encuesta (máximo 500 caracteres)</small>
                    </div>

                    <div class="recompensa-section">
                        <h5><i class="fas fa-award"></i> Regalo/Código (opcional)</h5>
                        <p>Muestra un código de descuento o regalo después de completar la encuesta</p>
                        <input type="text" 
                               name="regalo" 
                               class="form-control" 
                               maxlength="500"
                               placeholder="Ej: Usa el código GRACIAS10 para obtener el 10% de descuento"
                               value="<?= htmlspecialchars($regalo_existente) ?>">
                        <small class="text-muted d-block mt-2"><i class="fas fa-info-circle"></i> Se mostrará después de completar la encuesta (máximo 500 caracteres)</small>
                    </div>

                    <div id="preguntas-container">
                        <?php if (!empty($preguntas_existentes)): ?>
                            <?php foreach ($preguntas_existentes as $index => $pregunta): ?>
                                <div class="pregunta-item">
                                    <div class="pregunta-header">
                                        <h6><i></i> Pregunta <?= $index + 1 ?></h6>
                                        <button type="button" class="btn-eliminar" onclick="eliminarPregunta(this)" title="Eliminar pregunta">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><i></i> Pregunta</label>
                                        <textarea name="preguntas[]" class="form-control" rows="2" maxlength="1000" required placeholder="Escribe tu pregunta aquí..."><?= htmlspecialchars($pregunta['enunciado']) ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><i></i> Tipo de respuesta</label>
                                        <select name="tipos[]" class="form-select tipo-select" required onchange="toggleOpciones(this)">
                                            <option value="si_no" <?= $pregunta['tipo'] === 'si_no' ? 'selected' : '' ?>>Sí/No</option>
                                            <option value="selector" <?= $pregunta['tipo'] === 'selector' ? 'selected' : '' ?>>Selección múltiple</option>
                                            <option value="texto" <?= $pregunta['tipo'] === 'texto' ? 'selected' : '' ?>>Texto libre</option>
                                        </select>
                                    </div>
                                    
                                    <div class="opciones-container <?= $pregunta['tipo'] === 'selector' ? 'show' : '' ?>">
                                        <label class="form-label"><i></i> Opciones (separadas por coma)</label>
                                        <textarea name="opciones[]" class="form-control" rows="2" maxlength="2000" placeholder="Opción 1, Opción 2, Opción 3"><?= $pregunta['tipo'] === 'selector' ? htmlspecialchars(implode(', ', $pregunta['opciones'])) : '' ?></textarea>
                                        <small class="text-muted d-block mt-2"><i class="fas fa-lightbulb"></i> Ejemplo: Excelente, Muy bueno, Bueno, Regular, Malo (máximo 20 opciones)</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="text-center mb-4">
                        <button type="button" class="btn btn-outline-primary" onclick="agregarPregunta()" id="btn-agregar">
                            <i class="fas fa-plus-circle"></i> Agregar Pregunta
                        </button>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-<?= $encuesta_existente ? 'sync-alt' : 'save' ?>"></i>
                            <?= $encuesta_existente ? 'Actualizar Encuesta' : 'Crear Encuesta' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="../../assets/js/sidebar.js"></script>
    <script>
        let contadorPreguntas = <?= count($preguntas_existentes) ?>;
        let submitInProgress = false;

        function seleccionarNegocio(idNegocio) {
            if (typeof idNegocio !== 'number' || idNegocio <= 0) {
                console.error('ID de negocio inválido');
                return;
            }
            document.getElementById('id_negocio_seleccionado').value = idNegocio;
            document.getElementById('form-seleccionar-negocio').submit();
        }

        function actualizarContador() {
            const btnAgregar = document.getElementById('btn-agregar');
            if (contadorPreguntas >= 10) {
                btnAgregar.style.display = 'none';
            } else {
                btnAgregar.style.display = 'inline-flex';
            }
            
            const preguntas = document.querySelectorAll('.pregunta-item h6');
            preguntas.forEach((pregunta, index) => {
                pregunta.innerHTML = `<i></i> Pregunta ${index + 1}`;
            });
        }

        function sanitizeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function validateFormSecurity() {
            const honeypot = document.querySelector('input[name="website"]');
            if (honeypot && honeypot.value !== '') {
                return false;
            }
            
            const startTime = document.querySelector('input[name="form_start_time"]');
            if (startTime) {
                const timeSpent = Math.floor(Date.now() / 1000) - parseInt(startTime.value);
                if (timeSpent < 3) {
                    alert('Espere unos segundos antes de enviar el formulario');
                    return false;
                }
            }
            
            return true;
        }

        function agregarPregunta() {
            if (contadorPreguntas >= 10) {
                alert('Máximo 10 preguntas permitidas');
                return;
            }

            contadorPreguntas++;
            const container = document.getElementById('preguntas-container');
            
            const preguntaHTML = `
                <div class="pregunta-item">
                    <div class="pregunta-header">
                        <h6><i></i> Pregunta ${contadorPreguntas}</h6>
                        <button type="button" class="btn-eliminar" onclick="eliminarPregunta(this)" title="Eliminar pregunta">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i></i> Pregunta</label>
                        <textarea name="preguntas[]" class="form-control" rows="2" maxlength="1000" required placeholder="Escribe tu pregunta aquí..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-list-ul"></i> Tipo de respuesta</label>
                        <select name="tipos[]" class="form-select tipo-select" required onchange="toggleOpciones(this)">
                            <option value="si_no">Sí/No</option>
                            <option value="selector">Selección múltiple</option>
                            <option value="texto">Texto libre</option>
                        </select>
                    </div>
                    
                    <div class="opciones-container">
                        <label class="form-label"><i class="fas fa-list"></i> Opciones (separadas por coma)</label>
                        <textarea name="opciones[]" class="form-control" rows="2" maxlength="2000" placeholder="Opción 1, Opción 2, Opción 3"></textarea>
                        <small class="text-muted d-block mt-2"><i class="fas fa-lightbulb"></i> Ejemplo: Excelente, Muy bueno, Bueno, Regular, Malo (máximo 20 opciones)</small>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', preguntaHTML);
            actualizarContador();
        }

        function eliminarPregunta(btn) {
            const preguntaItem = btn.closest('.pregunta-item');
            preguntaItem.remove();
            contadorPreguntas--;
            actualizarContador();
        }

        function toggleOpciones(select) {
            const opcionesContainer = select.closest('.pregunta-item').querySelector('.opciones-container');
            if (select.value === 'selector') {
                opcionesContainer.classList.add('show');
                opcionesContainer.querySelector('textarea').required = true;
            } else {
                opcionesContainer.classList.remove('show');
                opcionesContainer.querySelector('textarea').required = false;
            }
        }

        <?php if ($id_negocio): ?>
        document.getElementById('form-encuesta').addEventListener('submit', function(e) {
            if (submitInProgress) {
                e.preventDefault();
                return;
            }

            if (!validateFormSecurity()) {
                e.preventDefault();
                return;
            }

            const preguntas = document.querySelectorAll('textarea[name="preguntas[]"]');
            if (preguntas.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos una pregunta');
                return;
            }

            const tiposSelect = document.querySelectorAll('select[name="tipos[]"]');
            const opciones = document.querySelectorAll('textarea[name="opciones[]"]');
            
            for (let i = 0; i < tiposSelect.length; i++) {
                if (preguntas[i].value.length > 1000) {
                    e.preventDefault();
                    alert('Las preguntas no pueden exceder 1000 caracteres');
                    return;
                }
                
                if (tiposSelect[i].value === 'selector') {
                    if (!opciones[i].value.trim()) {
                        e.preventDefault();
                        alert('Las preguntas de selección múltiple deben tener opciones');
                        return;
                    }
                    
                    const opcionesArray = opciones[i].value.split(',').filter(op => op.trim());
                    if (opcionesArray.length > 20) {
                        e.preventDefault();
                        alert('Máximo 20 opciones por pregunta de selección');
                        return;
                    }
                }
            }

            submitInProgress = true;
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        });

        actualizarContador();
        <?php endif; ?>
    </script>
</body>
</html>