<?php

namespace App\Http\Requests\Api\Order;

use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends BaseApiRequest
{
    public function rules()
    {
        $user = auth()->user();

        return [
            'address_id' => [
                'required',
                Rule::exists('addresses', 'id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }),
            ],

            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'schedule_date' => 'required|date|after_or_equal:today',
            // 'schedule_time' => 'required|date_format:H:i',
            'delivery_period_id' => 'required|integer|exists:delivery_periods,id',
        ];
    }

    public function messages(): array
    {
        return [ 
            'address_id.required' => __('admin.address_id_required'),
            'address_id.exists' => __('admin.address_id_exists'),
            'payment_method_id.required' => __('admin.payment_method_id_required'),
            'payment_method_id.exists' => __('admin.payment_method_id_exists'),
            'schedule_date.required' => __('admin.schedule_date_required'),
            'schedule_date.after_or_equal' => __('admin.schedule_date_after_or_equal'),
            'schedule_time.required' => __('admin.schedule_time_required'),
            'schedule_time.date_format' => __('admin.schedule_time_format'),
        ];
    }
}
