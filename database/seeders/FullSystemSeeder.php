<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Branch;
use App\Models\DeliveryPeriod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FullSystemSeeder extends Seeder
{
    /** @var int[] Product IDs seeded by ProductSeeder (1-14). */
    private array $productIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14];

    /** @var array Arabic status labels used in the database. */
    private const STATUS_NEW          = 'جديد';
    private const STATUS_CONFIRMED    = 'تم التاكيد  ';
    private const STATUS_OUT_DELIVERY = 'جاري التوصيل';
    private const STATUS_DELIVERED    = 'تم التوصيل';
    private const STATUS_CANCELLED    = 'ملغي';

    /** Payment method IDs. */
    private const PM_VISA = 1;
    private const PM_COD  = 5;

    private User $admin;

    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Clean up previously seeded order data
        DB::table('delivery_ratings')->truncate();
        DB::table('order_statuses')->truncate();
        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::table('addresses')->where('address_name', 'المنزل')->delete();
        DB::table('branch_deliveries')->delete();

        $this->seedDeliveryPeriods();
        $this->seedDeliveryUsers();
        $this->seedClientUsers();
        $this->seedAddresses();
        $this->seedBranch();
        $this->seedOrders();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('Full system seeded successfully!');
    }

    // ------------------------------------------------------------------
    //  Delivery Periods
    // ------------------------------------------------------------------

    private function seedDeliveryPeriods(): void
    {
        $periods = [
            ['name' => 'صباحي',     'description' => 'الفترة الصباحية 8 صباحاً - 12 ظهراً'],
            ['name' => 'ظهري',      'description' => 'فترة ما بعد الظهر 12 ظهراً - 4 عصراً'],
            ['name' => 'مسائي',     'description' => 'الفترة المسائية 4 عصراً - 8 مساءً'],
            ['name' => 'سريع',      'description' => 'التوصيل السريع خلال ساعة'],
        ];

        DB::table('delivery_periods')->truncate();
        foreach ($periods as $period) {
            DB::table('delivery_periods')->insert(array_merge($period, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    // ------------------------------------------------------------------
    //  Delivery Users
    // ------------------------------------------------------------------

    private function seedDeliveryUsers(): void
    {
        $deliveries = [
            ['name' => 'محمد العتيبي',    'phone' => '966501234001', 'city_id' => 1],
            ['name' => 'فهد الشمري',      'phone' => '966501234002', 'city_id' => 1],
            ['name' => 'عبدالرحمن السبيعي', 'phone' => '966501234003', 'city_id' => 1],
        ];

        foreach ($deliveries as $delivery) {
            User::updateOrCreate(
                ['phone' => $delivery['phone']],
                [
                    'name'       => $delivery['name'],
                    'type'       => 'delivery',
                    'is_active'  => 1,
                    'city_id'    => $delivery['city_id'],
                    'password'   => '123456',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('  Delivery users seeded');
    }

    // ------------------------------------------------------------------
    //  Client Users
    // ------------------------------------------------------------------

    private function seedClientUsers(): void
    {
        $clients = [
            ['name' => 'أحمد الخالدي',     'phone' => '966502345001', 'city_id' => 1],
            ['name' => 'سالم المطيري',     'phone' => '966502345002', 'city_id' => 1],
            ['name' => 'خالد الحربي',      'phone' => '966502345003', 'city_id' => 1],
            ['name' => 'عمر الزهراني',     'phone' => '966502345004', 'city_id' => 1],
            ['name' => 'يوسف القحطاني',    'phone' => '966502345005', 'city_id' => 1],
            ['name' => 'ناصر الغامدي',     'phone' => '966502345006', 'city_id' => 1],
            ['name' => 'سلطان العنزي',     'phone' => '966502345007', 'city_id' => 1],
            ['name' => 'تركي الدوسري',     'phone' => '966502345008', 'city_id' => 1],
            ['name' => 'فaisal البلوي',    'phone' => '966502345009', 'city_id' => 1],
            ['name' => 'منصور الرشيدي',    'phone' => '966502345010', 'city_id' => 1],
        ];

        foreach ($clients as $client) {
            User::updateOrCreate(
                ['phone' => $client['phone']],
                [
                    'name'       => $client['name'],
                    'type'       => 'client',
                    'is_active'  => 1,
                    'city_id'    => $client['city_id'],
                    'password'   => '123456',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('  Client users seeded');
    }

    // ------------------------------------------------------------------
    //  Addresses
    // ------------------------------------------------------------------

    private function seedAddresses(): void
    {
        $clientIds = User::where('type', 'client')->pluck('id')->toArray();
        if (empty($clientIds)) {
            return;
        }

        $addresses = [];
        foreach ($clientIds as $i => $userId) {
            $addresses[] = [
                'user_id'        => $userId,
                'address_name'   => 'المنزل',
                'city_id'        => 1,
                'districts_id'   => (($i % 10) + 1),
                'latitude'       => 24.7136 + ($i * 0.001),
                'longitude'      => 46.6753 + ($i * 0.001),
                'phone'          => '502345' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'country_code'   => '966',
                'is_default'     => 1,
                'building_number' => (string) ($i + 1),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        DB::table('addresses')->insert($addresses);
        $this->command->info('  Addresses seeded');
    }

    // ------------------------------------------------------------------
    //  Branch
    // ------------------------------------------------------------------

    private function seedBranch(): void
    {
        $branch = Branch::updateOrCreate(
            ['name' => 'الفرع الرئيسي - الرياض'],
            [
                'address'          => 'شارع التحلية، الرياض',
                'phone'            => '966112345678',
                'email'            => 'main@anaqeed.com',
                'latitude'         => '24.7136',
                'longitude'        => '46.6753',
                'delivery_type'    => 'normal',
                'expected_duration' => 30,
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        // Link all delivery users to the branch
        $deliveryIds = User::where('type', 'delivery')->pluck('id')->toArray();
        foreach ($deliveryIds as $deliveryId) {
            DB::table('branch_deliveries')->updateOrInsert(
                ['branch_id' => $branch->id, 'delivery_id' => $deliveryId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('  Branch seeded');
    }

    // ------------------------------------------------------------------
    //  Orders (the core of the seeder)
    // ------------------------------------------------------------------

    private function seedOrders(): void
    {
        $deliveryIds = User::where('type', 'delivery')->pluck('id')->toArray();
        $clientIds   = User::where('type', 'client')->pluck('id')->toArray();
        $addressIds  = Address::pluck('id')->toArray();

        if (empty($deliveryIds) || empty($clientIds)) {
            $this->command->warn('  Skipping orders: missing delivery or client users');
            return;
        }

        $orderNumber = 1000;
        $today       = Carbon::today();

        // ---- Today's Orders ----

        // 3 orders: assigned today, still "New"
        for ($i = 0; $i < 3; $i++) {
            $this->createOrder(
                ++$orderNumber,
                $deliveryIds[$i % count($deliveryIds)],
                $clientIds[array_rand($clientIds)],
                $addressIds[array_rand($addressIds)] ?? null,
                self::STATUS_NEW,
                $today,

                paymentMethodId: rand(1, 100) > 40 ? self::PM_COD : self::PM_VISA
            );
        }

        // 2 orders: assigned today, "Out for Delivery"
        for ($i = 0; $i < 2; $i++) {
            $this->createOrder(
                ++$orderNumber,
                $deliveryIds[$i % count($deliveryIds)],
                $clientIds[array_rand($clientIds)],
                $addressIds[array_rand($addressIds)] ?? null,
                self::STATUS_OUT_DELIVERY,
                $today,

                paymentMethodId: self::PM_COD
            );
        }

        // 3 orders: assigned today, "Delivered" (COD)
        for ($i = 0; $i < 3; $i++) {
            $this->createOrder(
                ++$orderNumber,
                $deliveryIds[$i % count($deliveryIds)],
                $clientIds[array_rand($clientIds)],
                $addressIds[array_rand($addressIds)] ?? null,
                self::STATUS_DELIVERED,
                $today,

                paymentMethodId: self::PM_COD,
                deliveryDate: $today
            );
        }

        // 1 order: assigned today, "Delivered" (Visa) — increases today_total but not cash
        $this->createOrder(
            ++$orderNumber,
            $deliveryIds[0],
            $clientIds[array_rand($clientIds)],
            $addressIds[array_rand($addressIds)] ?? null,
            self::STATUS_DELIVERED,
            $today,
            paymentMethodId: self::PM_VISA,
            deliveryDate: $today,
            totalOverride: 180.00
        );

        // 1 order: assigned today, cancelled by delivery
        $cancelledDeliveryId = $deliveryIds[1];
        $this->createOrder(
            ++$orderNumber,
            null, // delivery_id cleared on cancel
            $clientIds[array_rand($clientIds)],
            $addressIds[array_rand($addressIds)] ?? null,
            self::STATUS_CANCELLED,
            $today,
            cancelledByDeliveryId: $cancelledDeliveryId,
            cancelDate: $today,
            paymentMethodId: self::PM_COD,
            totalOverride: 120.00
        );

        // 1 order: assigned today, confirmed (in between new and out-for-delivery)
        $this->createOrder(
            ++$orderNumber,
            $deliveryIds[2],
            $clientIds[array_rand($clientIds)],
            $addressIds[array_rand($addressIds)] ?? null,
            self::STATUS_CONFIRMED,
            $today,
            paymentMethodId: self::PM_VISA
        );

        // ---- Earlier This Month (for monthly stats) ----

        for ($dayOffset = 1; $dayOffset <= 15; $dayOffset++) {
            $orderDate = Carbon::today()->subDays($dayOffset);
            $numOrders = rand(1, 3);

            for ($j = 0; $j < $numOrders; $j++) {
                $delivered = rand(1, 100) > 20; // 80% delivered, 20% cancelled

                if ($delivered) {
                    $this->createOrder(
                        ++$orderNumber,
                        $deliveryIds[array_rand($deliveryIds)],
                        $clientIds[array_rand($clientIds)],
                        $addressIds[array_rand($addressIds)] ?? null,
                        self::STATUS_DELIVERED,
                        $orderDate,
        
                        paymentMethodId: rand(1, 100) > 50 ? self::PM_COD : self::PM_VISA,
                        deliveryDate: $orderDate
                    );
                } else {
                    $cancelDeliveryId = $deliveryIds[array_rand($deliveryIds)];
                    $this->createOrder(
                        ++$orderNumber,
                        null,
                        $clientIds[array_rand($clientIds)],
                        $addressIds[array_rand($addressIds)] ?? null,
                        self::STATUS_CANCELLED,
                        $orderDate,
        
                        cancelledByDeliveryId: $cancelDeliveryId,
                        cancelDate: $orderDate,
                        paymentMethodId: self::PM_COD
                    );
                }
            }
        }

        // ---- Carry-Forward Orders (assigned previous days, still active) ----

        for ($i = 0; $i < 3; $i++) {
            $this->createOrder(
                ++$orderNumber,
                $deliveryIds[$i % count($deliveryIds)],
                $clientIds[array_rand($clientIds)],
                $addressIds[array_rand($addressIds)] ?? null,
                self::STATUS_NEW,
                Carbon::today()->subDays(rand(1, 3)),

                paymentMethodId: self::PM_COD
            );
        }

        $this->command->info('  Orders seeded (' . $orderNumber - 1000 . ' total)');
    }

    // ------------------------------------------------------------------
    //  Order Creation Helper
    // ------------------------------------------------------------------

    private function createOrder(
        int    $orderNumber,
        ?int   $deliveryUserId,
        int    $clientUserId,
        ?int   $addressId,
        string $status,
        Carbon $assignedDate,
        int    $paymentMethodId = self::PM_COD,
        ?Carbon $deliveryDate = null,
        ?Carbon $cancelDate = null,
        ?int   $cancelledByDeliveryId = null,
        float  $totalOverride = 0.0,
    ): Order {
        $subtotal     = $totalOverride > 0 ? $totalOverride * 0.8 : (float) rand(60, 250);
        $deliveryFee  = (float) rand(5, 25);
        $vatAmount    = round($subtotal * 0.15, 2);
        $total        = $totalOverride > 0 ? $totalOverride : round($subtotal + $deliveryFee, 2);

        $order = Order::create([
            'order_number'              => 'ORD-' . $orderNumber,
            'user_id'                   => $clientUserId,
            'delivery_id'               => $deliveryUserId,
            'cancelled_by_delivery_id'  => $cancelledByDeliveryId,
            'status'                    => $status,
            'address_id'                => $addressId,
            'payment_method_id'         => $paymentMethodId,
            'payment_status'            => $status === self::STATUS_DELIVERED ? 'success' : 'pending',
            'subtotal'                  => $subtotal,
            'delivery_fee'              => $deliveryFee,
            'vat_amount'                => $vatAmount,
            'total'                     => $total,
            'assigned_date'             => $assignedDate->toDateString(),
            'delivery_date'             => $deliveryDate?->toDateString(),
            'cancel_date'               => $cancelDate?->toDateString(),
            'delivery_period_id'        => rand(1, 4),
            'created_at'                => $assignedDate,
            'updated_at'                => now(),
        ]);

        // Create 1-3 order items
        $itemCount   = rand(1, 3);
        $usedProducts = [];
        for ($i = 0; $i < $itemCount; $i++) {
            do {
                $productId = $this->productIds[array_rand($this->productIds)];
            } while (in_array($productId, $usedProducts));
            $usedProducts[] = $productId;

            $quantity          = rand(2, 15);
            $deliveredQuantity = ($status === self::STATUS_DELIVERED) ? $quantity : 0;
            $itemPrice        = (float) rand(10, 50);
            $itemTotal        = round($itemPrice * $quantity, 2);

            OrderItem::create([
                'order_id'            => $order->id,
                'product_id'          => $productId,
                'quantity'            => $quantity,
                'delivered_quantity'  => $deliveredQuantity,
                'price'               => $itemPrice,
                'total'               => $itemTotal,
                'created_at'          => $assignedDate,
                'updated_at'          => now(),
            ]);
        }

        // cartons_count is auto-calculated by OrderItem boot, but refresh
        $order->refresh();

        // Create order status history
        $this->createOrderStatusHistory($order, $assignedDate);

        // Create delivery rating for delivered orders
        if ($status === self::STATUS_DELIVERED && $deliveryUserId) {
            DB::table('delivery_ratings')->insert([
                'order_id'     => $order->id,
                'delivery_id'  => $deliveryUserId,
                'user_id'      => $clientUserId,
                'rate'         => rand(3, 5),
                'comment'      => 'خدمة ممتازة',
                'created_at'   => $deliveryDate ?? $assignedDate,
                'updated_at'   => $deliveryDate ?? $assignedDate,
            ]);
        }

        return $order;
    }

    // ------------------------------------------------------------------
    //  Order Status History
    // ------------------------------------------------------------------

    private function createOrderStatusHistory(Order $order, Carbon $date): void
    {
        $statuses = [];

        $statuses[] = [
            'order_id'   => $order->id,
            'status'     => self::STATUS_NEW,
            'map_desc'   => 'تم تعيين الطلب لمندوب التوصيل',
            'created_at' => $date,
            'updated_at' => $date,
        ];

        if ($order->status === self::STATUS_CONFIRMED) {
            $statuses[] = [
                'order_id'   => $order->id,
                'status'     => self::STATUS_CONFIRMED,
                'map_desc'   => 'تم تأكيد الطلب',
                'created_at' => $date->copy()->addMinutes(30),
                'updated_at' => $date->copy()->addMinutes(30),
            ];
        }

        if ($order->status === self::STATUS_OUT_DELIVERY) {
            $statuses[] = [
                'order_id'   => $order->id,
                'status'     => self::STATUS_OUT_DELIVERY,
                'map_desc'   => 'الطلب خارج للتوصيل',
                'created_at' => $date->copy()->addHour(),
                'updated_at' => $date->copy()->addHour(),
            ];
        }

        if ($order->status === self::STATUS_DELIVERED) {
            $statuses[] = [
                'order_id'   => $order->id,
                'status'     => self::STATUS_DELIVERED,
                'map_desc'   => 'تم التوصيل بنجاح',
                'created_at' => $date->copy()->addHours(2),
                'updated_at' => $date->copy()->addHours(2),
            ];
        }

        if ($order->status === self::STATUS_CANCELLED) {
            $statuses[] = [
                'order_id'   => $order->id,
                'status'     => self::STATUS_CANCELLED,
                'map_desc'   => 'تم إلغاء الطلب',
                'created_at' => $date->copy()->addMinutes(45),
                'updated_at' => $date->copy()->addMinutes(45),
            ];
        }

        DB::table('order_statuses')->insert($statuses);
    }
}
