<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDeliveryFeeKeyToSiteSettings extends Migration
{
    public function up()
    {
        $exists = DB::table('site_settings')
            ->where('key', 'delivery_fee')
            ->exists();

        if (!$exists) {
            DB::table('site_settings')->insert([
                'key'   => 'delivery_fee',
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        DB::table('site_settings')->where('key', 'delivery_fee')->delete();
    }
}
