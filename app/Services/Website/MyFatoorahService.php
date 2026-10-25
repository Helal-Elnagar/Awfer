<?php

namespace App\Services\Website;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyFatoorahService
{
    protected $apiToken;
    protected $baseUrl;
    protected $testMode;
    protected $config;

    public function __construct()
    {
        $this->apiToken = config('services.myfatoorah.token');
        $this->testMode = config('services.myfatoorah.test_mode', true);

        // Validate API token
        if (empty($this->apiToken)) {
            Log::error('MyFatoorah API token is not configured');
            throw new Exception('MyFatoorah API token is not configured. Please set MYFATOORAH_API_KEY in .env file');
        }

        $region = config('myfatoorah.region', 'SAU');
        if ($this->testMode) {
            $this->baseUrl = 'https://apitest.myfatoorah.com';
        } else {
            if ($region === 'SAU') {
                $this->baseUrl = 'https://api-sa.myfatoorah.com';
            } elseif ($region === 'QAT') {
                $this->baseUrl = 'https://api-qa.myfatoorah.com';
            } elseif ($region === 'EGY') {
                $this->baseUrl = 'https://api-eg.myfatoorah.com';
            } else {
                $this->baseUrl = 'https://api.myfatoorah.com';
            }
        }

        $this->config = [
            'country_iso' => config('services.myfatoorah.country_iso', 'SAU'),
            'currency' => config('services.myfatoorah.currency_iso', 'SAR'),
            'language' => config('services.myfatoorah.language', 'ar'),
            'country_code' => config('services.myfatoorah.mobile_country_code', '+966'),
            'notification_option' => config('services.myfatoorah.notification_option', 'Lnk'),
        ];

        Log::info('MyFatoorah Service Initialized', [
            'test_mode' => $this->testMode,
            'base_url' => $this->baseUrl,
            'config' => $this->config,
            'token_set' => !empty($this->apiToken),
            'using_default_token' => ($this->apiToken === $defaultToken),
        ]);
    }

    /**
     * Create payment invoice for order
     *
     * @param array $orderData
     * @return array
     * @throws Exception
     */
    public function createOrderPayment(array $orderData): array
    {
        Log::info('=== CREATE ORDER PAYMENT START ===', [
            'order_data' => $orderData,
        ]);

        try {
            // Use custom callback URLs if provided, otherwise use website routes
            $callbackUrl = $orderData['callback_url'] ?? route('payment.success');
            $errorUrl = $orderData['error_url'] ?? route('payment.error');

            Log::info('=== ORDER PAYMENT - CALLBACK URLS ===', [
                'callback_url' => $callbackUrl,
                'error_url' => $errorUrl,
                'order_number' => $orderData['order_number'],
                'source' => isset($orderData['callback_url']) ? 'API' : 'Website',
            ]);

            $postFields = [
                'NotificationOption' => $this->config['notification_option'],
                'InvoiceValue' => $orderData['amount'],
                'CustomerName' => $orderData['customer_name'] ?? 'Customer',
                'DisplayCurrencyIso' => $this->config['currency'],
                'MobileCountryCode' => $this->config['country_code'],
                'CustomerMobile' => $this->cleanPhone($orderData['customer_phone'] ?? ''),
                'CallBackUrl' => $callbackUrl,
                'ErrorUrl' => $errorUrl,
                'Language' => $this->config['language'],
                'CustomerReference' => $orderData['order_number'],
                'UserDefinedField' => 'order_payment',
                'CustomerEmail' => $orderData['customer_email'] ?? 'noemail@example.com',
                'InvoiceItems' => [
                    [
                        'ItemName' => 'Order #' . $orderData['order_number'],
                        'Quantity' => 1,
                        'UnitPrice' => $orderData['amount'],
                    ]
                ],
            ];

            Log::info('MyFatoorah API Request - Order Payment', [
                'url' => $this->baseUrl . '/v2/SendPayment',
                'post_fields' => $postFields,
            ]);

            $response = $this->sendPaymentRequest($postFields);

            Log::info('MyFatoorah API Response - Order Payment', [
                'response' => $response,
            ]);

            if ($response['IsSuccess']) {
                $result = [
                    'success' => true,
                    'payment_url' => $response['Data']['InvoiceURL'],
                    'invoice_id' => $response['Data']['InvoiceId'],
                    'invoice_reference' => $response['Data']['InvoiceReference'] ?? null,
                ];

                Log::info('=== CREATE ORDER PAYMENT SUCCESS ===', $result);

                return $result;
            }

            throw new Exception($response['Message'] ?? 'Failed to create payment invoice');

        } catch (Exception $e) {
            Log::error('=== CREATE ORDER PAYMENT FAILED ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_data' => $orderData,
            ]);
            Log::error('MyFatoorah Order Payment Error', [
                'error' => $e->getMessage(),
                'order_data' => $orderData,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create payment invoice for wallet recharge
     *
     * @param array $walletData
     * @return array
     * @throws Exception
     */
    public function createWalletRecharge(array $walletData): array
    {
        try {
            // Use custom callback URLs if provided, otherwise use website routes
            $callbackUrl = $walletData['callback_url'] ?? route('payment.success');
            $errorUrl = $walletData['error_url'] ?? route('payment.error');

            Log::info('=== WALLET RECHARGE - CALLBACK URLS ===', [
                'callback_url' => $callbackUrl,
                'error_url' => $errorUrl,
                'transaction_id' => $walletData['transaction_id'],
                'source' => isset($walletData['callback_url']) ? 'API' : 'Website',
            ]);

            $postFields = [
                'NotificationOption' => $this->config['notification_option'],
                'InvoiceValue' => $walletData['amount'],
                'CustomerName' => $walletData['customer_name'] ?? 'Customer',
                'DisplayCurrencyIso' => $this->config['currency'],
                'MobileCountryCode' => $this->config['country_code'],
                'CustomerMobile' => $this->cleanPhone($walletData['customer_phone'] ?? ''),
                'CallBackUrl' => $callbackUrl,
                'ErrorUrl' => $errorUrl,
                'Language' => $this->config['language'],
                'CustomerReference' => $walletData['transaction_id'],
                'UserDefinedField' => 'wallet_recharge',
                'CustomerEmail' => $walletData['customer_email'] ?? 'noemail@example.com',
                'InvoiceItems' => [
                    [
                        'ItemName' => 'Wallet Recharge',
                        'Quantity' => 1,
                        'UnitPrice' => $walletData['amount'],
                    ]
                ],
            ];

            // If a specific gateway was provided, try to execute with that PaymentMethodId
            $gateway = $walletData['gateway'] ?? 'myfatoorah';

            if ($gateway !== 'myfatoorah') {
                $paymentMethodId = $this->resolvePaymentMethodId($gateway, (float) $walletData['amount']);

                if ($paymentMethodId !== null && $paymentMethodId !== 'myfatoorah') {
                    $response = $this->sendExecutePaymentRequest($postFields, $paymentMethodId);

                    if ($response['IsSuccess']) {
                        return [
                            'success' => true,
                            'payment_url' => $response['Data']['PaymentURL'],
                            'invoice_id' => $response['Data']['InvoiceId'],
                            'invoice_reference' => $response['Data']['InvoiceReference'] ?? null,
                        ];
                    }

                    throw new Exception($response['Message'] ?? 'Failed to execute payment');
                }
            }

            // Fallback: create a general invoice link (SendPayment)
            $response = $this->sendPaymentRequest($postFields);

            if ($response['IsSuccess']) {
                return [
                    'success' => true,
                    'payment_url' => $response['Data']['InvoiceURL'],
                    'invoice_id' => $response['Data']['InvoiceId'],
                    'invoice_reference' => $response['Data']['InvoiceReference'] ?? null,
                ];
            }

            throw new Exception($response['Message'] ?? 'Failed to create payment invoice');

        } catch (Exception $e) {
            Log::error('MyFatoorah Wallet Recharge Error', [
                'error' => $e->getMessage(),
                'wallet_data' => $walletData,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create payment invoice for course enrollment
     */
    public function createCoursePayment(array $data): array
    {
        try {
            $callbackUrl = $data['callback_url'] ?? route('payment.success');
            $errorUrl = $data['error_url'] ?? route('payment.error');

            $postFields = [
                'NotificationOption' => $this->config['notification_option'],
                'InvoiceValue' => (float) $data['amount'],
                'CustomerName' => $data['customer_name'] ?? 'Course Student',
                'DisplayCurrencyIso' => $this->config['currency'],
                'MobileCountryCode' => $this->config['country_code'],
                'CustomerMobile' => $this->cleanPhone($data['customer_phone'] ?? ''),
                'CallBackUrl' => $callbackUrl,
                'ErrorUrl' => $errorUrl,
                'Language' => $this->config['language'],
                'CustomerReference' => (string) $data['enrollment_id'],
                'UserDefinedField' => 'course_enrollment',
                'CustomerEmail' => $data['customer_email'] ?? 'noemail@example.com',
                'InvoiceItems' => [
                    [
                        'ItemName' => $data['course_name'] ?? 'Course Enrollment',
                        'Quantity' => 1,
                        'UnitPrice' => (float) $data['amount'],
                    ],
                ],
            ];

            $gateway = $data['gateway'] ?? 'myfatoorah';
            if ($gateway !== 'myfatoorah') {
                $paymentMethodId = $this->resolvePaymentMethodId($gateway, (float) $data['amount']);
                if ($paymentMethodId && $paymentMethodId !== 'myfatoorah') {
                    $resp = $this->sendExecutePaymentRequest($postFields, (int) $paymentMethodId);
                    if (($resp['IsSuccess'] ?? false) === true) {
                        return [
                            'success' => true,
                            'payment_url' => $resp['Data']['PaymentURL'] ?? null,
                            'invoice_id' => $resp['Data']['InvoiceId'] ?? null,
                            'invoice_reference' => $resp['Data']['InvoiceReference'] ?? null,
                        ];
                    }
                    throw new Exception($resp['Message'] ?? 'Failed to execute course payment');
                }
            }

            $resp = $this->sendPaymentRequest($postFields);
            if (($resp['IsSuccess'] ?? false) === true) {
                return [
                    'success' => true,
                    'payment_url' => $resp['Data']['InvoiceURL'] ?? null,
                    'invoice_id' => $resp['Data']['InvoiceId'] ?? null,
                    'invoice_reference' => $resp['Data']['InvoiceReference'] ?? null,
                ];
            }

            throw new Exception($resp['Message'] ?? 'Failed to create course payment invoice');
        } catch (Exception $e) {
            Log::error('MyFatoorah Course Payment Error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment status by payment ID
     *
     * @param string $paymentId
     * @return array
     * @throws Exception
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = Http::withoutVerifying()->asJson()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->retry(3, 500)->post($this->baseUrl . '/v2/GetPaymentStatus', [
                'Key' => $paymentId,
                'KeyType' => 'PaymentId',
            ]);

            if (!$response->successful()) {
                $status = $response->status();
                $body = $response->body();
                $json = null;
                try { $json = $response->json(); } catch (\Throwable $t) {}

                $maskedToken = substr($this->apiToken, 0, 8) . '...';
                Log::error('MyFatoorah Get Payment Status HTTP Error', [
                    'http_status' => $status,
                    'message' => $json['Message'] ?? $body ?? 'Unknown',
                    'payment_id' => $paymentId,
                    'base_url' => $this->baseUrl,
                    'test_mode' => $this->testMode,
                    'token_prefix' => $maskedToken,
                ]);

                throw new Exception('HTTP request returned status code ' . $status);
            }

            $data = $response->json();

            if (!$data['IsSuccess']) {
                throw new Exception($data['Message'] ?? 'Payment status check failed');
            }

            return [
                'success' => true,
                'data' => $data['Data'],
            ];

        } catch (Exception $e) {
            Log::error('MyFatoorah Get Payment Status Error', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send payment request to MyFatoorah API
     *
     * @param array $postFields
     * @return array
     * @throws Exception
     */
    public function sendPaymentRequest(array $postFields): array
    {
        try {
            $response = Http::withoutVerifying()->asJson()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->retry(3, 500)->post($this->baseUrl . '/v2/SendPayment', $postFields);
            Log::info('MyFatoorah SendPayment Raw Response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body(),
                'json' => $response->json(),
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $errorJson = $response->json();
                $errorMessage = $errorJson['Message'] ?? $errorBody ?? 'Unknown error';

                Log::error('MyFatoorah SendPayment Failed', [
                    'status' => $response->status(),
                    'error_body' => $errorBody,
                    'error_json' => $errorJson,
                ]);

                throw new Exception('MyFatoorah API request failed (HTTP ' . $response->status() . '): ' . $errorMessage);
            }

            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('MyFatoorah Connection Error', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl . '/v2/SendPayment',
            ]);
            throw new Exception('Failed to connect to MyFatoorah: ' . $e->getMessage());
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'MyFatoorah') !== false) {
                throw $e; // Re-throw our custom exceptions
            }
            Log::error('MyFatoorah Unexpected Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception('Unexpected error during payment request: ' . $e->getMessage());
        }
    }

    /**
     * Send execute payment request with specific PaymentMethodId
     */
    public function sendExecutePaymentRequest(array $postFields, int $paymentMethodId): array
    {
        try {
            $payload = $postFields;
            $payload['PaymentMethodId'] = $paymentMethodId;

            $response = Http::withoutVerifying()->asJson()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->retry(3, 500)->post($this->baseUrl . '/v2/ExecutePayment', $payload);

            Log::info('MyFatoorah ExecutePayment Raw Response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body(),
                'json' => $response->json(),
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $errorJson = $response->json();
                $errorMessage = $errorJson['Message'] ?? $errorBody ?? 'Unknown error';

                Log::error('MyFatoorah ExecutePayment Failed', [
                    'status' => $response->status(),
                    'error_body' => $errorBody,
                    'error_json' => $errorJson,
                ]);

                throw new Exception('MyFatoorah ExecutePayment request failed (HTTP ' . $response->status() . '): ' . $errorMessage);
            }

            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('MyFatoorah ExecutePayment Connection Error', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl . '/v2/ExecutePayment',
            ]);
            throw new Exception('Failed to connect to MyFatoorah ExecutePayment: ' . $e->getMessage());
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'MyFatoorah') !== false) {
                throw $e; // Re-throw our custom exceptions
            }
            Log::error('MyFatoorah ExecutePayment Unexpected Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception('Unexpected error during ExecutePayment request: ' . $e->getMessage());
        }
    }

    /**
     * Resolve PaymentMethodId for a given gateway name using InitiatePayment
     */
    public function resolvePaymentMethodId(string $gateway, float $amount): int|string|null
    {
        try {
            $response = Http::withoutVerifying()->asJson()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->retry(3, 500)->post($this->baseUrl . '/v2/InitiatePayment', [
                'InvoiceAmount' => $amount,
                'CurrencyIso' => $this->config['currency'],
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to get gateways: ' . $response->body());
            }

            $data = $response->json();
            $methods = $data['Data']['PaymentMethods'] ?? [];

            // Map our gateway names to MyFatoorah PaymentMethodCode values
            $map = [
                'visa_master' => 'visa',
                'mada' => 'mada',
            ];

            if (!isset($map[$gateway])) {
                Log::warning('Unsupported gateway for wallet recharge', ['gateway' => $gateway]);
                return 'myfatoorah';
            }

            $targetCode = $map[$gateway];

            foreach ($methods as $method) {
                if (($method['PaymentMethodCode'] ?? null) === $targetCode) {
                    return (int) $method['PaymentMethodId'];
                }
            }

            Log::warning('Payment method code not found; falling back', [
                'gateway' => $gateway,
                'target_code' => $targetCode,
            ]);
            return 'myfatoorah';
        } catch (Exception $e) {
            Log::error('Resolve PaymentMethodId failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getAvailableGateways(float $amount): array
    {
        try {
            $response = Http::withoutVerifying()->asJson()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->retry(3, 500)->post($this->baseUrl . '/v2/InitiatePayment', [
                'InvoiceAmount' => $amount,
                'CurrencyIso' => $this->config['currency'],
            ]);

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Failed to get payment gateways'];
            }

            $data = $response->json();
            $methods = $data['Data']['PaymentMethods'] ?? [];
            return ['success' => true, 'gateways' => $methods];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Clean phone number (remove non-digits)
     *
     * @param string $phone
     * @return string
     */
    protected function cleanPhone(string $phone): string
    {
        $cleaned = preg_replace('/\D/', '', $phone);
        
        // Remove leading country code if present
        if (str_starts_with($cleaned, '966')) {
            $cleaned = substr($cleaned, 3);
        }
        
        // Ensure it starts with 5 for Saudi numbers
        if (!str_starts_with($cleaned, '5') && strlen($cleaned) === 9) {
            return $cleaned;
        }
        
        return $cleaned ?: '500000000'; // Default fallback
    }

    /**
     * Verify payment callback data
     *
     * @param string $paymentId
     * @return array
     */
    public function verifyPayment(string $paymentId): array
    {
        Log::info('=== VERIFY PAYMENT START ===', [
            'payment_id' => $paymentId,
        ]);

        $statusResult = $this->getPaymentStatus($paymentId);

        Log::info('Payment status result', [
            'status_result' => $statusResult,
        ]);

        if (!$statusResult['success']) {
            Log::error('Payment status check failed', [
                'payment_id' => $paymentId,
                'status_result' => $statusResult,
            ]);
            return [
                'success' => false,
                'message' => $statusResult['message'] ?? 'Failed to verify payment',
            ];
        }

        $paymentData = $statusResult['data'];
        $invoiceStatus = $paymentData['InvoiceStatus'] ?? '';

        // Check transaction status (most recent transaction)
        $transactionStatus = null;
        $transactionError = null;
        if (!empty($paymentData['InvoiceTransactions'])) {
            $lastTransaction = $paymentData['InvoiceTransactions'][0];
            $transactionStatus = $lastTransaction['TransactionStatus'] ?? null;
            $transactionError = $lastTransaction['Error'] ?? null;
        }

        Log::info('Payment data extracted', [
            'invoice_status' => $invoiceStatus,
            'transaction_status' => $transactionStatus,
            'transaction_error' => $transactionError,
            'invoice_id' => $paymentData['InvoiceId'] ?? null,
            'customer_reference' => $paymentData['CustomerReference'] ?? null,
            'user_defined_field' => $paymentData['UserDefinedField'] ?? null,
            'full_payment_data' => $paymentData,
        ]);

        // Payment is successful only if:
        // 1. Invoice status is Paid or DuplicatePayment
        // 2. AND transaction status is Succss (note: MyFatoorah uses "Succss" not "Success")
        $isPaid = in_array($invoiceStatus, ['Paid', 'DuplicatePayment'])
                  && in_array($transactionStatus, ['Succss', 'Success']);

        $result = [
            'success' => true,
            'is_paid' => $isPaid,
            'invoice_status' => $invoiceStatus,
            'transaction_status' => $transactionStatus,
            'transaction_error' => $transactionError,
            'invoice_id' => $paymentData['InvoiceId'] ?? null,
            'invoice_reference' => $paymentData['InvoiceReference'] ?? null,
            'customer_reference' => $paymentData['CustomerReference'] ?? null,
            'user_defined_field' => $paymentData['UserDefinedField'] ?? null,
            'invoice_value' => $paymentData['InvoiceValue'] ?? 0,
            'paid_amount' => $paymentData['InvoiceTransactions'][0]['PaidCurrency'] ?? 0,
            'payment_date' => $paymentData['InvoiceTransactions'][0]['TransactionDate'] ?? null,
            'payment_method' => $paymentData['InvoiceTransactions'][0]['PaymentGateway'] ?? null,
            'transaction_id' => $paymentData['InvoiceTransactions'][0]['TransactionId'] ?? null,
        ];

        Log::info('=== VERIFY PAYMENT RESULT ===', $result);

        return $result;
    }
}

