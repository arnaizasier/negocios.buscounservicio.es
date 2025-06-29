<?php
require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../db-publica.php";
require_once __DIR__ . "/../../../db-suscripciones.php";
require_once 'helpers/email-functions.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$endpoint_secret = WEBHOOKDESTACATUNEGOCIO;

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    error_log('Webhook error - Invalid payload: ' . $e->getMessage());
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    error_log('Webhook error - Invalid signature: ' . $e->getMessage());
    http_response_code(400);
    exit();
}

error_log('Webhook DESTACADO received: ' . $event->type . ' - Event ID: ' . $event->id);

switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        
        // Verificar si es un pago de destacado
        $es_pago_destacado = (
            $session->payment_status === 'paid' && 
            isset($session->metadata->tipo_destacado) &&
            isset($session->metadata->negocio_id) &&
            isset($session->metadata->user_id) &&
            in_array($session->metadata->tipo_destacado, ['Mensual', 'Anual'])
        );
        
        if ($es_pago_destacado) {
            try {
                $negocio_id = $session->metadata->negocio_id ?? null;
                $user_id = $session->metadata->user_id ?? null;
                $tipo_destacado = $session->metadata->tipo_destacado ?? 'Mensual';
                
                if (!$negocio_id) {
                    error_log('Webhook error - No negocio_id in metadata');
                    break;
                }
                
                if (!$user_id) {
                    error_log('Webhook error - No user_id in metadata');
                    break;
                }
                
                $pdo2->beginTransaction();
                $pdo4->beginTransaction();
                
                // Actualizar negocio como destacado
                $stmt = $pdo2->prepare("UPDATE negocios SET destacado = 'SI' WHERE negocio_id = ?");
                $stmt->execute([$negocio_id]);
                
                // Verificar si existe suscripción
                $stmt = $pdo4->prepare("SELECT * FROM suscripciones WHERE negocio_id = ?");
                $stmt->execute([$negocio_id]);
                $suscripcion = $stmt->fetch();
                
                $expiracion_fecha = $tipo_destacado === 'Anual' 
                    ? date('Y-m-d', strtotime('+1 year')) 
                    : date('Y-m-d', strtotime('+1 month'));
                
                if ($suscripcion) {
                    // Actualizar suscripción existente
                    $stmt = $pdo4->prepare("
                        UPDATE suscripciones 
                        SET destacado = 1, 
                            expiracion_fecha = ?, 
                            tipo_destacado = ?, 
                            estado_destacado = 'activo'
                        WHERE negocio_id = ?
                    ");
                    $stmt->execute([$expiracion_fecha, $tipo_destacado, $negocio_id]);
                    
                    error_log("Suscripción actualizada para negocio_id: $negocio_id");
                } else {
                    // Crear nueva suscripción
                    $stmt = $pdo4->prepare("
                        INSERT INTO suscripciones 
                        (usuario_id, negocio_id, destacado, expiracion_fecha, tipo_destacado, estado_destacado) 
                        VALUES (?, ?, 1, ?, ?, 'activo')
                    ");
                    $stmt->execute([$user_id, $negocio_id, $expiracion_fecha, $tipo_destacado]);
                    
                    error_log("Nueva suscripción creada para negocio_id: $negocio_id");
                }
                
                $pdo2->commit();
                $pdo4->commit();
                
                // Enviar correo de confirmación
                enviarCorreoDeConfirmacion($user_id, $tipo_destacado, $pdo);
                
                error_log("Pago DESTACADO procesado exitosamente - Session ID: {$session->id}, Negocio ID: $negocio_id");
                
            } catch (Exception $e) {
                $pdo2->rollBack();
                $pdo4->rollBack();
                
                error_log('Error procesando pago en webhook: ' . $e->getMessage());
                error_log('Session ID: ' . $session->id);
                error_log('Negocio ID: ' . ($negocio_id ?? 'null'));
            }
        } else {
            $razon_ignore = [];
            if ($session->payment_status !== 'paid') {
                $razon_ignore[] = "pago no completado ({$session->payment_status})";
            }
            if (!isset($session->metadata->tipo_destacado)) {
                $razon_ignore[] = "sin metadata tipo_destacado";
            }
            if (!isset($session->metadata->negocio_id)) {
                $razon_ignore[] = "sin metadata negocio_id";
            }
            if (!isset($session->metadata->user_id)) {
                $razon_ignore[] = "sin metadata user_id";
            }
            if (isset($session->metadata->tipo_destacado) && !in_array($session->metadata->tipo_destacado, ['Mensual', 'Anual'])) {
                $razon_ignore[] = "tipo_destacado inválido ({$session->metadata->tipo_destacado})";
            }
            
            $razon_completa = implode(', ', $razon_ignore);
            error_log("Webhook DESTACADO - Evento ignorado: $razon_completa - Session ID: {$session->id}");
        }
        break;
        
    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        
        try {
            error_log("Suscripción cancelada - Subscription ID: {$subscription->id}");
            
        } catch (Exception $e) {
            error_log('Error procesando cancelación de suscripción: ' . $e->getMessage());
        }
        break;
        
    case 'invoice.payment_succeeded':
        $invoice = $event->data->object;
        error_log("Pago de factura exitoso - Invoice ID: {$invoice->id}");
        break;
        
    case 'invoice.payment_failed':
        $invoice = $event->data->object;
        error_log("Pago de factura fallido - Invoice ID: {$invoice->id}");
        break;
        
    default:
        error_log("Evento no manejado: {$event->type}");
}

http_response_code(200);
?>