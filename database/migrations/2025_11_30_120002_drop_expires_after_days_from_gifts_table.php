<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            if (Schema::hasColumn('gifts', 'expires_after_days')) {
                $table->dropColumn('expires_after_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            if (! Schema::hasColumn('gifts', 'expires_after_days')) {
                $table->integer('expires_after_days')->default(30);
            }
        });
    }
};

