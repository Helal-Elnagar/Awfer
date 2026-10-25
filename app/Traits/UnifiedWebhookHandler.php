<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

trait UnifiedWebhookHandler
{
    /**
     * Handle webhook from MyFatoorah
     */
    public function webhook(Request $request)
    {
        try {
            // Validate webhook signature if configured
            $webhookSecret = config('myfatoorah.webhook_secret');
            if ($webhookSecret) {
                $signature = $request->header('MyFatoorah-Signature');
                if (!$this->validateWebhookSignature($request->all(), $webhookSecret, $signature)) {
                    Log::warning('Invalid MyFatoorah webhook signature');
                    return response('Invalid signature', 401);
                }
            }

            $data = $request->all();
            $type = $this->getPaymentType(); // Get type from controller
            Log::info("MyFatoorah {$type} payment webhook received", $data);

            // Extract payment information - handle different webhook structures
            $paymentId = $data['Data']['InvoiceId'] ?? $data['InvoiceId'] ?? null;
            $status = $data['EventType'] ?? $data['InvoiceStatus'] ?? null;
            $entityId = $data['Data']['CustomerReference'] ?? $data['CustomerReference'] ?? null;

            if (!$paymentId) {
                Log::warning("MyFatoorah {$type} webhook missing payment ID", $data);
                return response('Missing payment ID', 400);
            }

            // Process the webhook based on event type
            switch ($status) {
                case 'InvoiceStatusChanged':
                case 'Paid':
                case 'DuplicatePayment':
                    return $this->handlePaymentStatusChanged($paymentId, $entityId, $data, $type);
                
                case 'Failed':
                case 'Cancelled':
                case 'Expired':
                    return $this->handlePaymentFailed($paymentId, $entityId, $data, $type);
                
                default:
                    Log::info("Unhandled MyFatoorah {$type} webhook event type", [
                        'event_type' => $status,
                        'payment_id' => $paymentId,
                        'entity_id' => $entityId,
                        'type' => $type
                    ]);
                    return response('OK', 200);
            }

        } catch (\Exception $e) {
            Log::error("MyFatoorah {$type} payment webhook processing failed", [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'type' => $type
            ]);

            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle successful payment status change
     */
    private function handlePaymentStatusChanged($paymentId, $entityId, $data, $type)
    {
        return DB::transaction(function () use ($paymentId, $entityId, $data, $type) {
            try {
                // Call the appropriate verification method based on type
                $result = $this->verifyPayment($paymentId, $entityId, $type);

                if ($result['success']) {
                    $entity = $result['entity'];
                    $entityName = $type === 'order' ? 'order' : 'enrollment';
                    
                    Log::info("{$type} payment webhook processed successfully", [
                        'payment_id' => $paymentId,
                        'entity_id' => $entity->id,
                        'entity_name' => $entityName,
                        'status' => $entity->status ?? $entity->current_status,
                        'payment_status' => $entity->payment_status
                    ]);

                    return response('Payment processed successfully', 200);
                } else {
                    Log::warning("{$type} payment webhook verification failed", [
                        'payment_id' => $paymentId,
                        'entity_id' => $entityId,
                        'type' => $type,
                        'error' => $result['message']
                    ]);

                    return response('Payment verification failed', 400);
                }

            } catch (\Exception $e) {
                Log::error("{$type} payment webhook status change handling failed", [
                    'payment_id' => $paymentId,
                    'entity_id' => $entityId,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);

                return response('Status change handling failed', 500);
            }
        });
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($paymentId, $entityId, $data, $type)
    {
        return DB::transaction(function () use ($paymentId, $entityId, $data, $type) {
            try {
                // Find entity by ID
                $entity = $this->findEntity($entityId, $type);

                if (!$entity) {
                    Log::warning("{$type} entity not found for failed payment webhook", [
                        'payment_id' => $paymentId,
                        'entity_id' => $entityId,
                        'type' => $type
                    ]);
                    return response('Entity not found', 404);
                }

                // Update entity status to failed
                $this->updateEntityToFailed($entity, $type);

                Log::info("{$type} payment failed webhook processed", [
                    'payment_id' => $paymentId,
                    'entity_id' => $entity->id,
                    'type' => $type,
                    'status' => $entity->status ?? $entity->current_status,
                    'payment_status' => $entity->payment_status
                ]);

                return response('Payment failure processed', 200);

            } catch (\Exception $e) {
                Log::error("{$type} payment failed webhook handling failed", [
                    'payment_id' => $paymentId,
                    'entity_id' => $entityId,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);

                return response('Failed payment handling failed', 500);
            }
        });
    }

    /**
     * Verify payment based on type
     */
    private function verifyPayment($paymentId, $entityId, $type)
    {
        if ($type === 'order') {
            return $this->orderPaymentService->verifyOrderPayment($paymentId, $entityId);
        } else {
            return $this->coursePaymentService->verifyCoursePayment($paymentId, $entityId);
        }
    }

    /**
     * Find entity by ID and type
     */
    private function findEntity($entityId, $type)
    {
        if ($type === 'order') {
            return \App\Models\Order::find($entityId);
        } else {
            return \App\Models\CourseEnrollment::find($entityId);
        }
    }

    /**
     * Update entity status to failed
     */
    private function updateEntityToFailed($entity, $type)
    {
        if ($type === 'order') {
            // Use OrderService to handle payment failure and restore quantities
            $orderService = app(\App\Services\OrderService::class);
            $orderService->handlePaymentFailure($entity, 'Payment failed via webhook');
        } else {
            $entity->update([
                'payment_status' => 'failed',
                'status' => 'cancelled',
            ]);
        }
    }

    /**
     * Get payment type - to be implemented by each controller
     */
    abstract protected function getPaymentType();

    /**
     * Validate webhook signature
     */
    private function validateWebhookSignature($data, $secret, $signature)
    {
        try {
            uksort($data, 'strcasecmp');
            
            $output = implode(',', array_map(
                function ($v, $k) {
                    return sprintf("%s=%s", $k, $v);
                },
                $data,
                array_keys($data)
            ));

            $hash = base64_encode(hash_hmac('sha256', $output, $secret, true));
            
            return $signature === $hash;

        } catch (\Exception $e) {
            Log::error('Webhook signature validation failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}