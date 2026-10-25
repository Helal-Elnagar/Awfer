<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_codes', function (Blueprint $table) {
            $table->id(); 

            $table->foreignId('gift_id')
                  ->constrained('gifts')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('gift_code')->unique(); 

            $table->timestamp('expires_at')->nullable(); 
            $table->timestamp('used_at')->nullable();    

            $table->boolean('is_finished')
                  ->default(false); 

            $table->timestamps(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_codes');
    }
};
