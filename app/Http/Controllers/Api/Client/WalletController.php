<?php

namespace App\Http\Controllers\Api\Client;

use App\Models\Transaction;
use App\Services\TransactionService;
use App\Services\WalletRechargeService;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Http\Requests\CreateWithdrawRequest;
use App\Http\Requests\CreateWalletRechargeRequest;
use App\Facades\Responder;

class WalletController extends Controller
{

    public function __construct(
        protected TransactionService $transactionService,
        protected WalletRechargeService $walletRechargeService
    ) {}

    public function wallet()
    {
        $user = auth()->user();
        $balance = $user->wallet_balance;

        $transactionsQuery = Transaction::where('user_id', $user->id);

        if ($type = request()->type) {
            if ($type == 'wallet-addons' || $type == 'wallet-recharge') {
                $transactionsQuery->whereIn('type', ['wallet-recharge', 'wallet-addons']);
            } elseif ($type == 'wallet-payments' || $type == 'wallet-payment') {
                $transactionsQuery->where('type', 'wallet-payment');
            } elseif ($type == 'wallet-withdraws' || $type == 'wallet-withdraw') {
                $transactionsQuery->where('type', 'wallet-withdraw');
            } else {
                $transactionsQuery->where('type', $type);
            }
        }

        $transactions = $transactionsQuery->latest()->get();

        return Responder::success([
            'balance' => number_format($balance, 2),
            'transactions' => TransactionResource::collection($transactions),
        ]);
    }

    public function createWithdrawRequest(CreateWithdrawRequest $request)
    {
        $data = $request->validated();
        $this->transactionService->createWithdrawRequest($data);
        return Responder::success([], ['message' => __('apis.success')]);
    }

    /**
     * Create wallet recharge request with MyFatoorah
     */
    public function createRechargeRequest(CreateWalletRechargeRequest $request)
    {
        $data = $request->validated();
        $user = auth()->user();

        // Pass API callback URLs for MyFatoorah payment
        $options = [
            'callback_url' => route('payment.success'),
            'error_url' => route('api.payment.error'),
        ];

        $result = $this->walletRechargeService->initializeWalletRecharge(
            $user,
            $data['amount'],
            $data['gateway'] ?? 'myfatoorah',
            $options
        );

        if ($result['success']) {
            return Responder::success([
                'payment_url' => $result['payment_url'],
                'invoice_id' => $result['invoice_id'],
                'transaction_id' => $result['transaction_id'],
            ], ['message' => __('apis.payment_initialized_successfully')]);
        }

        return Responder::error($result['message']);
    }
}
