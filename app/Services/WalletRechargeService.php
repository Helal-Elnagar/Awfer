<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletRechargeService
{
    protected $config;
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->config = config('myfatoorah');
        $this->transactionService = $transactionService;
    }

    /**
     * Initialize MyFatoorah payment for wallet recharge
     */
    public function initializeWalletRecharge(User $user, float $amount, string $gateway = 'myfatoorah', array $options = [])
    {
        try {
            // Create pending transaction first
            $transaction = $this->createPendingTransaction($user, $amount);

            // Use the new MyFatoorahService
            $myFatoorahService = app(\App\Services\Website\MyFatoorahService::class);

            // Prepare wallet data for payment
            $walletData = [
                'amount' => $amount,
                'transaction_id' => (string) $transaction->id,
                'customer_name' => $user->name ?? 'Customer',
                'customer_phone' => $user->phone ?? '',
                'customer_email' => $user->email ?? 'noemail@example.com',
                // Use custom callback URLs if provided (for API), otherwise use default (for website)
                'callback_url' => $options['callback_url'] ?? route('payment.success'),
                'error_url' => $options['error_url'] ?? route('payment.error'),
            ];

            Log::info('Wallet recharge initialization', [
                'user_id' => $user->id,
                'amount' => $amount,
                'transaction_id' => $transaction->id,
                'callback_url' => $walletData['callback_url'],
                'error_url' => $walletData['error_url'],
            ]);

            // Create payment invoice
            $result = $myFatoorahService->createWalletRecharge($walletData);

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            // Update transaction with payment information
            $transaction->update([
                'reference' => $result['invoice_id'],
            ]);

            return [
                'success' => true,
                'payment_url' => $result['payment_url'],
                'invoice_id' => $result['invoice_id'],
                'transaction_id' => $transaction->id,
            ];

        } catch (Exception $e) {
            Log::error('MyFatoorah wallet recharge creation failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create payment invoice: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a pending transaction for wallet recharge
     */
    private function createPendingTransaction(User $user, float $amount): Transaction
    {
        return $this->transactionService->createTransaction([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'wallet-addons',
            'status' => 'pending',
            'reference' => $this->transactionService->generateTransactionId(),
            'note' => json_encode([
                'ar' => 'شحن محفظة',
                'en' => 'Wallet Recharge'
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Complete wallet recharge after successful payment
     */
    public function completeWalletRecharge(Transaction $transaction): bool
    {
        try {
            return DB::transaction(function () use ($transaction) {
                // Update transaction status
                $transaction->update([
                    'status' => 'completed',
                ]);

                // Add amount to user's wallet
                $transaction->user->increment('wallet_balance', $transaction->amount);

                Log::info('Wallet recharge completed successfully', [
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'amount' => $transaction->amount,
                ]);

                return true;
            });
        } catch (Exception $e) {
            Log::error('Failed to complete wallet recharge', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }


    /**
     * Verify wallet recharge payment
     */
    public function verifyWalletRechargePayment(string $paymentId, string $transactionId): array
    {
        try {
            $transaction = Transaction::find($transactionId);
            if (!$transaction) {
                return ['success' => false, 'message' => 'Transaction not found'];
            }

            if ($transaction->type !== 'wallet-addons') {
                return ['success' => false, 'message' => 'Invalid transaction type'];
            }

            if ($transaction->status === 'completed') {
                return ['success' => true, 'message' => 'Payment already processed'];
            }

            // Log API configuration for debugging
            Log::info('Wallet recharge verification - API config', [
                'api_key_prefix' => substr($this->config['api_key'], 0, 20) . '...',
                'test_mode' => $this->config['test_mode'],
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
            ]);

            $mf = app(\App\Services\Website\MyFatoorahService::class);
            $status = $mf->getPaymentStatus($paymentId);
            if (!$status['success']) {
                return ['success' => false, 'message' => $status['message'] ?? 'Payment verification failed'];
            }
            $paymentData = $status['data'];

            if (($paymentData['InvoiceStatus'] ?? null) === 'Paid') {
                $this->completeWalletRecharge($transaction);
                return ['success' => true, 'message' => 'Payment verified and wallet recharged'];
            }

            return ['success' => false, 'message' => 'Payment not completed'];

        } catch (Exception $e) {
            Log::error('Wallet recharge payment verification failed', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
                'api_key_prefix' => isset($this->config['api_key']) ? substr($this->config['api_key'], 0, 20) . '...' : 'not_set',
                'test_mode' => $this->config['test_mode'] ?? 'not_set',
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Payment verification failed'];
        }
    }
}
