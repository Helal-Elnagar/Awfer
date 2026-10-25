<?php

namespace App\Services\Order;

use App\Models\Coupon;
use App\Models\District;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Repositories\CartRepository;
use App\Repositories\AddressRepository;
use App\Repositories\CouponRepository;
use Carbon\Carbon;
use App\Services\CartService;
use App\Services\WorkingDayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderCheckoutService
 * 
 * Orchestrates the entire checkout flow from cart to order
 * This is the main service that coordinates all other order-related services
 */
class OrderCheckoutService
{
    protected $orderRepository;
    protected $cartRepository;
    protected $addressRepository;
    protected $couponRepository;
    protected $cartService;
    protected $orderCreationService;
    protected $orderValidationService;
    protected $orderPaymentService;
    protected $deliveryCalculationService;
    protected $branchLocationService;
    protected $inventoryService;
    protected $orderNotificationService;
    protected $workingDayService;

    public function __construct(
        OrderRepository $orderRepository,
        CartRepository $cartRepository,
        AddressRepository $addressRepository,
        CouponRepository $couponRepository,
        CartService $cartService,
        OrderCreationService $orderCreationService,
        OrderValidationService $orderValidationService,
        OrderPaymentService $orderPaymentService,
        DeliveryCalculationService $deliveryCalculationService,
        BranchLocationService $branchLocationService,
        InventoryService $inventoryService,
        OrderNotificationService $orderNotificationService,
        WorkingDayService $workingDayService
    ) {
        $this->orderRepository = $orderRepository;
        $this->cartRepository = $cartRepository;
        $this->addressRepository = $addressRepository;
        $this->couponRepository = $couponRepository;
        $this->cartService = $cartService;
        $this->orderCreationService = $orderCreationService;
        $this->orderValidationService = $orderValidationService;
        $this->orderPaymentService = $orderPaymentService;
        $this->deliveryCalculationService = $deliveryCalculationService;
        $this->branchLocationService = $branchLocationService;
        $this->inventoryService = $inventoryService;
        $this->orderNotificationService = $orderNotificationService;
        $this->workingDayService = $workingDayService;
    }

    /**
     * Create order from cart (main checkout flow)
     *
     * @param User $user
     * @param array $data
     * @param array $options - Optional parameters like callback_url and error_url for API
     * @return array ['order' => Order, 'payment_url' => string|null]
     * @throws \Exception
     */
    public function createOrderFromCart(User $user, array $data, array $options = []): array
    {
        $orderDateTime = $this->resolveOrderDateTime($data);

        if (!$this->workingDayService->isOrderingOpenAt($orderDateTime)) {
            throw new \Exception(__('apis.order_outside_working_hours'));
        }

        return DB::transaction(function () use ($user, $data, $options) {
            // Step 1: Validate cart and address
            $validated = $this->orderValidationService->validateCartAndAddress($user, $data);
            $cart = $validated['cart'];
            $address = $validated['address'];

            // Step 2: Validate order type and delivery type
            $this->orderValidationService->validateOrderType($data);
            $this->orderValidationService->validateDeliveryType($data);

            // Step 3: Calculate delivery details (branch, distance, fee)
            $deliveryDetails = $this->deliveryCalculationService->calculateDeliveryDetails($user, $data);
            $branchId = $deliveryDetails['branch_id'];
            $deliveryFee = $deliveryDetails['delivery_fee'];

            // Step 4: Validate branch stock availability
            $orderType = $data['order_type'] ?? 'regular';
            $this->orderValidationService->validateBranchStock($cart, $branchId, $orderType);

            // Step 4.5: Create an address if not provided but coordinates exist (ordinary orders only)
            if ($orderType !== 'gift' && empty($data['address_id'])) {
                $lat = $data['latitude'] ?? $data['lat'] ?? null;
                $lng = $data['longitude'] ?? $data['lng'] ?? null;
                if ($lat !== null && $lng !== null) {
                    $address = $this->addressRepository->create([
                        'user_id' => $user->id,
                        'address_name' => $data['address_name'] ?? null,
                        'city_id' => $data['city_id'] ?? null,
                        'districts_id' => $data['districts_id'] ?? null,
                        'district_name' => isset($data['districts_id']) ? District::query()->where('id', (int) $data['districts_id'])->value('name') : null,
                        'latitude' => (float) $lat,
                        'longitude' => (float) $lng,
                        'phone' => $data['phone'] ?? null,
                        'country_code' => $data['country_code'] ?? null,
                        'description' => $data['description'] ?? null,
                        'is_default' => false,
                    ]);
                    // Attach the newly created address to the request data for downstream services
                    $data['address_id'] = $address->id;
                }
            }

            // Step 5: Prepare order data
            $orderData = $this->prepareOrderData($user, $cart, $data, $deliveryDetails, $address);

            // Step 6: Create order and order items
            $order = $this->orderCreationService->createFromCart($cart, $orderData);

            // Step 7: Deduct inventory stock
            $this->inventoryService->deductStock($order);

            // Step 8: Handle coupon usage if applied
            if (!empty($cart->coupon_code)) {
                $coupon = Coupon::where('coupon_num', $cart->coupon_code)->first();
                if ($coupon) {
                    $this->handleCouponUsage($coupon->id, $order, $user->id);
                }
            }

            // Step 9: Clear user cart and cleanup
            $this->clearUserCart($user);

            // Step 10: Process payment (pass options for API callback URLs)
            $paymentResult = $this->orderPaymentService->processPayment($order, $data, $options);

            // Step 11: Send notifications
            $this->orderNotificationService->notifyAdminsOfNewOrder($order);

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'user_id' => $user->id,
                'total' => $order->total,
            ]);

            return [
                'order' => $order->fresh(['items.product', 'address', 'branch']),
                'payment_url' => $paymentResult['payment_url'],
            ];
        });
    }

    /**
     * Prepare order data from cart and request data
     *
     * @param User $user
     * @param \App\Models\Cart $cart
     * @param array $data
     * @param array $deliveryDetails
     * @param \App\Models\Address|null $address
     * @return array
     */
    private function prepareOrderData(User $user, $cart, array $data, array $deliveryDetails, $address): array
    {
        $orderType = $data['order_type'] ?? 'regular';
        $deliveryType = $data['delivery_type'] ?? 'immediate';

        // Use cart's already calculated values
        $subtotal = $cart->subtotal;
        $discountAmount = $cart->discount ?? 0;
        $couponAmount = $cart->coupon_value ?? 0;
        // The delivery fee is derived from the City of the selected address.
        // Free delivery applies when the subtotal reaches the free-delivery minimum.
        $freeDeliveryMinimum = (float) getSiteSetting('total_for_free_delviery', 0);
        $cityDeliveryFee = (float) ($deliveryDetails['delivery_fee'] ?? 0);
        $deliveryFee = ($freeDeliveryMinimum > 0 && (float) $cart->subtotal >= $freeDeliveryMinimum)
            ? 0.0
            : $cityDeliveryFee;
        $walletDeduction = $cart->wallet_deduction ?? 0;
        $loyaltyPointsUsed = $cart->loyalty_points_used ?? 0;
        $loyaltyDeduction = $cart->loyalty_points_value ?? 0;
        $vatAmount = $cart->vat_amount ?? 0;

        $total = round((float) $cart->total + (float) $deliveryFee, 2);

        // Calculate loyalty points earned for this order
        $earnRate = (float) (\App\Models\SiteSetting::where('key', 'loyalty_points_earn_rate')->value('value') ?? 0);
        $productsTotalAfterDiscount = (float) ($subtotal - $discountAmount - $couponAmount);
        $loyaltyPointsEarned = (int) floor($productsTotalAfterDiscount * $earnRate);

        $orderData = [
            'user_id' => $user->id,
            'order_number' => $this->generateOrderNumber(),
            'status' => 'pending',
            'payment_method_id' => $data['payment_method_id'],
            'payment_status' => 'pending',
            'delivery_type' => $deliveryType,
            'order_type' => $orderType,
            'branch_id' => $deliveryDetails['branch_id'],
            'address_id' => $address?->id,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'discount_code' => $cart->coupon_code ?? null,
            'coupon_id' => Coupon::where('coupon_num', $cart->coupon_code)->value('id'),
            'coupon_amount' => $couponAmount,
            'delivery_fee' => $deliveryFee,
            'wallet_deduction' => $walletDeduction,
            'loyalty_points_used' => $loyaltyPointsUsed,
            'loyalty_deduction' => $loyaltyDeduction,
            'loyalty_points_earned' => $loyaltyPointsEarned,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'notes' => $data['notes'] ?? null,

        ];

        // Add gift order specific fields
        if ($orderType === 'gift') {
            $orderData = array_merge($orderData, [
                'reciver_name' => $data['reciver_name'] ?? null,
                'reciver_phone' => $data['reciver_phone'] ?? null,
                'gift_address_name' => $data['gift_address_name'] ?? null,
                'gift_city_id' => $data['gift_city_id'] ?? null,
                'gift_districts_id' => $data['gift_districts_id'] ?? null,
                'gift_latitude' => isset($data['gift_latitude']) ? (float) $data['gift_latitude'] : (isset($data['gift_lat']) ? (float) $data['gift_lat'] : null),
                'gift_longitude' => isset($data['gift_longitude']) ? (float) $data['gift_longitude'] : (isset($data['gift_lng']) ? (float) $data['gift_lng'] : null),
                'message' => $data['message'] ?? null,
                'whatsapp' => isset($data['whatsapp']) ? (int) $data['whatsapp'] : 0,
                'hide_sender' => isset($data['hide_sender']) ? (int) $data['hide_sender'] : 0,
            ]);
        }

        // Add schedule fields if present
        if (isset($data['schedule_date'])) {
            $orderData['schedule_date'] = $data['schedule_date'];
        }
        if (isset($data['schedule_time'])) {
            $orderData['schedule_time'] = $data['schedule_time'];
        }

        return $orderData;
    }

    private function resolveOrderDateTime(array $data): Carbon
    {
        if (!empty($data['schedule_date']) && !empty($data['schedule_time'])) {
            return Carbon::parse($data['schedule_date'] . ' ' . $data['schedule_time']);
        }

        return now();
    }

    /**
     * Generate unique order number
     *
     * @return string
     */
    private function generateOrderNumber(): string
    {
        return  rand(100000, 999900);
    }

    /**
     * Handle coupon usage after order creation
     *
     * @param int $couponId
     * @param \App\Models\Order $order
     */
    private function handleCouponUsage(int $couponId, $order, int $userId): void
    {
        $coupon = $this->couponRepository->find($couponId);

        if ($coupon) {
            // Attach coupon to order
            $this->orderRepository->attachCoupon($order, $couponId);

            // Increment per-user usage in coupons_users table
            $existing = $coupon->users()->where('users.id', $userId)->first();
            if ($existing) {
                $coupon->users()->updateExistingPivot($userId, [
                    'used_times' => ((int) ($existing->pivot->used_times ?? 0)) + 1,
                ]);
            } elseif (($coupon->audience_type ?? 'all') === 'all') {
                // For "all" audience, create a pivot usage row when user uses the coupon first time
                $coupon->users()->attach($userId, ['used_times' => 1]);
            }

            Log::info('Coupon applied to order', [
                'order_id' => $order->id,
                'coupon_id' => $couponId,
                'coupon_code' => $coupon->coupon_num,
            ]);
        }
    }

    /**
     * Clear user cart after successful order creation
     * Also cleanup wallet deductions and coupon applications
     *
     * @param User $user
     */
    private function clearUserCart(User $user): void
    {
        $cart = $this->cartRepository->getCart($user);

        if ($cart) {
            // Clear cart items and reset all cart fields
            // clearCart already handles: items deletion, subtotal, total, discount, coupon_code,
            // coupon_value, loyalty_points_used, loyalty_points_value, wallet_deduction, vat_amount
            $this->cartRepository->clearCart($cart);

            Log::info('Cart cleared after order creation', [
                'user_id' => $user->id,
            ]);
        }
    }
}

