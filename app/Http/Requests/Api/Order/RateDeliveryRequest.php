<?php

namespace App\Http\Requests\Api\Order;

use App\Http\Requests\Api\BaseApiRequest;

class RateDeliveryRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'delivery_id' => 'required|exists:users,id',
            'rate' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => __('apis.order_id_required'),
            'order_id.exists' => __('apis.order_not_found'),
            'delivery_id.required' => __('apis.delivery_id_required'),
            'delivery_id.exists' => __('apis.delivery_not_found'),
            'rate.required' => __('apis.rate_required'),
            'rate.integer' => __('apis.rate_must_be_numeric'),
            'rate.min' => __('apis.rate_min_value'),
            'rate.max' => __('apis.rate_max_value'),
            'comment.max' => __('apis.note_max_length'),
        ];
    }
}
