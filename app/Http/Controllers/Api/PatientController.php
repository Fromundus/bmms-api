<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PatientController extends Controller
{
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
            'total'      => Patient::count(),
        ];

        return response()->json([
            'patients' => $patients,
            'counts' => $counts,
        ]);
    }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'name' => 'required|string',
    //         'address' => 'required|string',
    //         'belongs_to_ip' => 'required|string',
    //         'sex' => 'required|string|in:Male,Female',
    //         'birthday' => 'required|date',
    //         'date_measured' => 'required|date',
    //         'weight' => 'required|integer',
    //         'height' => 'required|integer',
    //         'contact_number' => 'required|string',
    //     ]);

    //     // Calculate age
    //     $validated['age'] = \Carbon\Carbon::parse($validated['birthday'])
    //         ->diffInYears(\Carbon\Carbon::parse($validated['date_measured']));

    //     // Fetch thresholds
    //     $settings = Setting::firstOrFail();

    //     // Compute categories
    //     $validated['weight_for_age'] = $this->computeWFA($validated['weight'], $settings);
    //     $validated['height_for_age'] = $this->computeHFA($validated['height'], $settings);
    //     $validated['weight_for_ltht_status'] = $this->computeWFS($validated['weight'], $settings);

    //     $patient = Patient::create($validated);

    //     return response()->json($patient, 201);
    // }

    // /**
    //  * Update existing patient.
    //  */
    // public function update(Request $request, $id)
    // {
    //     $patient = Patient::findOrFail($id);

    //     $validated = $request->validate([
    //         'name' => 'sometimes|string',
    //         'address' => 'sometimes|string',
    //         'belongs_to_ip' => 'sometimes|string',
    //         'sex' => 'sometimes|string|in:Male,Female',
    //         'birthday' => 'sometimes|date',
    //         'date_measured' => 'sometimes|date',
    //         'weight' => 'sometimes|integer',
    //         'height' => 'sometimes|integer',
    //         'contact_number' => 'sometimes|string',
    //     ]);

    //     // Recompute if weight/height/birthday changed
    //     if (isset($validated['birthday']) || isset($validated['date_measured'])) {
    //         $birthday = $validated['birthday'] ?? $patient->birthday;
    //         $date_measured = $validated['date_measured'] ?? $patient->date_measured;
    //         $validated['age'] = \Carbon\Carbon::parse($birthday)
    //             ->diffInYears(\Carbon\Carbon::parse($date_measured));
    //     }

    //     if (isset($validated['weight']) || isset($validated['height'])) {
    //         $settings = Setting::firstOrFail();
    //         $weight = $validated['weight'] ?? $patient->weight;
    //         $height = $validated['height'] ?? $patient->height;

    //         $validated['weight_for_age'] = $this->computeWFA($weight, $settings);
    //         $validated['height_for_age'] = $this->computeHFA($height, $settings);
    //         $validated['weight_for_ltht_status'] = $this->computeWFS($weight, $settings);
    //     }

    //     $patient->update($validated);

    //     return response()->json($patient);
    // }

    // /**
    //  * Helpers for computing status.
    //  */
    // private function computeWFA($weight, $settings)
    // {
    //     if ($weight <= $settings->wfa_underweight) return 'Underweight';
    //     if ($weight <= $settings->wfa_normal) return 'Normal';
    //     return 'Overweight';
    // }

    // private function computeHFA($height, $settings)
    // {
    //     if ($height <= $settings->hfa_stunted) return 'Stunted';
    //     if ($height <= $settings->hfa_normal) return 'Normal';
    //     return 'Tall';
    // }

    // private function computeWFS($weight, $settings)
    // {
    //     if ($weight <= $settings->wfs_wasted) return 'Wasted';
    //     if ($weight <= $settings->wfs_normal) return 'Normal';
    //     return 'Obese';
    // }

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

            'immunizations' => 'required|string',
            'last_deworming_date' => 'nullable|date',
            'allergies' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'notes' => 'required|string',
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

        $patient = Patient::create($validated);

        return response()->json($patient, 201);
    }

    /**
     * Update an existing patient.
     */
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

            'immunizations' => 'sometimes|string',
            'last_deworming_date' => 'nullable|date',
            'allergies' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'notes' => 'sometimes|string',
        ]);

        // Recompute age if birthday or date_measured changed
        if (isset($validated['birthday']) || isset($validated['date_measured'])) {
            $birthday = $validated['birthday'] ?? $patient->birthday;
            $date_measured = $validated['date_measured'] ?? $patient->date_measured;
            $validated['age'] = Carbon::parse($birthday)
                ->diffInYears(Carbon::parse($date_measured));
        }

        // Recompute nutrition if weight/height changed
        if (isset($validated['weight']) || isset($validated['height'])) {
            $settings = Setting::firstOrFail();
            $weight = $validated['weight'] ?? $patient->weight;
            $height = $validated['height'] ?? $patient->height;

            $validated['weight_for_age'] = $this->computeWFA($weight, $settings);
            $validated['height_for_age'] = $this->computeHFA($height, $settings);
            $validated['weight_for_ltht_status'] = $this->computeWFS($weight, $settings);
        }

        $patient->update($validated);

        return response()->json($patient);
    }

    /**
     * Helpers for computing status based on settings.
     */
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

    public function delete(Request $request){
        $validated = $request->validate([
            'ids' => 'required|array',
        ]);

        $patients = Patient::whereIn('id', $validated['ids'])->get();

        Patient::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Patients deleted successfully']);
    }
}
