<?php

namespace App\Http\Resources\Api\Order;

use App\Enums\OrderStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // status = canonical internal key; status_enum = translated label.
        $orderStatus = OrderStatus::fromStored($this->status);

        return [
            'id'            => $this->id,
            'order_number'  => $this->formatOrderNumber($this->order_number),
            'status'        => $orderStatus ? trim($orderStatus->label()) : $this->status,
            'status_enum'   => $orderStatus?->value ?? $this->status,
            'payment_info'   => [
                'payment_method' => $this->paymentMethod?->name,
                'status'         => $this->payment_status,
                'payment_date'   => $this->payment_date ? $this->payment_date->format('Y-m-d H:i:s') : null,
                'invoice_number' => $this->invoice_number,
                'payment_link'   => $this->payment_url,
            ],
            'date'          => $this->created_at->format('Y-m-d h:i:s'),
            'total'         => money($this->total),
            'delivery_period' => $this->whenLoaded('deliveryPeriod', function () {
                return [
                    'id'          => $this->deliveryPeriod->id,
                    'name'        => $this->deliveryPeriod->name,
                    'description' => $this->deliveryPeriod->description,
                ];
            }),
        ];
    }

    /**
     * Format order number for shorter display.
     * Examples:
     *  - ORD-1761737250-2302 => ORD-2302
     *  - other formats remain unchanged
     */
    private function formatOrderNumber(?string $orderNumber): ?string
    {
        if (empty($orderNumber)) {
            return $orderNumber;
        }

        $parts = explode('-', $orderNumber);
        if (count($parts) >= 3 && strtoupper($parts[0]) === 'ORD') {
            $last = end($parts);
            return 'ORD-' . $last;
        }

        return $orderNumber;
    }
}
