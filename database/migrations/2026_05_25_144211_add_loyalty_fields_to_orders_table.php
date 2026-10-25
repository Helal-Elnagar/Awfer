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
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('loyalty_points_used')->default(0)->after('wallet_deduction');
            $table->decimal('loyalty_deduction', 10, 2)->default(0)->after('loyalty_points_used');
            $table->integer('loyalty_points_earned')->default(0)->after('loyalty_deduction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points_used', 'loyalty_deduction', 'loyalty_points_earned']);
        });
    }
};
