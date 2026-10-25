<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('gifts')->insert([
            [
                'name' => 'Gift Voucher 10%',
                'min_points' => 100,
                'coupon_id' => 1, 
                'expires_after_days' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Free Shipping',
                'min_points' => 200,
                'coupon_id' => 2,
                'expires_after_days' => 45,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '50 EGP Discount',
                'min_points' => 300,
                'coupon_id' => 3,
                'expires_after_days' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
