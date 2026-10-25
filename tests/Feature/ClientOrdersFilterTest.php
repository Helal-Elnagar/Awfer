<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class ClientOrdersFilterTest extends TestCase
{
    use DatabaseTransactions;

    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->clientUser = User::create([
            'name'     => 'Client Orders Tester',
            'phone'    => '966500000401',
            'type'     => 'client',
            'is_active' => 1,
        ]);
    }

    protected function createOrder(string $status): Order
    {
        return Order::create([
            'order_number'      => 'ORD-' . strtoupper(uniqid()),
            'user_id'           => $this->clientUser->id,
            'status'            => $status,
            'assigned_date'     => now()->toDateString(),
            'total'             => 100.00,
            'subtotal'          => 100.00,
            'payment_method_id' => 1,
        ]);
    }

    public function test_client_orders_out_for_delivery_returns_same_orders_as_confirmed(): void
    {
        $confirmed = $this->createOrder('confirmed');
        $outForDelivery = $this->createOrder('out-for-delivery');

        $confirmedResponse = $this->actingAs($this->clientUser, 'sanctum')
            ->getJson('/api/client/orders?status=confirmed');

        $confirmedResponse->assertOk();
        $confirmedNumbers = collect($confirmedResponse->json('data'))->pluck('order_number')->sort()->values();
        $this->assertTrue($confirmedNumbers->contains($confirmed->order_number));
        $this->assertFalse($confirmedNumbers->contains($outForDelivery->order_number));

        $outForDeliveryResponse = $this->actingAs($this->clientUser, 'sanctum')
            ->getJson('/api/client/orders?status=out-for-delivery');

        $outForDeliveryResponse->assertOk();
        $outForDeliveryNumbers = collect($outForDeliveryResponse->json('data'))->pluck('order_number')->sort()->values();

        $this->assertSame(
            $confirmedNumbers->all(),
            $outForDeliveryNumbers->all(),
            'status=out-for-delivery must return exactly the same orders as status=confirmed'
        );
    }
}
