<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('site_settings')
            ->whereIn('key', [
                'delivery_per_km_fee',
                'delivery_distance_threshold',
                'min_wallet_deduction',
                'min_refund_balance',
            ])->delete();
    }

    public function down(): void
    {
        $defaults = [
            ['key' => 'delivery_per_km_fee', 'value' => '0'],
            ['key' => 'delivery_distance_threshold', 'value' => '0'],
            ['key' => 'min_wallet_deduction', 'value' => '0'],
            ['key' => 'min_refund_balance', 'value' => '0'],
        ];

        foreach ($defaults as $row) {
            $exists = DB::table('site_settings')->where('key', $row['key'])->exists();
            if (!$exists) {
                DB::table('site_settings')->insert($row);
            }
        }
    }
};

