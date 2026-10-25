<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('assigned_date')->nullable()->after('delivery_id');
            $table->date('delivery_date')->nullable()->after('assigned_date');
            $table->date('cancel_date')->nullable()->after('delivery_date');
            $table->integer('cartons_count')->default(0)->after('cancel_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['assigned_date', 'delivery_date', 'cancel_date', 'cartons_count']);
        });
    }
};
