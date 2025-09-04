<?php

namespace Database\Seeders;

use App\Models\Setting;
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

    // public function run(): void
    // {
    //     $faker = Faker::create();

    //     for ($i = 0; $i < 100; $i++) {
    //         $birthday = $faker->dateTimeBetween('-80 years', '-1 years');
    //         $age = $birthday->diff(new \DateTime())->y;
    //         $weight = $faker->numberBetween(3, 120);
    //         $height = $faker->numberBetween(50, 200);

    //         // fix nullable date
    //         $deworming = $faker->optional()->dateTimeBetween('-2 years', 'now');

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

    //             'immunizations' => implode(', ', $faker->randomElements(
    //                 ['BCG', 'DPT', 'Polio', 'Measles', 'Hepatitis B'],
    //                 $faker->numberBetween(1, 5)
    //             )),
    //             'last_deworming_date' => $deworming ? $deworming->format('Y-m-d') : null,
    //             'allergies' => $faker->optional()->word,
    //             'medical_history' => $faker->optional()->sentence(10),
    //             'notes' => $faker->sentence(15),

    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);
    //     }
    // }

    public function run(): void
    {
        $faker = Faker::create();

        // Get thresholds from settings table
        $settings = Setting::first();
        if (!$settings) {
            $this->command->warn('⚠️ No settings found. Please seed settings first.');
            return;
        }

        for ($i = 0; $i < 100; $i++) {
            $birthday = $faker->dateTimeBetween('-80 years', '-1 years');
            $dateMeasured = $faker->dateTimeBetween('-1 years', 'now');
            $age = $birthday->diff($dateMeasured)->y;
            $weight = $faker->numberBetween(3, 120); // kg
            $height = $faker->numberBetween(50, 200); // cm

            // Compute nutrition
            $wfa = $this->computeWFA($weight, $settings);
            $hfa = $this->computeHFA($height, $settings);
            $wfs = $this->computeWFS($weight, $settings);

            // Compute overall status
            $status = $this->computeOverallStatus($wfa, $hfa, $wfs);

            // Optional deworming date
            $deworming = $faker->optional()->dateTimeBetween('-2 years', 'now');

            DB::table('patients')->insert([
                'name' => $faker->name,
                'address' => $faker->address,
                'belongs_to_ip' => $faker->company,
                'sex' => $faker->randomElement(['Male', 'Female']),
                'birthday' => $birthday->format('Y-m-d'),
                'date_measured' => $dateMeasured->format('Y-m-d'),
                'weight' => $weight,
                'height' => $height,
                'age' => $age,
                'weight_for_age' => $wfa,
                'height_for_age' => $hfa,
                'weight_for_ltht_status' => $wfs,
                'contact_number' => $faker->phoneNumber,

                'immunizations' => implode(', ', $faker->randomElements(
                    ['BCG', 'DPT', 'Polio', 'Measles', 'Hepatitis B'],
                    $faker->numberBetween(1, 5)
                )),
                'last_deworming_date' => $deworming ? $deworming->format('Y-m-d') : null,
                'allergies' => $faker->optional()->word,
                'medical_history' => $faker->optional()->sentence(10),
                'notes' => $faker->sentence(15),

                'status' => $status,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function computeWFA($weight, $settings)
    {
        if ($weight <= $settings->wfa_underweight) return 'Underweight';
        if ($weight <= $settings->wfa_normal) return 'Normal';
        return 'Overweight';
    }

    private function computeHFA($height, $settings)
    {
        if ($height <= $settings->hfa_stunted) return 'Stunted';
        if ($height <= $settings->hfa_normal) return 'Normal';
        return 'Tall';
    }

    private function computeWFS($weight, $settings)
    {
        if ($weight <= $settings->wfs_wasted) return 'Wasted';
        if ($weight <= $settings->wfs_normal) return 'Normal';
        return 'Obese';
    }

    private function computeOverallStatus($wfa, $hfa, $wfs)
    {
        if ($wfs === 'Wasted' || $hfa === 'Stunted') {
            return 'Severe'; // Red
        }

        if ($wfa === 'Underweight' || $wfa === 'Overweight' || $wfs === 'Obese') {
            return 'Moderate'; // Orange
        }

        if ($hfa === 'Tall') {
            return 'At Risk'; // Yellow
        }

        return 'Healthy'; // Green
    }
}
