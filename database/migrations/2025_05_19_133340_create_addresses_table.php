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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('address_name');
            $table->string('full_name');
            $table->string('address_type')->nullable();
            $table->string('building_number')->nullable();
            $table->string('floor_number')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('districts_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('latitude');
            $table->string('longitude');
            $table->string('phone');
            $table->string('country_code');
            $table->boolean('is_default')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
