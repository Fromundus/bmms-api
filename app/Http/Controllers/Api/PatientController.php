<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{
    public function getStats()
    {
        // Load all patients with their latest record
        $patients = Patient::with('latestRecord')->get();

        $totalPatients = $patients->count();

        $counts = [
            'Severe'   => 0,
            'Moderate' => 0,
            'At Risk'  => 0,
            'Healthy'  => 0,
        ];

        foreach ($patients as $patient) {
            $status = $patient->latestRecord?->status;

            if ($status && isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        // Helper to compute %
        $percent = fn($count) => $totalPatients > 0
            ? round(($count / $totalPatients) * 100, 2)
            : 0;

        return response()->json([
            'total_patients' => $totalPatients,
            'severe'   => ['count' => $counts['Severe'],   'percent' => $percent($counts['Severe'])],
            'moderate' => ['count' => $counts['Moderate'], 'percent' => $percent($counts['Moderate'])],
            'at_risk'  => ['count' => $counts['At Risk'],  'percent' => $percent($counts['At Risk'])],
            'healthy'  => ['count' => $counts['Healthy'],  'percent' => $percent($counts['Healthy'])],
        ]);
    }


    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);

        $wfa = $request->query('wfa');
        $hfa = $request->query('hfa');
        $wfltht = $request->query('wfltht');
        $status = $request->query('status');

        $query = Patient::query()->with("latestRecord");

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if($wfa && $wfa !== 'all'){
            $query->whereHas("latestRecord", function($q) use ($wfa){
                $q->where("weight_for_age", $wfa);
            });
        }

        if($hfa && $hfa !== 'all'){
            $query->whereHas("latestRecord", function($q) use ($hfa){
                $q->where("height_for_age", $hfa);
            });
        }

        if($wfltht && $wfltht !== 'all'){
            $query->whereHas("latestRecord", function($q) use ($wfltht){
                $q->where("weight_for_ltht_status", $wfltht);
            });
        }

        if($status && $status !== 'all'){
            $query->whereHas("latestRecord", function($q) use ($status){
                $q->where("status", $status);
            });
        }

        $patients = $query->orderBy('id', 'desc')->paginate($perPage);

        $counts = [
            'total' => Patient::count(),
        ];

        return response()->json([
            'patients' => $patients,
            'counts' => $counts,
        ]);
    }

    public function show($id){
        $patient = Patient::with("latestRecord")->findOrFail($id);

        return response()->json($patient);
    }

    public function history($id){
        $patient = Patient::with("records")->findOrFail($id);

        return response()->json($patient);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'sex' => 'required|string|in:Male,Female',
            'birthday' => 'required|date',
            'contact_number' => 'required|string',

            'date_measured' => 'required|date',
            'weight' => 'required|numeric',
            'height' => 'required|numeric',

            'immunizations' => 'nullable|string',
            'last_deworming_date' => 'nullable|date',
            'allergies' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'notes' => 'nullable|string',

            'questionnaire_data' => 'nullable|array',
        ]);

        // Age in years
        $validated['age'] = Carbon::parse($validated['birthday'])
            ->diffInYears(Carbon::parse($validated['date_measured']));

        // Compute BMI
        $bmi = $validated['weight'] / pow($validated['height'] / 100, 2);

        // Compute nutrition
        $validated['weight_for_age'] = $this->computeWFA($validated['age'], $validated['weight']);
        $validated['height_for_age'] = $this->computeHFA($validated['age'], $validated['height']);
        $validated['weight_for_ltht_status'] = $this->computeWFHOrBMI($validated['age'], $bmi);

        // Overall status
        $validated['status'] = $this->computeOverallStatus(
            $validated['weight_for_age'],
            $validated['height_for_age'],
            $validated['weight_for_ltht_status']
        );

        $questionnaire = $validated['questionnaire_data'] ?? [];
        $likelyCause = $this->analyzeMalnutritionCause($validated['status'], $questionnaire);

        Log::info($questionnaire);

        $patient = Patient::create(attributes: [
            'name' => $validated["name"],
            'address' => $validated["address"],
            'sex' => $validated["sex"],
            'birthday' => $validated["birthday"],
            'contact_number' => $validated["contact_number"],
        ]);

        if($patient){
            PatientRecord::create([
                'patient_id' => $patient->id,
                'date_measured' => $validated["date_measured"],
                'weight' => $validated["weight"],
                'height' => $validated["height"],
                'age' => $validated["age"],
                "weight_for_age" => $validated["weight_for_age"],
                "height_for_age" => $validated["height_for_age"],
                "weight_for_ltht_status" => $validated["weight_for_ltht_status"],
                
                'immunizations' => $validated["immunizations"],
                'last_deworming_date' => $validated["last_deworming_date"],
                'allergies' => $validated["allergies"],
                'medical_history' => $validated["medical_history"],
                'notes' => $validated["notes"],
                'status' => $validated["status"],

                'likely_cause' => implode(',', $likelyCause),
                'questionnaire_data' => $validated['questionnaire_data'],
            ]);
        }

        return response()->json($patient, 201);
    }

    public function updateInformation(Request $request, $id)
    {
        $patient = Patient::with("latestRecord")->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'sex' => 'required|string|in:Male,Female',
            'birthday' => 'required|date',
            'contact_number' => 'required|string',
        ]);

        // Recompute age if birthday or measured date changed
        if (isset($validated['birthday']) || isset($validated['date_measured'])) {
            $birthday = $validated['birthday'] ?? $patient->birthday;
            $date_measured = $validated['date_measured'] ?? $patient->latestRecord->date_measured;
            $validated['age'] = Carbon::parse($birthday)
                ->diffInYears(Carbon::parse($date_measured));
        }

        // Recompute nutrition if weight/height updated
        $weight = $validated['weight'] ?? $patient->latestRecord->weight;
        $height = $validated['height'] ?? $patient->latestRecord->height;
        $age = $validated['age'] ?? $patient->latestRecord->age;

        $bmi = $weight / pow($height / 100, 2);

        $wfa = $this->computeWFA($age, $weight);
        $hfa = $this->computeHFA($age, $height);
        $wfs = $this->computeWFHOrBMI($age, $bmi);

        $validated['weight_for_age'] = $wfa;
        $validated['height_for_age'] = $hfa;
        $validated['weight_for_ltht_status'] = $wfs;
        $validated['status'] = $this->computeOverallStatus($wfa, $hfa, $wfs);

        $patient->update([
            'name' => $validated["name"],
            'address' => $validated["address"],
            'sex' => $validated["sex"],
            'birthday' => $validated["birthday"],
            'contact_number' => $validated["contact_number"],
        ]);

        $latestRecord = PatientRecord::where("patient_id", $patient->id)->latest("id")->first();

        if($patient){
            $latestRecord->update([
                // 'patient_id' => $patient->id,
                // 'date_measured' => $validated["date_measured"],
                // 'weight' => $validated["weight"],
                // 'height' => $validated["height"],
                'age' => $validated["age"],
                // "weight_for_age" => $validated["weight_for_age"],
                // "height_for_age" => $validated["height_for_age"],
                // "weight_for_ltht_status" => $validated["weight_for_ltht_status"],
                
                // 'immunizations' => $validated["immunizations"],
                // 'last_deworming_date' => $validated["last_deworming_date"],
                // 'allergies' => $validated["allergies"],
                // 'medical_history' => $validated["medical_history"],
                // 'notes' => $validated["notes"],
                // 'status' => $validated["status"],
            ]);
        }

        return response()->json($patient);
    }

    //THIS IS FOR ADDING NEW RECORDS
    public function updateOrAddRecords(Request $request, $id)
    {
        $patient = Patient::with("latestRecord")->findOrFail($id);

        $validated = $request->validate([
            // 'name' => 'required|string',
            // 'address' => 'required|string',
            // 'belongs_to_ip' => 'required|string',
            // 'sex' => 'required|string|in:Male,Female',
            'birthday' => 'required|date',
            // 'contact_number' => 'required|string',

            'date_measured' => 'required|date',
            'weight' => 'required|numeric',
            'height' => 'required|numeric',

            'immunizations' => 'nullable|string',
            'last_deworming_date' => 'nullable|date',
            'allergies' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'notes' => 'nullable|string',
            'questionnaire_data' => 'nullable|array',
        ]);

        // Recompute age if birthday or measured date changed
        if (isset($validated['birthday']) || isset($validated['date_measured'])) {
            $birthday = $validated['birthday'] ?? $patient->birthday;
            $date_measured = $validated['date_measured'] ?? $patient->latestRecord->date_measured;
            $validated['age'] = Carbon::parse($birthday)
                ->diffInYears(Carbon::parse($date_measured));
        }

        // Recompute nutrition if weight/height updated
        $weight = $validated['weight'] ?? $patient->latestRecord->weight;
        $height = $validated['height'] ?? $patient->latestRecord->height;
        $age = $validated['age'] ?? $patient->latestRecord->age;

        $bmi = $weight / pow($height / 100, 2);

        $wfa = $this->computeWFA($age, $weight);
        $hfa = $this->computeHFA($age, $height);
        $wfs = $this->computeWFHOrBMI($age, $bmi);

        $validated['weight_for_age'] = $wfa;
        $validated['height_for_age'] = $hfa;
        $validated['weight_for_ltht_status'] = $wfs;
        $validated['status'] = $this->computeOverallStatus($wfa, $hfa, $wfs);

        $questionnaire = $validated['questionnaire_data'] ?? [];
        $likelyCause = $this->analyzeMalnutritionCause($validated['status'], $questionnaire);

        Log::info($questionnaire);

        // $patient->update([
        //     'name' => $validated["name"],
        //     'address' => $validated["address"],
        //     'belongs_to_ip' => $validated["belongs_to_ip"],
        //     'sex' => $validated["sex"],
        //     'birthday' => $validated["birthday"],
        //     'contact_number' => $validated["contact_number"],
        // ]);

        if($patient){
            PatientRecord::create([
                'patient_id' => $patient->id,
                'date_measured' => $validated["date_measured"],
                'weight' => $validated["weight"],
                'height' => $validated["height"],
                'age' => $validated["age"],
                "weight_for_age" => $validated["weight_for_age"],
                "height_for_age" => $validated["height_for_age"],
                "weight_for_ltht_status" => $validated["weight_for_ltht_status"],
                
                'immunizations' => $validated["immunizations"],
                'last_deworming_date' => $validated["last_deworming_date"],
                'allergies' => $validated["allergies"],
                'medical_history' => $validated["medical_history"],
                'notes' => $validated["notes"],
                'status' => $validated["status"],

                'likely_cause' => implode(',', $likelyCause),
                'questionnaire_data' => $validated['questionnaire_data'],
            ]);
        }

        return response()->json($patient);
    }

    // private function computeWFA($age, $weight)
    // {
    //     // Simplified thresholds (not real WHO Z-scores)
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
    //         return 'Severe';
    //     }

    //     if ($wfa === 'Underweight' || $wfa === 'Overweight' || $wfs === 'Obese') {
    //         return 'Moderate';
    //     }

    //     if ($hfa === 'Tall') {
    //         return 'At Risk';
    //     }

    //     return 'Healthy';
    // }

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

    private function analyzeMalnutritionCause(string $overallStatus, array $answers): array
    {
        $causes = [];

        // Only analyze if status is not Healthy
        if ($overallStatus !== 'Healthy') {

            // --- For undernutrition cases (Severe or Moderate) ---
            if (in_array($overallStatus, ['Severe', 'Moderate'])) {
                if (!empty($answers['lowIncome']))
                    $causes[] = 'Low household income / food insecurity';
                if (empty($answers['eats3Meals']))
                    $causes[] = 'Inadequate food intake or skipped meals';
                if (empty($answers['eatsVegetables']))
                    $causes[] = 'Poor diet quality (low fruits/vegetables)';
                if (!empty($answers['recentIllness']))
                    $causes[] = 'Frequent illness or infection';
                if (empty($answers['cleanWater']))
                    $causes[] = 'Unsafe water or poor sanitation';
                if (isset($answers['breastfeeding']) && !$answers['breastfeeding'])
                    $causes[] = 'Lack of breastfeeding for infant';
            }

            // --- For overweight / obesity cases ---
            if (in_array($overallStatus, ['At Risk', 'Severe'])) {
                if (!empty($answers['eats3Meals']) && empty($answers['eatsVegetables']))
                    $causes[] = 'High calorie intake with poor diet balance';
                if (empty($answers['recentIllness']) && empty($answers['lowIncome']))
                    $causes[] = 'Possible sedentary lifestyle or overeating';
            }
        }

        if (empty($causes)) {
            $causes[] = 'No significant cause detected based on questionnaire';
        }

        return $causes;
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
        ]);

        Patient::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Patients deleted successfully']);
    }
}
