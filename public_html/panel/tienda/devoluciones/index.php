<?php
require_once '../../../../config.php';
require_once '../../../../db-publica.php';
require_once '../../../../db-venta_productos.php';

use Delight\Auth\Auth;

$auth = new Auth($pdo);

// Verificar si el usuario está logeado
if (!$auth->isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

// Verificar si el usuario tiene rol "negocio"
$user_id = $auth->getUserId();
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user['role'] !== 'negocio') {
    echo "<div class='alert alert-danger'>Acceso denegado. Solo los negocios pueden acceder.</div>";
    exit;
}

$usuario_id = $auth->getUserId();
$negocio_id = null;
$error_msg = '';
$success_msg = '';

// --- Get Business ID associated with the user ---
try {
    $stmt_negocio = $pdo2->prepare("SELECT negocio_id FROM negocios WHERE usuario_id = :usuario_id LIMIT 1");
    $stmt_negocio->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_negocio->execute();
    $negocio = $stmt_negocio->fetch(PDO::FETCH_ASSOC);

    if ($negocio) {
        $negocio_id = $negocio['negocio_id'];
    } else {
        $error_msg = "No tiene un negocio asociado para gestionar devoluciones.";
    }
} catch (PDOException $e) {
    $error_msg = "Error al obtener información del negocio: " . $e->getMessage();
    error_log("PDO Error fetching negocio: " . $e->getMessage());
}

// --- Brevo Email Function ---
function sendBrevoEmail($apiKey, $toEmail, $toName, $subject, $htmlContent) {
    $senderEmail = 'info@buscounservicio.es';
    $senderName = 'buscounservicio Devoluciones'; 
    
    $url = 'https://api.brevo.com/v3/smtp/email';
    
    $data = [
        'sender' => ['name' => $senderName, 'email' => $senderEmail],
        'to' => [[ 'email' => $toEmail, 'name' => $toName ]],
        'subject' => $subject,
        'htmlContent' => $htmlContent
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return true; // Email sent successfully
    } else {
        // Log error
        error_log("Brevo API Error: HTTP Code: $httpCode, Response: $response, cURL Error: $curlError");
        return false; // Email sending failed
    }
}

// --- Helper function to get Customer Email ---
function getCustomerEmail($pdo_conn, $cliente_id) {
    try {
        $stmt = $pdo_conn->prepare("SELECT email FROM users WHERE id = :cliente_id LIMIT 1");
        $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user['email'] : null;
    } catch (PDOException $e) {
        error_log("Error fetching customer email: " . $e->getMessage());
        return null;
    }
}

// --- Helper function to get Original Sale Price ---
function getOriginalSalePrice($pdo_conn, $numero_pedido, $producto_id) {
    try {
        // First try to find the sale using just the numero_pedido
        $stmt_alt = $pdo_conn->prepare("SELECT cantidad_total, precio_envio FROM ventas WHERE numero_pedido = :numero_pedido LIMIT 1");
        $stmt_alt->bindParam(':numero_pedido', $numero_pedido, PDO::PARAM_STR);
        $stmt_alt->execute();
        $venta_alt = $stmt_alt->fetch(PDO::FETCH_ASSOC);
        
        if ($venta_alt) {
            $precio_producto = $venta_alt['cantidad_total'] - $venta_alt['precio_envio'];
            return $precio_producto > 0 ? $precio_producto : 0;
        }
        
        // If the first query fails, try with an alternative approach if needed
        // This section can be expanded later if needed
        return null; // Indicate price couldn't be found
    } catch (PDOException $e) {
        error_log("Error fetching sale price: " . $e->getMessage());
        return null;
    }
}

// --- Handle POST Requests for Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $negocio_id !== null) {
    $action = $_POST['action'] ?? '';
    $devolucion_id = filter_input(INPUT_POST, 'devolucion_id', FILTER_VALIDATE_INT);
    
    // Ensure the return belongs to the logged-in business before processing
    if ($devolucion_id) {
        try {
            $stmt_check = $pdo5->prepare("SELECT id, cliente_id, numero_pedido, producto_id, estado FROM devolucion_pedidos WHERE id = :id AND negocio_id = :negocio_id");
            $stmt_check->bindParam(':id', $devolucion_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
            $stmt_check->execute();
            $devolucion_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$devolucion_data) {
                $error_msg = "Devolución no encontrada o no pertenece a su negocio.";
                $action = ''; // Prevent further processing
            }

        } catch (PDOException $e) {
            $error_msg = "Error al verificar la devolución: " . $e->getMessage();
            error_log("PDO Error checking return ownership: " . $e->getMessage());
            $action = ''; // Prevent further processing
        }
    }

    // Proceed only if devolucion_data was fetched successfully
    if (!empty($devolucion_data)) {
        $cliente_id = $devolucion_data['cliente_id'];
        $customer_email = getCustomerEmail($pdo, $cliente_id); // Fetch customer email using main PDO

        if ($customer_email) {
            switch ($action) {
                case 'cancelar_solicitud':
                    if ($devolucion_data['estado'] === 'Solicitada') {
                        $motivo = trim($_POST['motivo_cancelacion'] ?? '');
                        if (!empty($motivo)) {
                            try {
                                $stmt_update = $pdo5->prepare("UPDATE devolucion_pedidos SET estado = 'Rechazada', motivo_rechazo_reserva = :motivo WHERE id = :id AND negocio_id = :negocio_id");
                                $stmt_update->bindParam(':motivo', $motivo, PDO::PARAM_STR);
                                $stmt_update->bindParam(':id', $devolucion_id, PDO::PARAM_INT);
                                $stmt_update->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
                                if ($stmt_update->execute()) {
                                    $success_msg = "Devolución cancelada correctamente.";
                                    // Send email to customer
                                    $email_subject = "Solicitud de Devolución Cancelada (Pedido: {$devolucion_data['numero_pedido']})";
                                    $email_body = "<p>Estimado cliente,</p>" . 
                                                  "<p>Lamentamos informarle que su solicitud de devolución para el pedido {$devolucion_data['numero_pedido']} ha sido cancelada por el siguiente motivo:</p>" . 
                                                  "<p><strong>" . htmlspecialchars($motivo) . "</strong></p>" . 
                                                  "<p>Si tiene alguna pregunta, por favor, póngase en contacto con nosotros.</p>" . 
                                                  "<p>Atentamente,<br>El equipo de la tienda</p>";
                                    sendBrevoEmail(BREVO_API_KEY, $customer_email, 'Cliente', $email_subject, $email_body);
                                } else {
                                    $error_msg = "Error al actualizar el estado de la devolución.";
                                }
                            } catch (PDOException $e) {
                                $error_msg = "Error al cancelar la devolución: " . $e->getMessage();
                                error_log("PDO Error cancelling return: " . $e->getMessage());
                            }
                        } else {
                            $error_msg = "El motivo de la cancelación es obligatorio.";
                        }
                    } else {
                         $error_msg = "No se puede cancelar una devolución que no está en estado 'Solicitada'.";
                    }
                    break;

                case 'aceptar_solicitud':
                     if ($devolucion_data['estado'] === 'Solicitada') {
                        $direccion_envio = trim($_POST['direccion_envio'] ?? '');
                        $monto_devolver = filter_input(INPUT_POST, 'monto_devolver', FILTER_VALIDATE_FLOAT);

                        if (!empty($direccion_envio) && $monto_devolver !== false && $monto_devolver >= 0) {
                            try {
                                $stmt_update = $pdo5->prepare("UPDATE devolucion_pedidos SET estado = 'Iniciada', direccion_envio = :direccion, monto_devuelto = :monto WHERE id = :id AND negocio_id = :negocio_id");
                                $stmt_update->bindParam(':direccion', $direccion_envio, PDO::PARAM_STR);
                                $stmt_update->bindParam(':monto', $monto_devolver, PDO::PARAM_STR); // PDO usually binds floats as strings
                                $stmt_update->bindParam(':id', $devolucion_id, PDO::PARAM_INT);
                                $stmt_update->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
                                if ($stmt_update->execute()) {
                                    $success_msg = "Devolución aceptada. Se han enviado instrucciones al cliente.";
                                    // Send email to customer with instructions
                                    $email_subject = "Instrucciones para su Devolución (Pedido: {$devolucion_data['numero_pedido']})";
                                    $email_body = "<p>Estimado cliente,</p>" . 
                                                  "<p>Su solicitud de devolución para el pedido {$devolucion_data['numero_pedido']} ha sido aceptada.</p>" .
                                                  "<p>Por favor, envíe el producto a la siguiente dirección:</p>" . 
                                                  "<p><strong>" . nl2br(htmlspecialchars($direccion_envio)) . "</strong></p>" . 
                                                  "<p>Una vez recibido y verificado el producto, procederemos a reembolsarle el importe de <strong>" . number_format($monto_devolver, 2, ',', '.') . " €</strong>.</p>" .
                                                  "<p>Guarde el comprobante de envío por si fuera necesario.</p>" .
                                                  "<p>Atentamente,<br>El equipo de la tienda</p>";
                                    sendBrevoEmail(BREVO_API_KEY, $customer_email, 'Cliente', $email_subject, $email_body);
                                } else {
                                    $error_msg = "Error al actualizar la devolución.";
                                }
                            } catch (PDOException $e) {
                                $error_msg = "Error al aceptar la devolución: " . $e->getMessage();
                                error_log("PDO Error accepting return: " . $e->getMessage());
                            }
                        } else {
                            $error_msg = "La dirección de envío y un monto a devolver válido (0 o mayor) son obligatorios.";
                        }
                     } else {
                         $error_msg = "No se puede aceptar una devolución que no está en estado 'Solicitada'.";
                     }
                    break;
                
                case 'rechazar_devolucion':
                     if ($devolucion_data['estado'] === 'Iniciada') {
                        $motivo_rechazo = trim($_POST['motivo_rechazo'] ?? '');
                        if (!empty($motivo_rechazo)) {
                            try {
                                $stmt_update = $pdo5->prepare("UPDATE devolucion_pedidos SET estado = 'Rechazada', motivo_rechazo_reserva = :motivo, devolucion_rechazada_negocio = :motivo WHERE id = :id AND negocio_id = :negocio_id");
                                $stmt_update->bindParam(':motivo', $motivo_rechazo, PDO::PARAM_STR);
                                $stmt_update->bindParam(':id', $devolucion_id, PDO::PARAM_INT);
                                $stmt_update->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
                                if ($stmt_update->execute()) {
                                    $success_msg = "La devolución ha sido rechazada.";
                                    // Send email to customer
                                    $email_subject = "Devolución Rechazada (Pedido: {$devolucion_data['numero_pedido']})";
                                    $email_body = "<p>Estimado cliente,</p>" .
                                                  "<p>Le informamos que, tras recibir y revisar el producto devuelto del pedido {$devolucion_data['numero_pedido']}, la devolución ha sido rechazada por el siguiente motivo:</p>" . 
                                                  "<p><strong>" . htmlspecialchars($motivo_rechazo) . "</strong></p>" . 
                                                  "<p>Si tiene alguna duda, póngase en contacto con nosotros.</p>" . 
                                                  "<p>Atentamente,<br>El equipo de la tienda</p>";
                                    sendBrevoEmail(BREVO_API_KEY, $customer_email, 'Cliente', $email_subject, $email_body);
                                } else {
                                    $error_msg = "Error al actualizar el estado de la devolución.";
                                }
                            } catch (PDOException $e) {
                                $error_msg = "Error al rechazar la devolución: " . $e->getMessage();
                                error_log("PDO Error rejecting return: " . $e->getMessage());
                            }
                        } else {
                            $error_msg = "El motivo del rechazo es obligatorio.";
                        }
                     } else {
                         $error_msg = "No se puede rechazar una devolución que no está en estado 'Iniciada'.";
                     }
                    break;
                
                 case 'aceptar_reembolso':
                     if ($devolucion_data['estado'] === 'Iniciada') {
                         try {
                             $stmt_update = $pdo5->prepare("UPDATE devolucion_pedidos SET estado = 'Completada' WHERE id = :id AND negocio_id = :negocio_id");
                             $stmt_update->bindParam(':id', $devolucion_id, PDO::PARAM_INT);
                             $stmt_update->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
                             if ($stmt_update->execute()) {
                                 $success_msg = "Devolución marcada como completada. Se ha notificado para procesar el reembolso.";
                                 
                                 // Send email to admin/internal email
                                 $admin_email = 'buscounservicio@gmail.com'; 
                                 $admin_subject = "Devolución Exitosa - Procesar Reembolso (ID: {$devolucion_id})";
                                 $admin_body = "<p>Se ha confirmado una devolución como exitosa.</p>" .
                                               "<p><strong>ID de Devolución:</strong> {$devolucion_id}</p>" .
                                               "<p>Por favor, procese el reembolso correspondiente al cliente.</p>";
                                 sendBrevoEmail(BREVO_API_KEY, $admin_email, 'Admin', $admin_subject, $admin_body);

                                 // Send confirmation to customer as well
                                  $customer_subject = "Reembolso Procesado (Pedido: {$devolucion_data['numero_pedido']})";
                                 $customer_body = "<p>Estimado cliente,</p><p>Le confirmamos que hemos procesado el reembolso de su devolución para el pedido {$devolucion_data['numero_pedido']}. El importe debería reflejarse en su cuenta en los próximos días hábiles.</p><p>Atentamente,<br>El equipo de la tienda</p>";
                                 sendBrevoEmail(BREVO_API_KEY, $customer_email, 'Cliente', $customer_subject, $customer_body);

                             } else {
                                 $error_msg = "Error al completar la devolución.";
                             }
                         } catch (PDOException $e) {
                             $error_msg = "Error al completar la devolución: " . $e->getMessage();
                             error_log("PDO Error completing return: " . $e->getMessage());
                         }
                     } else {
                         $error_msg = "No se puede completar una devolución que no está en estado 'Iniciada'.";
                     }
                     break;
            }
        } else {
            $error_msg = "No se pudo encontrar el correo electrónico del cliente para la notificación.";
        }
    }
}

// --- Fetch Returns for Display ---
$devoluciones = [];
if ($negocio_id !== null && empty($error_msg)) { // Fetch only if no major errors so far
    try {
        // Paso 1: Obtener devoluciones desde pdo5
        $stmt_devoluciones = $pdo5->prepare(
            "SELECT dp.* 
             FROM devolucion_pedidos dp 
             WHERE dp.negocio_id = :negocio_id 
             AND dp.estado IN ('Solicitada', 'Iniciada')
             ORDER BY dp.fecha DESC"
        );
        $stmt_devoluciones->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
        $stmt_devoluciones->execute();
        $devoluciones = $stmt_devoluciones->fetchAll(PDO::FETCH_ASSOC);

        // Paso 2: Obtener datos adicionales si hay devoluciones
        if (!empty($devoluciones)) {
            // Recolectar IDs para consultas posteriores
            $cliente_ids = array_unique(array_column($devoluciones, 'cliente_id'));
            $producto_ids = array_unique(array_column($devoluciones, 'producto_id'));

            // Paso 3: Obtener usernames y emails desde pdo (users)
            $users_data = [];
            if (!empty($cliente_ids)) {
                $placeholders = implode(',', array_fill(0, count($cliente_ids), '?'));
                $stmt_users = $pdo->prepare(
                    "SELECT id, username, email 
                     FROM users 
                     WHERE id IN ($placeholders)"
                );
                $stmt_users->execute($cliente_ids);
                $users_data = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
                $users_data = array_column($users_data, null, 'id'); 
            }

            // Paso 4: Obtener nombres de productos desde pdo2 (productos)
            $productos_data = [];
            if (!empty($producto_ids)) {
                $placeholders = implode(',', array_fill(0, count($producto_ids), '?'));
                $stmt_productos = $pdo2->prepare(
                    "SELECT producto_id, nombre 
                     FROM productos 
                     WHERE producto_id IN ($placeholders)"
                );
                $stmt_productos->execute($producto_ids);
                $productos_data = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
                $productos_data = array_column($productos_data, null, 'producto_idid'); 
            }

            // Paso 5: Combinar datos en $devoluciones
            foreach ($devoluciones as &$devolucion) {
                $cliente_id = $devolucion['cliente_id'];
                $producto_id = $devolucion['producto_id'];

                // Agregar datos de users
                $devolucion['cliente_username'] = isset($users_data[$cliente_id]) ? $users_data[$cliente_id]['username'] : null;
                $devolucion['cliente_email'] = isset($users_data[$cliente_id]) ? $users_data[$cliente_id]['email'] : null;

                // Agregar datos de productos
                $devolucion['producto_nombre'] = isset($productos_data[$producto_id]) ? $productos_data[$producto_id]['nombre'] : null;
            }
            unset($devolucion); // Romper la referencia
        }

    } catch (PDOException $e) {
        $error_msg = "Error al obtener la lista de devoluciones: " . $e->getMessage();
        error_log("PDO Error fetching returns: " . $e->getMessage());
    } catch (Exception $e) {
        $error_msg = "Error de configuración: " . $e->getMessage();
        error_log("Configuration Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Devoluciones - Panel Tienda</title>
    <link rel="stylesheet" href="/assets/css/sidebar.css"> 
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

</head>
<body>

    <?php include_once '../../../assets/includes/sidebar.php'; ?>

    <div id="main-content">
        <div class="container">
            <h1>Gestionar Devoluciones</h1>

            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>

            <?php if ($negocio_id === null && empty($error_msg)): ?>
                 <div class="alert alert-danger">Error: No se pudo identificar el negocio.</div>
            <?php elseif (empty($devoluciones) && empty($error_msg)): ?>
                <div class="alert alert-info">No hay devoluciones pendientes de gestionar en este momento.</div>
            <?php elseif (!empty($devoluciones)): ?>
                <!-- Nuevo contenedor para la tabla con scroll horizontal -->
                <div class="table-container">
                    <table class="devoluciones-table">
                        <thead>
                            <tr>
                                <th>ID Devolución</th>
                                <th>Nº Pedido</th>
                                <th>Fecha Solicitud</th>
                                <th>Cliente</th>
                                <th>Producto</th> 
                                <th>Motivo</th>
                                <th>Comentarios</th>
                                <th>Foto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devoluciones as $devolucion): ?>
                                <?php 
                                    // Calculate original price for the 'Accept' form prefill/info
                                    $precio_original_producto = getOriginalSalePrice($pdo5, $devolucion['numero_pedido'], $devolucion['producto_id']);
                                    // Ensure monto_devuelto exists and is numeric for the confirmation button
                                    $monto_devuelto_valido = isset($devolucion['monto_devuelto']) && is_numeric($devolucion['monto_devuelto']) ? $devolucion['monto_devuelto'] : '0';
                                ?>
                                <tr>
                                    <td data-label="ID Devolución"><?php echo htmlspecialchars($devolucion['id']); ?></td>
                                    <td data-label="Nº Pedido"><?php echo htmlspecialchars($devolucion['numero_pedido']); ?></td>
                                    <td data-label="Fecha Solicitud"><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($devolucion['fecha']))); ?></td>
                                    <td data-label="Cliente"><?php echo htmlspecialchars($devolucion['cliente_username'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($devolucion['cliente_email'] ?? 'N/A'); ?>)</td>
                                    <td data-label="Producto"><?php echo htmlspecialchars($devolucion['producto_nombre'] ?? 'ID: ' . $devolucion['producto_id']); ?></td> 
                                    <td data-label="Motivo"><?php echo htmlspecialchars($devolucion['motivo_devolucion']); ?></td>
                                    <td data-label="Comentarios"><?php echo nl2br(htmlspecialchars($devolucion['comentarios_adicionales'] ?? '')); ?></td>
                                    <td data-label="Foto">
                                        <?php if (!empty($devolucion['url_fotos'])): ?>
                                            <?php 
                                                // Parse the JSON string to get the actual filename
                                                $fotos_array = json_decode($devolucion['url_fotos'], true);
                                                // Get the first image (or the only one)
                                                $foto_filename = is_array($fotos_array) ? $fotos_array[0] : $devolucion['url_fotos'];
                                                // Remove any extra "imagenes/devoluciones/" prefix if present in the filename
                                                $foto_filename = str_replace('imagenes/devoluciones/', '', $foto_filename);
                                                // Set the base URL path
                                                $foto_url = '/imagenes/devoluciones/' . $foto_filename;
                                            ?>
                                            <a href="<?php echo $foto_url; ?>" target="_blank">
                                                <img src="<?php echo $foto_url; ?>" alt="Foto Devolución" style="max-width: 100px; max-height: 100px;">
                                            </a>
                                        <?php else: ?>
                                            Sin foto
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Estado"><?php echo htmlspecialchars($devolucion['estado']); ?></td>
                                    <td data-label="Acciones">
                                        <?php if ($devolucion['estado'] === 'Solicitada'): ?>
                                            <button class="btn btn-aceptar" onclick="mostrarFormularioAceptar(<?php echo $devolucion['id']; ?>, <?php echo json_encode($precio_original_producto); ?>)">Aceptar Solicitud</button>
                                            <button class="btn btn-cancelar" onclick="mostrarFormularioCancelar(<?php echo $devolucion['id']; ?>)">Cancelar Solicitud</button>
                                        <?php elseif ($devolucion['estado'] === 'Iniciada'): ?>
                                            <button class="btn btn-info" onclick="confirmarReembolso(<?php echo $devolucion['id']; ?>, '<?php echo $monto_devuelto_valido; ?>')">Aceptar y Reembolsar</button>
                                            <button class="btn btn-rechazar" onclick="mostrarFormularioRechazar(<?php echo $devolucion['id']; ?>)">Rechazar Devolución</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals for Actions -->

    <!-- Modal: Aceptar Solicitud -->
    <div id="form-aceptar-solicitud" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="cerrarModal('form-aceptar-solicitud')">&times;</span>
            <h2>Aceptar Solicitud de Devolución</h2>
            <form class="modal-form" method="POST" action="">
                <input type="hidden" name="action" value="aceptar_solicitud">
                <input type="hidden" name="devolucion_id" id="aceptar-devolucion-id" value="">
                
                <label for="direccion_envio">Dirección para envío del producto:</label>
                <textarea id="direccion_envio" name="direccion_envio" rows="4" required placeholder="Escriba aquí la dirección completa a la que el cliente debe enviar el producto..."></textarea>
                
                <label for="monto_devolver">Monto a devolver (€):</label>
                <input type="number" id="monto_devolver" name="monto_devolver" step="0.01" min="0" required placeholder="0.00">
                 <p class="precio-info" id="precio-original-info"></p> <!-- Info about original price -->

                <button type="submit" class="btn btn-aceptar">Confirmar Aceptación y Enviar Instrucciones</button>
            </form>
        </div>
    </div>

    <!-- Modal: Cancelar Solicitud -->
    <div id="form-cancelar-solicitud" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="cerrarModal('form-cancelar-solicitud')">&times;</span>
            <h2>Cancelar Solicitud de Devolución</h2>
            <form class="modal-form" method="POST" action="">
                <input type="hidden" name="action" value="cancelar_solicitud">
                <input type="hidden" name="devolucion_id" id="cancelar-devolucion-id" value="">
                
                <label for="motivo_cancelacion">Motivo de la cancelación:</label>
                <textarea id="motivo_cancelacion" name="motivo_cancelacion" rows="4" required placeholder="Explique por qué se cancela la solicitud inicial..."></textarea>
                
                <button type="submit" class="btn btn-cancelar">Confirmar Cancelación</button>
            </form>
        </div>
    </div>

     <!-- Modal: Rechazar Devolución (Estado Iniciada) -->
    <div id="form-rechazar-devolucion" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="cerrarModal('form-rechazar-devolucion')">&times;</span>
            <h2>Rechazar Devolución Recibida</h2>
            <form class="modal-form" method="POST" action="">
                <input type="hidden" name="action" value="rechazar_devolucion">
                <input type="hidden" name="devolucion_id" id="rechazar-devolucion-id" value="">
                
                <label for="motivo_rechazo">Motivo del rechazo:</label>
                <textarea id="motivo_rechazo" name="motivo_rechazo" rows="4" required placeholder="Explique por qué se rechaza la devolución después de recibir el producto (ej. producto dañado, no coincide, etc.)..."></textarea>
                
                <button type="submit" class="btn btn-rechazar">Confirmar Rechazo</button>
            </form>
        </div>
    </div>


    <!-- Include Sidebar JS -->
    <script src="/assets/js/sidebar.js" defer></script>
    <!-- Include Page Specific JS -->
    <script src="gestionar_devoluciones.js"></script>
</body>
</html> 