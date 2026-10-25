<?php

namespace App\Http\Requests\Api\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowed = array_diff(
            array_column(OrderStatus::cases(), 'value'),
            [OrderStatus::CANCELLED->value]
        );

        return [
            'status' => 'required|in:' . implode(',', $allowed),
        ];
    }
}
