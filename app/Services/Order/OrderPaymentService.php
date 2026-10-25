<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Log;

/**
 * OrderPaymentService
 *
 * Handles all payment processing logic for orders
 * Manages payment gateway integration, wallet payments, and payment status updates
 */
class OrderPaymentService
{
    protected $orderRepository;
    protected $transactionService;

    public function __construct(
        OrderRepository $orderRepository,
        TransactionService $transactionService
    ) {
        $this->orderRepository = $orderRepository;
        $this->transactionService = $transactionService;
    }

    /**
     * Process order payment based on payment method
     *
     * @param Order $order
     * @param array $data
     * @param array $options - Optional parameters like callback_url and error_url for API
     * @return array ['payment_url' => string|null, 'payment_status' => string]
     * @throws \Exception
     */
    public function processPayment(Order $order, array $data, array $options = []): array
    {
        $paymentMethodId = (int) ($data['payment_method_id'] ?? 0);

        // Payment Method: Offline (e.g., Cash/Bank Transfer). Skip MyFatoorah.
        if ($paymentMethodId == 5) {
            return $this->processCashPayment($order);
        }

        // Payment Method: Electronic Payment (MyFatoorah)
        if ($paymentMethodId != 5) {
            return $this->processElectronicPayment($order, $options);
        }

        throw new \Exception(__('apis.invalid_payment_method'));
    }

    /**
     * Process cash on delivery payment
     *
     * @param Order $order
     * @return array
     */
    private function processCashPayment(Order $order): array
    {
        $this->orderRepository->update($order, [
            'payment_status' => 'pending',
        ]);

        // For cash payment, if wallet deduction was applied, create transaction record
        if ($order->wallet_deduction > 0) {
            $this->transactionService->createWalletPaymentTransaction(
                $order->user_id,
                $order->wallet_deduction,
                $order
            );

            Log::info('Wallet deduction transaction created for cash payment', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'wallet_deduction' => $order->wallet_deduction,
            ]);
        }

        return [
            'payment_url' => null,
            'payment_status' => 'pending',
        ];
    }

    /**
     * Process electronic payment via MyFatoorah
     *
     * @param Order $order
     * @param array $options - Optional parameters like callback_url and error_url for API
     * @return array
     * @throws \Exception
     */
    private function processElectronicPayment(Order $order, array $options = []): array
    {
        try {
            $paymentUrl = $this->initiateMyFatoorahPayment($order, $options);

            $this->orderRepository->update($order, [
                'payment_status' => 'pending',
            ]);

            // IMPORTANT: For electronic payments, wallet was already deducted in CartService
            // But payment will be confirmed via webhook, so we refund it now
            // and will deduct again when webhook confirms payment
            if ($order->wallet_deduction > 0) {
                $user = $order->user;
                $this->orderRepository->incrementUserWallet($user, $order->wallet_deduction);

                Log::info('Wallet deduction refunded for electronic payment (will be deducted on webhook confirmation)', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'wallet_deduction' => $order->wallet_deduction,
                    'payment_method' => $order->payment_method_id,
                ]);
            }

            return [
                'payment_url' => $paymentUrl,
                'payment_status' => 'pending',
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah payment initiation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception(__('apis.payment_gateway_error'));
        }
    }

    /**
     * Process wallet payment
     *
     * @param Order $order
     * @return array
     * @throws \Exception
     */
    private function processWalletPayment(Order $order): array
    {
        $user = $order->user;

        // Validate wallet balance
        if ($user->wallet_balance < $order->total) {
            throw new \Exception(__('apis.insufficient_wallet_balance'));
        }

        // Deduct from wallet
        $this->orderRepository->decrementUserWallet($user, $order->total);

        // Update order payment status
        $this->orderRepository->update($order, [
            'payment_status' => 'success',
            'status' => 'new', // Move to 'new' status after successful payment
        ]);

        // Create wallet payment transaction if wallet deduction was applied
        if ($order->wallet_deduction > 0) {
            $this->transactionService->createWalletPaymentTransaction(
                $order->user_id,
                $order->wallet_deduction,
                $order
            );
        }

        Log::info('Wallet payment processed', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $order->total,
            'wallet_deduction' => $order->wallet_deduction,
        ]);

        return [
            'payment_url' => null,
            'payment_status' => 'success',
        ];
    }

    /**
     * Initiate MyFatoorah payment and get payment URL
     *
     * @param Order $order
     * @param array $options - Optional parameters like callback_url and error_url for API
     * @return string Payment URL
     * @throws \Exception
     */
    private function initiateMyFatoorahPayment(Order $order, array $options = []): string
    {
        try {
            // Use the new MyFatoorahService
            $myFatoorahService = app(\App\Services\Website\MyFatoorahService::class);

            // Prepare order data for payment
            $orderData = [
                'amount' => (float) $order->total,
                'order_number' => $order->order_number,
                'customer_name' => $order->user->name ?? 'Customer',
                'customer_phone' => $order->user->phone ?? '',
                'customer_email' => $order->user->email ?? 'noemail@example.com',
            ];

            // Add custom callback URLs if provided (for API)
            if (isset($options['callback_url'])) {
                $orderData['callback_url'] = $options['callback_url'];
            }
            if (isset($options['error_url'])) {
                $orderData['error_url'] = $options['error_url'];
            }

            // Create payment invoice
            $result = $myFatoorahService->createOrderPayment($orderData);

            if (!$result['success']) {
                throw new \Exception($result['message'] ?? 'Failed to create payment invoice');
            }

            // Update order with payment reference
            $this->orderRepository->update($order, [
                'payment_reference' => $result['invoice_id'],
                'payment_url' => $result['payment_url'],
            ]);

            Log::info('MyFatoorah payment initiated successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'invoice_id' => $result['invoice_id'],
                'payment_url' => $result['payment_url'],
            ]);

            return $result['payment_url'];
        } catch (\Exception $e) {
            Log::error('MyFatoorah payment initiation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception('Failed to initiate payment: ' . $e->getMessage());
        }
    }

    /**
     * Confirm payment after successful gateway callback
     *
     * @param Order $order
     * @return bool
     */
    public function confirmPayment(Order $order): bool
    {
        $updated = $this->orderRepository->update($order, [
            'payment_status' => 'success',
            'status' => 'new', // Move to 'new' status after payment confirmation
        ]);

        if ($updated) {
            Log::info('Payment confirmed for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        }

        return $updated;
    }

    /**
     * Refund payment to user wallet
     *
     * @param Order $order
     * @return bool
     */
    public function refundToWallet(Order $order): bool
    {
        $user = $order->user;

        if (!$user) {
            return false;
        }

        // Determine refund amount: use refund_amount if set, otherwise use total
        $refundAmount = $order->refund_amount ?? $order->total;

        // Add refund amount to wallet
        $this->orderRepository->incrementUserWallet($user, $refundAmount);
        $this->transactionService->createWalletRefundTransaction($order->user_id, $refundAmount, $order->order_number);

        Log::info('Payment refunded to wallet', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $refundAmount,
            'is_refund_order' => $order->is_refund ?? false,
        ]);

        return true;
    }

    /**
     * Refund delivery fee to user wallet
     * Useful when delivery service is cancelled or not performed
     */
    public function refundDeliveryFee(Order $order): bool
    {
        $user = $order->user;
        if (!$user) {
            return false;
        }

        $amount = (float) ($order->delivery_fee ?? 0);
        if ($amount <= 0) {
            return false;
        }

        $this->orderRepository->incrementUserWallet($user, $amount);
        $this->transactionService->createWalletRefundTransaction($order->user_id, $amount, $order->order_number);

        Log::info('Delivery fee refunded to wallet', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $amount,
        ]);

        return true;
    }

    /**
     * Handle payment failure
     *
     * @param Order $order
     * @param string|null $reason
     * @return bool
     */
    public function handlePaymentFailure(Order $order, ?string $reason = null): bool
    {
        $updated = $this->orderRepository->update($order, [
            'payment_status' => 'failed',
            'status' => 'cancelled',
        ]);

        if ($updated) {
            Log::warning('Payment failed for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'reason' => $reason,
            ]);
        }

        return $updated;
    }

    /**
     * Check if order payment is completed
     *
     * @param Order $order
     * @return bool
     */
    public function isPaymentCompleted(Order $order): bool
    {
        return $order->payment_status === 'success';
    }

    /**
     * Check if order payment is pending
     *
     * @param Order $order
     * @return bool
     */
    public function isPaymentPending(Order $order): bool
    {
        return $order->payment_status === 'pending';
    }

    /**
     * Clean phone number for MyFatoorah
     * Removes non-numeric characters, leading zeros, and country code
     * Validates Saudi phone number format (9 digits starting with 5)
     *
     * @param string|null $phone
     * @return string
     */
    private function cleanPhoneNumber($phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // Remove country code if present
        if (str_starts_with($phone, '966')) {
            $phone = substr($phone, 3);
        }

        // Ensure we have a valid Saudi phone number (9 digits starting with 5)
        if (strlen($phone) === 9 && str_starts_with($phone, '5')) {
            return $phone;
        }

        // If phone doesn't match expected format, return empty (will trigger validation error)
        return '';
    }

    /**
     * Build invoice items array for MyFatoorah payment
     * Ensures items total matches order total exactly
     *
     * @param Order $order
     * @return array
     */
    private function buildInvoiceItems(Order $order): array
    {
        $items = [];

        // Add order items (products)
        if ($order->items && $order->items->count() > 0) {
            foreach ($order->items as $orderItem) {
                $items[] = [
                    'ItemName' => $orderItem->item->name ?? 'Product',
                    'Quantity' => $orderItem->quantity,
                    'UnitPrice' => (float) $orderItem->price,
                    'Weight' => 0,
                    'Width' => 0,
                    'Height' => 0,
                    'Depth' => 0,
                ];
            }
        }

        // Add delivery fee if applicable
        if ($order->delivery_fee > 0) {
            $items[] = [
                'ItemName' => 'Delivery Fee',
                'Quantity' => 1,
                'UnitPrice' => (float) $order->delivery_fee,
                'Weight' => 0,
                'Width' => 0,
                'Height' => 0,
                'Depth' => 0,
            ];
        }

        // Add VAT if applicable
        if ($order->vat_amount > 0) {
            $items[] = [
                'ItemName' => 'VAT',
                'Quantity' => 1,
                'UnitPrice' => (float) $order->vat_amount,
                'Weight' => 0,
                'Width' => 0,
                'Height' => 0,
                'Depth' => 0,
            ];
        }

        // Add discount as negative item if applicable
        if ($order->discount_amount > 0) {
            $items[] = [
                'ItemName' => 'Discount' . ($order->coupon_code ? ' (' . $order->coupon_code . ')' : ''),
                'Quantity' => 1,
                'UnitPrice' => -(float) $order->discount_amount,
                'Weight' => 0,
                'Width' => 0,
                'Height' => 0,
                'Depth' => 0,
            ];
        }

        // Add wallet deduction as negative item if applicable
        if ($order->wallet_deduction > 0) {
            $items[] = [
                'ItemName' => 'Wallet Deduction',
                'Quantity' => 1,
                'UnitPrice' => -(float) $order->wallet_deduction,
                'Weight' => 0,
                'Width' => 0,
                'Height' => 0,
                'Depth' => 0,
            ];
        }

        // If no items, add a generic order item
        if (empty($items)) {
            $items[] = [
                'ItemName' => 'Order #' . $order->order_number,
                'Quantity' => 1,
                'UnitPrice' => (float) $order->total,
                'Weight' => 0,
                'Width' => 0,
                'Height' => 0,
                'Depth' => 0,
            ];
        }

        // Calculate items total
        $itemsTotal = 0;
        foreach ($items as $item) {
            $itemsTotal += $item['Quantity'] * $item['UnitPrice'];
        }
        $itemsTotal = round($itemsTotal, 3);

        // Check if there's a discrepancy and add adjustment item if needed
        $orderTotal = round((float) $order->total, 3);
        $difference = round($orderTotal - $itemsTotal, 3);

        if (abs($difference) > 0.001) { // If difference is more than 0.001
            $items[] = [
                'ItemName' => 'Adjustment',
                'Quantity' => 1,
                'UnitPrice' => $difference,
                'Weight' => 0,
                'Width' => 0,
                'Height' => 0,
                'Depth' => 0,
            ];

            // Recalculate total after adjustment
            $itemsTotal = 0;
            foreach ($items as $item) {
                $itemsTotal += $item['Quantity'] * $item['UnitPrice'];
            }
            $itemsTotal = round($itemsTotal, 3);
        }

        Log::info('MyFatoorah invoice items built', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_total' => $orderTotal,
            'items_total' => $itemsTotal,
            'difference' => $difference,
            'items_count' => count($items),
            'items' => $items,
        ]);

        return $items;
    }
}

