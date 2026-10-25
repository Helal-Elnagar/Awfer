<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('country_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->decimal('delivery_fee', 10, 2)->nullable()->after('name');
            $table->json('boundary')->nullable()->after('delivery_fee');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee', 'boundary']);
        });
    }
};
