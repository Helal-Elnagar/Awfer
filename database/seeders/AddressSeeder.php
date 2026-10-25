<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Address;
use App\Enums\AddressType;
use Faker\Factory as Faker;

class AddressSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // Generate 20 addresses
        for ($i = 0; $i < 20; $i++) {
            Address::create([
                'user_id' => rand(1, 10), // Make sure you have users with these IDs
                'phone' => $faker->numerify('05########'),
                'address_name' => $faker->streetName,
                'description' => $faker->sentence,
                'city_id' => rand(1, 10),       // Make sure cities exist
                'districts_id' => rand(1, 10),  // Make sure districts exist
                'country_code' => $faker->numberBetween(966, 968), // example SA, OM etc.
                'latitude' => $faker->latitude,
                'longitude' => $faker->longitude,
                'is_default' => rand(0,1),
                'building_number' => $faker->optional()->buildingNumber,
                'floor_number' => $faker->optional()->numberBetween(1, 20),
                'note' => $faker->optional()->sentence(3),
                'address_type' => $faker->randomElement(AddressType::values()), // pick random valid enum
            ]);
        }
    }
}
