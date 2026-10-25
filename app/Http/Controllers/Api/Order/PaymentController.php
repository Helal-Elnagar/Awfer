<?php

namespace App\Http\Controllers\Api\Order;

use App\Models\Order;
use App\Facades\Responder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\Website\MyFatoorahService;
use App\Services\WalletRechargeService;

class PaymentController extends Controller
{
    protected $walletRechargeService;
    protected $myFatoorahService;

    public function __construct(
        WalletRechargeService $walletRechargeService,
        MyFatoorahService $myFatoorahService
    ) {
        $this->walletRechargeService = $walletRechargeService;
        $this->myFatoorahService = $myFatoorahService;
    }

    /**
     * Get payment type for this controller
     */
    protected function getPaymentType()
    {
        return 'order';
    }

    /**
     * Handle successful payment callback from MyFatoorah (both order and wallet)
     */
    public function success(Request $request)
    {
        // Log all incoming callback data
        Log::info('=== API PAYMENT CALLBACK RECEIVED ===', [
            'all_request_data' => $request->all(),
            'query_params' => $request->query(),
            'post_data' => $request->post(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);

        try {
            $paymentId = $request->input('paymentId');

            if (!$paymentId) {
                Log::error('API Payment callback missing paymentId', ['request' => $request->all()]);
                return view('payment.fail', ['message' => 'Payment ID is required']);
            }

            Log::info('Step 1: Payment ID extracted', ['payment_id' => $paymentId]);

            // Verify payment with MyFatoorah
            Log::info('Step 2: Calling MyFatoorah verifyPayment API');
            $verificationResult = $this->myFatoorahService->verifyPayment($paymentId);

            Log::info('Step 3: MyFatoorah verification response', [
                'verification_result' => $verificationResult,
            ]);

            if (!$verificationResult['success']) {
                Log::error('API Payment verification failed', [
                    'payment_id' => $paymentId,
                    'result' => $verificationResult,
                ]);
                return view('payment.fail', ['message' => 'Payment verification failed']);
            }

            // Check if payment was successful
            if (!$verificationResult['is_paid']) {
                Log::warning('API Payment not completed', [
                    'payment_id' => $paymentId,
                    'is_paid' => $verificationResult['is_paid'],
                    'invoice_status' => $verificationResult['invoice_status'] ?? null,
                    'transaction_status' => $verificationResult['transaction_status'] ?? null,
                    'transaction_error' => $verificationResult['transaction_error'] ?? null,
                ]);

                // Get error message from transaction if available
                $errorMessage = 'Payment was not completed successfully';
                if (!empty($verificationResult['transaction_error'])) {
                    $errorMessage .= ' - ' . $verificationResult['transaction_error'];
                }

                return view('payment.fail', ['message' => $errorMessage]);
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
            } elseif ($paymentType === 'course_enrollment') {
                Log::info('Step 5: Routing to COURSE payment handler', [
                    'enrollment_id' => $customerReference,
                ]);
                return $this->handleCoursePayment($customerReference, $verificationResult);
            }

            Log::error('Unknown payment type', [
                'payment_type' => $paymentType,
                'customer_reference' => $customerReference,
                'verification' => $verificationResult,
            ]);

            return view('payment.fail', ['message' => 'Unknown payment type']);

        } catch (\Exception $e) {
            Log::error('=== API PAYMENT CALLBACK EXCEPTION ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_data' => $request->all(),
            ]);

            return view('payment.fail', ['message' => 'An error occurred while processing payment']);
        }
    }

    /**
     * Handle order payment success
     */
    protected function handleOrderPayment(string $orderNumber, array $paymentData)
    {
        Log::info('=== HANDLE API ORDER PAYMENT START ===', [
            'order_number' => $orderNumber,
            'payment_data' => $paymentData,
        ]);

        try {
            return DB::transaction(function () use ($orderNumber, $paymentData) {
                Log::info('Step 6: Searching for order', ['order_number' => $orderNumber]);

                $order = \App\Models\Order::where('order_number', $orderNumber)->first();

                if (!$order) {
                    Log::error('Order not found for payment', [
                        'order_number' => $orderNumber,
                        'searched_in_db' => true,
                    ]);
                    return view('payment.fail', ['message' => 'Order not found']);
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
                    return view('payment.success', [
                        'message' => 'Payment already completed',
                        'order_number' => $order->order_number,
                    ]);
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

                Log::info('=== API ORDER PAYMENT SUCCESSFUL ===', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_status' => $order->payment_status,
                    'status' => $order->status,
                    'amount' => $paymentData['paid_amount'] ?? null,
                    'invoice_id' => $paymentData['invoice_id'] ?? null,
                ]);

                Log::info('Step 10: Showing success page', [
                    'order_number' => $order->order_number,
                ]);

                return view('payment.success', [
                    'message' => 'Payment completed successfully',
                    'order_number' => $order->order_number,
                    'amount' => $paymentData['paid_amount'] ?? $order->total,
                ]);
            });

        } catch (\Exception $e) {
            Log::error('=== API ORDER PAYMENT HANDLING ERROR ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'order_number' => $orderNumber,
                'payment_data' => $paymentData,
            ]);

            return view('payment.fail', ['message' => 'An error occurred while updating order status']);
        }
    }

    /**
     * Handle course payment success
     */
    protected function handleCoursePayment(string $enrollmentId, array $paymentData)
    {
        Log::info('=== HANDLE API COURSE PAYMENT START ===', [
            'enrollment_id' => $enrollmentId,
            'payment_data' => $paymentData,
        ]);

        try {
            return DB::transaction(function () use ($enrollmentId, $paymentData) {
                $enrollment = \App\Models\CourseEnrollment::find($enrollmentId);
                if (!$enrollment) {
                    Log::error('Enrollment not found for payment', ['enrollment_id' => $enrollmentId]);
                    return view('payment.fail', ['message' => 'Enrollment not found']);
                }

                if ($enrollment->payment_status === 'paid') {
                    return view('payment.success', [
                        'message' => 'Payment already completed',
                        'enrollment_id' => $enrollment->id,
                    ]);
                }

                $enrollment->update([
                    'payment_status' => 'paid',
                    'status' => 'active',
                    'payment_reference' => $paymentData['invoice_id'] ?? null,
                    'payment_completed_at' => now(),
                ]);

                $user = $enrollment->user;
                if ($user && method_exists($user, 'addLoyaltyPoints')) {
                    $points = $user->calculateLoyaltyPointsEarned($enrollment->amount_paid);
                    if ($points > 0) {
                        $user->addLoyaltyPoints($points);
                    }
                }

                return view('payment.success', [
                    'message' => 'Payment completed successfully',
                    'enrollment_id' => $enrollment->id,
                    'amount' => $enrollment->amount_paid,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('=== API COURSE PAYMENT HANDLING ERROR ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'enrollment_id' => $enrollmentId,
                'payment_data' => $paymentData,
            ]);
            return view('payment.fail', ['message' => 'An error occurred while updating enrollment status']);
        }
    }

    /**
     * Handle wallet recharge success
     */
    protected function handleWalletRecharge(string $transactionId, array $paymentData)
    {
        Log::info('=== HANDLE API WALLET RECHARGE START ===', [
            'transaction_id' => $transactionId,
            'payment_data' => $paymentData,
        ]);

        try {
            return DB::transaction(function () use ($transactionId, $paymentData) {
                $transaction = \App\Models\Transaction::find($transactionId);

                if (!$transaction) {
                    Log::error('Transaction not found for payment', ['transaction_id' => $transactionId]);
                    return view('payment.fail', ['message' => 'Transaction not found']);
                }

                // Check if already processed
                if ($transaction->status === 'approved') {
                    Log::warning('Wallet recharge already processed', [
                        'transaction_id' => $transaction->id,
                    ]);
                    return view('payment.success', [
                        'message' => 'Wallet already recharged',
                        'amount' => $transaction->amount,
                    ]);
                }

                $user = \App\Models\User::find($transaction->user_id);

                if (!$user) {
                    Log::error('User not found for transaction', ['user_id' => $transaction->user_id]);
                    return view('payment.fail', ['message' => 'User not found']);
                }

                // Update transaction status
                $transaction->update([
                    'status' => 'approved',
                    'reference' => $paymentData['invoice_id'],
                ]);

                // Add amount to user wallet
                $user->increment('wallet_balance', $transaction->amount);

                Log::info('=== API WALLET RECHARGE SUCCESSFUL ===', [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'new_balance' => $user->wallet_balance,
                    'invoice_id' => $paymentData['invoice_id'],
                ]);

                return view('payment.success', [
                    'message' => 'Wallet recharged successfully',
                    'amount' => $transaction->amount,
                    'new_balance' => $user->wallet_balance,
                ]);
            });

        } catch (\Exception $e) {
            Log::error('=== API WALLET RECHARGE HANDLING ERROR ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'transaction_id' => $transactionId,
                'payment_data' => $paymentData,
            ]);

            return view('payment.fail', ['message' => 'An error occurred while recharging wallet']);
        }
    }

    /**
     * Handle failed payment callback from MyFatoorah (both order and wallet)
     */
    public function error(Request $request)
    {
        Log::warning('=== API PAYMENT ERROR CALLBACK ===', ['request' => $request->all()]);

        try {
            $paymentId = $request->input('paymentId');

            if ($paymentId) {
                // Try to get payment status to determine what failed
                $verificationResult = $this->myFatoorahService->verifyPayment($paymentId);

                if ($verificationResult['success']) {
                    $paymentType = $verificationResult['user_defined_field'];
                    $customerReference = $verificationResult['customer_reference'];

                    // Handle wallet recharge failure
                    if ($paymentType === 'wallet_recharge') {
                        $transaction = \App\Models\Transaction::find($customerReference);
                        if ($transaction && $transaction->type === 'wallet-addons') {
                            $transaction->update([
                                'status' => 'rejected',
                                'note' => json_encode([
                                    'ar' => 'شحن محفظة - فشل في الدفع',
                                    'en' => 'Wallet Recharge - Payment Failed'
                                ], JSON_UNESCAPED_UNICODE),
                            ]);
                        }

                        $errorMessage = $verificationResult['transaction_error'] ?? 'Wallet recharge failed';
                        return view('payment.fail', [
                            'message' => $errorMessage,
                            'type' => 'wallet_recharge'
                        ]);
                    }

                    // Handle order payment failure
                    if ($paymentType === 'order_payment') {
                        $order = \App\Models\Order::where('order_number', $customerReference)->first();
                        if ($order) {
                            $order->update([
                                'payment_status' => 'failed',
                            ]);
                        }

                        $errorMessage = $verificationResult['transaction_error'] ?? 'Payment failed';
                        return view('payment.fail', [
                            'message' => $errorMessage,
                            'type' => 'order_payment',
                            'order_number' => $customerReference,
                        ]);
                    }

                    // Handle course payment failure
                    if ($paymentType === 'course_enrollment') {
                        $enrollment = \App\Models\CourseEnrollment::find($customerReference);
                        if ($enrollment) {
                            $enrollment->update([
                                'payment_status' => 'failed',
                                'status' => 'cancelled',
                            ]);
                        }
                        $errorMessage = $verificationResult['transaction_error'] ?? 'Course payment failed';
                        return view('payment.fail', [
                            'message' => $errorMessage,
                            'type' => 'course_enrollment',
                            'enrollment_id' => $customerReference,
                        ]);
                    }
                }
            }

            return view('payment.fail', ['message' => 'Payment failed']);

        } catch (\Exception $e) {
            Log::error('=== API PAYMENT ERROR CALLBACK EXCEPTION ===', [
                'payment_id' => $request->input('paymentId'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return view('payment.fail', ['message' => 'Payment processing error']);
        }
    }

    /**
     * Handle webhook from MyFatoorah for both order and wallet payments
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('=== MyFatoorah Webhook Received ===', [
                'timestamp' => now()->toISOString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'content_type' => $request->header('Content-Type')
            ]);

            Log::info('Webhook Headers', [
                'headers' => $request->headers->all()
            ]);

            Log::info('Webhook Raw Payload', [
                'body' => $request->all(),
                'raw_content' => $request->getContent(),
                'content_length' => strlen($request->getContent())
            ]);

            // Extract key webhook data for easier debugging
            $webhookData = [
                'event_type' => $request->input('EventType'),
                'invoice_status' => $request->input('InvoiceStatus'),
                'payment_id' => $request->input('Data.PaymentId') ?? $request->input('PaymentId'),
                'invoice_id' => $request->input('Data.InvoiceId') ?? $request->input('InvoiceId'),
                'customer_reference' => $request->input('Data.CustomerReference') ?? $request->input('CustomerReference'),
                'user_defined_field' => $request->input('Data.UserDefinedField') ?? $request->input('UserDefinedField'),
                'transaction_status' => $request->input('Data.focusTransaction.TransactionStatus') ?? $request->input('focusTransaction.TransactionStatus'),
                'amount' => $request->input('Data.InvoiceValue') ?? $request->input('InvoiceValue'),
                'currency' => $request->input('Data.DisplayCurrencyIso') ?? $request->input('DisplayCurrencyIso')
            ];

            Log::info('Extracted Webhook Data', $webhookData);

            // Determine payment type from UserDefinedField
            $paymentType = $this->determinePaymentType($request);

            Log::info('Payment Type Determination', [
                'determined_type' => $paymentType,
                'user_defined_field' => $webhookData['user_defined_field'],
                'available_types' => ['order_payment', 'wallet_recharge'],
                'determination_method' => 'UserDefinedField analysis'
            ]);

            switch ($paymentType) {
                case 'order_payment':
                    Log::info('=== Processing Order Payment Webhook ===', [
                        'payment_id' => $webhookData['payment_id'],
                        'customer_reference' => $webhookData['customer_reference'],
                        'transaction_status' => $webhookData['transaction_status']
                    ]);
                    return $this->handleOrderPaymentWebhook($request);

                case 'wallet_recharge':
                    Log::info('=== Processing Wallet Recharge Webhook ===', [
                        'payment_id' => $webhookData['payment_id'],
                        'customer_reference' => $webhookData['customer_reference'],
                        'transaction_status' => $webhookData['transaction_status']
                    ]);
                    return $this->handleWalletRechargeWebhook($request);

                default:
                    Log::warning('=== Unknown Payment Type in Webhook ===', [
                        'determined_type' => $paymentType,
                        'user_defined_field' => $webhookData['user_defined_field'],
                        'expected_types' => ['order_payment', 'wallet_recharge'],
                        'full_webhook_data' => $webhookData,
                        'raw_request' => $request->all()
                    ]);
                    return response('Unknown payment type', 400);
            }

        } catch (\Exception $e) {
            Log::error('=== Payment Webhook Processing Exception ===', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'request_headers' => $request->headers->all(),
                'timestamp' => now()->toISOString()
            ]);

            // Return 200 to prevent MyFatoorah from retrying
            return response('Webhook received but processing failed', 200);
        }
    }

    /**
     * Determine payment type from webhook data
     */
    private function determinePaymentType(Request $request)
    {
        $data = $request->all();

        Log::info('=== Determining Payment Type ===', [
            'available_data_keys' => array_keys($data),
            'has_data_array' => isset($data['Data']),
            'data_array_keys' => isset($data['Data']) ? array_keys($data['Data']) : null
        ]);

        // Try to get UserDefinedField from different possible locations
        $userDefinedField = null;
        $source = null;

        if (isset($data['Data']['UserDefinedField'])) {
            $userDefinedField = $data['Data']['UserDefinedField'];
            $source = 'Data.UserDefinedField';
        } elseif (isset($data['UserDefinedField'])) {
            $userDefinedField = $data['UserDefinedField'];
            $source = 'UserDefinedField';
        } else {
            // If UserDefinedField is not available, try to determine from payment ID
            $paymentId = $data['Data']['PaymentId'] ?? $data['PaymentId'] ?? null;
            if ($paymentId) {
                Log::info('UserDefinedField not found in webhook, querying MyFatoorah', [
                    'payment_id' => $paymentId
                ]);
                $userDefinedField = $this->getUserDefinedFieldFromPaymentId($paymentId);
                $source = 'MyFatoorah API query';
            }
        }

        // Check if UserDefinedField is JSON (order payment)
        $paymentType = $userDefinedField;
        if ($userDefinedField) {
            $decoded = json_decode($userDefinedField, true);
            if (is_array($decoded) && isset($decoded['order_id'])) {
                // This is an order payment (UserDefinedField contains JSON with order_id)
                $paymentType = 'order_payment';
            }
        }

        Log::info('Payment Type Determination Result', [
            'user_defined_field' => $userDefinedField,
            'source' => $source,
            'final_type' => $paymentType ?? 'unknown',
            'search_locations' => [
                'Data.UserDefinedField' => $data['Data']['UserDefinedField'] ?? 'not_found',
                'UserDefinedField' => $data['UserDefinedField'] ?? 'not_found'
            ]
        ]);

        return $paymentType ?? 'unknown';
    }

    /**
     * Get UserDefinedField by querying MyFatoorah with payment ID
     */
    private function getUserDefinedFieldFromPaymentId($paymentId)
    {
        try {
            Log::info('Querying MyFatoorah for UserDefinedField', [
                'payment_id' => $paymentId,
                'api_key_prefix' => substr(config('myfatoorah.api_key'), 0, 10) . '...',
                'test_mode' => config('myfatoorah.test_mode')
            ]);

            $pay = new PaymentMyfatoorahApiV2(
                config('myfatoorah.api_key'),
                config('myfatoorah.test_mode')
            );

            $responseData = $pay->getPaymentStatus($paymentId, 'PaymentId');
            $responseArray = json_decode(json_encode($responseData), true);

            $userDefinedField = $responseArray['UserDefinedField'] ?? 'unknown';

            Log::info('MyFatoorah UserDefinedField Query Result', [
                'payment_id' => $paymentId,
                'user_defined_field' => $userDefinedField,
                'customer_reference' => $responseArray['CustomerReference'] ?? null,
                'transaction_status' => $responseArray['focusTransaction']['TransactionStatus'] ?? null
            ]);

            return $userDefinedField;

        } catch (\Exception $e) {
            Log::error('Failed to get UserDefinedField from MyFatoorah', [
                'payment_id' => $paymentId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            return 'unknown';
        }
    }

    /**
     * Handle order payment webhook
     */
    private function handleOrderPaymentWebhook(Request $request)
    {
        try {
            Log::info('=== Starting Order Payment Webhook Processing ===');

            $data = $request->all();

            // Extract payment information with detailed logging
            $paymentId = $data['Data']['PaymentId'] ?? $data['PaymentId'] ?? null;
            $status = $data['EventType'] ?? $data['InvoiceStatus'] ?? null;
            $orderId = $data['Data']['CustomerReference'] ?? $data['CustomerReference'] ?? null;
            $transactionStatus = $data['Data']['focusTransaction']['TransactionStatus'] ?? $data['focusTransaction']['TransactionStatus'] ?? null;
            $amount = $data['Data']['InvoiceValue'] ?? $data['InvoiceValue'] ?? null;

            Log::info('Order Payment Webhook Data Extraction', [
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'event_type' => $status,
                'transaction_status' => $transactionStatus,
                'amount' => $amount,
                'extraction_sources' => [
                    'payment_id_source' => $paymentId ? ($data['Data']['PaymentId'] ?? null ? 'Data.PaymentId' : 'PaymentId') : 'not_found',
                    'order_id_source' => $orderId ? ($data['Data']['CustomerReference'] ?? null ? 'Data.CustomerReference' : 'CustomerReference') : 'not_found'
                ]
            ]);

            if (!$paymentId || !$orderId) {
                Log::error('Order Payment Webhook Missing Critical Data', [
                    'missing_payment_id' => !$paymentId,
                    'missing_order_id' => !$orderId,
                    'available_data_keys' => array_keys($data),
                    'data_array_keys' => isset($data['Data']) ? array_keys($data['Data']) : null,
                    'full_payload' => $data
                ]);
                return response('Missing required data', 400);
            }

            $status = $this->myFatoorahService->getPaymentStatus($paymentId);
            if (!$status['success']) {
                Log::warning('Order payment status check failed', [
                    'payment_id' => $paymentId,
                    'message' => $status['message'] ?? 'Unknown error'
                ]);
                return response('Webhook processing failed', 400);
            }

            $dataArr = $status['data'];
            $order = \App\Models\Order::withTrashed()->where('order_number', $orderId)->first() ?? \App\Models\Order::withTrashed()->find($orderId);
            if (!$order) {
                Log::error('Order not found during webhook', [
                    'order_reference' => $orderId,
                ]);
                return response('Order not found', 404);
            }

            if (($dataArr['InvoiceStatus'] ?? null) === 'Paid') {
                $order->update([
                    'payment_status' => 'success',
                    'payment_reference' => $dataArr['InvoiceId'] ?? null,
                    'payment_date' => now(),
                    'status' => 'pending'
                ]);
                return response('OK', 200);
            }

            $order->update([
                'payment_status' => 'failed'
            ]);
            return response('Payment not completed', 200);

        } catch (\Exception $e) {
            Log::error('=== Order Payment Webhook Exception ===', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'timestamp' => now()->toISOString()
            ]);

            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle wallet recharge webhook
     */
    private function handleWalletRechargeWebhook(Request $request)
    {
        try {
            Log::info('Processing wallet recharge webhook');

            $data = $request->all();

            // Extract payment information
            $paymentId = $data['Data']['PaymentId'] ?? $data['PaymentId'] ?? null;
            $status = $data['EventType'] ?? $data['InvoiceStatus'] ?? null;
            $transactionId = $data['Data']['CustomerReference'] ?? $data['CustomerReference'] ?? null;

            if (!$paymentId || !$transactionId) {
                Log::warning('Wallet recharge webhook missing required data', $data);
                return response('Missing required data', 400);
            }

            // Process the webhook based on event type
            switch ($status) {
                case 'InvoiceStatusChanged':
                case 'Paid':
                case 'DuplicatePayment':
                    return $this->handleWalletPaymentSuccess($paymentId, $transactionId, $data);

                case 'Failed':
                case 'Cancelled':
                case 'Expired':
                    return $this->handleWalletPaymentFailed($paymentId, $transactionId, $data);

                default:
                    Log::info('Unhandled wallet recharge webhook event type', [
                        'event_type' => $status,
                        'payment_id' => $paymentId,
                        'transaction_id' => $transactionId,
                    ]);
                    return response('OK', 200);
            }

        } catch (\Exception $e) {
            Log::error('Wallet recharge webhook processing exception', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle successful wallet payment
     */
    private function handleWalletPaymentSuccess($paymentId, $transactionId, $data)
    {
        try {
            Log::info('Processing wallet payment success', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
            ]);

            $result = $this->walletRechargeService->verifyWalletRechargePayment($paymentId, $transactionId);

            if ($result['success']) {
                Log::info('Wallet recharge completed successfully', [
                    'payment_id' => $paymentId,
                    'transaction_id' => $transactionId,
                ]);
                return response('Payment processed successfully', 200);
            } else {
                Log::warning('Wallet recharge verification failed', [
                    'payment_id' => $paymentId,
                    'transaction_id' => $transactionId,
                    'error' => $result['message'],
                ]);
                return response('Payment verification failed', 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process wallet payment success', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return response('Payment processing failed', 500);
        }
    }

    /**
     * Handle failed wallet payment
     */
    private function handleWalletPaymentFailed($paymentId, $transactionId, $data)
    {
        try {
            Log::info('Processing wallet payment failure', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
            ]);

            // Update transaction status to failed
            $transaction = \App\Models\Transaction::find($transactionId);
            if ($transaction && $transaction->type === 'wallet-addons') {
                $transaction->update([
                    'status' => 'failed',
                    'note' => json_encode([
                        'ar' => 'شحن محفظة - فشل في الدفع',
                        'en' => 'Wallet Recharge - Payment Failed'
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                Log::info('Wallet recharge marked as failed', [
                    'payment_id' => $paymentId,
                    'transaction_id' => $transactionId,
                ]);
            }

            return response('Payment failure processed', 200);

        } catch (\Exception $e) {
            Log::error('Failed to process wallet payment failure', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return response('Payment failure processing failed', 500);
        }
    }

    /**
     * Get available payment gateways
     */
    public function gateways(Request $request)
    {
        try {
            $amount = $request->get('amount', 0);
            $result = $this->myFatoorahService->getAvailableGateways((float) $amount);
            if (($result['success'] ?? false) === true) {
                return Responder::success(['gateways' => $result['gateways']]);
            }
            return Responder::error($result['message'] ?? 'Failed to get payment gateways');

        } catch (\Exception $e) {
            Log::error('Failed to get order payment gateways', [
                'error' => $e->getMessage()
            ]);

            return Responder::error('Failed to get payment gateways');
        }
    }
}
