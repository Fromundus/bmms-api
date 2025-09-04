<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    // public function run(): void
    // {
    //     $faker = Faker::create();

    //     for ($i = 0; $i < 100; $i++) {
    //         $birthday = $faker->dateTimeBetween('-80 years', '-1 years');
    //         $age = $birthday->diff(new \DateTime())->y;
    //         $weight = $faker->numberBetween(3, 120); // realistic weights
    //         $height = $faker->numberBetween(50, 200); // cm

    //         DB::table('patients')->insert([
    //             'name' => $faker->name,
    //             'address' => $faker->address,
    //             'belongs_to_ip' => $faker->company,
    //             'sex' => $faker->randomElement(['Male', 'Female']),
    //             'birthday' => $birthday->format('Y-m-d'),
    //             'date_measured' => $faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d'),
    //             'weight' => $weight,
    //             'height' => $height,
    //             'age' => $age,
    //             'weight_for_age' => $faker->randomElement(['Normal', 'Underweight', 'Overweight']),
    //             'height_for_age' => $faker->randomElement(['Normal', 'Stunted', 'Tall']),
    //             'weight_for_ltht_status' => $faker->randomElement(['Normal', 'Wasted', 'Obese']),
    //             'contact_number' => $faker->phoneNumber,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);
    //     }
    // }

    public function run(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $birthday = $faker->dateTimeBetween('-80 years', '-1 years');
            $age = $birthday->diff(new \DateTime())->y;
            $weight = $faker->numberBetween(3, 120);
            $height = $faker->numberBetween(50, 200);

            // fix nullable date
            $deworming = $faker->optional()->dateTimeBetween('-2 years', 'now');

            DB::table('patients')->insert([
                'name' => $faker->name,
                'address' => $faker->address,
                'belongs_to_ip' => $faker->company,
                'sex' => $faker->randomElement(['Male', 'Female']),
                'birthday' => $birthday->format('Y-m-d'),
                'date_measured' => $faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d'),
                'weight' => $weight,
                'height' => $height,
                'age' => $age,
                'weight_for_age' => $faker->randomElement(['Normal', 'Underweight', 'Overweight']),
                'height_for_age' => $faker->randomElement(['Normal', 'Stunted', 'Tall']),
                'weight_for_ltht_status' => $faker->randomElement(['Normal', 'Wasted', 'Obese']),
                'contact_number' => $faker->phoneNumber,

                'immunizations' => implode(', ', $faker->randomElements(
                    ['BCG', 'DPT', 'Polio', 'Measles', 'Hepatitis B'],
                    $faker->numberBetween(1, 5)
                )),
                'last_deworming_date' => $deworming ? $deworming->format('Y-m-d') : null,
                'allergies' => $faker->optional()->word,
                'medical_history' => $faker->optional()->sentence(10),
                'notes' => $faker->sentence(15),

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
