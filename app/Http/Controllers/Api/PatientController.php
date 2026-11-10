<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Setting;
use App\Services\ActivityLogService;
use App\Services\SMSService;
use App\Services\WHOAnthropometryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class PatientController extends Controller
{
    public function getStats(Request $request)
    {
        $role = auth()->user()->role;

        $auth = $request->user();

        $query = Patient::with('latestRecord');

        // 🔐 Apply role-based age filter
        if ($role === 'bns') {
            // 20 years old and below
            $query->where('barangay_id', $auth->barangay_id)->whereHas('latestRecord', function ($q) {
                $q->where('age', '<', 20);
            });
        }

        if ($role === 'bhw') {
            // 21 years old and above
            $query->where('barangay_id', $auth->barangay_id)->whereHas('latestRecord', function ($q) {
                $q->where('age', '>=', 20);
            });
        }
        // admin → no filter

        // Fetch only patients allowed for this role
        $patients = $query->get();

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

        $percent = fn ($count) => $totalPatients > 0
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

    public function getStatsByBarangay(Request $request)
    {
        $user = $request->user();
        $role = $user->role;

        $query = Patient::with([
            'barangay:id,name',
            'latestRecord'
        ]);

        // Barangay filter
        if ($user->barangay_id !== null) {
            $query->where('barangay_id', $user->barangay_id);
        }

        // Role-based age filter
        if ($role === 'bns') {
            $query->whereHas('latestRecord', function ($q) {
                $q->where('age', '<', 20);
            });
        }

        if ($role === 'bhw') {
            $query->whereHas('latestRecord', function ($q) {
                $q->where('age', '>=', 20);
            });
        }

        // Get patients
        $patients = $query->get();

        // Group patients by barangay
        $grouped = $patients->groupBy(function ($patient) {
            return $patient->barangay_id ?? 'unassigned';
        });

        $barangayStats = $grouped->map(function ($patients, $barangayId) {

            $total = $patients->count();

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

            $percent = fn($count) => $total > 0
                ? round(($count / $total) * 100, 2)
                : 0;

            return [
                'barangay_id' => $barangayId === 'unassigned'
                    ? null
                    : $barangayId,

                'barangay_name' => $patients->first()->barangay?->name
                    ?? 'Unassigned',

                'total_patients' => $total,

                'severe' => [
                    'count' => $counts['Severe'],
                    'percent' => $percent($counts['Severe']),
                ],

                'moderate' => [
                    'count' => $counts['Moderate'],
                    'percent' => $percent($counts['Moderate']),
                ],

                'at_risk' => [
                    'count' => $counts['At Risk'],
                    'percent' => $percent($counts['At Risk']),
                ],

                'healthy' => [
                    'count' => $counts['Healthy'],
                    'percent' => $percent($counts['Healthy']),
                ],
            ];
        })->values();

        return response()->json([
            'total_barangays' => $barangayStats->count(),
            'total_patients' => $patients->count(),
            'barangays' => $barangayStats,
        ]);
    }

    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage = $request->query('per_page', 1000);

        $wfa = $request->query('wfa');
        $hfa = $request->query('hfa');
        $wfltht = $request->query('wfltht');
        $status = $request->query('status');

        $query = Patient::query()->with(["latestRecord", "barangay"]);

        $role = auth()->user()->role;

        $auth = $request->user();

        if ($role === 'bns') {
            // 20 years old and below
            $query->where('barangay_id', $auth->barangay_id)->whereHas('latestRecord', function ($q) {
                $q->where('age', '<', 20);
            });
        }

        if ($role === 'bhw') {
            // 21 years old and above
            $query->where('barangay_id', $auth->barangay_id)->whereHas('latestRecord', function ($q) {
                $q->where('age', '>=', 20);
            });
        }


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
    
    public function store(Request $request, SMSService $smsservice, ActivityLogService $activityLogService, WHOAnthropometryService $who)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'barangay_id' => 'required',
            // 'address' => 'required|string',
            'sex' => 'required|string|in:Male,Female',
            'birthday' => 'required|date',
            'contact_number' => 'required|string|min:11|max:11',
            'belongs_to_ip' => 'required|boolean',
            'is_pregnant' => 'required|boolean',

            'date_measured' => 'required|date',
            'weight' => 'required|numeric',
            'height' => 'required|numeric',

            'immunizations' => 'nullable|string',
            'last_deworming_date' => 'nullable|date',
            'allergies' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'notes' => 'nullable|string',

            "birth_history" => "nullable|string",
            "past_illnesses" => "nullable|string",
            "current_medication" => "nullable|string",
            "family_medical_history" => "nullable|string",

            'questionnaire_data' => 'nullable|array',
        ]);

        $exists = Patient::where('name', $validated['name'])
            ->whereDate('birthday', $validated['birthday'])
            ->where('sex', $validated['sex'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Resident already exists.',
                'errors' => [
                    'name' => ['A resident with the same name, birthday, and sex already exists.'],
                ],
            ], 422);
        }

        $assessment = $who->assess([
            "sex" => $validated["sex"],
            "date_of_birth" => $validated["birthday"],
            "weight" => $validated["weight"],
            "height" => $validated["height"],
        ]);

        // Age in years
        $validated['age'] = Carbon::parse($validated['birthday'])
            ->diffInYears(Carbon::parse($validated['date_measured']));

        $questionnaire = $validated['questionnaire_data'] ?? [];
        $likelyCause = $this->analyzeMalnutritionCause($assessment['overall_status'], $questionnaire);

        Log::info($questionnaire);

        $patient = Patient::create(attributes: [
            'name' => $validated["name"],
            // 'address' => $validated["address"],
            'barangay_id' => $validated["barangay_id"],
            'sex' => $validated["sex"],
            'birthday' => $validated["birthday"],
            'contact_number' => $validated["contact_number"],
            'belongs_to_ip' => $validated["belongs_to_ip"],
            'is_pregnant' => $validated["is_pregnant"],
        ]);
        
        if($patient){
            PatientRecord::create([
                'patient_id' => $patient->id,
                'date_measured' => $validated["date_measured"],
                'weight' => $validated["weight"],
                'height' => $validated["height"],
                'age' => $validated["age"],
                "weight_for_age" => $assessment["wfa_status"],
                "height_for_age" => $assessment["hfa_status"],
                "weight_for_ltht_status" => $assessment["wfl_status"],
                "bmi_for_age" => $assessment["bfa_status"],
                "bmi" => $assessment["bmi_status"] ?? null,
                
                'immunizations' => $validated["immunizations"],
                'last_deworming_date' => $validated["last_deworming_date"],
                'allergies' => $validated["allergies"],
                'medical_history' => $validated["medical_history"],
                'notes' => $validated["notes"],
                'status' => $assessment["overall_status"],

                "birth_history" => $validated["birth_history"],
                "past_illnesses" => $validated["past_illnesses"],
                "current_medication" => $validated["current_medication"],
                "family_medical_history" => $validated["family_medical_history"],


                'likely_cause' => implode(',', $likelyCause),
                'questionnaire_data' => $validated['questionnaire_data'],
            ]);

            $activityLogService->log(
                auth()->id(),
                'create',
                "{$request->user()->name} created a new resident: {$patient->name}.",
                $request
            );

            /**
             * ✅ Create Notification for Admins (Monitoring)
             */
            $status = ucfirst($assessment['overall_status']);
            $causes = !empty($likelyCause)
                ? implode(', ', $likelyCause)
                : 'No likely causes identified';

            // Build a clean human-readable message
            $message = "A new patient record was added.\n\n"
                . "👤 **Name:** {$patient->name}\n"
                . "📊 **Status:** {$status}\n"
                . "🩺 **Likely Cause(s):** {$causes}\n"
                . "📅 **Measured on:** {$validated['date_measured']}";

            Notification::create([
                'title' => "New Patient Added ({$status})",
                'message' => $message,
            ]);

            // $patientMessage = "Hi {$patient->name}, your nutrition record dated {$validated['date_measured']} was added. Status: {$validated['status']}. Please follow you health worker's advice.";

            $patientMessage = "Hi {$patient->name}, your nutrition record dated {$validated['date_measured']} was added. Status: {$assessment['overall_status']}" 
            . (!empty($causes) ? ", cause(s): {$causes}." : ".") 
            . " Please follow your health worker's advice.";

            $response = $smsservice->sendSms("{$patient->contact_number}", $patientMessage);
            // $response = "";
        }

        return response()->json(["data" => $patient, "response" => $response], 200);
    }

    public function updateInformation(Request $request, $id, SMSService $smsservice, ActivityLogService $activityLogService, WHOAnthropometryService $who)
    {
        $patient = Patient::with("latestRecord")->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string',
            'barangay_id' => 'required',
            // 'address' => 'required|string',
            'sex' => 'required|string|in:Male,Female',
            'birthday' => 'required|date',
            'contact_number' => 'required|string|min:11|max:11',
            'belongs_to_ip' => 'required|boolean',
            'is_pregnant' => 'required|boolean',
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

        $assessment = $who->assess([
            "sex" => $patient->sex,
            "date_of_birth" => $patient->birthday,
            "weight" => $weight,
            "height" => $height,
        ]);

        $patient->update([
            'name' => $validated["name"],
            // 'address' => $validated["address"],
            'barangay_id' => $validated["barangay_id"],
            'sex' => $validated["sex"],
            'birthday' => $validated["birthday"],
            'contact_number' => $validated["contact_number"],
            'belongs_to_ip' => $validated["belongs_to_ip"],
            'is_pregnant' => $validated["is_pregnant"],
        ]);

        $activityLogService->log(
            auth()->id(),
            'update',
            "{$request->user()->name} updated a resident: {$patient->name}.",
            $request
        );

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

            /**
             * ✅ Create Notification for Admins (Monitoring)
             */
            $status = ucfirst($assessment['overall_status']);
            $previousStatus = ucfirst($latestRecord->status ?? 'N/A');

            // Build notification message
            $message = "Patient information has been updated.\n\n"
                . "👤 **Name:** {$patient->name}\n"
                . "📊 **Previous Status:** {$previousStatus}\n"
                . "📈 **Current Status:** {$status}\n"
                . "📅 **Last Measured:** {$latestRecord->date_measured}\n"
                . "Please review for follow-up or verification.";

            Notification::create([
                'title' => "Patient Record Updated ({$status})",
                'message' => $message,
            ]);

            // $patientMessage = "Hi {$patient->name}, your nutrition record was updated. Prev: {$previousStatus}, Now: {$status}. Keep following your health worker's advice.";

            $patientMessage = "Hi {$patient->name}, your nutrition record was updated. Prev: {$previousStatus}, Now: {$status}" 
            . (!empty($causeList) ? ", cause(s): {$causeList}." : ".") 
            . " Keep following your health worker's advice.";

            $response = $smsservice->sendSms($patient->contact_number, $patientMessage);
            // $response = "";
        }

        return response()->json(["data" => $patient, "response" => $response], 200);
    }

    //THIS IS FOR ADDING NEW RECORDS
    public function updateOrAddRecords(Request $request, $id, SMSService $smsservice, ActivityLogService $activityLogService, WHOAnthropometryService $who)
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

            "birth_history" => "nullable|string",
            "past_illnesses" => "nullable|string",
            "current_medication" => "nullable|string",
            "family_medical_history" => "nullable|string",

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

        $assessment = $who->assess([
            "sex" => $patient->sex,
            "date_of_birth" => $patient->birthday,
            "weight" => $weight,
            "height" => $height,
        ]);

        $questionnaire = $validated['questionnaire_data'] ?? [];
        $likelyCause = $this->analyzeMalnutritionCause($assessment['overall_status'], $questionnaire);

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
                "weight_for_age" => $assessment["wfa_status"],
                "height_for_age" => $assessment["hfa_status"],
                "weight_for_ltht_status" => $assessment["wfl_status"],
                "bmi_for_age" => $assessment["bfa_status"],
                "bmi" => $assessment["bmi_status"] ?? null,
                
                'immunizations' => $validated["immunizations"],
                'last_deworming_date' => $validated["last_deworming_date"],
                'allergies' => $validated["allergies"],
                'medical_history' => $validated["medical_history"],
                'notes' => $validated["notes"],
                'status' => $assessment["overall_status"],

                "birth_history" => $validated["birth_history"],
                "past_illnesses" => $validated["past_illnesses"],
                "current_medication" => $validated["current_medication"],
                "family_medical_history" => $validated["family_medical_history"],

                'likely_cause' => implode(',', $likelyCause),
                'questionnaire_data' => $validated['questionnaire_data'],
            ]);

            $activityLogService->log(
                auth()->id(),
                'create',
                "{$request->user()->name} created a new record for resident: {$patient->name}.",
                $request
            );

            /**
             * ✅ Create Notification for Admin Monitoring
             */
            $status = ucfirst($assessment["overall_status"]);
            $causeList = implode(', ', $likelyCause) ?: 'No likely cause identified';

            $message = "A new record has been added for **{$patient->name}**.\n\n"
                . "📅 **Date Measured:** {$validated['date_measured']}\n"
                . "📊 **Nutritional Status:** {$status}\n"
                . "🩺 **Possible Cause(s):** {$causeList}\n"
                . "Please review for appropriate intervention or follow-up.";

            Notification::create([
                'title' => "New Patient Record ({$status})",
                'message' => $message,
            ]);

            $patientMessage = "Hi {$patient->name}, your nutrition record ({$validated['date_measured']}) was added. Status: {$status}" 
                . (!empty($causeList) ? ", cause(s): {$causeList}." : ".") 
                . " Please follow your health worker's advice.";


            $response = $smsservice->sendSms($patient->contact_number, $patientMessage);
            // $response = "";
        }

        return response()->json(["data" => $patient, "response" => $response], 200);
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

    public function delete(Request $request, ActivityLogService $activityLogService)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
        ]);

        Patient::whereIn('id', $validated['ids'])->delete();

        $activityLogService->log(
            auth()->id(),
            'delete',
            "{$request->user()->name} deleted a resident.",
            $request
        );

        return response()->json(['message' => 'Patients deleted successfully']);
    }
}
