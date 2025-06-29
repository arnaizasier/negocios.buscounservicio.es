<?php
require_once 'conexion.php';

// Inicializar $auth
$auth = new \Delight\Auth\Auth($pdo);

// Verificar que el usuario esté autenticado
$usuario_id = verificarUsuarioAutenticado($auth);

// Comprobar que existe un ID de negocio
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$negocio_id = intval($_GET['id']);

// Verificar que el negocio pertenece al usuario
$stmt = $pdo2->prepare("SELECT * FROM negocios WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id");
$stmt->execute([':negocio_id' => $negocio_id, ':usuario_id' => $usuario_id]);
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    header('Location: index.php');
    exit();
}

// Inicializar FAQ
$preguntas_frecuentes = [];
if (!empty($negocio['preguntas_frecuentes'])) {
    $preguntas_frecuentes = json_decode($negocio['preguntas_frecuentes'], true) ?: [];
}

// Si se envía el formulario, procesar las acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Acción: Agregar FAQ
    if (isset($_POST['agregar_faq']) && !empty($_POST['pregunta']) && !empty($_POST['respuesta'])) {
        $pregunta = sanitizarInput($_POST['pregunta']);
        $respuesta = sanitizarInput($_POST['respuesta']);
        
        // Crear un ID único para la FAQ
        $faq_id = uniqid('faq_');
        
        // Agregar la nueva FAQ
        $preguntas_frecuentes[$faq_id] = [
            'pregunta' => $pregunta,
            'respuesta' => $respuesta
        ];
        
        // Actualizar en la base de datos
        actualizarFAQ($pdo2, $negocio_id, $preguntas_frecuentes);
        $success = "La pregunta frecuente se ha añadido correctamente.";
    }
    
    // Acción: Eliminar FAQ
    else if (isset($_POST['eliminar_faq']) && !empty($_POST['faq_id'])) {
        $faq_id = sanitizarInput($_POST['faq_id']);
        
        if (isset($preguntas_frecuentes[$faq_id])) {
            unset($preguntas_frecuentes[$faq_id]);
            
            // Actualizar en la base de datos
            actualizarFAQ($pdo2, $negocio_id, $preguntas_frecuentes);
            $success = "La pregunta frecuente se ha eliminado correctamente.";
        }
    }
    
    // Acción: Finalizar
    else if (isset($_POST['finalizar'])) {
        // Guardar las FAQ antes de finalizar
        if (isset($_POST['pregunta']) && isset($_POST['respuesta'])) {
            $preguntas = $_POST['pregunta'] ?? [];
            $respuestas = $_POST['respuesta'] ?? [];
            
            // Recopilar los datos actualizados
            $nuevas_faqs = [];
            
            foreach ($preguntas_frecuentes as $faq_id => $faq) {
                if (isset($_POST["pregunta_$faq_id"]) && isset($_POST["respuesta_$faq_id"])) {
                    $nuevas_faqs[$faq_id] = [
                        'pregunta' => sanitizarInput($_POST["pregunta_$faq_id"]),
                        'respuesta' => sanitizarInput($_POST["respuesta_$faq_id"])
                    ];
                }
            }
            
            // Actualizar en la base de datos
            actualizarFAQ($pdo2, $negocio_id, $nuevas_faqs);
        }
        
        // Redirigir a la página de confirmación
        header("Location: confirmacion.php?id=$negocio_id");
        exit();
    }
}

// Función para actualizar las FAQ en la base de datos
function actualizarFAQ($pdo, $negocio_id, $preguntas_frecuentes) {
    $faqs_json = json_encode($preguntas_frecuentes);
    
    $stmt = $pdo->prepare("UPDATE negocios SET preguntas_frecuentes = :preguntas_frecuentes WHERE negocio_id = :negocio_id");
    $stmt->execute([
        ':preguntas_frecuentes' => $faqs_json,
        ':negocio_id' => $negocio_id
    ]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Negocio - Preguntas Frecuentes</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/paso7.css">
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Preguntas Frecuentes</h2>
            
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="form-group">
                <div class="form-group">
                    <label for="pregunta">Pregunta</label>
                    <input type="text" id="pregunta" name="pregunta">
                </div>
                
                <div class="form-group">
                    <label for="respuesta">Respuesta</label>
                    <textarea id="respuesta" name="respuesta"></textarea>
                </div>
                
                <button type="submit" name="agregar_faq">Añadir</button>
            </form>
            
            <div class="faq-container">
                <?php if (empty($preguntas_frecuentes)): ?>
                    <p>No hay preguntas frecuentes.</p>
                <?php else: ?>
                    <form method="POST" action="">
                        <?php foreach ($preguntas_frecuentes as $faq_id => $faq): ?>
                            <div class="faq-item">
                                <div class="faq-header">
                                    <h3>Pregunta</h3>
                                    <form method="POST" action="" style="margin: 0;">
                                        <input type="hidden" name="faq_id" value="<?php echo $faq_id; ?>">
                                        <button type="submit" name="eliminar_faq" class="button-secondary" style="padding: 5px 10px;">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="faq-content">
                                    <div class="form-group">
                                        <textarea name="pregunta_<?php echo $faq_id; ?>" style="font-weight: bold;"><?php echo htmlspecialchars($faq['pregunta']); ?></textarea>
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <textarea name="respuesta_<?php echo $faq_id; ?>"><?php echo htmlspecialchars($faq['respuesta']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="btn-nav">
                <a href="paso6.php?id=<?php echo $negocio_id; ?>" class="button-secondary">Anterior</a>
                <form method="POST" action="" style="margin: 0;">
                    <button type="submit" name="finalizar" class="button-special">Publicar Negocio</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 