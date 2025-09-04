<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    // public function getStats(){
    //     $totalPatients = Patient::count();

    //     $severe = Patient::where("status", "Severe")->count();
    //     $moderate = Patient::where("status", "Moderate")->count();
    //     $atRisk = Patient::where("status", "At Risk")->count();
    //     $healthy = Patient::where("status", "Healthy")->count();

    //     return response()->json([
    //         'total_patients' => $totalPatients,
    //         'severe' => $severe,
    //         'moderate' => $moderate,
    //         'at_risk' => $atRisk,
    //         'healthy' => $healthy,
    //     ]);
    // }

    public function getStats()
    {
        $totalPatients = Patient::count();

        $severe = Patient::where("status", "Severe")->count();
        $moderate = Patient::where("status", "Moderate")->count();
        $atRisk = Patient::where("status", "At Risk")->count();
        $healthy = Patient::where("status", "Healthy")->count();

        // Prevent division by zero
        $percent = function ($count) use ($totalPatients) {
            return $totalPatients > 0 ? round(($count / $totalPatients) * 100, 2) : 0;
        };

        return response()->json([
            'total_patients' => $totalPatients,

            'severe' => [
                'count' => $severe,
                'percent' => $percent($severe)
            ],

            'moderate' => [
                'count' => $moderate,
                'percent' => $percent($moderate)
            ],

            'at_risk' => [
                'count' => $atRisk,
                'percent' => $percent($atRisk)
            ],

            'healthy' => [
                'count' => $healthy,
                'percent' => $percent($healthy)
            ],
        ]);
    }


    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);

        $query = Patient::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'belongs_to_ip' => 'required|string',
            'sex' => 'required|string|in:Male,Female',
            'birthday' => 'required|date',
            'date_measured' => 'required|date',
            'weight' => 'required|integer',
            'height' => 'required|integer',
            'contact_number' => 'required|string',

            'immunizations' => 'nullable|string',
            'last_deworming_date' => 'nullable|date',
            'allergies' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Calculate age
        $validated['age'] = Carbon::parse($validated['birthday'])
            ->diffInYears(Carbon::parse($validated['date_measured']));

        // Fetch thresholds
        $settings = Setting::firstOrFail();

        // Compute nutritional status
        $validated['weight_for_age'] = $this->computeWFA($validated['weight'], $settings);
        $validated['height_for_age'] = $this->computeHFA($validated['height'], $settings);
        $validated['weight_for_ltht_status'] = $this->computeWFS($validated['weight'], $settings);

        // Compute overall status
        $validated['status'] = $this->computeOverallStatus(
            $validated['weight_for_age'],
            $validated['height_for_age'],
            $validated['weight_for_ltht_status']
        );

        $patient = Patient::create($validated);

        return response()->json($patient, 201);
    }

    public function show($id){
        $patient = Patient::findOrFail($id);

        return response()->json($patient);
    }

    public function update(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'address' => 'sometimes|string',
            'belongs_to_ip' => 'sometimes|string',
            'sex' => 'sometimes|string|in:Male,Female',
            'birthday' => 'sometimes|date',
            'date_measured' => 'sometimes|date',
            'weight' => 'sometimes|integer',
            'height' => 'sometimes|integer',
            'contact_number' => 'sometimes|string',

            'immunizations' => 'nullable|sometimes|string',
            'last_deworming_date' => 'nullable|date',
            'allergies' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'notes' => 'nullable|sometimes|string',
        ]);

        // Recompute age if needed
        if (isset($validated['birthday']) || isset($validated['date_measured'])) {
            $birthday = $validated['birthday'] ?? $patient->birthday;
            $date_measured = $validated['date_measured'] ?? $patient->date_measured;
            $validated['age'] = Carbon::parse($birthday)
                ->diffInYears(Carbon::parse($date_measured));
        }

        // Recompute nutrition + status if weight/height changed
        if (isset($validated['weight']) || isset($validated['height'])) {
            $settings = Setting::firstOrFail();
            $weight = $validated['weight'] ?? $patient->weight;
            $height = $validated['height'] ?? $patient->height;

            $wfa = $this->computeWFA($weight, $settings);
            $hfa = $this->computeHFA($height, $settings);
            $wfs = $this->computeWFS($weight, $settings);

            $validated['weight_for_age'] = $wfa;
            $validated['height_for_age'] = $hfa;
            $validated['weight_for_ltht_status'] = $wfs;

            $validated['status'] = $this->computeOverallStatus($wfa, $hfa, $wfs);
        }

        $patient->update($validated);

        return response()->json($patient);
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

    /**
     * Compute overall patient status color.
     */
    private function computeOverallStatus($wfa, $hfa, $wfs)
    {
        // Severe red cases
        if (in_array('Wasted', [$wfs]) || in_array('Stunted', [$hfa])) {
            return 'Severe';
        }

        // Moderate orange cases
        if ($wfa === 'Underweight' || $wfa === 'Overweight' || $wfs === 'Obese') {
            return 'Moderate';
        }

        // At risk yellow
        if ($hfa === 'Tall') {
            return 'At Risk';
        }

        // Healthy
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
