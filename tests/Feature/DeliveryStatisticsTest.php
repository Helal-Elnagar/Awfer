<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\DeliveryStatisticsService;
use App\Services\Order\OrderStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeliveryStatisticsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $deliveryUser;
    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time to today for consistent date-based assertions.
        Carbon::setTestNow(Carbon::today());

        // Disable FK checks within the transaction for test data creation.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->deliveryUser = User::create([
            'name'     => 'Delivery Man',
            'phone'    => '966500000001',
            'type'     => 'delivery',
            'is_active' => 1,
        ]);

        $this->clientUser = User::create([
            'name'     => 'Client User',
            'phone'    => '966500000002',
            'type'     => 'client',
            'is_active' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /**
     * Create an order assigned to the delivery person with the given status.
     */
    protected function createAssignedOrder(string $status = 'جديد', array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number'      => 'ORD-' . strtoupper(uniqid()),
            'user_id'           => $this->clientUser->id,
            'delivery_id'       => $this->deliveryUser->id,
            'status'            => $status,
            'assigned_date'     => Carbon::today()->toDateString(),
            'total'             => 100.00,
            'subtotal'          => 80.00,
            'delivery_fee'      => 20.00,
            'vat_amount'        => 12.00,
            'payment_method_id' => 1,
        ], $overrides));
    }

    /**
     * Create a product with OrderItems for the given order.
     */
    protected function createOrderItems(Order $order, int $productCount = 1): array
    {
        $products = [];
        for ($i = 0; $i < $productCount; $i++) {
            $product = Product::create([
                'name'               => "Product {$i}",
                'category_id'        => 1,
                'parent_category_id' => 0,
                'base_price'         => 10.00,
                'brand_id'           => 1,
            ]);
            OrderItem::create([
                'order_id'           => $order->id,
                'product_id'         => $product->id,
                'quantity'           => 10,
                'delivered_quantity' => 0,
                'price'              => 10.00,
                'total'              => 100.00,
            ]);
            $products[] = $product;
        }
        return $products;
    }

    /**
     * Get fresh (uncached) statistics for the delivery user.
     */
    protected function getStatistics(): array
    {
        $service = new DeliveryStatisticsService($this->deliveryUser->id);
        return $service->getDashboardData();
    }

    // ------------------------------------------------------------------
    //  Test: Empty Dashboard
    // ------------------------------------------------------------------

    public function test_empty_dashboard_returns_zeros(): void
    {
        $stats = $this->getStatistics();

        $this->assertEquals(0, $stats['summary']['daily_orders']);
        $this->assertEquals(0, $stats['summary']['today_new_orders']);
        $this->assertEquals(0, $stats['summary']['today_processing_orders']);
        $this->assertEquals(0, $stats['summary']['today_delivered']);
        $this->assertEquals(0, $stats['summary']['today_cancelled']);
        $this->assertEquals(0, $stats['summary']['today_remaining']);
        $this->assertEquals(0.0, $stats['summary']['today_cash_collected']);
        $this->assertEquals(0.0, $stats['summary']['today_total_collected']);
        $this->assertEquals(0.0, $stats['summary']['monthly_cash_collected']);
        $this->assertEquals(0, $stats['summary']['monthly_cartons']);

        $this->assertEquals(0, $stats['delivery_progress']['total_required']);
        $this->assertEquals(0, $stats['delivery_progress']['total_delivered']);
        $this->assertEquals(0, $stats['delivery_progress']['completion_percentage']);

        $this->assertEmpty($stats['products']);
    }

    // ------------------------------------------------------------------
    //  Test: Assign Order (Scenario 1)
    // ------------------------------------------------------------------

    public function test_statistics_after_order_assignment(): void
    {
        $this->createAssignedOrder('جديد');

        $stats = $this->getStatistics();

        $this->assertEquals(1, $stats['summary']['daily_orders']);
        $this->assertEquals(1, $stats['summary']['today_new_orders']);
        $this->assertEquals(0, $stats['summary']['today_processing_orders']);
        $this->assertEquals(0, $stats['summary']['today_delivered']);
        $this->assertEquals(0, $stats['summary']['today_cancelled']);
        $this->assertEquals(1, $stats['summary']['today_remaining']);

        // Delivery progress: 1 required, 0 delivered
        $this->assertEquals(1, $stats['delivery_progress']['total_required']);
        $this->assertEquals(0, $stats['delivery_progress']['total_delivered']);
        $this->assertEquals(0, $stats['delivery_progress']['completion_percentage']);
    }

    // ------------------------------------------------------------------
    //  Test: New → Out for Delivery (Scenario 2)
    // ------------------------------------------------------------------

    public function test_statistics_after_new_to_out_for_delivery(): void
    {
        $order = $this->createAssignedOrder('جديد');

        // Transition: New → Out for Delivery
        $order->update(['status' => 'جاري التوصيل']);

        $stats = $this->getStatistics();

        $this->assertEquals(1, $stats['summary']['daily_orders']);
        $this->assertEquals(0, $stats['summary']['today_new_orders']);
        $this->assertEquals(1, $stats['summary']['today_processing_orders']);
        $this->assertEquals(0, $stats['summary']['today_delivered']);
        $this->assertEquals(0, $stats['summary']['today_cancelled']);
        $this->assertEquals(1, $stats['summary']['today_remaining']);
    }

    // ------------------------------------------------------------------
    //  Test: Out for Delivery → Delivered (Scenario 3)
    // ------------------------------------------------------------------

    public function test_statistics_after_out_for_delivery_to_delivered(): void
    {
        $order = $this->createAssignedOrder('جديد');
        $order->update(['status' => 'جاري التوصيل']);

        // Transition: Out for Delivery → Delivered
        $order->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(1, $stats['summary']['daily_orders']);
        $this->assertEquals(0, $stats['summary']['today_new_orders']);
        $this->assertEquals(0, $stats['summary']['today_processing_orders']);
        $this->assertEquals(1, $stats['summary']['today_delivered']);
        $this->assertEquals(0, $stats['summary']['today_cancelled']);
        $this->assertEquals(0, $stats['summary']['today_remaining']);

        // Delivery progress
        $this->assertEquals(1, $stats['delivery_progress']['total_required']);
        $this->assertEquals(1, $stats['delivery_progress']['total_delivered']);
        $this->assertEquals(100, $stats['delivery_progress']['completion_percentage']);
    }

    // ------------------------------------------------------------------
    //  Test: New → Cancelled (Scenario B)
    // ------------------------------------------------------------------

    public function test_statistics_after_new_to_cancelled(): void
    {
        $order = $this->createAssignedOrder('جديد');

        // Simulate cancelDeliveryOrder: delivery_id is cleared,
        // cancelled_by_delivery_id is set.
        $order->update([
            'status'                    => 'ملغي',
            'delivery_id'               => null,
            'cancelled_by_delivery_id'  => $this->deliveryUser->id,
            'cancel_date'               => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        // today_cancelled uses cancelled_by_delivery_id query
        $this->assertEquals(1, $stats['summary']['today_cancelled']);
        $this->assertEquals(0, $stats['summary']['today_remaining']);
    }

    // ------------------------------------------------------------------
    //  Test: Out for Delivery → Cancelled (Scenario C)
    // ------------------------------------------------------------------

    public function test_statistics_after_out_for_delivery_to_cancelled(): void
    {
        $order = $this->createAssignedOrder('جديد');
        $order->update(['status' => 'جاري التوصيل']);

        // Simulate cancelDeliveryOrder
        $order->update([
            'status'                    => 'ملغي',
            'delivery_id'               => null,
            'cancelled_by_delivery_id'  => $this->deliveryUser->id,
            'cancel_date'               => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(1, $stats['summary']['today_cancelled']);
        $this->assertEquals(0, $stats['summary']['today_delivered']);
        $this->assertEquals(0, $stats['summary']['today_processing_orders']);
        $this->assertEquals(0, $stats['summary']['today_remaining']);
    }

    // ------------------------------------------------------------------
    //  Test: Two Orders — Deliver One, Cancel One (Scenario 5)
    // ------------------------------------------------------------------

    public function test_two_orders_deliver_one_cancel_one(): void
    {
        // Order 1: assigned today → delivered
        $order1 = $this->createAssignedOrder('جديد');
        $order1->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        // Order 2: assigned today → cancelled by delivery
        $order2 = $this->createAssignedOrder('جديد');
        $order2->update([
            'status'                    => 'ملغي',
            'delivery_id'               => null,
            'cancelled_by_delivery_id'  => $this->deliveryUser->id,
            'cancel_date'               => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(2, $stats['summary']['daily_orders']);
        $this->assertEquals(1, $stats['summary']['today_delivered']);
        $this->assertEquals(1, $stats['summary']['today_cancelled']);
        $this->assertEquals(0, $stats['summary']['today_remaining']);

        // Delivery progress: 2 required (cancelled orders count too), 1 delivered = 50%
        $this->assertEquals(2, $stats['delivery_progress']['total_required']);
        $this->assertEquals(1, $stats['delivery_progress']['total_delivered']);
        $this->assertEquals(50, $stats['delivery_progress']['completion_percentage']);
    }

    // ------------------------------------------------------------------
    //  Test: today_remaining Never Negative
    // ------------------------------------------------------------------

    public function test_today_remaining_never_negative(): void
    {
        // Assign 1 order, deliver it immediately
        $order = $this->createAssignedOrder('جديد');
        $order->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(0, $stats['summary']['today_remaining']);
        $this->assertGreaterThanOrEqual(0, $stats['summary']['today_remaining']);
    }

    // ------------------------------------------------------------------
    //  Test: Completion Percentage
    // ------------------------------------------------------------------

    public function test_completion_percentage_zero_when_no_orders(): void
    {
        $stats = $this->getStatistics();
        $this->assertEquals(0, $stats['delivery_progress']['completion_percentage']);
    }

    public function test_completion_percentage_rounding(): void
    {
        // 2 orders assigned, 1 delivered = 50%
        $order1 = $this->createAssignedOrder('جديد');
        $order1->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        $this->createAssignedOrder('جديد');

        $stats = $this->getStatistics();

        $this->assertEquals(2, $stats['delivery_progress']['total_required']);
        $this->assertEquals(1, $stats['delivery_progress']['total_delivered']);
        $this->assertEquals(50, $stats['delivery_progress']['completion_percentage']);
    }

    // ------------------------------------------------------------------
    //  Test: Product Statistics After Delivery
    // ------------------------------------------------------------------

    public function test_product_statistics_after_delivery(): void
    {
        $order = $this->createAssignedOrder('جديد');
        $products = $this->createOrderItems($order, 1);

        // Update delivered quantities
        $order->items()->update(['delivered_quantity' => 7]);

        // Mark as delivered
        $order->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        $this->assertCount(1, $stats['products']);
        $this->assertEquals($products[0]->id, $stats['products'][0]['id']);
        $this->assertEquals(10, $stats['products'][0]['required_quantity']);
        $this->assertEquals(7, $stats['products'][0]['delivered_quantity']);
        $this->assertEquals(3, $stats['products'][0]['remaining_quantity']);
        $this->assertEquals(70, $stats['products'][0]['completion_percentage']);
    }

    public function test_products_empty_when_no_order_items(): void
    {
        $this->createAssignedOrder('جديد');

        $stats = $this->getStatistics();
        $this->assertEmpty($stats['products']);
    }

    public function test_products_not_delivered_show_zero_delivered(): void
    {
        $order = $this->createAssignedOrder('جديد');
        $this->createOrderItems($order);

        $stats = $this->getStatistics();

        $this->assertCount(1, $stats['products']);
        $this->assertEquals(10, $stats['products'][0]['required_quantity']);
        $this->assertEquals(0, $stats['products'][0]['delivered_quantity']);
        $this->assertEquals(10, $stats['products'][0]['remaining_quantity']);
        $this->assertEquals(0, $stats['products'][0]['completion_percentage']);
    }

    // ------------------------------------------------------------------
    //  Test: Delivery Date Set on Delivery
    // ------------------------------------------------------------------

    public function test_delivery_date_set_on_delivery(): void
    {
        $order = $this->createAssignedOrder('جاري التوصيل');

        $order->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        $order->refresh();
        $this->assertNotNull($order->delivery_date);
        $this->assertEquals(Carbon::today()->toDateString(), $order->delivery_date->toDateString());
    }

    // ------------------------------------------------------------------
    //  Test: Cancel Date Set on Cancellation
    // ------------------------------------------------------------------

    public function test_cancel_date_set_on_cancellation(): void
    {
        $order = $this->createAssignedOrder('جديد');

        $order->update([
            'status'                    => 'ملغي',
            'cancelled_by_delivery_id'  => $this->deliveryUser->id,
            'cancel_date'               => Carbon::today()->toDateString(),
        ]);

        $order->refresh();
        $this->assertNotNull($order->cancel_date);
        $this->assertEquals(Carbon::today()->toDateString(), $order->cancel_date->toDateString());
    }

    // ------------------------------------------------------------------
    //  Test: Cache Invalidation
    // ------------------------------------------------------------------

    public function test_cache_cleared_for_user(): void
    {
        $service = new DeliveryStatisticsService($this->deliveryUser->id);

        // Populate cache
        $service->getDashboardData();
        $cacheKey = "delivery_dashboard_{$this->deliveryUser->id}_" . Carbon::today()->toDateString();
        $this->assertTrue(Cache::has($cacheKey));

        // Clear cache
        DeliveryStatisticsService::clearCacheForUser($this->deliveryUser->id);
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_cache_rebuilt_after_invalidation(): void
    {
        $service = new DeliveryStatisticsService($this->deliveryUser->id);

        // First call: no orders → zeros
        $stats1 = $service->getDashboardData();
        $this->assertEquals(0, $stats1['summary']['daily_orders']);

        // Invalidate cache and add an order
        DeliveryStatisticsService::clearCacheForUser($this->deliveryUser->id);
        $this->createAssignedOrder('جديد');

        // Second call: should see the new order (cache was rebuilt)
        $stats2 = $service->getDashboardData();
        $this->assertEquals(1, $stats2['summary']['daily_orders']);
    }

    // ------------------------------------------------------------------
    //  Test: Monthly Statistics
    // ------------------------------------------------------------------

    public function test_monthly_progress_includes_this_month_orders(): void
    {
        // Order assigned today (this month)
        $this->createAssignedOrder('جديد');

        $stats = $this->getStatistics();
        $this->assertEquals(1, $stats['delivery_progress']['total_required']);
    }

    public function test_monthly_cartons_counted_correctly(): void
    {
        // Create delivered order with items → cartons_count auto-calculated
        $order = $this->createAssignedOrder('تم التوصيل', [
            'delivery_date' => Carbon::today()->toDateString(),
        ]);
        $this->createOrderItems($order, 1); // quantity=10 → cartons_count=10

        DeliveryStatisticsService::clearCacheForUser($this->deliveryUser->id);
        $stats = $this->getStatistics();
        $this->assertEquals(10, $stats['summary']['monthly_cartons']);
    }

    // ------------------------------------------------------------------
    //  Test: cartons_count Auto-Calculation
    // ------------------------------------------------------------------

    public function test_cartons_count_auto_calculated_from_items(): void
    {
        // Create order, then items → cartons_count should auto-update
        $order = $this->createAssignedOrder('جديد');
        $this->createOrderItems($order, 2); // 2 items × quantity=10 → cartons_count=20

        $order->refresh();
        $this->assertEquals(20, $order->cartons_count);
    }

    public function test_cartons_count_updates_when_item_added(): void
    {
        $order = $this->createAssignedOrder('جديد');
        $this->assertEquals(0, $order->fresh()->cartons_count);

        // Add items → cartons_count auto-updates
        $this->createOrderItems($order, 1);
        $this->assertEquals(10, $order->fresh()->cartons_count);
    }

    // ------------------------------------------------------------------
    //  Test: Carry-Forward Orders (Assigned Previous Days)
    // ------------------------------------------------------------------

    public function test_carry_forward_orders_not_counted_in_daily_orders(): void
    {
        // Order assigned yesterday, still New
        $this->createAssignedOrder('جديد', [
            'assigned_date' => Carbon::yesterday()->toDateString(),
        ]);

        // Order assigned today, New
        $this->createAssignedOrder('جديد');

        $stats = $this->getStatistics();

        // daily_orders counts ONLY today's assignments
        $this->assertEquals(1, $stats['summary']['daily_orders']);
        $this->assertEquals(1, $stats['summary']['today_new_orders']);
    }

    // ------------------------------------------------------------------
    //  Test: Multiple Delivery Persons Are Isolated
    // ------------------------------------------------------------------

    public function test_statistics_isolated_per_delivery_person(): void
    {
        $otherDelivery = User::create([
            'name'     => 'Other Delivery',
            'phone'    => '966500000003',
            'type'     => 'delivery',
            'is_active' => 1,
        ]);

        $this->createAssignedOrder('جديد');
        Order::create([
            'order_number'      => 'ORD-' . strtoupper(uniqid()),
            'user_id'           => $this->clientUser->id,
            'delivery_id'       => $otherDelivery->id,
            'status'            => 'جديد',
            'assigned_date'     => Carbon::today()->toDateString(),
            'total'             => 50.00,
            'subtotal'          => 50.00,
            'payment_method_id' => 1,
        ]);

        $stats = $this->getStatistics();

        // Should only count orders assigned to this delivery person
        $this->assertEquals(1, $stats['summary']['daily_orders']);
    }

    // ------------------------------------------------------------------
    //  Test: Collected Amounts (from Delivered Orders)
    // ------------------------------------------------------------------

    public function test_cash_collected_today(): void
    {
        // COD order delivered today → should count in both cash and total
        $this->createAssignedOrder('تم التوصيل', [
            'payment_method_id' => 5,
            'total'             => 250.50,
            'delivery_date'     => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(250.50, $stats['summary']['today_cash_collected']);
        $this->assertEquals(250.50, $stats['summary']['today_total_collected']);
    }

    public function test_total_collected_includes_all_payment_methods(): void
    {
        // COD delivered
        $this->createAssignedOrder('تم التوصيل', [
            'payment_method_id' => 5,
            'total'             => 100.00,
            'delivery_date'     => Carbon::today()->toDateString(),
        ]);

        // Visa delivered
        $this->createAssignedOrder('تم التوصيل', [
            'payment_method_id' => 1,
            'total'             => 200.00,
            'delivery_date'     => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        // Cash collected = 100 (only COD)
        $this->assertEquals(100.00, $stats['summary']['today_cash_collected']);
        // Total collected = 100 + 200 = 300 (all delivered orders)
        $this->assertEquals(300.00, $stats['summary']['today_total_collected']);
    }

    public function test_single_visa_delivered_updates_total_only(): void
    {
        $this->createAssignedOrder('تم التوصيل', [
            'payment_method_id' => 1,
            'total'             => 100.00,
            'delivery_date'     => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(0.0, $stats['summary']['today_cash_collected']);
        $this->assertEquals(100.00, $stats['summary']['today_total_collected']);
    }

    public function test_single_cash_delivered_updates_both_fields(): void
    {
        $this->createAssignedOrder('تم التوصيل', [
            'payment_method_id' => 5,
            'total'             => 80.00,
            'delivery_date'     => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(80.00, $stats['summary']['today_cash_collected']);
        $this->assertEquals(80.00, $stats['summary']['today_total_collected']);
    }

    public function test_cash_out_for_delivery_not_counted(): void
    {
        // COD order in "Out for Delivery" → money must NOT be counted
        $this->createAssignedOrder('جاري التوصيل', [
            'payment_method_id' => 5,
            'total'             => 70.00,
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(0.0, $stats['summary']['today_cash_collected']);
        $this->assertEquals(0.0, $stats['summary']['today_total_collected']);
    }

    public function test_visa_out_for_delivery_not_counted(): void
    {
        // Visa order in "Out for Delivery" → money must NOT be counted
        $this->createAssignedOrder('جاري التوصيل', [
            'payment_method_id' => 1,
            'total'             => 120.00,
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(0.0, $stats['summary']['today_cash_collected']);
        $this->assertEquals(0.0, $stats['summary']['today_total_collected']);
    }

    public function test_multiple_delivered_orders_accumulate(): void
    {
        // Order A: Visa 100 → delivered
        $this->createAssignedOrder('تم التوصيل', [
            'payment_method_id' => 1,
            'total'             => 100.00,
            'delivery_date'     => Carbon::today()->toDateString(),
        ]);

        // Order B: Cash 50 → delivered
        $this->createAssignedOrder('تم التوصيل', [
            'payment_method_id' => 5,
            'total'             => 50.00,
            'delivery_date'     => Carbon::today()->toDateString(),
        ]);

        // Order C: Cash 70 → out for delivery (not delivered, not counted)
        $this->createAssignedOrder('جاري التوصيل', [
            'payment_method_id' => 5,
            'total'             => 70.00,
        ]);

        $stats = $this->getStatistics();

        $this->assertEquals(50.00, $stats['summary']['today_cash_collected']);
        $this->assertEquals(150.00, $stats['summary']['today_total_collected']);
    }

    public function test_delivered_then_cancelled_removes_from_count(): void
    {
        // Deliver a COD order
        $order = $this->createAssignedOrder('تم التوصيل', [
            'payment_method_id' => 5,
            'total'             => 100.00,
            'delivery_date'     => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();
        $this->assertEquals(100.00, $stats['summary']['today_cash_collected']);
        $this->assertEquals(100.00, $stats['summary']['today_total_collected']);

        // Cancel the order (simulate cancellation after delivery)
        DeliveryStatisticsService::clearCacheForUser($this->deliveryUser->id);
        $order->update([
            'status'                    => 'ملغي',
            'delivery_id'               => null,
            'cancelled_by_delivery_id'  => $this->deliveryUser->id,
            'cancel_date'               => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();
        $this->assertEquals(0.0, $stats['summary']['today_cash_collected']);
        $this->assertEquals(0.0, $stats['summary']['today_total_collected']);
    }

    // ------------------------------------------------------------------
    //  Test: Full Flow — Assign → Out → Delivered → Products
    // ------------------------------------------------------------------

    public function test_full_delivery_flow(): void
    {
        // Step 1: Assign order
        $order = $this->createAssignedOrder('جديد');
        $products = $this->createOrderItems($order, 2);

        $stats = $this->getStatistics();
        $this->assertEquals(1, $stats['summary']['daily_orders']);
        $this->assertEquals(1, $stats['summary']['today_new_orders']);
        $this->assertEquals(0, $stats['summary']['today_delivered']);
        $this->assertEquals(1, $stats['summary']['today_remaining']);

        // Step 2: Out for Delivery — clear cache since we bypass OrderStatusService
        DeliveryStatisticsService::clearCacheForUser($this->deliveryUser->id);
        $order->update(['status' => 'جاري التوصيل']);

        $stats = $this->getStatistics();
        $this->assertEquals(0, $stats['summary']['today_new_orders']);
        $this->assertEquals(1, $stats['summary']['today_processing_orders']);
        $this->assertEquals(1, $stats['summary']['today_remaining']);

        // Step 3: Delivered with items — clear cache since we bypass OrderStatusService
        DeliveryStatisticsService::clearCacheForUser($this->deliveryUser->id);
        $order->items()->update(['delivered_quantity' => 10]);
        $order->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        $stats = $this->getStatistics();
        $this->assertEquals(0, $stats['summary']['today_new_orders']);
        $this->assertEquals(0, $stats['summary']['today_processing_orders']);
        $this->assertEquals(1, $stats['summary']['today_delivered']);
        $this->assertEquals(0, $stats['summary']['today_remaining']);
        $this->assertEquals(100, $stats['delivery_progress']['completion_percentage']);

        // Products should show both items
        $this->assertCount(2, $stats['products']);
        foreach ($stats['products'] as $product) {
            $this->assertEquals(10, $product['required_quantity']);
            $this->assertEquals(10, $product['delivered_quantity']);
            $this->assertEquals(0, $product['remaining_quantity']);
            $this->assertEquals(100, $product['completion_percentage']);
        }
    }

    // ------------------------------------------------------------------
    //  Test: Invalid User
    // ------------------------------------------------------------------

    public function test_invalid_user_id_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DeliveryStatisticsService(0);
    }

    // ------------------------------------------------------------------
    //  Test: Reassign Delivery Person (Scenario D)
    // ------------------------------------------------------------------

    public function test_reassignment_reflected_in_statistics(): void
    {
        $otherDelivery = User::create([
            'name'     => 'Other Delivery',
            'phone'    => '966500000004',
            'type'     => 'delivery',
            'is_active' => 1,
        ]);

        // Order assigned to other delivery person
        Order::create([
            'order_number'      => 'ORD-' . strtoupper(uniqid()),
            'user_id'           => $this->clientUser->id,
            'delivery_id'       => $otherDelivery->id,
            'status'            => 'جديد',
            'assigned_date'     => Carbon::today()->toDateString(),
            'total'             => 100.00,
            'subtotal'          => 80.00,
            'delivery_fee'      => 20.00,
            'vat_amount'        => 12.00,
            'payment_method_id' => 1,
        ]);

        // Reassign to our delivery person
        Order::where('delivery_id', $otherDelivery->id)->update([
            'delivery_id' => $this->deliveryUser->id,
        ]);

        $stats = $this->getStatistics();
        $this->assertEquals(1, $stats['summary']['daily_orders']);
        $this->assertEquals(1, $stats['summary']['today_new_orders']);
    }

    // ------------------------------------------------------------------
    //  Test: Delivery Workflow — Cash & Online
    // ------------------------------------------------------------------

    public function test_delivering_cash_order_updates_statistics(): void
    {
        $order = $this->createAssignedOrder('جاري التوصيل', [
            'payment_method_id' => 5,
            'total'             => 150.00,
        ]);

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $result = $service->updateDeliveryOrderStatus($order->id, 'تم التوصيل');

        $this->assertNotNull($result);
        $this->assertEquals('delivered', $result->status);

        // Cash and total should reflect the delivered COD order
        $stats = $this->getStatistics();
        $this->assertEquals(1, $stats['summary']['today_delivered']);
        $this->assertEquals(0, $stats['summary']['today_remaining']);
        $this->assertEquals(150.00, $stats['summary']['today_cash_collected']);
        $this->assertEquals(150.00, $stats['summary']['today_total_collected']);
        $this->assertEquals(150.00, $stats['summary']['monthly_cash_collected']);
        $this->assertEquals(100, $stats['delivery_progress']['completion_percentage']);
    }

    public function test_delivering_online_paid_order_updates_total_only(): void
    {
        // payment_method_id = 1 → Visa (online payment)
        $order = $this->createAssignedOrder('جاري التوصيل', [
            'payment_method_id' => 1,
            'total'             => 200.00,
        ]);

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $result = $service->updateDeliveryOrderStatus($order->id, 'تم التوصيل');

        $this->assertNotNull($result);

        // Total collected should include Visa, but cash should be 0
        $stats = $this->getStatistics();
        $this->assertEquals(1, $stats['summary']['today_delivered']);
        $this->assertEquals(0, $stats['summary']['today_remaining']);
        $this->assertEquals(0.0, $stats['summary']['today_cash_collected']);
        $this->assertEquals(200.00, $stats['summary']['today_total_collected']);
        $this->assertEquals(100, $stats['delivery_progress']['completion_percentage']);
    }

    // ------------------------------------------------------------------
    //  Test: Validation Scenario — 4 Assigned, 3 Delivered, 1 Cancelled
    // ------------------------------------------------------------------

    public function test_validation_scenario_4_assigned_3_delivered_1_cancelled(): void
    {
        // Order A: assigned → delivered
        $orderA = $this->createAssignedOrder('جديد');
        $orderA->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        // Order B: assigned → delivered
        $orderB = $this->createAssignedOrder('جديد');
        $orderB->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        // Order C: assigned → delivered
        $orderC = $this->createAssignedOrder('جديد');
        $orderC->update([
            'status'        => 'تم التوصيل',
            'delivery_date' => Carbon::today()->toDateString(),
        ]);

        // Order D: assigned → cancelled by delivery
        $orderD = $this->createAssignedOrder('جديد');
        $orderD->update([
            'status'                    => 'ملغي',
            'delivery_id'               => null,
            'cancelled_by_delivery_id'  => $this->deliveryUser->id,
            'cancel_date'               => Carbon::today()->toDateString(),
        ]);

        DeliveryStatisticsService::clearCacheForUser($this->deliveryUser->id);
        $stats = $this->getStatistics();

        // All 4 assigned today
        $this->assertEquals(4, $stats['summary']['daily_orders']);
        $this->assertEquals(3, $stats['summary']['today_delivered']);
        $this->assertEquals(1, $stats['summary']['today_cancelled']);
        $this->assertEquals(0, $stats['summary']['today_remaining']);

        // Delivery progress includes cancelled orders in total_required
        $this->assertEquals(4, $stats['delivery_progress']['total_required']);
        $this->assertEquals(3, $stats['delivery_progress']['total_delivered']);
        $this->assertEquals(75, $stats['delivery_progress']['completion_percentage']);
    }

    // ------------------------------------------------------------------
    //  Test: delivered_quantity Auto-Set on Delivery
    // ------------------------------------------------------------------

    public function test_delivered_quantity_auto_set_when_order_delivered(): void
    {
        // Create order in Out for Delivery status with items (quantity=10, delivered_quantity defaults to 0)
        $order = $this->createAssignedOrder('جاري التوصيل');
        $products = $this->createOrderItems($order, 1);

        // Verify delivered_quantity is 0 before delivery
        $item = $order->items()->first();
        $this->assertEquals(0, $item->delivered_quantity);

        // Deliver via OrderStatusService (simulates delivery driver action)
        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $result = $service->updateDeliveryOrderStatus($order->id, 'تم التوصيل');

        $this->assertNotNull($result);
        $this->assertEquals('delivered', $result->status);

        // delivered_quantity must now equal quantity
        $item->refresh();
        $this->assertEquals(10, $item->delivered_quantity);

        // Product statistics must reflect the auto-set values
        DeliveryStatisticsService::clearCacheForUser($this->deliveryUser->id);
        $stats = $this->getStatistics();

        $this->assertCount(1, $stats['products']);
        $this->assertEquals(10, $stats['products'][0]['required_quantity']);
        $this->assertEquals(10, $stats['products'][0]['delivered_quantity']);
        $this->assertEquals(0, $stats['products'][0]['remaining_quantity']);
        $this->assertEquals(100, $stats['products'][0]['completion_percentage']);
    }

    public function test_delivered_quantity_auto_set_with_multiple_products(): void
    {
        $order = $this->createAssignedOrder('جاري التوصيل');
        $products = $this->createOrderItems($order, 2);

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $service->updateDeliveryOrderStatus($order->id, 'تم التوصيل');

        // Both items should have delivered_quantity = 10
        foreach ($order->items()->get() as $item) {
            $this->assertEquals(10, $item->fresh()->delivered_quantity);
        }
    }

    // ------------------------------------------------------------------
    //  Test: Expanded Delivery Status Transitions
    // ------------------------------------------------------------------

    public function test_new_to_confirmed_via_delivery_endpoint(): void
    {
        $order = $this->createAssignedOrder('جديد');

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $result = $service->updateDeliveryOrderStatus($order->id, 'confirmed');

        $this->assertNotNull($result);
        $this->assertEquals('confirmed', $result->status);
    }

    public function test_new_to_out_for_delivery_via_delivery_endpoint(): void
    {
        $order = $this->createAssignedOrder('جديد');

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $result = $service->updateDeliveryOrderStatus($order->id, 'out-for-delivery');

        $this->assertNotNull($result);
        $this->assertEquals('out-for-delivery', $result->status);
    }

    public function test_new_to_problem_via_delivery_endpoint(): void
    {
        $order = $this->createAssignedOrder('جديد');

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $result = $service->updateDeliveryOrderStatus($order->id, 'problem');

        $this->assertNotNull($result);
        $this->assertEquals('problem', $result->status);
    }

    public function test_out_for_delivery_to_problem_via_delivery_endpoint(): void
    {
        $order = $this->createAssignedOrder('جاري التوصيل');

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $result = $service->updateDeliveryOrderStatus($order->id, 'problem');

        $this->assertNotNull($result);
        $this->assertEquals('problem', $result->status);
    }

    public function test_cancelled_rejected_via_delivery_endpoint(): void
    {
        $order = $this->createAssignedOrder('جديد');

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $result = $service->updateDeliveryOrderStatus($order->id, 'cancelled');

        $this->assertFalse($result);
    }

    public function test_delivered_skipped_from_new_is_rejected(): void
    {
        $order = $this->createAssignedOrder('جديد');

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $result = $service->updateDeliveryOrderStatus($order->id, 'delivered');

        $this->assertFalse($result);
    }

    public function test_non_delivered_status_does_not_set_delivery_date(): void
    {
        $order = $this->createAssignedOrder('جديد');

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $service->updateDeliveryOrderStatus($order->id, 'out-for-delivery');

        $order->refresh();
        $this->assertNull($order->delivery_date);
    }

    public function test_non_delivered_status_does_not_trigger_delivered_quantity(): void
    {
        $order = $this->createAssignedOrder('جديد');
        $this->createOrderItems($order, 1);

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);
        $service->updateDeliveryOrderStatus($order->id, 'out-for-delivery');

        $item = $order->items()->first();
        $this->assertEquals(0, $item->fresh()->delivered_quantity);
    }

    public function test_full_flow_new_to_confirmed_to_out_for_delivery_to_delivered(): void
    {
        $order = $this->createAssignedOrder('جديد');
        $this->createOrderItems($order, 1);

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);

        // Step 1: New → Confirmed
        $result = $service->updateDeliveryOrderStatus($order->id, 'confirmed');
        $this->assertNotNull($result);
        $this->assertEquals('confirmed', $result->status);

        // Step 2: Confirmed → Out for Delivery
        $result = $service->updateDeliveryOrderStatus($order->id, 'out-for-delivery');
        $this->assertNotNull($result);
        $this->assertEquals('out-for-delivery', $result->status);

        // Step 3: Out for Delivery → Delivered
        $result = $service->updateDeliveryOrderStatus($order->id, 'delivered');
        $this->assertNotNull($result);
        $this->assertEquals('delivered', $result->status);
        $this->assertNotNull($result->delivery_date);
    }

    public function test_confirmed_to_delivered_immediately_after_confirming(): void
    {
        $order = $this->createAssignedOrder('جديد');
        $this->createOrderItems($order, 1);

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);

        // New → Confirmed
        $confirmed = $service->updateDeliveryOrderStatus($order->id, 'confirmed');
        $this->assertNotNull($confirmed);
        $this->assertEquals('confirmed', $confirmed->status);

        // The very next request may move the confirmed order straight to delivered.
        $delivered = $service->updateDeliveryOrderStatus($order->id, 'delivered');
        $this->assertNotNull($delivered, 'confirmed -> delivered must succeed');
        $this->assertEquals('delivered', $delivered->status);
        $this->assertNotNull($delivered->delivery_date);

        // Delivered-specific side effects must run.
        foreach ($order->items()->get() as $item) {
            $this->assertEquals($item->quantity, $item->fresh()->delivered_quantity);
        }
    }

    public function test_confirmed_arabic_stored_status_to_delivered(): void
    {
        $order = $this->createAssignedOrder('تم التاكيد');

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);

        $result = $service->updateDeliveryOrderStatus($order->id, 'delivered');
        $this->assertNotNull($result, 'confirmed (legacy Arabic stored) -> delivered must succeed');
        $this->assertEquals('delivered', $result->status);
    }

    public function test_confirmed_to_new_via_delivery_endpoint(): void
    {
        $order = $this->createAssignedOrder('جديد');

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);

        // Step 1: New → Confirmed
        $result = $service->updateDeliveryOrderStatus($order->id, 'confirmed');
        $this->assertNotNull($result);
        $this->assertEquals('confirmed', $result->status);

        // Step 2: Confirmed → New (the only new transition being added)
        $result = $service->updateDeliveryOrderStatus($order->id, 'new');
        $this->assertNotNull($result);
        $this->assertEquals('new', $result->status);
    }

    public function test_confirmed_to_new_does_not_trigger_delivered_logic(): void
    {
        $order = $this->createAssignedOrder('جديد');
        $this->createOrderItems($order, 1);

        $this->actingAs($this->deliveryUser);
        $service = app(OrderStatusService::class);

        // New → Confirmed
        $service->updateDeliveryOrderStatus($order->id, 'confirmed');

        // Confirmed → New
        $result = $service->updateDeliveryOrderStatus($order->id, 'new');
        $this->assertNotNull($result);
        $this->assertEquals('new', $result->status);

        // Verify no delivered-specific side effects
        $order->refresh();
        $this->assertNull($order->delivery_date);

        $item = $order->items()->first();
        $this->assertEquals(0, $item->fresh()->delivered_quantity);
    }
}
