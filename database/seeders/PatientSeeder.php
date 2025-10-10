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

    // private function computeWFA($age, $weight)
    // {
    //     if ($age < 5 && $weight < 10) return 'Underweight';
    //     if ($age < 10 && $weight < 20) return 'Underweight';
    //     if ($age >= 10 && $weight < 40) return 'Underweight';
    //     if ($weight > 80) return 'Overweight';
    //     return 'Normal';
    // }

    // private function computeHFA($age, $height)
    // {
    //     if ($age < 5 && $height < 85) return 'Stunted';
    //     if ($age < 10 && $height < 120) return 'Stunted';
    //     if ($age >= 10 && $height < 150) return 'Stunted';
    //     if ($height > 190) return 'Tall';
    //     return 'Normal';
    // }

    // private function computeWFHOrBMI($age, $bmi)
    // {
    //     if ($age < 20) {
    //         if ($bmi < 14) return 'Wasted';
    //         if ($bmi > 21) return 'Obese';
    //         return 'Normal';
    //     } else {
    //         if ($bmi < 18.5) return 'Wasted';
    //         if ($bmi >= 30) return 'Obese';
    //         return 'Normal';
    //     }
    // }

    // private function computeOverallStatus($wfa, $hfa, $wfs)
    // {
    //     if ($wfs === 'Wasted' || $hfa === 'Stunted') {
    //         return 'Severe'; // Red
    //     }
    //     if ($wfa === 'Underweight' || $wfa === 'Overweight' || $wfs === 'Obese') {
    //         return 'Moderate'; // Orange
    //     }
    //     if ($hfa === 'Tall') {
    //         return 'At Risk'; // Yellow
    //     }
    //     return 'Healthy'; // Green
    // }

    // ✅ WHO-like Weight-for-Age Classification
    private function computeWFA($age, $weight)
    {
        if ($age < 5) {
            if ($weight < 10) return 'Severely Underweight';
            if ($weight < 14) return 'Underweight';
        } elseif ($age < 10) {
            if ($weight < 20) return 'Underweight';
            if ($weight < 25) return 'Mildly Underweight';
        } elseif ($age < 20) {
            if ($weight < 40) return 'Underweight';
        }

        if ($weight > 80) return 'Overweight';
        return 'Normal';
    }

    // ✅ WHO-like Height-for-Age Classification
    private function computeHFA($age, $height)
    {
        if ($age < 5) {
            if ($height < 85) return 'Severely Stunted';
            if ($height < 95) return 'Stunted';
        } elseif ($age < 10) {
            if ($height < 120) return 'Stunted';
        } elseif ($age < 20) {
            if ($height < 150) return 'Stunted';
        }

        if ($height > 190) return 'Tall';
        return 'Normal';
    }

    // ✅ WHO-like BMI-for-Age (under 20) or Adult BMI (20+)
    private function computeWFHOrBMI($age, $bmi)
    {
        if ($age < 20) {
            // WHO BMI-for-age cutoffs (approximate)
            if ($bmi < 14) return 'Severely Wasted';
            if ($bmi < 16) return 'Wasted';
            if ($bmi > 27) return 'Obese';
            if ($bmi > 23) return 'Overweight';
            return 'Normal';
        } else {
            // Adult BMI WHO cutoffs
            if ($bmi < 16) return 'Severely Wasted';
            if ($bmi < 18.5) return 'Wasted';
            if ($bmi >= 30) return 'Obese';
            if ($bmi >= 25) return 'Overweight';
            return 'Normal';
        }
    }

    // ✅ Adjusted overall status (WHO-based priority)
    private function computeOverallStatus($wfa, $hfa, $wfh, $bmi = null)
    {
        // If BMI value is available, prioritize it for overall classification
        if ($bmi !== null) {
            if ($bmi < 16) {
                return 'Severe'; // 🔴 Severe wasting
            }
            if ($bmi < 18.5) {
                return 'Moderate'; // 🟠 Underweight / mild wasting
            }
            if ($bmi >= 30) {
                return 'Severe'; // 🔴 Obese
            }
            if ($bmi >= 25) {
                return 'At Risk'; // 🟡 Slightly high / pre-obese
            }

            // Normal BMI range → tentatively Healthy
            $status = 'Healthy';
        } else {
            // Fallback if BMI not available
            $status = 'Healthy';
        }

        // --- Adjust based on secondary indicators (fine-tuning) ---
        if (str_contains($wfa, 'Severely') || str_contains($hfa, 'Severely') || str_contains($wfh, 'Severely')) {
            return 'Severe';
        }

        if (in_array($wfa, ['Underweight']) || in_array($hfa, ['Stunted']) || in_array($wfh, ['Wasted', 'Obese'])) {
            // If BMI was normal but these are off, mark as Moderate
            if ($status === 'Healthy') {
                $status = 'Moderate';
            }
        }

        if ($status === 'Healthy' && (in_array($wfa, ['Mildly Underweight']) || in_array($hfa, ['Tall']) || in_array($wfh, ['Overweight']))) {
            $status = 'At Risk';
        }

        return $status;
    }
}
