<?php

require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";
require_once __DIR__ . "/../../../db-suscripciones.php";

use Stripe\Webhook;
use Stripe\Checkout\Session;

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$event = null;

try {
    $event = Webhook::constructEvent(
        $payload, $sig_header, WEBHOOKPERFILPLANPREMIUM
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

try {
    switch ($event->type) {
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            handleSubscriptionEvent($event->data->object, $pdo, $pdo4);
            break;
        case 'customer.subscription.deleted':
            handleSubscriptionCancellation($event->data->object, $pdo4);
            break;
        default:
            break;
    }
} catch (Exception $e) {
    error_log("Error en webhook: " . $e->getMessage());
    http_response_code(500);
    exit();
}

http_response_code(200);
echo json_encode(['status' => 'success']);

function handleSubscriptionEvent($subscription, $pdo, $pdo4) {
    
    $usuario_id = $subscription->metadata['user_id'] ?? null;
    $negocio_id = $subscription->metadata['negocio_id'] ?? null;
    
    if (!$usuario_id) {
        error_log("No se pudo obtener usuario_id del subscription: {$subscription->id}");
        return;
    }

    if ($subscription->status === 'incomplete' || $subscription->status === 'incomplete_expired') {
        error_log("Subscription con estado incompleto ignorada: {$subscription->id}, Status: {$subscription->status}");
        return;
    }

    // Verificar si ya existe una suscripción reciente para evitar duplicados
    $checkRecentStmt = $pdo4->prepare("
        SELECT id FROM suscripciones 
        WHERE usuario_id = :usuario_id 
        " . ($negocio_id ? "AND negocio_id = :negocio_id" : "AND negocio_id IS NULL") . "
        AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        AND estado_plan = 'activo'
        AND tipo_plan = 'premium'
    ");
    
    $checkRecentStmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    if ($negocio_id) {
        $checkRecentStmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
    }
    
    $checkRecentStmt->execute();
    if ($checkRecentStmt->fetch()) {
        error_log("Suscripción reciente ya existe - evitando duplicado. Usuario: $usuario_id, Negocio: $negocio_id, Subscription: {$subscription->id}");
        return;
    }

    try {
        require_once __DIR__ . '/../../../db-suscripciones.php';
        
        $plan = 'Premium';
        $estado_plan = ($subscription->status === 'active') ? 'activo' : $subscription->status;
        $fecha_expiracion = date('Y-m-d', $subscription->current_period_end);
        $tipo_plan = 'premium';
        
        $pdo4->beginTransaction();

        $checkStmt = $pdo4->prepare("
            SELECT id FROM suscripciones 
            WHERE usuario_id = :usuario_id " . 
            ($negocio_id ? "AND negocio_id = :negocio_id" : "AND negocio_id IS NULL")
        );
        
        $checkStmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        if ($negocio_id) {
            $checkStmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
        }
        
        $checkStmt->execute();
        $existingSuscripcion = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSuscripcion) {
            $updateStmt = $pdo4->prepare("
                UPDATE suscripciones 
                SET plan = :plan, estado_plan = :estado_plan, fecha_expiracion = :fecha_expiracion, tipo_plan = :tipo_plan 
                WHERE usuario_id = :usuario_id " . 
                ($negocio_id ? "AND negocio_id = :negocio_id" : "AND negocio_id IS NULL")
            );
            
            $updateStmt->bindParam(':plan', $plan);
            $updateStmt->bindParam(':estado_plan', $estado_plan);
            $updateStmt->bindParam(':fecha_expiracion', $fecha_expiracion);
            $updateStmt->bindParam(':tipo_plan', $tipo_plan);
            $updateStmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            if ($negocio_id) {
                $updateStmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
            }
            
            $updateStmt->execute();
            error_log("Subscription actualizada - Usuario: $usuario_id, Negocio: $negocio_id, Estado: $estado_plan, Subscription ID: {$subscription->id}");
        } else {
            if ($subscription->status === 'active') {
                if ($negocio_id !== null) {
                    $insertStmt = $pdo4->prepare("
                        INSERT INTO suscripciones (usuario_id, negocio_id, plan, estado_plan, fecha_expiracion, tipo_plan) 
                        VALUES (:usuario_id, :negocio_id, :plan, :estado_plan, :fecha_expiracion, :tipo_plan)
                    ");
                    
                    $insertStmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                    $insertStmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
                    $insertStmt->bindParam(':plan', $plan);
                    $insertStmt->bindParam(':estado_plan', $estado_plan);
                    $insertStmt->bindParam(':fecha_expiracion', $fecha_expiracion);
                    $insertStmt->bindParam(':tipo_plan', $tipo_plan);
                } else {
                    $insertStmt = $pdo4->prepare("
                        INSERT INTO suscripciones (usuario_id, plan, estado_plan, fecha_expiracion, tipo_plan) 
                        VALUES (:usuario_id, :plan, :estado_plan, :fecha_expiracion, :tipo_plan)
                    ");
                    
                    $insertStmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                    $insertStmt->bindParam(':plan', $plan);
                    $insertStmt->bindParam(':estado_plan', $estado_plan);
                    $insertStmt->bindParam(':fecha_expiracion', $fecha_expiracion);
                    $insertStmt->bindParam(':tipo_plan', $tipo_plan);
                }
                
                $insertStmt->execute();
                error_log("Nueva subscription creada - Usuario: $usuario_id, Negocio: $negocio_id, Subscription ID: {$subscription->id}");
                
                // Enviar correo de confirmación solo para nuevas suscripciones
                enviarCorreoConfirmacionSuscripcion($usuario_id, $pdo);
            }
        }
        
        $pdo4->commit();
        
    } catch (Exception $e) {
        $pdo4->rollBack();
        error_log("Error en handleSubscriptionEvent: " . $e->getMessage());
    }
}

function handleSubscriptionCancellation($subscription, $pdo4) {
    
    $usuario_id = $subscription->metadata['user_id'] ?? null;
    $negocio_id = $subscription->metadata['negocio_id'] ?? null;
    
    if (!$usuario_id) {
        error_log("No se pudo obtener usuario_id del subscription cancelado: {$subscription->id}");
        return;
    }

    try {
        require_once __DIR__ . '/../../../db-suscripciones.php';
        
        $pdo4->beginTransaction();
        
        $updateStmt = $pdo4->prepare("
            UPDATE suscripciones 
            SET estado_plan = 'cancelado'
            WHERE usuario_id = :usuario_id " . 
            ($negocio_id ? "AND negocio_id = :negocio_id" : "AND negocio_id IS NULL") . "
            AND estado_plan = 'activo'
        ");
        
        $updateStmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        if ($negocio_id) {
            $updateStmt->bindParam(':negocio_id', $negocio_id, PDO::PARAM_INT);
        }
        
        $updateStmt->execute();
        
        $pdo4->commit();
        
        error_log("Subscription cancelada - Usuario: $usuario_id, Negocio: $negocio_id, Subscription ID: {$subscription->id}");
        
    } catch (Exception $e) {
        $pdo4->rollBack();
        error_log("Error en handleSubscriptionCancellation: " . $e->getMessage());
    }
}

function enviarCorreoConfirmacionSuscripcion($usuario_id, $pdo)
{
    if (empty($usuario_id) || !is_numeric($usuario_id)) {
        error_log("ID de usuario no válido en el webhook.");
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = :usuario_id");
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            error_log("Usuario no encontrado con ID: $usuario_id");
            return;
        }
        
        $email_destino = $usuario['email'];
        $nombre_destino = $usuario['first_name'] ?? '';
        
        include __DIR__ . '/helpers/enviar-correo-confirmacion.php';
        
        error_log("Correo enviado exitosamente a: $email_destino");
        
    } catch (Exception $e) {
        error_log("Error al enviar correo de confirmación: " . $e->getMessage());
    }
}

?>