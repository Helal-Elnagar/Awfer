<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class DeliveryNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $deliveryUser;
    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->deliveryUser = User::create([
            'name'          => 'Delivery Notify Tester',
            'phone'         => '966500000401',
            'type'          => 'delivery',
            'is_active'     => 1,
            'is_notify'     => false,
            'accept_orders' => false,
        ]);

        $this->clientUser = User::create([
            'name'      => 'Client Notify Tester',
            'phone'     => '966500000402',
            'type'      => 'client',
            'is_active' => 1,
            'is_notify' => false,
        ]);
    }

    public function test_switch_notify_toggles_is_notify_for_client(): void
    {
        $this->assertFalse((bool) $this->clientUser->is_notify);

        $response = $this->actingAs($this->clientUser, 'sanctum')
            ->patchJson('/api/switch-notify');

        $response->assertOk();
        $response->assertJsonPath('data.notify', true);
        $this->assertTrue((bool) $this->clientUser->fresh()->is_notify);

        $profile = $this->actingAs($this->clientUser, 'sanctum')
            ->getJson('/api/profile');
        $profile->assertOk();
        $profile->assertJsonPath('data.is_notify', true);
    }

    public function test_switch_order_toggles_is_notify_for_delivery_user(): void
    {
        $this->assertFalse((bool) $this->deliveryUser->is_notify);
        $this->assertFalse((bool) $this->deliveryUser->accept_orders);

        $response = $this->actingAs($this->deliveryUser, 'sanctum')
            ->patchJson('/api/delivery/switch-order');

        $response->assertOk();
        $response->assertJsonPath('data.is_notify', true);
        $response->assertJsonPath('data.accept_orders', true);

        $fresh = $this->deliveryUser->fresh();
        $this->assertTrue((bool) $fresh->is_notify);
        $this->assertTrue((bool) $fresh->accept_orders);

        // Profile must reflect the updated value immediately.
        $profile = $this->actingAs($this->deliveryUser, 'sanctum')
            ->getJson('/api/profile');
        $profile->assertOk();
        $profile->assertJsonPath('data.is_notify', true);
    }

    public function test_switch_order_and_switch_notify_share_same_source_of_truth(): void
    {
        // Both endpoints must toggle the same field (is_notify) so the profile
        // value stays consistent regardless of which endpoint the app calls.
        $this->actingAs($this->deliveryUser, 'sanctum')
            ->patchJson('/api/delivery/switch-order')
            ->assertOk();

        $this->assertTrue((bool) $this->deliveryUser->fresh()->is_notify);

        $this->actingAs($this->deliveryUser, 'sanctum')
            ->patchJson('/api/switch-notify')
            ->assertOk();

        $this->assertFalse((bool) $this->deliveryUser->fresh()->is_notify);
    }

    public function test_switch_order_rejects_non_delivery_user(): void
    {
        $response = $this->actingAs($this->clientUser, 'sanctum')
            ->patchJson('/api/delivery/switch-order');

        $response->assertJsonPath('status', false);
    }
}
