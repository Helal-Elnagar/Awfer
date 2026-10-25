<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_wallet_deduction_updates_cart_and_user_balance()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret',
            'wallet_balance' => 500,
            'is_active' => 1,
        ]);

        $product = Product::create([
            'name' => ['en' => 'Test', 'ar' => 'اختبار'],
            'description' => ['en' => 'Desc', 'ar' => 'وصف'],
            'base_price' => 100,
            'price_after_discount' => 100,
            'discount_percentage' => 0,
            'is_active' => true,
            'quantity' => 100,
        ]);

        $cartService = app(CartService::class);
        $cartService->addToCart($user, [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $cart = $cartService->applyWalletDeduction($user, 10);

        $this->assertEquals(10, (int)($cart->wallet_deduction));
        $this->assertEquals(490, (int)($user->fresh()->wallet_balance));
    }

    public function test_cash_payment_creates_wallet_payment_transaction()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'secret',
            'wallet_balance' => 500,
            'is_active' => 1,
        ]);

        $product = Product::create([
            'name' => ['en' => 'Test', 'ar' => 'اختبار'],
            'description' => ['en' => 'Desc', 'ar' => 'وصف'],
            'base_price' => 100,
            'price_after_discount' => 100,
            'discount_percentage' => 0,
            'is_active' => true,
            'quantity' => 100,
        ]);

        $cartService = app(CartService::class);
        $orderService = app(OrderService::class);

        $cartService->addToCart($user, [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $cartService->applyWalletDeduction($user, 10);

        $result = $orderService->createOrderFromCart($user, [
            'payment_method_id' => 5,
            'delivery_type' => 'pickup',
        ]);

        $orderArr = $result['order'];
        $orderId = is_array($orderArr) ? ($orderArr['id'] ?? null) : ($orderArr->id ?? null);

        $this->assertNotNull($orderId);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'order_id' => $orderId,
            'type' => 'wallet-payment',
            'amount' => 10,
        ]);
    }
}
