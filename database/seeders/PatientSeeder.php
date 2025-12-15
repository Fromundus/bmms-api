<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $birthday = $faker->dateTimeBetween('-80 years', '-1 years');
            $dateMeasured = $faker->dateTimeBetween('-1 years', 'now');
            $age = $birthday->diff($dateMeasured)->y;
            $weight = $faker->numberBetween(3, 120); // kg
            $height = $faker->numberBetween(50, 200); // cm

            // Compute BMI
            $bmi = $weight / pow($height / 100, 2);

            // Compute categories
            $wfa = $this->computeWFA($age, $weight);
            $hfa = $this->computeHFA($age, $height);
            $wfs = $this->computeWFHOrBMI($age, $bmi);

            // Overall status
            $status = $this->computeOverallStatus($wfa, $hfa, $wfs);

            // Optional deworming date
            $deworming = $faker->optional()->dateTimeBetween('-2 years', 'now');

            // 1️⃣ Insert patient
            $patientId = DB::table('patients')->insertGetId([
                'name'            => $faker->name,
                'address'         => $faker->address,
                'sex'             => $faker->randomElement(['Male', 'Female']),
                'birthday'        => $birthday->format('Y-m-d'),
                'contact_number'  => $faker->phoneNumber,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // 2️⃣ Insert patient record (linked to patient)
            DB::table('patient_records')->insert([
                'patient_id'           => $patientId,
                'date_measured'        => $dateMeasured->format('Y-m-d'),
                'weight'               => $weight,
                'height'               => $height,
                'age'                  => $age,
                'weight_for_age'       => $wfa,
                'height_for_age'       => $hfa,
                'weight_for_ltht_status' => $wfs,
                'immunizations'        => implode(', ', $faker->randomElements(
                    ['BCG', 'DPT', 'Polio', 'Measles', 'Hepatitis B'],
                    $faker->numberBetween(1, 5)
                )),
                'last_deworming_date'  => $deworming ? $deworming->format('Y-m-d') : null,
                'allergies'            => $faker->optional()->word,
                'medical_history'      => $faker->optional()->sentence(10),
                'notes'                => $faker->sentence(15),
                'status'               => $status,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }
    }

    private function computeWFA($age, $weight)
    {
        // 🚫 WHO: Weight-for-Age NOT valid for ≥10 years
        if ($age >= 10) {
            return 'Not Applicable';
        }

        if ($age < 5) {
            if ($weight < 10) return 'Severely Underweight';
            if ($weight < 14) return 'Underweight';
        } else { // 5–9 years
            if ($weight < 20) return 'Underweight';
            if ($weight < 25) return 'Mildly Underweight';
        }

        return 'Normal';
    }


    private function computeHFA($age, $height)
    {
        // 🚫 WHO: Height-for-Age NOT valid for ≥20 years
        if ($age >= 20) {
            return 'Not Applicable';
        }

        if ($age < 5) {
            if ($height < 85) return 'Severely Stunted';
            if ($height < 95) return 'Stunted';
        } elseif ($age < 10) {
            if ($height < 120) return 'Stunted';
        } else { // 10–19 years
            if ($height < 150) return 'Stunted';
        }

        return 'Normal';
    }


    private function computeWFHOrBMI($age, $bmi)
    {
        // CHILDREN & ADOLESCENTS (5–19 years)
        if ($age < 20) {
            if ($bmi < 14) return 'Severely Wasted';
            if ($bmi < 16) return 'Wasted';
            if ($bmi > 27) return 'Obese';
            if ($bmi > 23) return 'Overweight';
            return 'Normal';
        }

        // ADULTS (≥20 years)
        if ($bmi < 16) return 'Severely Wasted';
        if ($bmi < 18.5) return 'Wasted';
        if ($bmi >= 30) return 'Obese';
        if ($bmi >= 25) return 'Overweight';

        return 'Normal';
    }

    private function computeOverallStatus($wfa, $hfa, $wfh, $bmi = null)
    {
        if ($bmi !== null) {

            if ($bmi < 16 || $bmi >= 30) {
                return 'Severe';
            }

            if (($bmi >= 16 && $bmi < 18.5) || ($bmi >= 25 && $bmi < 30)) {
                return 'Moderate';
            }

            if ($bmi >= 18.5 && $bmi < 20) {
                return 'At Risk'; // Low-normal BMI
            }
        }

        if (
            str_contains($wfa, 'Severely') ||
            str_contains($hfa, 'Severely') ||
            str_contains($wfh, 'Severely')
        ) {
            return 'Severe';
        }

        if (
            in_array($wfa, ['Underweight'], true) ||
            in_array($hfa, ['Stunted'], true) ||
            in_array($wfh, ['Wasted', 'Obese'], true)
        ) {
            return 'Moderate';
        }

        if (
            in_array($wfa, ['Mildly Underweight'], true) ||
            in_array($wfh, ['Overweight'], true)
        ) {
            return 'At Risk';
        }

        return 'Healthy';
    }
}
