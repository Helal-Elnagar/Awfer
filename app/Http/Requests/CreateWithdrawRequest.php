<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWithdrawRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = auth()->user();

        // Align with UI: withdrawable = current wallet balance minus pending withdraw requests
        $withdrawableBalance = 0;
        if ($user) {
            $balance = $user->wallet_balance;
            $pendingWithdrawSum = \App\Models\Transaction::where('user_id', $user->id)
                ->where('type', 'wallet-withdraw')
                ->where('status', 'pending')
                ->sum('amount');
            $withdrawableBalance = max($balance - $pendingWithdrawSum, 0);
        }

        // Check minimum withdraw amount from settings
        $minimumWithdraw = (float) (\App\Models\SiteSetting::where('key', 'minimum_withdraw_amount')->value('value') ?? 0);

        $rules = [
            'amount' => [
                'required',
                'numeric',
                'min:' . max($minimumWithdraw, 1),
                'max:' . $withdrawableBalance,
            ],
            'bank_name'           => ['required', 'string', 'max:255'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'account_number'      => [
                'required_without:iban',
                'digits_between:10,18', // Saudi bank accounts usually 10-18 digits
                'regex:/^[0-9]+$/',    // Only digits
            ],
            'iban'                => [
                'required_without:account_number',
                'string',
                'regex:/^SA[0-9]{2}[A-Z0-9]{20}$/', // Saudi IBAN format (24 chars, no spaces or hyphens)
            ],
            'transfer_reference'  => ['nullable', 'string', 'max:255'],
            'note' => 'nullable|string|max:255',
        ];

        // If wallet balance is below minimum, reject entirely
        if ($user && $user->wallet_balance < $minimumWithdraw && $minimumWithdraw > 0) {
            $rules['amount'][] = 'gte:0'; // will always fail with custom message below
        }

        return $rules;
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        $user = auth()->user();

        // Use same logic as rules() for max amount message
        $withdrawableBalance = 0;
        if ($user) {
            $balance = $user->wallet_balance;
            $pendingWithdrawSum = \App\Models\Transaction::where('user_id', $user->id)
                ->where('type', 'wallet-withdraw')
                ->where('status', 'pending')
                ->sum('amount');
            $withdrawableBalance = max($balance - $pendingWithdrawSum, 0);
        }

        $minimumWithdraw = (float) (\App\Models\SiteSetting::where('key', 'minimum_withdraw_amount')->value('value') ?? 0);

        $messages = [
            'amount.required' => __('site.amount_required'),
            'amount.numeric' => __('validation.numeric', ['attribute' => __('site.amount')]),
            'amount.min' => __('site.min_withdraw_amount') . ($minimumWithdraw > 0 ? ': ' . number_format($minimumWithdraw, 2) : ''),
            'amount.max' => __('site.max_withdraw_amount', ['amount' => number_format($withdrawableBalance, 2)]),
            'bank_name.required' => __('site.bank_name_required'),
            'account_holder_name.required' => __('site.account_holder_name_required'),
            'account_number.required_without' => __('site.account_or_iban_required'),
            'account_number.digits_between' => __('site.account_number_digits'),
            'account_number.regex' => __('validation.regex', ['attribute' => __('site.account_number')]),
            'iban.required_without' => __('site.iban_or_account_required'),
            'iban.regex' => __('site.iban_format_error'),
        ];

        // Add wallet-below-minimum message if applicable
        if ($user && $user->wallet_balance < $minimumWithdraw && $minimumWithdraw > 0) {
            $messages['amount.gte'] = __('site.below_minimum_withdraw', ['amount' => number_format($minimumWithdraw, 2)]);
        }

        return $messages;
    }
}
