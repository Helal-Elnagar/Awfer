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
        Schema::table('gifts', function (Blueprint $table) {

            if (Schema::hasColumn('gifts', 'min_points')) {
                $table->dropColumn('min_points');
            }

            if (Schema::hasColumn('gifts', 'coupon_id')) {
                $table->dropForeign(['coupon_id']);
                $table->dropColumn('coupon_id');
            }

            if (!Schema::hasColumn('gifts', 'level_id')) {
                $table->foreignId('level_id')
                      ->after('name')
                      ->constrained('levels_gift')
                      ->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {

            if (Schema::hasColumn('gifts', 'level_id')) {
                $table->dropForeign(['level_id']);
                $table->dropColumn('level_id');
            }

            if (!Schema::hasColumn('gifts', 'min_points')) {
                $table->integer('min_points')->nullable();
            }

            if (!Schema::hasColumn('gifts', 'coupon_id')) {
                $table->foreignId('coupon_id')
                      ->nullable()
                      ->constrained('coupons')
                      ->nullOnDelete();
            }
        });
    }
};
