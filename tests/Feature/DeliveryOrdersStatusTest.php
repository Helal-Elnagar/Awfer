<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class DeliveryOrdersStatusTest extends TestCase
{
    use DatabaseTransactions;

    protected User $deliveryUser;
    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->deliveryUser = User::create([
            'name'     => 'Delivery Status Tester',
            'phone'    => '966500000301',
            'type'     => 'delivery',
            'is_active' => 1,
        ]);

        $this->clientUser = User::create([
            'name'     => 'Client Status Tester',
            'phone'    => '966500000302',
            'type'     => 'client',
            'is_active' => 1,
        ]);
    }

    protected function createAssignedOrder(string $status, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number'      => 'ORD-' . strtoupper(uniqid()),
            'user_id'           => $this->clientUser->id,
            'delivery_id'       => $this->deliveryUser->id,
            'status'            => $status,
            'assigned_date'     => now()->toDateString(),
            'total'             => 100.00,
            'subtotal'          => 100.00,
            'payment_method_id' => 1,
        ], $overrides));
    }

    public function test_delivery_orders_list_returns_newly_assigned_english_status_order(): void
    {
        $order = $this->createAssignedOrder('new');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/delivery/orders');

        $response->assertOk();
        $response->assertJsonPath('status', true);

        $orderNumbers = collect($response->json('data'))->pluck('order_number');
        $this->assertTrue(
            $orderNumbers->contains($order->order_number),
            'GET /api/delivery/orders did NOT return the newly assigned order (status=new)'
        );
    }

    public function test_delivery_orders_list_filters_by_status_new(): void
    {
        $englishNew = $this->createAssignedOrder('new');
        $legacyArabicNew = $this->createAssignedOrder('جديد');
        $delivered = $this->createAssignedOrder('تم التوصيل');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/delivery/orders?status=new');

        $response->assertOk();
        $response->assertJsonPath('status', true);

        $orderNumbers = collect($response->json('data'))->pluck('order_number');
        $this->assertTrue(
            $orderNumbers->contains($englishNew->order_number),
            'GET /api/delivery/orders?status=new did NOT return the English "new" order'
        );
        $this->assertTrue(
            $orderNumbers->contains($legacyArabicNew->order_number),
            'GET /api/delivery/orders?status=new did NOT return the legacy Arabic "جديد" order'
        );
        $this->assertFalse(
            $orderNumbers->contains($delivered->order_number),
            'GET /api/delivery/orders?status=new incorrectly returned a delivered order'
        );

        // Every returned order must expose the internal key 'new'.
        $returned = collect($response->json('data'))->filter(fn ($o) => in_array($o['order_number'], [$englishNew->order_number, $legacyArabicNew->order_number], true));
        $this->assertNotEmpty($returned);
        foreach ($returned as $item) {
            $this->assertSame('new', $item['status']);
        }
    }

    public function test_delivery_orders_list_returns_internal_status_key_and_label(): void
    {
        $englishNew = $this->createAssignedOrder('new');
        $legacyArabicNew = $this->createAssignedOrder('جديد');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/delivery/orders');

        $response->assertOk();
        $response->assertJsonPath('status', true);

        $orders = collect($response->json('data'));

        foreach ([$englishNew, $legacyArabicNew] as $order) {
            $item = $orders->firstWhere('order_number', $order->order_number);
            $this->assertNotNull($item, "Order {$order->order_number} missing from response");
            $this->assertSame(
                'new',
                $item['status'],
                "Order {$order->order_number}: status must be the internal key 'new'"
            );
            $this->assertSame(
                'جديد',
                $item['status_enum'],
                "Order {$order->order_number}: status_enum must be the translated label 'جديد'"
            );
        }
    }

    public function test_delivery_orders_list_out_for_delivery_returns_confirmed_and_out_for_delivery_orders(): void
    {
        $confirmedEnglish = $this->createAssignedOrder('confirmed');
        $confirmedArabic = $this->createAssignedOrder('تم التاكيد');
        $outForDeliveryEnglish = $this->createAssignedOrder('out-for-delivery');
        $outForDeliveryArabic = $this->createAssignedOrder('جاري التوصيل');
        $delivered = $this->createAssignedOrder('تم التوصيل');
        $new = $this->createAssignedOrder('new');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/delivery/orders?status=out-for-delivery');

        $response->assertOk();
        $response->assertJsonPath('status', true);

        $orderNumbers = collect($response->json('data'))->pluck('order_number');

        foreach ([$confirmedEnglish, $confirmedArabic, $outForDeliveryEnglish, $outForDeliveryArabic] as $order) {
            $this->assertTrue(
                $orderNumbers->contains($order->order_number),
                "Order {$order->order_number} (status={$order->status}) missing from out-for-delivery results"
            );
        }

        foreach ([$delivered, $new] as $order) {
            $this->assertFalse(
                $orderNumbers->contains($order->order_number),
                "Order {$order->order_number} (status={$order->status}) must NOT be returned by the out-for-delivery filter"
            );
        }
    }

    public function test_delivery_orders_list_confirmed_filter_keeps_out_for_delivery_orders(): void
    {
        $confirmedEnglish = $this->createAssignedOrder('confirmed');
        $confirmedArabic = $this->createAssignedOrder('تم التاكيد');
        $outForDeliveryEnglish = $this->createAssignedOrder('out-for-delivery');
        $outForDeliveryArabic = $this->createAssignedOrder('جاري التوصيل');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/delivery/orders?status=confirmed');

        $response->assertOk();
        $response->assertJsonPath('status', true);

        $orderNumbers = collect($response->json('data'))->pluck('order_number');

        foreach ([$outForDeliveryEnglish, $outForDeliveryArabic] as $order) {
            $this->assertTrue(
                $orderNumbers->contains($order->order_number),
                "Order {$order->order_number} (status={$order->status}) missing from confirmed filter results"
            );
        }

        foreach ([$confirmedEnglish, $confirmedArabic] as $order) {
            $this->assertFalse(
                $orderNumbers->contains($order->order_number),
                "Order {$order->order_number} (status={$order->status}) must NOT be returned by the confirmed filter"
            );
        }
    }

    public function test_delivery_orders_status_update_succeeds_for_english_new_order(): void
    {
        $order = $this->createAssignedOrder('new');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->putJson("/api/delivery/orders/{$order->id}/status", [
                'status' => 'out-for-delivery',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.order.id', $order->id);
        $response->assertJsonPath('data.order.status', 'out-for-delivery');
        $response->assertJsonPath('data.order.status_enum', 'جاري التوصيل');

        // The internal status key must be written to the database.
        $this->assertSame('out-for-delivery', $order->fresh()->status);
    }

    public function test_delivery_orders_show_returns_internal_status_key_and_label(): void
    {
        $order = $this->createAssignedOrder('new');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson("/api/delivery/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'new');
        $response->assertJsonPath('data.status_enum', 'جديد');
    }

    public function test_other_delivery_user_cannot_update_this_order(): void
    {
        $order = $this->createAssignedOrder('new');

        $otherDelivery = User::create([
            'name'     => 'Other Status Delivery',
            'phone'    => '966500000303',
            'type'     => 'delivery',
            'is_active' => 1,
        ]);

        $response = $this->actingAs($otherDelivery, 'sanctum')
            ->putJson("/api/delivery/orders/{$order->id}/status", [
                'status' => 'out-for-delivery',
            ]);

        $response->assertStatus(404);
        $response->assertJsonPath('status', false);
        $this->assertSame('new', $order->fresh()->status);
    }
}
