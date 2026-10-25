<?php

namespace App\Http\Resources\Api;

use App\Services\Order\OrderCalculationService;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray($request)
    {
        $summary = OrderCalculationService::computeCartSummary($this->resource);

        $couponPercent = null;
        if (!empty($this->coupon_code)) {
            $giftCode = \App\Models\GiftCode::with('gift.level')
                ->where('gift_code', $this->coupon_code)
                ->where('user_id', $this->user_id)
                ->first();
            if ($giftCode) {
                $couponPercent = (float) ($giftCode->gift?->level?->percentage ?? 0);
            } elseif ($this->coupon) {
                if ($this->coupon->type === 'ratio') {
                    $couponPercent = (float) ($this->coupon->discount ?? 0);
                }
            }
        }

        $freeDeliveryMinimum = (float) getSiteSetting('total_for_free_delviery', 0);
        $isFreeDelivery = ($this->items?->count() ?? 0) > 0 && $freeDeliveryMinimum > 0 && (float) ($this->subtotal ?? 0) >= $freeDeliveryMinimum;

        return [
            'id' => $this->id,

            'products' => $this->items->map(function ($item) {
                $formatted = OrderCalculationService::formatOrderItem($item);
                return [
                    'id'                  => $item->product->id,
                    'name'                => $item->product->name,
                    'brand_name'          => $item->product->brand?->name,
                    'image'               => $item->product->image_url ?? null,
                    'price'               => $formatted['price'],
                    'discount'            => $formatted['discount'],
                    'price_after_discount' => $formatted['price_after_discount'],
                    'quantity'            => (int) $item->quantity,
                    'total'               => $formatted['total'],
                ];
            }),

            'cost_details' => [
                'items_count'                   => $this->items ? $this->items->sum('quantity') : 0,
                'products_total_without_vat'    => $summary['products_total_without_vat'],
                'subtotal'                      => $summary['subtotal'],
                'coupon_code'                   => $this->coupon_code,
                'coupon_value'                  => $couponPercent,
                'coupon_amount'                 => $summary['coupon_amount'],
                'coupon_type'                   => $this->coupon?->type ?? null,
                'discount'                      => $summary['discount'],
                'loyalty_points_used'           => $summary['loyalty_points_used'],
                'loyalty_points_value'          => $summary['loyalty_points_value'],
                'products_total_after_discount' => $summary['products_total_after_discount'],
                'vat_amount'                    => $summary['vat_amount'],
                'wallet_deduction'              => $summary['wallet_deduction'],
                'vat_percent'                   => $summary['vat_percent'],
                'total_with_vat'                => $summary['total_with_vat'],
                'total_without_vat'             => $summary['total_without_vat'],
                'delivery_fee'                  => $summary['delivery_fee'],
                'is_free_delivery'              => $isFreeDelivery,
                'earned_points'                 => $summary['earned_points'],
                'total'                         => $summary['total'],
            ],
        ];
    }
}
