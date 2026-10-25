<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Website\MyFatoorahService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $myFatoorahService;

    public function __construct(MyFatoorahService $myFatoorahService)
    {
        $this->myFatoorahService = $myFatoorahService;
    }

    /**
     * Handle payment callback from MyFatoorah
     */
    public function callback(Request $request)
    {
        // Log all incoming callback data
        Log::info('=== PAYMENT CALLBACK RECEIVED ===', [
            'all_request_data' => $request->all(),
            'query_params' => $request->query(),
            'post_data' => $request->post(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);

        try {
            $paymentId = $request->input('paymentId');

            if (!$paymentId) {
                Log::error('Payment callback missing paymentId', ['request' => $request->all()]);
                return redirect()->route('website.home')
                    ->with('error', 'حدث خطأ في معالجة الدفع');
            }

            Log::info('Step 1: Payment ID extracted', ['payment_id' => $paymentId]);

            // Verify payment with MyFatoorah
            Log::info('Step 2: Calling MyFatoorah verifyPayment API');
            $verificationResult = $this->myFatoorahService->verifyPayment($paymentId);

            Log::info('Step 3: MyFatoorah verification response', [
                'verification_result' => $verificationResult,
            ]);

            if (!$verificationResult['success']) {
                Log::error('Payment verification failed', [
                    'payment_id' => $paymentId,
                    'result' => $verificationResult,
                ]);
                return $this->handlePaymentError('فشل التحقق من الدفع');
            }

            // Check if payment was successful
            if (!$verificationResult['is_paid']) {
                Log::warning('Payment not completed', [
                    'payment_id' => $paymentId,
                    'is_paid' => $verificationResult['is_paid'],
                    'invoice_status' => $verificationResult['invoice_status'] ?? null,
                    'transaction_status' => $verificationResult['transaction_status'] ?? null,
                    'transaction_error' => $verificationResult['transaction_error'] ?? null,
                ]);

                // Get error message from transaction if available
                $errorMessage = 'لم يتم إتمام الدفع بنجاح';
                if (!empty($verificationResult['transaction_error'])) {
                    $errorMessage .= ' - ' . $verificationResult['transaction_error'];
                }

                return $this->handlePaymentError($errorMessage);
            }

            // Determine payment type (order or wallet)
            $paymentType = $verificationResult['user_defined_field'];
            $customerReference = $verificationResult['customer_reference'];

            Log::info('Step 4: Payment type determined', [
                'payment_type' => $paymentType,
                'customer_reference' => $customerReference,
            ]);

            if ($paymentType === 'order_payment') {
                Log::info('Step 5: Routing to ORDER payment handler', [
                    'order_number' => $customerReference,
                ]);
                return $this->handleOrderPayment($customerReference, $verificationResult);
            } elseif ($paymentType === 'wallet_recharge') {
                Log::info('Step 5: Routing to WALLET recharge handler', [
                    'transaction_id' => $customerReference,
                ]);
                return $this->handleWalletRecharge($customerReference, $verificationResult);
            }

            Log::error('Unknown payment type', [
                'payment_type' => $paymentType,
                'customer_reference' => $customerReference,
                'verification' => $verificationResult,
            ]);

            return $this->handlePaymentError('نوع الدفع غير معروف');

        } catch (\Exception $e) {
            Log::error('=== PAYMENT CALLBACK EXCEPTION ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_data' => $request->all(),
            ]);

            return $this->handlePaymentError('حدث خطأ أثناء معالجة الدفع');
        }
    }

    /**
     * Handle order payment success
     */
    protected function handleOrderPayment(string $orderNumber, array $paymentData)
    {
        Log::info('=== HANDLE ORDER PAYMENT START ===', [
            'order_number' => $orderNumber,
            'payment_data' => $paymentData,
        ]);

        try {
            return DB::transaction(function () use ($orderNumber, $paymentData) {
                Log::info('Step 6: Searching for order', ['order_number' => $orderNumber]);

                $order = Order::where('order_number', $orderNumber)->first();

                if (!$order) {
                    Log::error('Order not found for payment', [
                        'order_number' => $orderNumber,
                        'searched_in_db' => true,
                    ]);
                    return $this->handlePaymentError('الطلب غير موجود');
                }

                Log::info('Step 7: Order found', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'current_payment_status' => $order->payment_status,
                    'current_status' => $order->status,
                ]);

                // Check if already paid
                if ($order->payment_status === 'success') {
                    Log::warning('Order already paid', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ]);
                    return redirect()->route('website.checkout.success', ['orderNumber' => $order->order_number])
                        ->with('info', 'تم الدفع مسبقاً');
                }

                Log::info('Step 8: Updating order payment status', [
                    'order_id' => $order->id,
                    'new_payment_status' => 'success',
                    'new_status' => 'new',
                    'invoice_id' => $paymentData['invoice_id'] ?? null,
                ]);

                // Update order payment status
                $updateResult = $order->update([
                    'payment_status' => 'success',
                    'payment_reference' => $paymentData['invoice_id'] ?? null,
                    'payment_date' => now(),
                    'status' => 'pending', // Move to 'new' status after successful payment
                ]);

                Log::info('Step 9: Order update result', [
                    'update_result' => $updateResult,
                    'order_id' => $order->id,
                ]);

                // Refresh order to get updated values
                $order->refresh();

                Log::info('=== ORDER PAYMENT SUCCESSFUL ===', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_status' => $order->payment_status,
                    'status' => $order->status,
                    'amount' => $paymentData['paid_amount'] ?? null,
                    'invoice_id' => $paymentData['invoice_id'] ?? null,
                ]);

                Log::info('Step 10: Redirecting to checkout success page', [
                    'route' => 'website.checkout.success',
                    'order_number' => $order->order_number,
                ]);

                return redirect()->route('website.checkout.success', ['orderNumber' => $order->order_number]);
            });

        } catch (\Exception $e) {
            Log::error('=== ORDER PAYMENT HANDLING ERROR ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'order_number' => $orderNumber,
                'payment_data' => $paymentData,
            ]);

            return $this->handlePaymentError('حدث خطأ أثناء تحديث حالة الطلب');
        }
    }

    /**
     * Handle wallet recharge success
     */
    protected function handleWalletRecharge(string $transactionId, array $paymentData)
    {
        try {
            return DB::transaction(function () use ($transactionId, $paymentData) {
                $transaction = Transaction::find($transactionId);

                if (!$transaction) {
                    Log::error('Transaction not found for payment', ['transaction_id' => $transactionId]);
                    return $this->handlePaymentError('المعاملة غير موجودة');
                }

                // Check if already processed
                if ($transaction->status === 'approved') {
                    return redirect()->route('website.wallet.index')
                        ->with('info', 'تم شحن المحفظة مسبقاً');
                }

                $user = User::find($transaction->user_id);

                if (!$user) {
                    Log::error('User not found for transaction', ['user_id' => $transaction->user_id]);
                    return $this->handlePaymentError('المستخدم غير موجود');
                }

                // Update transaction status
                $transaction->update([
                    'status' => 'approved',
                    'reference' => $paymentData['invoice_id'],
                ]);

                // Add amount to user wallet
                $user->increment('wallet_balance', $transaction->amount);

                Log::info('Wallet recharge successful', [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'new_balance' => $user->wallet_balance,
                    'invoice_id' => $paymentData['invoice_id'],
                ]);

                return redirect()->route('website.wallet.index')
                    ->with('success', 'تم شحن المحفظة بنجاح! المبلغ: ' . number_format($transaction->amount, 2) . ' ريال');
            });

        } catch (\Exception $e) {
            Log::error('Wallet recharge handling error', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            return $this->handlePaymentError('حدث خطأ أثناء شحن المحفظة');
        }
    }

    /**
     * Handle payment error
     */
    public function error(Request $request)
    {
        Log::warning('Payment error callback', ['request' => $request->all()]);

        return $this->handlePaymentError('فشلت عملية الدفع. يرجى المحاولة مرة أخرى');
    }

    /**
     * Handle payment error with redirect
     */
    protected function handlePaymentError(string $message)
    {
        // Try to determine where to redirect based on session or default to home
        $redirectRoute = session('payment_redirect_route', 'website.home');
        
        return redirect()->route($redirectRoute)
            ->with('error', $message);
    }
}

