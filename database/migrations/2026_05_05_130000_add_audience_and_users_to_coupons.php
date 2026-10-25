<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'audience_type')) {
                $table->string('audience_type')->default('all')->after('usage_time');
            }
        });

        if (!Schema::hasTable('coupons_users')) {
            Schema::create('coupons_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('used_times')->default(0);
                $table->timestamps();

                $table->unique(['coupon_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons_users');

        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'audience_type')) {
                $table->dropColumn('audience_type');
            }
        });
    }
};
