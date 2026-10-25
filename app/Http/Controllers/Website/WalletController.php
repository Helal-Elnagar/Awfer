<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Services\Website\MyFatoorahService;
use App\Http\Requests\CreateWithdrawRequest;
use App\Http\Requests\CreateWalletRechargeRequest;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
        protected MyFatoorahService $myFatoorahService
    ) {}

    /**
     * Display wallet page with transactions
     */
    public function index()
    {
        $user = Auth::guard('web')->user();
        
        // Get wallet balance
        $balance = $user->wallet_balance;
        
        // Get all transactions
        $allTransactions = Transaction::where('user_id', $user->id)
            ->latest()
            ->get();
        
        // Filter transactions by type
        $paymentTransactions = Transaction::where('user_id', $user->id)
            ->where('type', 'wallet-payment')
            ->latest()
            ->get();
            
        $addonTransactions = Transaction::where('user_id', $user->id)
            ->whereIn('type', ['wallet-addons', 'wallet-recharge'])
            ->latest()
            ->get();
            
        $withdrawTransactions = Transaction::where('user_id', $user->id)
            ->where('type', 'wallet-withdraw')
            ->latest()
            ->get();
        
        // New logic per product requirement:
        // - Withdrawable balance: current wallet available for new withdraw requests
        //   equals wallet_balance minus any amounts already requested and pending approval.
        // - Non-withdrawable balance: sum of pending withdraw requests awaiting admin approval.
        // - Total balance card displays wallet_balance, which equals (withdrawable + pending).

        $pendingWithdrawSum = Transaction::where('user_id', $user->id)
            ->where('type', 'wallet-withdraw')
            ->where('status', 'pending')
            ->sum('amount');

        $withdrawableBalance = max($balance - $pendingWithdrawSum, 0);
        $nonWithdrawableBalance = max($pendingWithdrawSum, 0);
        
        return view('website.pages.account.wallet', compact(
            'balance',
            'withdrawableBalance',
            'nonWithdrawableBalance',
            'paymentTransactions',
            'addonTransactions',
            'withdrawTransactions'
        ));
    }

    /**
     * Create withdraw request
     */
    public function createWithdrawRequest(CreateWithdrawRequest $request)
    {
        $data = $request->validated();

        try {
            $this->transactionService->createWithdrawRequest($data);

            return redirect()->route('website.wallet.index')
                ->with('success', __('site.withdraw_request_success'));
        } catch (\Exception $e) {
            return redirect()->route('website.wallet.index')
                ->with('error', __('site.withdraw_request_error'));
        }
    }

    /**
     * Create wallet recharge request with MyFatoorah
     */
    public function createRechargeRequest(CreateWalletRechargeRequest $request)
    {
        $data = $request->validated();
        $user = Auth::guard('web')->user();

        try {
            // Create pending transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'amount' => $data['amount'],
                'type' => 'wallet-addons',
                'status' => 'pending',
                'reference' => 'pending',
                'note' => json_encode(value: ['ar' => 'شحن محفظة', 'en' => 'Wallet Recharge']),
            ]);

            // Create payment with MyFatoorah
            $result = $this->myFatoorahService->createWalletRecharge([
                'amount' => $data['amount'],
                'customer_name' => $user->name,
                'customer_phone' => $user->phone,
                'customer_email' => $user->email,
                'transaction_id' => $transaction->id,
                'gateway' => $data['gateway'] ?? 'visa_master',
            ]);

            if ($result['success']) {
                // Update transaction with payment reference
                $transaction->update([
                    'reference' => $result['invoice_id'],
                ]);

                // Redirect to payment URL
                return redirect($result['payment_url']);
            }

            // Delete the pending transaction if payment creation failed
            $transaction->delete();

            return redirect()->route('website.wallet.index')
                ->with('error', $result['message'] ?? 'حدث خطأ أثناء تهيئة الدفع');
        } catch (\Exception $e) {
            return redirect()->route('website.wallet.index')
                ->with('error', 'حدث خطأ أثناء تهيئة الدفع. يرجى المحاولة مرة أخرى');
        }
    }
}

