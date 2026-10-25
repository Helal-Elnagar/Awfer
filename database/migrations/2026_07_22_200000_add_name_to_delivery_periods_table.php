<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_periods', function (Blueprint $table) {
            $table->string('name')->after('id')->nullable();
        });

        // Migrate existing description values into the new name column
        DB::table('delivery_periods')
            ->whereNull('name')
            ->update(['name' => DB::raw('description')]);

        // Make name non-nullable after data migration
        Schema::table('delivery_periods', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });

        // Make description nullable
        Schema::table('delivery_periods', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_periods', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('delivery_periods', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
        });
    }
};
