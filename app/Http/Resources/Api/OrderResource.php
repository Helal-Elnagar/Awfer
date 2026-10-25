<?php

namespace App\Http\Resources\Api;

use App\Http\Resources\AddressResource;
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
        return [
            'id' => $this->id,
            'order_num' => $this->order_num,
            'address' => $this->whenLoaded('address' , [
                'id' => $this->address?->id,
                'address' => $this->address?->address,
                'alternative_phone' => $this->address?->phone,
                'is_default' => $this->address?->is_default,
                'city' => $this->address?->city->name,
            ]),
            'payment_method' => [
                'id' => $this->paymentMethod->id,
                'name' => $this->paymentMethod->name,
            ],
            'delivery_period' => $this->deliveryPeriod ? [
                'id'          => $this->deliveryPeriod->id,
                'name'        => $this->deliveryPeriod->name,
                'description' => $this->deliveryPeriod->description,
            ] : null,
            'coupon_amount' => money($this->coupon_amount),
            'total_qty' => (int)$this->items()->sum('quantity'),
            'total_products' => money($this->total_products),
            'vat_amount' => money($this->vat_amount),
            'final_total' => money($this->final_total),
            'status_text' => $this->status_text,
            'notes' => $this->notes,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'date' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
