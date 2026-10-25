<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('site_settings')
            ->where('key', 'total_for_free_delviery')
            ->exists();

        if (!$exists) {
            DB::table('site_settings')->insert([
                'key' => 'total_for_free_delviery',
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'total_for_free_delviery')
            ->delete();
    }
};
