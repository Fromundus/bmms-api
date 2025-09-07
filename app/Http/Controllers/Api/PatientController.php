<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
            'belongs_to_ip' => 'required|string',
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

        $patient = Patient::create(attributes: [
            'name' => $validated["name"],
            'address' => $validated["address"],
            'belongs_to_ip' => $validated["belongs_to_ip"],
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
            'belongs_to_ip' => 'required|string',
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
            'belongs_to_ip' => $validated["belongs_to_ip"],
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
            ]);
        }

        return response()->json($patient);
    }

    private function computeWFA($age, $weight)
    {
        // Simplified thresholds (not real WHO Z-scores)
        if ($age < 5 && $weight < 10) return 'Underweight';
        if ($age < 10 && $weight < 20) return 'Underweight';
        if ($age >= 10 && $weight < 40) return 'Underweight';

        if ($weight > 80) return 'Overweight';

        return 'Normal';
    }

    private function computeHFA($age, $height)
    {
        if ($age < 5 && $height < 85) return 'Stunted';
        if ($age < 10 && $height < 120) return 'Stunted';
        if ($age >= 10 && $height < 150) return 'Stunted';

        if ($height > 190) return 'Tall';

        return 'Normal';
    }

    private function computeWFHOrBMI($age, $bmi)
    {
        if ($age < 20) {
            if ($bmi < 14) return 'Wasted';
            if ($bmi > 21) return 'Obese';
            return 'Normal';
        } else {
            if ($bmi < 18.5) return 'Wasted';
            if ($bmi >= 30) return 'Obese';
            return 'Normal';
        }
    }

    private function computeOverallStatus($wfa, $hfa, $wfs)
    {
        if ($wfs === 'Wasted' || $hfa === 'Stunted') {
            return 'Severe';
        }

        if ($wfa === 'Underweight' || $wfa === 'Overweight' || $wfs === 'Obese') {
            return 'Moderate';
        }

        if ($hfa === 'Tall') {
            return 'At Risk';
        }

        return 'Healthy';
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
