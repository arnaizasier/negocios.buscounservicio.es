<?php
require_once 'conexion.php';

// Inicializar $auth
$auth = new \Delight\Auth\Auth($pdo);

$usuario_id = verificarUsuarioAutenticado($auth);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$negocio_id = intval($_GET['id']);

$stmt = $pdo2->prepare("SELECT * FROM negocios WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id");
$stmt->execute([':negocio_id' => $negocio_id, ':usuario_id' => $usuario_id]);
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefono = sanitizarInput($_POST['telefono'] ?? '');
    $pagina_web = sanitizarInput($_POST['pagina_web'] ?? '');
    $correo_electronico = sanitizarInput($_POST['correo_electronico'] ?? '');
    $facebook = sanitizarInput($_POST['facebook'] ?? '');
    $twitter = sanitizarInput($_POST['twitter'] ?? '');
    $youtube = sanitizarInput($_POST['youtube'] ?? '');
    $instagram = sanitizarInput($_POST['instagram'] ?? '');
    $whatsapp = sanitizarInput($_POST['whatsapp'] ?? '');
    $linkedin = sanitizarInput($_POST['linkedin'] ?? '');
    
    if (!empty($correo_electronico) && !filter_var($correo_electronico, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del correo electrónico no es válido.";
    } 
    else if (!empty($pagina_web) && !filter_var($pagina_web, FILTER_VALIDATE_URL)) {
        $error = "El formato de la URL de la página web no es válido.";
    } 
    else {
        try {
            $stmt = $pdo2->prepare("UPDATE negocios SET 
                telefono = :telefono,
                pagina_web = :pagina_web,
                correo_electronico = :correo_electronico,
                facebook = :facebook,
                twitter = :twitter,
                youtube = :youtube,
                instagram = :instagram,
                whatsapp = :whatsapp,
                linkedin = :linkedin
                WHERE negocio_id = :negocio_id");
                
            if (!empty($whatsapp)) {
                $whatsapp = preg_replace('/[^0-9]/', '', $whatsapp);
                // Añadir el prefijo wa.me/
                $whatsapp = 'wa.me/' . $whatsapp;
            }
            
            $stmt->execute([
                ':telefono' => $telefono,
                ':pagina_web' => $pagina_web,
                ':correo_electronico' => $correo_electronico,
                ':facebook' => $facebook,
                ':twitter' => $twitter,
                ':youtube' => $youtube,
                ':instagram' => $instagram,
                ':whatsapp' => $whatsapp,
                ':linkedin' => $linkedin,
                ':negocio_id' => $negocio_id
            ]);
            
            header("Location: paso5.php?id=$negocio_id");
            exit();
        } catch (PDOException $e) {
            $error = "Error al guardar los datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Negocio - Contacto y Redes Sociales</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/paso4.css">
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Contacto y Redes Sociales</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="contact-section">
                    <div class="section-header">
                        <h3>Información de Contacto</h3>
                        <div class="icons-container">
                            <div class="icon"><i class="fas fa-phone icon-phone"></i></div>
                            <div class="icon"><i class="fab fa-whatsapp icon-whatsapp"></i></div>
                            <div class="icon"><i class="fas fa-globe icon-globe"></i></div>
                            <div class="icon"><i class="fas fa-envelope icon-envelope"></i></div>
                        </div>
                    </div>
                    
                    <div class="form-controls">
                        <div class="form-control-item">
                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="tel" id="telefono" name="telefono" placeholder="Sin esapcios" value="<?php echo htmlspecialchars($negocio['telefono'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-control-item">
                            <div class="form-group">
                                <label for="whatsapp">WhatsApp</label>
                                <input type="tel" id="whatsapp" name="whatsapp" placeholder="Sin esapcios" 
                                       value="<?php 
                                            if (!empty($negocio['whatsapp']) && strpos($negocio['whatsapp'], 'wa.me/') === 0) {
                                                echo htmlspecialchars(substr($negocio['whatsapp'], 6));
                                            } else {
                                                echo htmlspecialchars($negocio['whatsapp'] ?? '');
                                            }
                                       ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-controls">
                        <div class="form-control-item">
                            <div class="form-group">
                                <label for="pagina_web">Página Web</label>
                                <input type="url" id="pagina_web" name="pagina_web" value="<?php echo htmlspecialchars($negocio['pagina_web'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-control-item">
                            <div class="form-group">
                                <label for="correo_electronico">Correo Electrónico</label>
                                <input type="email" id="correo_electronico" name="correo_electronico" value="<?php echo htmlspecialchars($negocio['correo_electronico'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="contact-section">
                    <div class="section-header">
                        <h3>Redes Sociales</h3>
                        <div class="icons-container">
                            <div class="icon"><i class="fab fa-facebook icon-facebook"></i></div>
                            <div class="icon"><i class="fab fa-instagram icon-instagram"></i></div>
                            <div class="icon"><i class="fab fa-x-twitter icon-x-twitter"></i></div>
                            <div class="icon"><i class="fab fa-youtube icon-youtube"></i></div>
                            <div class="icon"><i class="fab fa-linkedin icon-linkedin"></i></div>
                        </div>
                    </div>
                    
                    <div class="form-controls">
                        <div class="form-control-item">
                            <div class="form-group">
                                <label for="facebook">Facebook</label>
                                <input type="url" id="facebook" name="facebook" placeholder="https://www.facebook.com/tunegocio" value="<?php echo htmlspecialchars($negocio['facebook'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-control-item">
                            <div class="form-group">
                                <label for="instagram">Instagram</label>
                                <input type="url" id="instagram" name="instagram" placeholder="https://www.instagram.com/tunegocio" value="<?php echo htmlspecialchars($negocio['instagram'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-controls">
                        <div class="form-control-item">
                            <div class="form-group">
                                <label for="twitter">X (Twitter)</label>
                                <input type="url" id="twitter" name="twitter" placeholder="https://twitter.com/tunegocio" value="<?php echo htmlspecialchars($negocio['twitter'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-control-item">
                            <div class="form-group">
                                <label for="youtube">YouTube</label>
                                <input type="url" id="youtube" name="youtube" placeholder="https://www.youtube.com/c/tunegocio" value="<?php echo htmlspecialchars($negocio['youtube'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-controls">
                        <div class="form-control-item">
                            <div class="form-group">
                                <label for="linkedin">LinkedIn</label>
                                <input type="url" id="linkedin" name="linkedin" placeholder="https://www.linkedin.com/company/tunegocio" value="<?php echo htmlspecialchars($negocio['linkedin'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-control-item">
                        </div>
                    </div>
                </div>
                
                <div class="btn-nav">
                    <a href="paso3.php?id=<?php echo $negocio_id; ?>" class="button-secondary">Anterior</a>
                    <button type="submit">Siguiente</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 