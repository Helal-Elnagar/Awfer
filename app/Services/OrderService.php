<?php

namespace App\Services;

use App\Models\Gift;
use App\Models\GiftCode;
use App\Models\LevelGift;
use App\Models\User;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mpdf\Container\NotFoundException;
use Illuminate\Support\Facades\Schema;

class OrderService
{
    protected $cartService;
    protected $loyaltyPointsService;
    protected $paymentService;
    protected $transactionService;
    protected $workingDayService;

    public function __construct(
        CartService $cartService,
        LoyaltyPointsService $loyaltyPointsService,
        PaymentService $paymentService,
        TransactionService $transactionService,
        WorkingDayService $workingDayService
    ) {
        $this->cartService = $cartService;
        $this->loyaltyPointsService = $loyaltyPointsService;
        $this->paymentService = $paymentService;
        $this->transactionService = $transactionService;
        $this->workingDayService = $workingDayService;
    }

    public function getUserOrders(User $user, array $filters = [])
    {
        $query = $user->orders()->with(['deliveryPeriod']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'latest';
        if ($sortBy === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->get();
    }

    public function getUserOrder(User $user, int $orderId)
    {
        $order = $user->orders()
            ->with(['items.item.brand', 'address', 'problem', 'coupon', 'cancelReason', 'deliveryUser', 'deliveryRating', 'deliveryPeriod'])
            ->find($orderId);
        if (!$order) {
            throw new NotFoundException('Order not found');
        }

        return $order;
    }

    public function createOrderFromCart($user, array $data)
    {
        $orderDateTime = $this->resolveOrderDateTime($data);

        if (!$this->workingDayService->isOrderingOpenAt($orderDateTime)) {
            throw new \Exception(__('apis.order_outside_working_hours'));
        }

        return DB::transaction(function () use ($user, $data) {

            // ========== 1) العمليات الأساسية ==========
            $cart = $this->validateCartAndAddress($user, $data);
            $deliveryDetails = $this->calculateDeliveryDetails($user, $data);
            $order = $this->createOrderFromCartData($user, $cart, $data, $deliveryDetails);

            $order->load(['items.product.brand', 'deliveryPeriod']);

            $paymentResult = $this->processOrderPayment($order, $user, $data, $deliveryDetails);


            // ==================================================
            // ========== 2)   🟩 إضافة نظام الهدايا  ==========
            // ==================================================

            try {

                // (A) حساب نقاط كل الأوردرات السابقة + الحالي
                $userTotalPoints = $user->orders()
                    ->with('items.product')
                    ->get()
                    ->sum(function ($orderItem) {
                        return $orderItem->items->sum(function ($item) {
                            return ($item->product->points ?? 0) * $item->quantity;
                        });
                    });

                // (B) تحديد المستويات اللي حققها اليوزر
                $eligibleLevelIds = LevelGift::where('points_required', '<=', $userTotalPoints)
                    ->pluck('id');

                if ($eligibleLevelIds->isNotEmpty()) {

                    // (C) جلب الهدايا الخاصة بهذه المستويات
                    $eligibleGifts = Gift::whereIn('level_id', $eligibleLevelIds)->get();

                    // (D) معرفة الهدايا اللي اليوزر حصل عليها قبل كده
                    $receivedGifts = GiftCode::where('user_id', $user->id)->pluck('gift_id');

                    // (E) الهدايا الجديدة فقط
                    $newGifts = $eligibleGifts->whereNotIn('id', $receivedGifts);

                    // (F) توليد أكواد للهدايا الجديدة
                    foreach ($newGifts as $gift) {
                        GiftCode::create([
                            'gift_id'    => $gift->id,
                            'user_id'    => $user->id,
                            'gift_code'  => strtoupper(Str::random(12)),
                            'expires_at' => now()->addDays(30),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // لو حصل خطأ في الهدايا مش هيبوظ الأوردر
                Log::error("Gift code generation error: " . $e->getMessage());
            }

            // ==================================================
            // ========== 3)  إعادة تهيئة بيانات الأوردر ==========
            // ==================================================

            if (isset($paymentResult['order'])) {
                $orderArray = $paymentResult['order']->toArray();

                if (!empty($orderArray['items']) && is_array($orderArray['items'])) {
                    foreach ($order->items as $index => $item) {

                        $orderArray['items'][$index]['brand_name'] = $item->product->brand?->name;

                        unset(
                            $orderArray['items'][$index]['weight'],
                            $orderArray['items'][$index]['cutting'],
                            $orderArray['items'][$index]['packaging'],
                            $orderArray['items'][$index]['weight_option_id'],
                            $orderArray['items'][$index]['cutting_option_id'],
                            $orderArray['items'][$index]['packaging_option_id'],
                            $orderArray['items'][$index]['cutting_price'],
                            $orderArray['items'][$index]['packaging_price'],
                            $orderArray['items'][$index]['created_at'],
                            $orderArray['items'][$index]['updated_at']
                        );
                    }
                }

                $paymentResult['order'] = $orderArray;
            }

            return $paymentResult;
        });
    }

    private function resolveOrderDateTime(array $data): Carbon
    {
        if (!empty($data['schedule_date']) && !empty($data['schedule_time'])) {
            return Carbon::parse($data['schedule_date'] . ' ' . $data['schedule_time']);
        }

        return now();
    }



    private function validateCartAndAddress($user, array $data)
    {
        // التحقق من السلة
        $cart = $this->cartService->getCart($user);
        if (!$cart || $cart->items()->count() === 0) {
            throw new \Exception(__('cart.cart_is_empty'));
        }

        // التحقق من العنوان في حالة التوصيل المنزلي
        if (($data['delivery_type'] ?? null) === 'home_delivery') {
            $address = $user->addresses()->find($data['address_id'] ?? null);
            if (!$address) {
                throw new \Exception(__('apis.address_not_found'));
            }
        }

        // التحقق من بيانات الهدايا إذا كان نوع الطلب هدية
        if (($data['order_type'] ?? 'ordinary') === 'gift') {
            $name = trim((string)($data['reciver_name'] ?? ''));
            $phone = preg_replace('/\D+/', '', (string)($data['reciver_phone'] ?? ''));
            $addressName = trim((string)($data['gift_address_name'] ?? ''));

            if (mb_strlen($name) < 3) {
                throw new \Exception(__('site.please_enter_recipient_name_min'));
            }
            if (strlen($phone) !== 9 || !str_starts_with($phone, '5')) {
                throw new \Exception(__('site.invalid_saudi_phone'));
            }
            if ($addressName === '') {
                throw new \Exception(__('site.please_enter_delivery_address_name'));
            }
        }

        return $cart;
    }



    private function calculateDeliveryDetails($user, array $data)
    {
        $deliveryCalculationService = app(\App\Services\Order\DeliveryCalculationService::class);

        // The delivery fee is derived from the City of the selected address,
        // so no comparison against the global Settings delivery fee is needed.
        return $deliveryCalculationService->calculateDeliveryDetails($user, $data);
    }


    private function createOrderFromCartData($user, $cart, array $data, array $deliveryDetails)
    {
        // Free delivery applies when the order subtotal reaches the
        // free-delivery minimum; the City delivery fee is then skipped.
        $freeDeliveryMinimum = (float) getSiteSetting('total_for_free_delviery', 0);
        $cityDeliveryFee = (float) ($deliveryDetails['delivery_fee'] ?? 0);
        $deliveryFee = ($freeDeliveryMinimum > 0 && (float) $cart->subtotal >= $freeDeliveryMinimum)
            ? 0.0
            : $cityDeliveryFee;

        $orderData = [
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $user->id,
            'status' => 'pending',
            'address_id' => $data['address_id'] ?? null,
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'subtotal' => $cart->subtotal,
            'discount_amount' => $cart->discount ?? 0,
            'discount_code' => $cart->coupon_code ?? null,
            'coupon_id' => null,
            'coupon_amount' => $cart->coupon_value ?? 0,
            'delivery_fee' => $deliveryFee,
            'wallet_deduction' => $cart->wallet_deduction ?? 0,
            'loyalty_points_used' => $cart->loyalty_points_used ?? 0,
            'loyalty_deduction' => $cart->loyalty_points_value ?? 0,
            'total' => round((float) $cart->total + $deliveryFee, 2),
            'notes' => $data['notes'] ?? null,
            'vat_amount' => $cart->vat_amount ?? 0,
            'schedule_date' => $data['schedule_date'] ?? null,
            'schedule_time' => $data['schedule_time'] ?? null,
            'delivery_period_id' => $data['delivery_period_id'] ?? null,
        ];


        $order = Order::create($orderData);

        $this->attachCouponToOrder($order, $cart);
        $this->incrementCouponUsageForUser($order, $cart, $user);

        if (!empty($cart->coupon_code)) {
            $giftCode = GiftCode::where('gift_code', $cart->coupon_code)
                ->where('user_id', $user->id)
                ->first();
            if ($giftCode) {
                $giftCode->update([
                    'used_at' => now(),
                    'is_finished' => true,
                ]);
            }
        }
        $this->transferCartItemsToOrder($order, $cart);
        $this->deductProductQuantities($order);
        $this->cartService->clearCart($user);

        return $order;
    }

    private function attachCouponToOrder($order, $cart)
    {
        if (!empty($cart->coupon_code)) {
            $coupon = \App\Models\Coupon::where('coupon_num', $cart->coupon_code)->first();
            if ($coupon && Schema::hasColumn('orders', 'coupon_id')) {
                try {
                    $order->update(['coupon_id' => $coupon->id]);
                } catch (\Exception $e) {
                    Log::warning('Failed to attach coupon_id to order', [
                        'order_id' => $order->id,
                        'coupon_id' => $coupon->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function incrementCouponUsageForUser($order, $cart, $user): void
    {
        if (empty($cart->coupon_code)) {
            return;
        }

        $coupon = \App\Models\Coupon::where('coupon_num', $cart->coupon_code)->first();
        if (!$coupon) {
            return;
        }

        // Safety: if audience is specific and user is not allowed, skip.
        if (!$coupon->canBeUsedByUser($user->id)) {
            return;
        }

        // Per-user coupon usage (coupons_users.used_times)
        $existing = $coupon->users()->where('users.id', $user->id)->first();
        if ($existing) {
            $coupon->users()->updateExistingPivot($user->id, [
                'used_times' => ((int) ($existing->pivot->used_times ?? 0)) + 1,
            ]);
        } elseif (($coupon->audience_type ?? 'all') === 'all') {
            $coupon->users()->attach($user->id, ['used_times' => 1]);
        }
    }

    private function processLoyaltyPointsForOrder(Order $order, $cart, User $user): void
    {
        $appInfo = \App\Models\SiteSetting::pluck('value', 'key')->toArray();
        $settings = SettingService::appInformations($appInfo);

        if (!(bool) ($settings['loyalty_points_enabled'] ?? false)) {
            return;
        }

        if (!Schema::hasColumn('users', 'loyalty_points')) {
            return;
        }

        $pointsUsed = (int) ($cart->loyalty_points_used ?? 0);
        $pointsValueUsed = (float) ($cart->loyalty_points_value ?? 0);

        if ($pointsUsed > 0) {
            $currentPoints = (int) ($user->loyalty_points ?? 0);
            if ($currentPoints < $pointsUsed) {
                throw new \Exception(__('cart.insufficient_loyalty_points'));
            }

            $user->decrement('loyalty_points', $pointsUsed);
            $this->transactionService->createLoyaltyPointsDeductionTransaction($user->id, $pointsUsed);
        }

        $earnRate = (float) ($settings['loyalty_points_earn_rate'] ?? 0);
        if ($earnRate <= 0) {
            return;
        }

        // Points are earned only on the actual paid amount (excluding redeemed loyalty value).
        $actualPaidAmount = max(0, ((float) $order->total) - $pointsValueUsed);
        $pointsEarned = (int) floor($actualPaidAmount * $earnRate);

        if ($pointsEarned > 0) {
            $user->increment('loyalty_points', $pointsEarned);
            $this->transactionService->createLoyaltyPointsRewardTransaction($user->id, $pointsEarned, $order->order_number);
            $order->update(['loyalty_points_earned' => $pointsEarned]);
        }
    }

    private function transferCartItemsToOrder($order, $cart)
    {
        foreach ($cart->items as $cartItem) {
            $order->items()->create([
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
                'weight_option_id' => $cartItem->weight_option_id,
                'cutting_option_id' => $cartItem->cutting_option_id,
                'packaging_option_id' => $cartItem->packaging_option_id,
                'price' => $cartItem->price,
                'discount_amount' => $cartItem->discount_amount,
                'total' => $cartItem->total,
            ]);
        }
    }

    private function processOrderPayment($order, $user, array $data, array $deliveryDetails)
    {
        if (in_array($data['payment_method_id'], [5])) {
            $order->update(['payment_status' => 'pending']);

            if ($order->wallet_deduction > 0) {
                app(\App\Services\TransactionService::class)
                    ->createWalletPaymentTransaction($order->user_id, $order->wallet_deduction, $order);
            }

            $this->sendNewOrderNotificationToAdmins($order->order_number, $order->id);
            DB::commit();

            $earnRate = (float) (\App\Models\SiteSetting::where('key', 'loyalty_points_earn_rate')->value('value') ?? 0);
            $earnedPoints = (int) floor(($order->total - $order->delivery_fee) * $earnRate);

            return [
                'status' => 'success',
                'message' => __('apis.order_created'),
                'earned_points' => $earnedPoints,
                'payment_info' => [
                    'payment_method' => $order->paymentMethod?->name,
                    'status'         => $order->payment_status,
                    'payment_date'   => $order->payment_date ? $order->payment_date->format('Y-m-d H:i:s') : null,
                    'invoice_number' => $order->invoice_number,
                    'payment_link'   => $order->payment_url,
                ],
                'order' => $order->load('items'),
                'delivery_distance_km' => $deliveryDetails['distance'],
                'delivery_fee' => $deliveryDetails['delivery_fee'],
            ];
        }

        $paymentMethod = PaymentMethod::tryFrom($data['payment_method_id']);
        if ($paymentMethod === null) {
            throw new \Exception('Invalid payment method');
        }

        $gateway = $this->paymentService->getPaymentGateway($paymentMethod, $data);
        $paymentResult = $this->paymentService->initializeMyFatoorahPayment($order, $user, [
            'gateway' => $gateway,
            'payment_method' => $paymentMethod->label()
        ]);

        if (isset($paymentResult['status']) && $paymentResult['status'] === 'error') {
            DB::rollBack();
            return $paymentResult;
        }

        $this->sendNewOrderNotificationToAdmins($order->order_number, $order->id);
        DB::commit();

        $earnRate = (float) (\App\Models\SiteSetting::where('key', 'loyalty_points_earn_rate')->value('value') ?? 0);
        $earnedPoints = (int) floor(($order->total - $order->delivery_fee) * $earnRate);

        return [
            'status' => 'success',
            'message' => __('apis.order_created'),
            'earned_points' => $earnedPoints,
            'payment_info' => [
                'payment_method' => $order->paymentMethod?->name,
                'status'         => $order->payment_status,
                'payment_date'   => $order->payment_date ? $order->payment_date->format('Y-m-d H:i:s') : null,
                'invoice_number' => $order->invoice_number,
                'payment_link'   => $order->payment_url,
            ],
            'payment_url' => $paymentResult['invoiceURL'],
            'order' => $order->load('items'),
            'delivery_distance_km' => $deliveryDetails['distance'],
            'delivery_fee' => $deliveryDetails['delivery_fee'],
        ];
    }

    // private function processLoyaltyPoints(Order $order, string $paymentMethod)
    // {
    //     $loyaltyResult = $this->loyaltyPointsService->processOrderLoyaltyPoints(
    //         $order->user,
    //         $order->total,
    //         $order->loyalty_points_used,
    //         $paymentMethod,
    //         $order
    //     );

    //     $order->update([
    //         'loyalty_points_earned' => $loyaltyResult['points_earned'],
    //     ]);
    // }

    protected function sendNewOrderNotificationToAdmins($orderNum, $id): void
    {
        $message = 'يوجد طلب حجز جديد برقم #' . $orderNum;
        $admins = Admin::all();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\NotifyAdmin([
                'title' => ['ar' => 'طلب جديد', 'en' => 'New Order'],
                'body' => ['ar' => $message, 'en' => $message],
                'type' => 'new_order',
                'order_id' => $id
            ]));
        }
    }

    protected function generateOrderNumber()
    {
        return '#' . str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function handlePaymentCallback(Request $request)
    {
        return $this->paymentService->handlePaymentCallback($request);
    }

    private function deductProductQuantities(Order $order)
    {
        foreach ($order->items as $orderItem) {
            if ($orderItem->product_id) {
                $product = \App\Models\Product::find($orderItem->product_id);
                if ($product && $product->quantity >= $orderItem->quantity) {
                    $product->decrement('quantity', $orderItem->quantity);
                }
            }
        }
    }

    private function restoreProductQuantities(Order $order)
    {
        foreach ($order->items as $orderItem) {
            if ($orderItem->product_id) {
                $product = \App\Models\Product::find($orderItem->product_id);
                if ($product) {
                    $product->increment('quantity', $orderItem->quantity);
                }
            }
        }
    }

    public function cancelOrder(Order $order, $reason = null, $cancelledBy = null)
    {
        try {
            DB::beginTransaction();
            $order->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason
            ]);
            $this->restoreProductQuantities($order);
            Log::info('Order cancelled and quantities restored', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'cancelled_by' => $cancelledBy ? get_class($cancelledBy) . ':' . $cancelledBy->id : 'system',
                'reason' => $reason
            ]);
            DB::commit();
            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to cancel order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function confirmPayment(Order $order, $paymentReference = null)
    {
        try {
            DB::beginTransaction();
            if ($order->trashed()) {
                $order->restore();
            }
            $order->update([
                'payment_status' => 'success',
                'payment_reference' => $paymentReference
            ]);
            Log::info('Order payment confirmed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_reference' => $paymentReference
            ]);
            DB::commit();
            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to confirm payment', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function handlePaymentFailure(Order $order, $failureReason = null)
    {
        try {
            DB::beginTransaction();
            if ($order->trashed()) {
                $order->restore();
            }
            $order->update([
                'payment_status' => 'failed',
                'status' => 'cancelled',
                'cancel_reason' => $failureReason
            ]);
            $this->restoreProductQuantities($order);
            Log::info('Order payment failed and quantities restored', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'failure_reason' => $failureReason
            ]);
            DB::commit();
            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to handle payment failure', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function ClientOnlinePayments()
    {
        return Order::query()
            ->where('payment_status', 'success')
            ->where('user_id', auth()->id());
    }
}
