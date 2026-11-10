<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\WHOAnthropometryService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

class PatientSeeder extends Seeder
{
    public function run(WHOAnthropometryService $who): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $birthday = $faker->dateTimeBetween('-80 years', '-1 years');
            $dateMeasured = $faker->dateTimeBetween('-1 years', 'now');
            $age = $birthday->diff($dateMeasured)->y;
            $weight = $faker->numberBetween(3, 120); // kg
            $height = $faker->numberBetween(50, 200); // cm

            // Optional deworming date
            $deworming = $faker->optional()->dateTimeBetween('-2 years', 'now');

            $sex = $faker->randomElement(['Male', 'Female']);
            $birthday = $birthday->format('Y-m-d');

            $barangayId = DB::table('barangays')->inRandomOrder()->value('id');

            // 1️⃣ Insert patient
            $patientId = DB::table('patients')->insertGetId([
                'name'            => $faker->name,
                // 'address'         => $faker->address,
                'sex'             => $sex,
                'birthday'        => $birthday,
                'contact_number'  => $faker->phoneNumber,
                'barangay_id'    => $barangayId,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $assessment = $who->assess([
                "sex" => $sex,
                "date_of_birth" => $birthday,
                "weight" => $weight,
                "height" => $height,
            ]);

            // 2️⃣ Insert patient record (linked to patient)
            DB::table('patient_records')->insert([
                'patient_id'           => $patientId,
                'date_measured'        => $dateMeasured->format('Y-m-d'),
                'weight'               => $weight,
                'height'               => $height,
                'age'                  => $age,
                "weight_for_age" => $assessment["wfa_status"],
                "height_for_age" => $assessment["hfa_status"],
                "weight_for_ltht_status" => $assessment["wfl_status"],
                "bmi_for_age" => $assessment["bfa_status"],
                "bmi" => $assessment["bmi_status"] ?? null,

                'immunizations'        => implode(', ', $faker->randomElements(
                    ['BCG', 'DPT', 'Polio', 'Measles', 'Hepatitis B'],
                    $faker->numberBetween(1, 5)
                )),
                'last_deworming_date'  => $deworming ? $deworming->format('Y-m-d') : null,
                'allergies'            => $faker->optional()->word,
                'medical_history'      => $faker->optional()->sentence(10),
                "birth_history" => $faker->optional()->sentence(10),
                "past_illnesses"    => $faker->optional()->sentence(10),
                "current_medication"    => $faker->optional()->sentence(10),
                "family_medical_history"    => $faker->optional()->sentence(10),
                'notes'                => $faker->sentence(15),
                'status'               => $assessment["overall_status"],
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }
    }
}
