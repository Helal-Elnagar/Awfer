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
        Schema::table('user_gift', function (Blueprint $table) {
                $table->boolean('is_used')->default(false)->after('expires_at');
                    $table->boolean('is_finished')->default(false)->after('is_used');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_gift', function (Blueprint $table) {
            //
        });
    }
};
