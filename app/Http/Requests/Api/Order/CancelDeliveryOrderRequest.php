<?php

namespace App\Http\Requests\Api\Order;

use App\Http\Requests\Api\BaseApiRequest;

class CancelDeliveryOrderRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'cancel_reason' => 'required|string|max:500',
        ];
    }
}
