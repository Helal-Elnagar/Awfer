<?php

namespace App\Http\Resources\Api\Client;

use App\Enums\OrderStatus;
use App\Http\Resources\AddressResource;
use App\Http\Resources\OrderRatingResource;
use App\Services\Order\OrderCalculationService;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailsResource extends JsonResource
{


    public function toArray($request)
    {
        $paymentMethod = \App\Models\PaymentMethod::find($this->payment_method_id);
        $deliveryUser  = $this->whenLoaded('deliveryUser') ? $this->deliveryUser : ($this->delivery_id ? $this->deliveryUser()->first() : null);

        // Use shared calculation service for identical results everywhere
        $summary = OrderCalculationService::computeOrderSummary($this->resource);

        $orderStatus = OrderStatus::fromStored($this->status);

        $earnRate = (float) (\App\Models\SiteSetting::where('key', 'loyalty_points_earn_rate')->value('value') ?? 0);
        $earnedPoints = (int) \App\Models\Transaction::where('user_id', $this->user_id)
            ->where('type', 'loyalty_reward')
            ->where('reference', $this->order_number)
            ->value('amount') ?? 0;

        // Resolve coupon data from relation only
        $couponModel = $this->coupon ?? null;
        $couponType   = $couponModel?->type;

        // Align coupon percentage with GiftCode logic like CartResource
        $couponPercent = null;
        if (!empty($this->coupon_code)) {
            $giftCode = \App\Models\GiftCode::with('gift.level')
                ->where('gift_code', $this->coupon_code)
                ->where('user_id', $this->user_id)
                ->first();
            if ($giftCode) {
                $couponPercent = (float) ($giftCode->gift?->level?->percentage ?? 0);
            } elseif ($couponModel && $couponModel->type === 'ratio') {
                $couponPercent = (float) ($couponModel->discount ?? 0);
            }
        }

        return [
            // Order Info
            'order_number'   => $this->formatOrderNumber($this->order_number),
            'client_name'    => $this->user?->name,
            'date'           => $this->created_at->format('Y-m-d h:i:s'),
            'status_enum'    => $orderStatus?->value ?? $this->status,
            'status'         => $orderStatus ? trim($orderStatus->label()) : $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->paymentMethod?->name,
            'payment_info'   => [
                'payment_method' => $this->paymentMethod?->name,
                'status'         => $this->payment_status,
                'payment_date'   => $this->payment_date ? $this->payment_date->format('Y-m-d H:i:s') : null,
                'invoice_number' => $this->invoice_number,
                'payment_link'   => $this->payment_url,
            ],
            'address'        => $this->whenLoaded('address', function () {
                return AddressResource::make($this->address);
            }),

            // Products
            'products'       => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    $formatted = OrderCalculationService::formatOrderItem($item);
                    $product   = $item->product;
                    return [
                        'id'                   => $product?->id,
                        'name'                 => $product?->name,
                        'image'                => $product?->image_url,
                        'price'                => $formatted['price'],
                        'brand_name'           => $product?->brand?->name,
                        'discount'             => $formatted['discount'],
                        'price_after_discount' => $formatted['price_after_discount'],
                        'quantity'             => (int) $item->quantity,
                        'total'                => $formatted['total'],
                        'cart_quantity'        => $product ? (int) $product->cart_quantity : 0,
                    ];
                });
            }),

            'cost_details'   => [
                'items_count'                   => $this->items ? $this->items->sum('quantity') : 0,
                'products_total_without_vat'    => $summary['products_total_without_vat'],
                'subtotal'                      => $summary['subtotal'],
                'coupon_code'                   => $this->coupon_code,
                'coupon_value'                  => $couponPercent,
                'payment_url'                   => $this->payment_url,
                'coupon_amount'                 => money($this->coupon_amount ?? 0),
                'coupon_type'                   => $couponType,
                'discount'                      => $summary['discount'],
                'products_total_after_discount' => $summary['products_total_after_discount'],
                'wallet_deduction'              => $summary['wallet_deduction'],
                'loyalty_points_used'           => $summary['loyalty_points_used'],
                'loyalty_points_value'          => $summary['loyalty_points_value'],
                'vat_amount'                    => $summary['vat_amount'],
                'vat_percent'                   => $summary['vat_percent'],
                'total_with_vat'                => $summary['total_with_vat'],
                'total_without_vat'             => $summary['total_without_vat'],
                'delivery_fee'                  => $summary['delivery_fee'],
                'earned_points'                 => (int) ($this->loyalty_points_earned ?? $earnedPoints),
                'total'                         => $summary['total'],
            ],

            // Scheduled order fields
            'schedule_date'  => isset($this->schedule_date) && $this->schedule_date ? (
                $this->schedule_date instanceof \DateTimeInterface
                ? $this->schedule_date->format('Y-m-d')
                : (($ts = strtotime($this->schedule_date)) ? date('Y-m-d', $ts) : (string) $this->schedule_date)
            ) : null,
            'delivery_period' => $this->whenLoaded('deliveryPeriod', function () {
                return [
                    'id'          => $this->deliveryPeriod->id,
                    'name'        => $this->deliveryPeriod->name,
                    'description' => $this->deliveryPeriod->description,
                ];
            }, function () {
                return $this->deliveryPeriod ? [
                    'id'          => $this->deliveryPeriod->id,
                    'name'        => $this->deliveryPeriod->name,
                    'description' => $this->deliveryPeriod->description,
                ] : null;
            }),
            'delivery_id' => $this->delivery_id,
            'delivery_name' => $deliveryUser?->name,
            'delivery_phone' => $deliveryUser?->phone,
            'delivery_rate'  => $deliveryUser ? $deliveryUser->average_delivery_rate : 0,
            'order_delivery_rate'  => $this->deliveryRating ? [
                'rate'    => (int) $this->deliveryRating->rate,
                'comment' => $this->deliveryRating->comment,
            ] : null,
            'delivery_date' => isset($this->schedule_date) && $this->schedule_date ? (
                $this->schedule_date instanceof \DateTimeInterface
                ? $this->schedule_date->format('Y-m-d')
                : (($ts = strtotime($this->schedule_date)) ? date('Y-m-d', $ts) : (string) $this->schedule_date)
            ) : null,

            'problem'        => (function () {
                if (OrderStatus::fromStored($this->status)?->value !== 'problem') {
                    return null;
                }
                if (! empty($this->notes)) {
                    return $this->notes;
                }
                if ($this->problem_id) {
                    $problem = $this->whenLoaded('problem') ? $this->problem : (\App\Models\Problem::find($this->problem_id));
                    return $problem?->problem ?? null;
                }
                return null;
            })(),

            'cancelReason'   => (function () {
                if (OrderStatus::fromStored($this->status)?->value !== 'cancelled') {
                    return null;
                }
                if ($this->cancel_reason_id) {
                    $cr = $this->whenLoaded('cancelReason') ? $this->cancelReason : (\App\Models\CancelReason::find($this->cancel_reason_id));
                    return $cr?->reason ?? null;
                }
                if (! empty($this->notes)) {
                    return $this->notes;
                }
                return null;
            })(),

            'order_ratings'  => OrderRatingResource::collection($this->rate),

            'can_cancel'     => !empty($this->cancelled_by_delivery_id),

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
