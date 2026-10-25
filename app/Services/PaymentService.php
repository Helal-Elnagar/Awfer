<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Models\Order;
use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Website\MyFatoorahService;

class PaymentService
{
    protected $config;

    public function __construct()
    {
        $this->config = config('myfatoorah');
    }

    /**
     * Initialize MyFatoorah payment for an order
     */
    public function initializeMyFatoorahPayment(Order $order, User $user, array $options = [])
    {
        try {
            $mf = app(MyFatoorahService::class);
            $gateway = $options['gateway'] ?? 'myfatoorah';
            $result = $mf->createOrderPayment([
                'amount' => (float) $order->total,
                'order_number' => $order->order_number,
                'customer_name' => $user->name ?? 'Customer',
                'customer_phone' => $user->phone ?? '',
                'customer_email' => $user->email ?? 'noemail@example.com',
                'gateway' => $gateway,
            ]);

            if (($result['success'] ?? false) !== true) {
                throw new \Exception($result['message'] ?? 'Payment initialization failed');
            }

            $order->update([
                'payment_url' => $result['payment_url'] ?? null,
                'payment_gateway_data' => [
                    'gateway' => $gateway,
                    'invoice_id' => $result['invoice_id'] ?? null,
                    'invoice_url' => $result['payment_url'] ?? null,
                    'invoice_reference' => $result['invoice_reference'] ?? null,
                    'created_at' => now()->toISOString(),
                ],
            ]);

            return [
                'status' => 'success',
                'invoiceURL' => $result['payment_url'],
                'invoiceId' => $result['invoice_id'],
            ];

        } catch (\Exception $e) {
            Log::error('MyFatoorah payment initialization failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Payment initialization failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get payment gateway based on payment method
     */
    public function getPaymentGateway(PaymentMethod $paymentMethod, array $data): string
    {
        // If gateway is explicitly provided in data, use it
        if (isset($data['gateway'])) {
            return $data['gateway'];
        }

        // Map payment method to default gateway
        return match ($paymentMethod) {
            PaymentMethod::VISA => 'visa',
            PaymentMethod::MADA => 'mada',
            PaymentMethod::APPLE_PAY => 'applepay',
            PaymentMethod::GOOGLE_PAY => 'googlepay',
            default => 'myfatoorah',
        };
    }

    /**
     * Resolve payment method ID for specific gateways (same approach as WalletRechargeService)
     */

    /**
     * Handle payment callback from MyFatoorah webhook
     */
    public function handlePaymentCallback(Request $request)
    {
        try {
            Log::info('MyFatoorah webhook received', $request->all());
            $data = $request->all();
            $paymentId = $data['Data']['PaymentId'] ?? $data['PaymentId'] ?? null;
            if (!$paymentId) {
                return ['success' => false, 'message' => 'Payment ID is required'];
            }
            $mf = app(MyFatoorahService::class);
            $status = $mf->getPaymentStatus($paymentId);
            if (!$status['success']) {
                return ['success' => false, 'message' => $status['message'] ?? 'Status check failed'];
            }
            $resp = $status['data'];
            $orderRef = $resp['CustomerReference'] ?? null;
            $order = Order::withTrashed()->where('order_number', $orderRef)->first() ?? Order::withTrashed()->find($orderRef);
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found'];
            }
            if (($resp['InvoiceStatus'] ?? null) === 'Paid') {
                $order->update([
                    'payment_status' => 'success',
                    'payment_reference' => $resp['InvoiceId'] ?? null,
                    'payment_date' => now(),
                ]);
                $this->processPostPaymentActions($order);
                return ['success' => true, 'order_id' => $order->id, 'status' => $order->status, 'message' => 'Payment processed successfully'];
            }
            $order->update(['payment_status' => 'failed']);
            return ['success' => false, 'order_id' => $order->id, 'status' => $order->status, 'message' => 'Payment failed'];
        } catch (\Exception $e) {
            Log::error('Payment callback processing failed', [
                'error' => $e->getMessage(),
                'payment_id' => $request->paymentId ?? null
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process post-payment actions like cart clearing
     */
    private function processPostPaymentActions(Order $order)
    {
        // Clear cart
        $cartService = app(CartService::class);
        $cartService->clearCart($order->user);
    }

    /**
     * Format order items for MyFatoorah invoice
     */
    private function formatOrderItems(Order $order)
    {
        $items = [];
        $totalItemsValue = 0;

        Log::info('Starting order items calculation', [
            'order_id' => $order->id,
            'final_total' => $order->final_total,
            'vat_amount' => $order->vat_amount ?? 0,
            'coupon_amount' => $order->coupon_amount ?? 0,
            'delivery_fee' => $order->delivery_fee ?? 0,
            'wallet_deduction' => $order->wallet_deduction ?? 0

        ]);

        // Add products
        foreach ($order->items as $item) {
            $itemTotal = round($item->price * $item->quantity, 2);
            $totalItemsValue += $itemTotal;
            $itemName = null;
            // Try to get product name, fallback to item name, fallback to generic
            if (isset($item->product) && !empty($item->product->title)) {
                $itemName = $item->product->title;
            } elseif (!empty($item->name)) {
                $itemName = $item->name;
            } else {
                $itemName = __('apis.product');
            }
            $items[] = [
                'ItemName' => $itemName,
                'Quantity' => $item->quantity,
                'UnitPrice' => round($item->price, 2),
            ];
        }

        // Add delivery fee if applicable
        if (($order->delivery_fee ?? 0) > 0) {
            $fee = (float) $order->delivery_fee;
            $items[] = [
                'ItemName' => 'Delivery Fee',
                'Quantity' => 1,
                'UnitPrice' => round($fee, 2),
            ];
            $totalItemsValue += round($fee, 2);
        }

        // Add VAT if applicable
        if (($order->vat_amount ?? 0) > 0) {
            $vat = (float) $order->vat_amount;
            $items[] = [
                'ItemName' => 'VAT',
                'Quantity' => 1,
                'UnitPrice' => round($vat, 2),
            ];
            $totalItemsValue += round($vat, 2);
        }

        // Add coupon discount if applicable (as a negative value)
        if (($order->coupon_amount ?? 0) > 0) {
            $couponAmount = round((float) $order->coupon_amount, 2);
            $items[] = [
                'ItemName' => 'Coupon Discount',
                'Quantity' => 1,
                'UnitPrice' => -$couponAmount,
            ];
            $totalItemsValue -= $couponAmount;
        }

        // Add wallet deduction if applicable (as a negative value)
        if (($order->wallet_deduction ?? 0) > 0) {
            $walletAmount = round((float) $order->wallet_deduction, 2);
            $items[] = [
                'ItemName' => 'Wallet Deduction',
                'Quantity' => 1,
                'UnitPrice' => -$walletAmount,
            ];
            $totalItemsValue -= $walletAmount;
        }



        // Ensure the sum of items matches the order total (InvoiceValue)
        $orderTotal = isset($order->total) ? (float)$order->total : 0.0;
        $totalItemsValue = (float) $totalItemsValue;
        $totalItemsValue = round($totalItemsValue, 2);
        $orderTotal = round($orderTotal, 2);
        $diff = round($orderTotal - $totalItemsValue, 2);
        if (abs($diff) > 0.01) { // Only adjust if difference is significant
            $items[] = [
                'ItemName' => 'Rounding Adjustment',
                'Quantity' => 1,
                'UnitPrice' => $diff,
            ];
            $totalItemsValue += $diff;
        }

        Log::info('Final calculation', [
            'calculated_total' => $totalItemsValue,
            'order_total' => $orderTotal,
            'difference' => abs($totalItemsValue - $orderTotal),
            'items' => $items
        ]);

        return $items;
    }
}
