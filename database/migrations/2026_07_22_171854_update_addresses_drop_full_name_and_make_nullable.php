<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            if (Schema::hasColumn('addresses', 'full_name')) {
                $table->dropColumn('full_name');
            }
            $table->string('phone')->nullable()->change();
            $table->string('country_code')->nullable()->change();
            $table->string('building_number')->nullable()->change();
            $table->string('floor_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('addresses', 'full_name')) {
                $table->string('full_name')->default('');
            }
            $table->string('phone')->default('')->change();
            $table->string('country_code')->default('')->change();
            $table->string('building_number')->default('')->change();
            $table->string('floor_number')->default('')->change();
        });
    }
};
