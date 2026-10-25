<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            if (!Schema::hasColumn('gifts', 'level_id')) {
                $table->foreignId('level_id')
                    ->nullable()
                    ->constrained('levels_gift')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            if (Schema::hasColumn('gifts', 'level_id')) {
                $table->dropForeign(['level_id']);
                $table->dropColumn('level_id');
            }
        });
    }
};

