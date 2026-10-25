<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class DeliveryOrdersListTest extends TestCase
{
    use DatabaseTransactions;

    protected User $deliveryUser;
    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->deliveryUser = User::create([
            'name'     => 'Delivery Tester',
            'phone'    => '966500000201',
            'type'     => 'delivery',
            'is_active' => 1,
        ]);

        $this->clientUser = User::create([
            'name'     => 'Client Tester',
            'phone'    => '966500000202',
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
        // Simulates exactly what Admin\OrderController::assignDelivery stores:
        // delivery_id = delivery user id, status = 'new' (English enum value).
        $order = $this->createAssignedOrder('new');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/delivery/orders');

        $response->assertOk();
        $response->assertJsonPath('status', true);

        $orderNumbers = collect($response->json('data'))->pluck('order_number');
        $this->assertTrue(
            $orderNumbers->contains($order->order_number),
            'Newly assigned order (status=new) was NOT returned by GET /api/delivery/orders'
        );
    }

    public function test_delivery_orders_list_still_returns_legacy_arabic_status_orders(): void
    {
        $order = $this->createAssignedOrder('جديد');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/delivery/orders');

        $response->assertOk();

        $orderNumbers = collect($response->json('data'))->pluck('order_number');
        $this->assertTrue(
            $orderNumbers->contains($order->order_number),
            'Legacy order (status=جديد) was NOT returned by GET /api/delivery/orders'
        );
    }

    public function test_delivery_orders_list_still_returns_out_for_delivery_and_delivered_orders(): void
    {
        $outForDelivery = $this->createAssignedOrder('جاري التوصيل');
        $delivered = $this->createAssignedOrder('تم التوصيل');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/delivery/orders');

        $response->assertOk();

        $orderNumbers = collect($response->json('data'))->pluck('order_number');
        $this->assertTrue($orderNumbers->contains($outForDelivery->order_number));
        $this->assertTrue($orderNumbers->contains($delivered->order_number));
    }

    public function test_delivery_orders_list_excludes_other_delivery_and_unassigned_orders(): void
    {
        $otherDelivery = User::create([
            'name'     => 'Other Delivery',
            'phone'    => '966500000203',
            'type'     => 'delivery',
            'is_active' => 1,
        ]);

        $otherAssigned = $this->createAssignedOrder('new', [
            'delivery_id' => $otherDelivery->id,
        ]);

        $unassigned = $this->createAssignedOrder('new', [
            'delivery_id' => null,
        ]);

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/delivery/orders');

        $response->assertOk();

        $orderNumbers = collect($response->json('data'))->pluck('order_number');
        $this->assertFalse($orderNumbers->contains($otherAssigned->order_number));
        $this->assertFalse($orderNumbers->contains($unassigned->order_number));
    }

    public function test_delivery_orders_show_returns_newly_assigned_english_status_order(): void
    {
        $order = $this->createAssignedOrder('new');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson("/api/delivery/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('status', true);
    }

    public function test_delivery_orders_show_returns_confirmed_english_status_order(): void
    {
        $order = $this->createAssignedOrder('confirmed');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson("/api/delivery/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status_enum', 'confirmed');
    }

    public function test_delivery_orders_show_returns_confirmed_legacy_arabic_status_order(): void
    {
        $order = $this->createAssignedOrder('تم التاكيد');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson("/api/delivery/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status_enum', 'confirmed');
    }

    public function test_delivery_orders_show_returns_out_for_delivery_order(): void
    {
        $order = $this->createAssignedOrder('out-for-delivery');

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson("/api/delivery/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status_enum', 'out-for-delivery');
    }
}
