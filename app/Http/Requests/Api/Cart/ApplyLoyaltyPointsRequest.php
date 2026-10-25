<?php

namespace App\Http\Requests\Api\Cart;

use App\Http\Requests\Api\BaseApiRequest;

class ApplyLoyaltyPointsRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'points' => 'required|integer|min:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'points.required' => __('cart.points_amount_required'),
            'points.integer' => __('cart.points_must_be_valid_number'),
            'points.min' => __('cart.points_must_be_at_least_1'),
        ];
    }
}
