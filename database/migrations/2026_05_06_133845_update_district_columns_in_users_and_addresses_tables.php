<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'district_name')) {
                $table->text('district_name')->nullable()->after('district_id');
            }
        });

        Schema::table('addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('addresses', 'district_name')) {
                $table->text('district_name')->nullable()->after('districts_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'district_name')) {
                $table->dropColumn('district_name');
            }
        });

        Schema::table('addresses', function (Blueprint $table) {
            if (Schema::hasColumn('addresses', 'district_name')) {
                $table->dropColumn('district_name');
            }
        });
    }
};
