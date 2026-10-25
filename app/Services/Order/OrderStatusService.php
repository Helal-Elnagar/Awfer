<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\DeliveryStatisticsService;
use App\Services\LoyaltyPointsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderStatusService
 *
 * Handles order status transitions and validations
 * Uses repositories for all database operations
 */
class OrderStatusService
{
    protected $orderRepository;
    protected $inventoryService;
    protected $notificationService;
    protected $paymentService;
    protected $loyaltyPointsService;

    public function __construct(
        OrderRepository $orderRepository,
        InventoryService $inventoryService,
        OrderNotificationService $notificationService,
        OrderPaymentService $paymentService,
        LoyaltyPointsService $loyaltyPointsService
    ) {
        $this->orderRepository = $orderRepository;
        $this->inventoryService = $inventoryService;
        $this->notificationService = $notificationService;
        $this->paymentService = $paymentService;
        $this->loyaltyPointsService = $loyaltyPointsService;
    }

    /**
     * Validate if a status transition is allowed
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    public function isValidTransition(string $currentStatus, string $newStatus): bool
    {
        try {
            $current = $this->resolveStatus($currentStatus);
        } catch (\ValueError $e) {
            return false;
        }
        $allowed = array_map(fn(OrderStatus $s) => $s->value, $current->nextStatuses());
        $resolvedNew = $this->resolveStatus($newStatus);
        return in_array($resolvedNew->value, $allowed, true);
    }

    /**
     * Resolve a status string (English or Arabic) to an OrderStatus enum case.
     */
    private function resolveStatus(string $status): OrderStatus
    {
        $status = trim($status);

        return match($status) {
            'قيد الانتظار'          => OrderStatus::PENDING,
            'جاري المراجعه'         => OrderStatus::PROCESSING,
            'جديد'                  => OrderStatus::NEW,
            'تم التاكيد'            => OrderStatus::CONFIRMED,
            'جاري التوصيل'          => OrderStatus::OUT_OF_DELIVERY,
            'تم التوصيل'            => OrderStatus::DELIVERED,
            'به مشكلة'              => OrderStatus::PROBLEM,
            'ملغي'                  => OrderStatus::CANCELLED,
            'طلب الإلغاء'           => OrderStatus::REQUEST_CANCEL,
            'طلب استرجاع'           => OrderStatus::REQUEST_REFUND,
            'تم رفض طلب الاسترجاع'  => OrderStatus::REQUEST_REJECTED,
            'تم الاسترجاع'          => OrderStatus::REFUNDED,
            'جاري التجهيز والشحن'   => OrderStatus::PREPARING,
            default                 => OrderStatus::from($status),
        };
    }

    /**
     * Update order status with validation
     *
     * @param Order $order
     * @param string $newStatus
     * @param array $options ['user_type' => 'admin'|'delivery', 'notify' => true]
     * @return Order
     * @throws \Exception
     */
    public function updateStatus(Order $order, string $newStatus, array $options = []): Order
    {
        return $this->orderRepository->transaction(function () use ($order, $newStatus, $options) {
            // Validate transition unless admin is forcing override
            $userType = $options['user_type'] ?? null;
            $bypassValidation = ($userType === 'admin');

            if (!$bypassValidation && !$this->isValidTransition($order->status, $newStatus)) {
                throw new \Exception(__('admin.invalid_status_transition'));
            }

            $oldStatus = $order->status;

            // Build update payload with status and relevant date columns.
            // delivery_date is set when the order reaches "تم التوصيل" (delivered).
            // cancel_date is set when the order reaches "ملغي" (cancelled).
            $updateData = ['status' => $newStatus];

            $resolved = $this->resolveStatus($newStatus);
            if ($resolved === OrderStatus::DELIVERED) {
                $updateData['delivery_date'] = now()->toDateString();
            } elseif ($resolved === OrderStatus::CANCELLED) {
                $updateData['cancel_date'] = now()->toDateString();
            }

            $this->orderRepository->update($order, $updateData);

            // Auto-set delivered_quantity on order items when delivered (admin path).
            if ($resolved === OrderStatus::DELIVERED) {
                $order->items()->update(['delivered_quantity' => DB::raw('quantity')]);
            }

            // Handle side effects (inventory, payments, loyalty, etc.)
            $this->handleStatusSideEffects($order, $oldStatus, $newStatus);

            // Send notification if enabled
            if ($options['notify'] ?? true) {
                $this->notificationService->notifyUserOfStatusChange($order, $newStatus);
            }

            // Invalidate delivery dashboard cache if this order is assigned to a delivery person.
            // This ensures the statistics endpoint returns fresh data immediately.
            if ($order->delivery_id) {
                DeliveryStatisticsService::clearCacheForUser((int) $order->delivery_id);
            }

            Log::info('Order status updated', [
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => $options['user_type'] ?? 'system',
            ]);

            return $order->fresh();
        });
    }

    /**
     * Cancel order with reason
     *
     * @param Order $order
     * @param string|null $reason
     * @param mixed $cancelledBy
     * @return Order
     * @throws \Exception
     */
    public function cancelOrder(Order $order, ?string $reason = null, $cancelledBy = null): Order
    {
        return $this->orderRepository->transaction(function () use ($order, $reason, $cancelledBy) {
            try {
                // Update order status using repository.
                // Set cancel_date so statistics can filter by date.
                $this->orderRepository->update($order, [
                    'status' => 'cancelled',
                    'cancel_reason' => $reason,
                    'cancel_date' => now()->toDateString(),
                ]);

                // Restore product quantities
                $this->inventoryService->restoreStock($order);

                // Send notification
                $this->notificationService->notifyUserOfCancellation($order, $reason);

                // Invalidate delivery dashboard cache if this order has a delivery person.
                if ($order->delivery_id) {
                    DeliveryStatisticsService::clearCacheForUser((int) $order->delivery_id);
                }

                Log::info('Order cancelled', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'cancelled_by' => $cancelledBy ? get_class($cancelledBy) . ':' . $cancelledBy->id : 'system',
                    'reason' => $reason,
                ]);

                return $order->fresh();
            } catch (\Exception $e) {
                Log::error('Failed to cancel order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Update delivery person's order status
     *
     * @param int $orderId
     * @param string $status
     * @return Order|null|false
     * @throws \Exception
     */
    public function updateDeliveryOrderStatus(int $orderId, string $status)
    {
        $user = auth()->user();

        if ($user->type !== 'delivery') {
            return null;
        }

        // Delivery users act on orders in any active (non-final) status
        // from which a valid delivery transition exists.
        $activeStatuses = collect(OrderStatus::cases())
            ->filter(fn(OrderStatus $s) => !$s->isFinal() && $s !== OrderStatus::CANCELLED)
            ->flatMap(fn(OrderStatus $s) => [trim($s->label()), $s->value])
            ->values()
            ->all();

        $order = Order::whereIn('status', $activeStatuses)
            ->where('delivery_id', $user->id)
            ->find($orderId);

        if (!$order) {
            return null;
        }

        // Cancelled is not allowed through this endpoint
        $resolvedIncoming = $this->resolveStatus($status);
        if ($resolvedIncoming === OrderStatus::CANCELLED) {
            return false;
        }

        // Store the canonical internal status key in the database.
        $targetStatus = $resolvedIncoming->value;

        // Validate status transition
        if (!$this->isValidTransition($order->status, $targetStatus)) {
            return false;
        }

        // Business rule: delivery persons must not skip to delivered
        // unless the order is already confirmed or out-for-delivery.
        // NEW -> DELIVERED remains blocked (no direct skip), while an order
        // that was just confirmed may move straight to delivered.
        if ($targetStatus === OrderStatus::DELIVERED->value
            && !in_array(OrderStatus::fromStored($order->status)?->value, [
                OrderStatus::OUT_OF_DELIVERY->value,
                OrderStatus::CONFIRMED->value,
            ], true)) {
            return false;
        }

        // Wrap status update, collection creation, and payment updates
        // in a single DB transaction. If collection creation fails,
        // rollback everything.
        $this->orderRepository->transaction(function () use ($order, $targetStatus) {
            $updateData = ['status' => $targetStatus];

            if ($targetStatus === OrderStatus::DELIVERED->value) {
                $updateData['delivery_date'] = now()->toDateString();
            }

            $this->orderRepository->update($order, $updateData);

            // Auto-set delivered_quantity on order items when delivered.
            // Business rule: delivered_quantity must equal the ordered quantity
            // for every product in the order once status becomes "delivered".
            if ($targetStatus === OrderStatus::DELIVERED->value) {
                $order->items()->update(['delivered_quantity' => DB::raw('quantity')]);
            }

            // Handle refund orders: process payment when delivered
            if ($targetStatus === OrderStatus::DELIVERED->value && $order->is_refund) {
                $this->processRefundPaymentOnDelivery($order);
            } elseif ($targetStatus === OrderStatus::DELIVERED->value && (int) $order->payment_method_id === 5) {
                $this->orderRepository->update($order, ['payment_status' => 'success', 'payment_date' => now()]);
            }
        });

        // Invalidate delivery dashboard cache immediately so statistics
        // reflect the new state without waiting for cache expiry.
        DeliveryStatisticsService::clearCacheForUser($user->id);

        // Send notifications
        $this->notificationService->notifyUserOfStatusChange($order->fresh('user'), $targetStatus);
        $this->notificationService->notifyAdminsOfDeliveryStatusUpdate($order->fresh(), $targetStatus);

        // Process loyalty points when order is delivered
        if ($targetStatus === OrderStatus::DELIVERED->value) {
            $this->loyaltyPointsService->processOrderLoyaltyPoints(
                $order->user,
                $order->total,
                $order->loyalty_points_used,
                $order->payment_method_id == 1 ? 'cash' : 'electronic',
                $order
            );
        }

        return $order->fresh();
    }

    /**
     * Cancel an order assigned to a delivery driver
     *
     * Removes the delivery assignment and sets status back to pending.
     * Only allowed for orders in 'new' or 'out-for-delivery' status.
     *
     * @param int $orderId
     * @param string $cancelReason
     * @return Order|null false if driver doesn't own order or status is invalid
     */
    public function cancelDeliveryOrder(int $orderId, string $cancelReason)
    {
        $user = auth()->user();

        if ($user->type !== 'delivery') {
            return null;
        }

        $order = Order::whereIn('status', [
                'new',
                'out-for-delivery',
                'جديد',
                'جاري التوصيل',
            ])
            ->where('delivery_id', $user->id)
            ->find($orderId);

        if (!$order) {
            return null;
        }

        if (!empty($order->cancelled_by_delivery_id)) {
            throw new \Exception(__('apis.order_already_cancelled_by_delivery'));
        }

        $oldStatus = $order->status;

        $this->orderRepository->update($order, [
            'status' => 'cancelled',
            'delivery_id' => null,
            'cancelled_by_delivery_id' => $user->id,
            'cancel_reason' => $cancelReason,
            'cancel_date' => now()->toDateString(),
        ]);

        // Invalidate delivery dashboard cache immediately.
        // Even though delivery_id is cleared, the statistics service uses
        // cancelled_by_delivery_id to count cancelled orders.
        DeliveryStatisticsService::clearCacheForUser($user->id);

        // Notify user
        $this->notificationService->notifyUserOfCancellation($order->fresh(), $cancelReason);

        // Notify admins
        $admins = \App\Models\Admin::all();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\NotifyAdmin([
                'title' => [
                    'ar' => 'إلغاء التوصيل',
                    'en' => 'Delivery Cancelled',
                ],
                'body' => [
                    'ar' => 'قام مندوب التوصيل بإلغاء الطلب #' . $order->order_number . ' والسبب: ' . $cancelReason,
                    'en' => 'Delivery person cancelled order #' . $order->order_number . ' Reason: ' . $cancelReason,
                ],
                'type' => 'delivery_status_update',
                'order_id' => $order->id,
            ]));
        }

        Log::info('Order cancelled by delivery driver', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'delivery_id' => $user->id,
            'old_status' => $oldStatus,
            'reason' => $cancelReason,
        ]);

        return $order->fresh();
    }

    /**
     * Process refund payment when delivery confirms pickup
     *
     * @param Order $order
     * @return void
     */
    private function processRefundPaymentOnDelivery(Order $order): void
    {
        try {
            // Process refund to wallet
            $this->paymentService->refundToWallet($order);

            // Create transaction record
            $transactionService = app(\App\Services\TransactionService::class);
            $transactionService->createRefundTransaction(
                $order->user_id,
                $order->refund_amount,
                $order
            );

            // Update order to refunded status
            $this->orderRepository->update($order, [
                'status' => 'refunded',
                'payment_status' => 'refunded',
            ]);

            Log::info('Refund payment processed after delivery confirmation', [
                'order_id' => $order->id,
                'refund_number' => $order->refund_number,
                'refund_amount' => $order->refund_amount,
                'user_id' => $order->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process refund payment on delivery', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle side effects when status changes
     *
     * @param Order $order
     * @param string $oldStatus
     * @param string $newStatus
     */
    private function handleStatusSideEffects(Order $order, string $oldStatus, string $newStatus): void
    {
        $resolved = $this->resolveStatus($newStatus);

        // Restore stock when order is cancelled
        if ($resolved === OrderStatus::CANCELLED) {
            $this->inventoryService->restoreStock($order);
        }

        // COD: mark payment as success and record payment date when delivered
        if ($resolved === OrderStatus::DELIVERED && (int) $order->payment_method_id === 5) {
            $this->orderRepository->update($order, ['payment_status' => 'success', 'payment_date' => now()]);
        }

        // Process loyalty points when order is delivered
        if ($resolved === OrderStatus::DELIVERED) {
            $this->loyaltyPointsService->processOrderLoyaltyPoints(
                $order->user,
                $order->total,
                $order->loyalty_points_used,
                $order->payment_method_id == 1 ? 'cash' : 'electronic',
                $order
            );
        }

        // Additional side effects can be added here
        // For example: update delivery person availability, send SMS, etc.
    }
}
