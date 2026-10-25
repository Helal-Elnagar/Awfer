<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LevelGift;

class LevelGiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        LevelGift::insert([
            ['name' => 'Bronze', 'points_required' => 0,   'percentage' => 5,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Silver', 'points_required' => 50,  'percentage' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gold',   'points_required' => 100, 'percentage' => 15, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
