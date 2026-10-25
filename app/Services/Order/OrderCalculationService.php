<?php

namespace App\Services\Order;

/**
 * OrderCalculationService
 *
 * Centralized order summary calculations used across all applications
 * (Client API, Delivery API, Admin, Website, Invoices, Mobile).
 *
 * Every module that needs to display or return an order summary must use
 * this service to guarantee identical results and 2-decimal formatting.
 */
class OrderCalculationService
{
    /**
     * Resolve the VAT rate from site settings.
     *
     * @return float VAT rate as a decimal (e.g. 0.15 for 15%)
     */
    public static function getVatRate(): float
    {
        $vatPercent = (float) (\App\Models\SiteSetting::where('key', 'vat_amount')->value('value') ?? 0);
        $rate = $vatPercent > 1 ? ($vatPercent / 100) : $vatPercent;
        return max(0, $rate);
    }

    /**
     * Resolve the VAT percentage (display value, e.g. 15 for 15%).
     */
    public static function getVatPercent(): float
    {
        return (float) (\App\Models\SiteSetting::where('key', 'vat_amount')->value('value') ?? 0);
    }

    /**
     * Extract VAT amount from a VAT-inclusive subtotal.
     *
     * @param float $vatInclusiveSubtotal  Subtotal that already includes VAT
     * @param float|null $vatRate          Optional override (uses site setting if null)
     * @return float
     */
    public static function extractVat(float $vatInclusiveSubtotal, ?float $vatRate = null): float
    {
        $rate = $vatRate ?? static::getVatRate();
        if ($rate <= 0) {
            return 0.0;
        }
        return money($vatInclusiveSubtotal - ($vatInclusiveSubtotal / (1 + $rate)));
    }

    /**
     * Compute the full order summary from stored order data.
     *
     * This produces the canonical cost_details block used in:
     * - CartResource (live cart)
     * - OrderDetailsResource (order detail view)
     * - Invoice blades
     * - Admin order views
     *
     * @param object $order  An Order model (or any object with the monetary attributes)
     * @return array Canonical cost_details with every value formatted to 2 decimals
     */
    public static function computeOrderSummary(object $order): array
    {
        $vatPercent = static::getVatPercent();
        $vatRate    = static::getVatRate();

        $subtotal              = money($order->subtotal ?? 0);
        $discountAmount        = money($order->discount_amount ?? 0);
        $couponAmount          = money($order->coupon_amount ?? 0);
        $walletDeduction       = money($order->wallet_deduction ?? 0);
        $loyaltyDeduction      = money($order->loyalty_deduction ?? 0);
        $loyaltyPointsUsed     = (int) ($order->loyalty_points_used ?? 0);
        $deliveryFee           = money($order->delivery_fee ?? 0);
        $vatAmount             = money($order->vat_amount ?? 0);
        $total                 = money($order->total ?? 0);

        // Products total after all discounts (still VAT-inclusive)
        $productsTotalAfterDiscount = money($subtotal - $discountAmount);

        // Subtotal without VAT = products_after_discount - VAT
        $subtotalWithoutVat = money($productsTotalAfterDiscount - $vatAmount);

        // Total with VAT = products_after_discount + delivery - wallet
        $totalWithVat = money($productsTotalAfterDiscount + $deliveryFee - $walletDeduction);

        // Total without VAT
        $totalWithoutVat = money($totalWithVat - $vatAmount);

        return [
            'subtotal'                      => $subtotal,
            'discount'                      => $discountAmount,
            'coupon_amount'                 => $couponAmount,
            'products_total_after_discount' => $productsTotalAfterDiscount,
            'products_total_without_vat'    => $subtotalWithoutVat,
            'vat_percent'                   => $vatPercent,
            'vat_amount'                    => $vatAmount,
            'delivery_fee'                  => $deliveryFee,
            'wallet_deduction'              => $walletDeduction,
            'loyalty_points_used'           => $loyaltyPointsUsed,
            'loyalty_points_value'          => $loyaltyDeduction,
            'total_with_vat'                => $totalWithVat,
            'total_without_vat'             => $totalWithoutVat,
            'total'                         => $total,
        ];
    }

    /**
     * Compute the live cart summary (used by CartResource).
     *
     * @param object $cart  A Cart model with items
     * @return array Canonical cost_details
     */
    public static function computeCartSummary(object $cart): array
    {
        $vatPercent = static::getVatPercent();
        $vatRate    = static::getVatRate();

        $subtotal       = money($cart->subtotal ?? 0);
        $discountTotal  = money($cart->discount ?? 0);
        $couponValue    = money($cart->coupon_value ?? 0);
        $loyaltyValue   = money($cart->loyalty_points_value ?? 0);
        $walletDeduction = money($cart->wallet_deduction ?? 0);
        $vatAmount      = money($cart->vat_amount ?? 0);
        $deliveryFee    = money($cart->delivery_fee ?? 0);

        $productsTotalAfterDiscount = money($subtotal - $discountTotal);
        $subtotalWithoutVat         = money($productsTotalAfterDiscount - $vatAmount);
        $totalWithVat               = money($productsTotalAfterDiscount + $deliveryFee);
        $totalWithoutVat            = money($totalWithVat - $vatAmount);

        $earnRate    = (float) (\App\Models\SiteSetting::where('key', 'loyalty_points_earn_rate')->value('value') ?? 0);
        $actualCashPaid = max(0, $productsTotalAfterDiscount + $deliveryFee - $loyaltyValue);
        $earnedPoints  = (int) floor($actualCashPaid * $earnRate);

        return [
            'subtotal'                      => $subtotal,
            'discount'                      => $discountTotal,
            'coupon_amount'                 => $couponValue,
            'products_total_after_discount' => $productsTotalAfterDiscount,
            'products_total_without_vat'    => $subtotalWithoutVat,
            'vat_percent'                   => $vatPercent,
            'vat_amount'                    => $vatAmount,
            'delivery_fee'                  => $deliveryFee,
            'wallet_deduction'              => $walletDeduction,
            'loyalty_points_used'           => (int) ($cart->loyalty_points_used ?? 0),
            'loyalty_points_value'          => $loyaltyValue,
            'total_with_vat'                => $totalWithVat,
            'total_without_vat'             => $totalWithoutVat,
            'total'                         => money($cart->total ?? 0),
            'earned_points'                 => $earnedPoints,
        ];
    }

    /**
     * Format a single order item's monetary fields.
     */
    public static function formatOrderItem(object $item): array
    {
        return [
            'price'               => money($item->price ?? 0),
            'discount'            => money($item->discount_amount ?? 0),
            'price_after_discount' => money(($item->price ?? 0) - ($item->discount_amount ?? 0)),
            'total'               => money($item->total ?? 0),
        ];
    }

    /**
     * Format a refund amount.
     */
    public static function formatRefundAmount(object $order): float
    {
        if (!empty($order->refundable)) {
            return money($order->refund_amount ?? 0);
        }
        return money($order->total ?? 0);
    }
}
