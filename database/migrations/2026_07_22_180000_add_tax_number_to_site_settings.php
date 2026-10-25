<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $settings = [
            ['key' => 'tax_number', 'value' => ''],
            ['key' => 'company_name', 'value' => ''],
        ];

        foreach ($settings as $row) {
            $exists = DB::table('site_settings')->where('key', $row['key'])->exists();
            if (!$exists) {
                DB::table('site_settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->whereIn('key', ['tax_number', 'company_name'])
            ->delete();
    }
};
