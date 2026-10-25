<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use App\Models\Coupon;
use App\Models\GiftCode;
use App\Repositories\CartRepository;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Create a new CartService instance.
     *
     * @param CartRepository $repository
     * @param TransactionService $transactionService
     */
    public function __construct(
        protected CartRepository $repository,
        protected TransactionService $transactionService
    ) {
    }
    /**
     * Get or create a cart for a user
     *
     * @param User $user
     * @return Cart
     */
    public function getCart($user)
    {
        return $this->repository->getCart($user);
    }

    /**
     * Add an item to the cart
     *
     * @param User $user
     * @param array $data
     * @return Cart
     */
    public function addToCart($user, $data)
    {
        $cart = $this->getCart($user);
        
        $this->repository->findOrCreateCartItem($cart, $data);
        $this->updateCartItemsTotals($cart);
        $this->calculateTotals($cart);
        
        return $cart->fresh('items.product');
    }

    /**
     * Update a cart item
     *
     * @param User $user
     * @param array $data
     * @return Cart
     */
    public function updateCartItem($user, $data)
    {
        $cart = $this->getCart($user);
        
        $this->repository->updateCartItem($cart, $data);
        
        $this->updateCartItemsTotals($cart);
        $this->calculateTotals($cart);
        
        return $cart->fresh('items.product');
    }

    /**
     * Remove an item from the cart
     *
     * @param User $user
     * @param int $itemId
     * @return Cart
     */
    public function removeFromCart($user, $itemId)
    {
        $cart = $this->getCart($user);

        $this->repository->removeFromCart($cart, $itemId);

        // Refresh cart to get updated items count
        $cart->refresh();

        // If cart is now empty, reset all values (coupon, wallet, discounts, VAT)
        if ($cart->items->count() === 0) {
            // Refund wallet deduction if any
            $walletAmount = (int) ($cart->wallet_deduction ?? 0);
            if ($walletAmount > 0) {
                $user->increment('wallet_balance', $walletAmount);
            }

            // Reset all cart values
            $cart->update([
                'subtotal' => 0,
                'total' => 0,
                'discount' => 0,
                'coupon_code' => null,
                'coupon_value' => 0,
                'wallet_deduction' => 0,
                'vat_amount' => 0,
            ]);
        } else {
            // Cart still has items, recalculate totals normally
            $this->updateCartItemsTotals($cart);
            $this->calculateTotals($cart);
        }

        return $cart->fresh('items.product');
    }

    /**
     * Clear all items from the cart
     *
     * @param User $user
     * @return bool
     */
    public function clearCart($user)
    {
        $cart = $this->getCart($user);
        return $this->repository->clearCart($cart);
    }

    /**
     * Apply a coupon to the cart
     *
     * @param User $user
     * @param string $code
     * @return Cart
     */
    public function applyCoupon(User $user, $code)
    {
        $cart = $this->getCart($user);
        $this->updateCartItemsTotals($cart);

        if (!empty($cart->coupon_code)) {
            if ($cart->coupon_code === $code) {
                return $cart->fresh('items.product');
            }
            $this->repository->removeCouponFromCart($cart);
        }

        $coupon = Coupon::query()
            ->where('coupon_num', $code)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expire_date')
                    ->orWhere('expire_date', '>=', now());
            })
            ->first();

        if ($coupon) {
            if (!$coupon->canBeUsedByUser($user->id)) {
                throw new \Exception(__('cart.invalid_coupon_code'));
            }

            $usagePerUser = (int) ($coupon->usage_time ?? 0);
            if ($usagePerUser > 0) {
                $currentUsed = (int) ($coupon->users()
                    ->where('users.id', $user->id)
                    ->first()?->pivot?->used_times ?? 0);

                if ($currentUsed >= $usagePerUser) {
                    throw new \Exception(__('cart.coupon_not_valid_or_usage_limit_reached'));
                }
            }

            $couponValue = $this->calculateCouponDiscount($coupon, (float) $cart->items->sum('total'));
            $this->repository->applyCouponToCart($cart, $code, $couponValue);
            $this->calculateTotals($cart);

            return $cart->fresh('items.product');
        }

        $giftCode = GiftCode::where('gift_code', $code)
            ->where('user_id', $user->id)
            ->first();

        if (!$giftCode) {
            throw new \Exception(__('cart.invalid_coupon_code'));
        }

        if ($giftCode->isExpired()) {
            throw new \Exception(__('cart.coupon_expired'));
        }

        if ($giftCode->isUsed() || (bool) $giftCode->is_finished) {
            throw new \Exception(__('cart.coupon_already_used'));
        }

        $percentage = (float) ($giftCode->gift?->level?->percentage ?? 0);
        $couponValue = $this->calculatePercentageDiscount($cart, $percentage);

        $this->repository->applyCouponToCart($cart, $code, $couponValue);
        $this->calculateTotals($cart);

        return $cart->fresh('items.product');
    }

    /**
     * Apply loyalty points to the cart
     *
     * @param User $user
     * @param int $points
     * @return Cart
     */
    public function applyLoyaltyPoints(User $user, int $points)
    {
        return $this->repository->transaction(function () use ($user, $points) {
            $cart = $this->getCart($user);

            if ($cart->items()->count() === 0) {
                throw new \Exception(__('cart.cart_is_empty'));
            }

            // get loyalty points settings
            $appInfo = $this->repository->getAppSettings();
            $settings = SettingService::appInformations($appInfo);

            // check if loyalty points are enabled
            if (!$settings['loyalty_points_enabled']) {
                throw new \Exception(__('cart.loyalty_points_system_disabled'));
            }

            // check minimum points requirement
            $minRedeem = $settings['loyalty_points_min_redeem'] ?? 10;
            if ($points < $minRedeem) {
                throw new \Exception(__('cart.minimum_points_required', ['points' => $minRedeem]));
            }

            // check if user has enough points
            if ($user->loyalty_points < $points) {
                throw new \Exception(__('cart.insufficient_loyalty_points'));
            }

            // calculate maximum points that can be used (based on percentage)
            $maxPercentage = $settings['loyalty_points_max_redeem_percentage'] ?? 50;
            $cartTotal = max(0, (float) $cart->subtotal - (float) ($cart->coupon_value ?? 0) - (float) ($cart->wallet_deduction ?? 0));
            $maxPointsValue = ($cartTotal * $maxPercentage) / 100;

            // convert points to value
            $redeemRate = $settings['loyalty_points_redeem_rate'] ?? 1;
            $pointsValue = $points * $redeemRate;

            if ($pointsValue > $maxPointsValue) {
                $maxPoints = floor($maxPointsValue / $redeemRate);
                throw new \Exception(__('cart.cannot_use_more_than_max_points', [
                    'max_points' => $maxPoints,
                    'percentage' => $maxPercentage
                ]));
            }

            $this->repository->applyLoyaltyPointsToCart($cart, $points, $pointsValue);
            $this->updateCartItemsTotals($cart);
            $this->calculateTotals($cart);

            return $cart->load(['items.item']);
        });
    }

    /**
     * Apply wallet deduction to the cart
     *
     * @param User $user
     * @param int $amount
     * @return void
     */
    public function applyWalletDeduction(User $user, int $amount)
    {
        // Validate wallet balance
        $wallet = $user->wallet_balance;
        if ($wallet < $amount) {
            throw new \Exception(__('cart.insufficient_wallet_balance'));
        }

        // Validate amount does not exceed allowed maximum (subtotal minus coupon)
        $cart = $this->getCart($user);
        $this->updateCartItemsTotals($cart); // ensure item totals are up to date
        $subtotal = (float) $cart->items->sum('total');
        $coupon   = (float) ($cart->coupon_value ?? 0);
        $loyalty = (float) ($cart->loyalty_points_value ?? 0);
        $maxAllowed = max(0, $subtotal - $coupon - $loyalty);
        if ($amount > $maxAllowed) {
            throw new \Exception(__('cart.maximum_wallet_deduction_exceeded', ['amount' => number_format($maxAllowed, 2)]));
        }
        
        // Deduct from user wallet and apply to cart
        $this->repository->decrementUserWalletBalance($user, $amount);
        $this->repository->applyWalletDeductionToCart($cart, $amount);
        
        // Recalculate cart totals from server
        $this->updateCartItemsTotals($cart);
        $this->calculateTotals($cart);
        return $cart->fresh('items.product');
    }

    /**
     * Remove coupon from the cart
     *
     * @param User $user
     * @return Cart
     */
 public function removeCoupon($user)
{
    $cart = $this->getCart($user);
    if (empty($cart->coupon_code)) {
        return $cart->fresh('items.product');
    }
    $this->repository->removeCouponFromCart($cart);
    $this->updateCartItemsTotals($cart);
    $this->calculateTotals($cart);
    return $cart->fresh('items.product');
}


    /**
     * Remove wallet deduction from the cart and refund to user's wallet
     *
     * @param User $user
     * @return Cart
     */
    public function removeWalletDeduction(User $user)
    {
        $cart = $this->getCart($user);
        $walletAmount = (int) ($cart->wallet_deduction ?? 0);

        if ($walletAmount > 0) {
            // Refund the wallet deduction back to user's wallet
            $user->increment('wallet_balance', $walletAmount);

            // Remove wallet deduction from cart
            $cart->update(['wallet_deduction' => 0]);

            // Recalculate cart totals
            $this->updateCartItemsTotals($cart);
            $this->calculateTotals($cart);
        }

        return $cart->fresh('items.product');
    }


    /**
     * Calculate cart totals
     *
     * @param Cart $cart
     * @return void
     */
    public function calculateTotals($cart)
    {
        // 1) Subtotal (VAT-inclusive product prices)
        $subtotal = $cart->items->sum('total');

        // Discounts
        $couponDiscount   = $cart->coupon_value ?? 0;
        $loyaltyDiscount  = $cart->loyalty_points_value ?? 0;
        $walletDiscount   = $cart->wallet_deduction ?? 0;

        $totalDiscount = $couponDiscount + $walletDiscount + $loyaltyDiscount;

        // Settings
        $appSettings = $this->repository->getAppSettings();

        /**
         * VAT
         */
        $vatSetting = isset($appSettings['vat_amount']) ? (float) $appSettings['vat_amount'] : 15.0;
        $vatRate = $vatSetting > 1 ? ($vatSetting / 100) : $vatSetting;
        $vatRate = max(0, $vatRate);

        // Calculate VAT from subtotal (which is already VAT-inclusive)
        $vatFromSubtotal = $vatRate > 0
            ? round($subtotal - ($subtotal / (1 + $vatRate)), 2)
            : 0.0;

        // Amount after discounts (still VAT-inclusive)
        $amountAfterDiscounts = max(0, $subtotal - $totalDiscount);

        // The Cart does not calculate the final delivery fee.
        // It is always 0 here, regardless of the global Settings delivery fee,
        // and the actual fee is determined from the selected City when the
        // Order is created.
        $deliveryFee = 0;

        $deliveryFee = round($deliveryFee, 2);

        // Extract VAT portion from final amount after discounts
        $vatAmount = $vatRate > 0
            ? round($amountAfterDiscounts - ($amountAfterDiscounts / (1 + $vatRate)), 2)
            : 0.0;

        // TOTAL: products (already include VAT) + delivery fee
        $total = round($amountAfterDiscounts + $deliveryFee, 2);

        // Update DB
        $this->repository->updateCartTotals($cart, $subtotal, $totalDiscount, $total, $vatAmount, $deliveryFee);
    }






    /**
     * Calculate coupon discount
     *
     * @param Cart $cart
     * @param Coupon $coupon
     * @return float
     */
    private function calculatePercentageDiscount($cart, $percentage)
    {
        $subtotal = $cart->items->sum('total');
        $discount = ($subtotal * max(0, (float) $percentage)) / 100;
        $discount = min($discount, $subtotal);
        return round($discount, 2);
    }

    private function calculateCouponDiscount(Coupon $coupon, float $subtotal): float
    {
        if ($coupon->type === 'ratio') {
            $discount = ($subtotal * (float) $coupon->discount) / 100;

            if ($coupon->max_discount) {
                $discount = min($discount, (float) $coupon->max_discount);
            }
        } elseif ($coupon->type === 'number') {
            $discount = (float) $coupon->discount;
        } else {
            $discount = 0;
        }

        return round(max(0, min($discount, $subtotal)), 2);
    }

    /**
     * Update cart item totals
     *
     * @param Cart $cart
     * @return void
     */
    public function updateCartItemsTotals($cart)
    {
        foreach ($cart->items as $item) {
            $product = $item->product;
            $weightOption = $item->weightOption;
            $cuttingOption = $item->cuttingOption;
            $packagingOption = $item->packagingOption;

            $basePrice = $product->price_after_discount ?? $product->base_price ?? 0;
            $weightPrice = $weightOption ? ($weightOption->additional_price ?? 0) : 0;
            $cuttingPrice = $cuttingOption ? ($cuttingOption->additional_price ?? 0) : 0;
            $packagingPrice = $packagingOption ? ($packagingOption->additional_price ?? 0) : 0;

            // per-unit calculations: include option fees per unit, then multiply by quantity
            $discountPerUnit = ($product->discount_percentage / 100 * $basePrice) ?? 0;
            $optionsPerUnit = $weightPrice + $cuttingPrice + $packagingPrice;

            // unit price shown/stored should include options
            $unitPriceWithOptions = $basePrice + $optionsPerUnit;

            // total = (unit price including options - discount per unit) * quantity
            $total = ($unitPriceWithOptions - $discountPerUnit) * $item->quantity;

            // round for monetary values
            $unitPriceWithOptions = round($unitPriceWithOptions, 2);
            $discountPerUnit = round($discountPerUnit, 2);
            $total = round($total, 2);

            $this->repository->updateCartItemTotals($item, $unitPriceWithOptions, $discountPerUnit, $total);
        }
    }
}
