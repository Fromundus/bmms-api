<?php

namespace App\Http\Controllers\Api;

use App\Exports\PatientRecordsExport;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(){
        $reports = Report::orderByDesc('id')->get();

        return response()->json($reports);
    }

    // public function export(Request $request, $type)
    // {
    //     $from = $request->query('from_date');
    //     $to = $request->query('to_date');

    //     $query = PatientRecord::with('patient');

    //     if ($from && $to) {
    //         $query->whereBetween('date_measured', [$from, $to]);
    //     }

    //     $records = $query->orderBy('date_measured', 'desc')->get();

    //     if ($type === 'excel') {
    //         return Excel::download(new PatientRecordsExport($records), 'patients.xlsx');
    //     }

    //     if ($type === 'pdf') {
    //         $pdf = Pdf::loadView('reports.patients', ['records' => $records]);
    //         return $pdf->download('patients.pdf');
    //     }

    //     return response()->json(['error' => 'Invalid export type'], 400);
    // }

    // public function export(Request $request, $type)
    // {
    //     $from = $request->query('from_date');
    //     $to   = $request->query('to_date');

    //     $query = PatientRecord::with('patient')
    //         ->whereIn('id', function ($sub) use ($from, $to) {
    //             $sub->selectRaw('MAX(id)')
    //                 ->from('patient_records as pr2')
    //                 ->when($from && $to, function ($q) use ($from, $to) {
    //                     $q->whereBetween('date_measured', [$from, $to]);
    //                 })
    //                 ->groupBy('patient_id');
    //         });

    //     $records = $query->orderBy('date_measured', 'desc')->get();

    //     if ($type === 'excel') {
    //         return Excel::download(new PatientRecordsExport($records), 'patients.xlsx');
    //     }

    //     if ($type === 'pdf') {
    //         $pdf = Pdf::loadView('reports.patients', ['records' => $records]);
    //         return $pdf->download('patients.pdf');
    //     }

    //     return response()->json(['error' => 'Invalid export type'], 400);
    // }

    public function export(Request $request, $type)
    {
        $from = $request->query('from_date');
        $to   = $request->query('to_date');

        $role = auth()->user()->role;

        $query = PatientRecord::with('patient')
            ->whereIn('id', function ($sub) use ($from, $to, $role) {
                $sub->selectRaw('MAX(id)')
                    ->from('patient_records as pr2')

                    // 🔐 Role-based age filter
                    ->when($role === 'bns', function ($q) {
                        $q->where('age', '<', 20);
                    })
                    ->when($role === 'bhw', function ($q) {
                        $q->where('age', '>=', 20);
                    })

                    // 📅 Date filter
                    ->when($from && $to, function ($q) use ($from, $to) {
                        $q->whereBetween('date_measured', [$from, $to]);
                    })

                    ->groupBy('patient_id');
            });

        $records = $query->orderBy('date_measured', 'desc')->get();

        if ($type === 'excel') {
            return Excel::download(new PatientRecordsExport($records), 'patients.xlsx');
        }

        if ($type === 'pdf') {
            $pdf = Pdf::loadView('reports.patients', ['records' => $records]);
            return $pdf->download('patients.pdf');
        }

        return response()->json(['error' => 'Invalid export type'], 400);
    }

    // public function generateReport()
    // {
    //     // 1. Fetch database records
    //     $children = Patient::with("latestRecord")->get();

    //     // 2. Convert dataset to JSON
    //     $dataset = json_encode($children);

    //     // 3. Build prompt
    //     $prompt = "
    //         You are a nutrition specialist. Based on the following nutritional dataset,
    //         generate a Barangay Nutritional Status Solution Report.

    //         DATA:
    //         $dataset

    //         Include:
    //         - Summary of statistics (weight, height, BMI, age groups)
    //         - Underweight, stunting, wasting prevalence
    //         - Identify high-risk age groups
    //         - Nutritional deficiencies observed
    //         - Recommended immediate interventions
    //         - Long-term barangay-level strategies
    //         - Suggested feeding programs
    //     ";

    //     // 4. Call Gemini API
    //     $client = new Client();
    //     $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=AIzaSyCHhGWj3YK0X18P37DuhTvbJa1_lx1z6G8", [
    //         'json' => [
    //             "contents" => [
    //                 [
    //                     "parts" => [
    //                         ["text" => $prompt]
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     ]);


    //     $result = json_decode($response->getBody(), true);

    //     // Extract generated text
    //     $reportText = $result['candidates'][0]['content']['parts'][0]['text'] ?? "No report generated";

    //     return response()->json([
    //         'report' => $reportText
    //     ]);
    // }

    // public function generateReport()
    // {
    //     // 1. Fetch all patients with their latest record
    //     $patients = Patient::with("latestRecord")->get();

    //     // If no patients exist
    //     if ($patients->isEmpty()) {
    //         return response()->json([
    //             'report' => "No patient data found to generate a report."
    //         ]);
    //     }

    //     // 2. Summarize the dataset
    //     $summary = [
    //         "total_population" => $patients->count(),
    //         "age_groups" => [
    //             "0_19" => 0,
    //             "20_above" => 0,
    //         ],
    //         "wfa_status" => [],
    //         "hfa_status" => [],
    //         "wfh_bmi_status" => [],
    //         "overall_status" => [],
    //     ];

    //     $wfaCounts = [];
    //     $hfaCounts = [];
    //     $wfhCounts = [];
    //     $overallCounts = [];


    //     foreach ($patients as $patient) {
    //         $record = $patient->latestRecord;
    //         if (!$record) continue;

    //         $age = $patient->age;
    //         $weight = $record->weight;
    //         $height = $record->height;

    //         // BMI computed
    //         $bmi = $height > 0 ? $weight / pow($height / 100, 2) : null;

    //         // Apply your computation rules
    //         $wfa = $this->computeWFA($age, $weight);
    //         $hfa = $this->computeHFA($age, $height);
    //         $wfh = $this->computeWFHOrBMI($age, $bmi);
    //         $overall = $this->computeOverallStatus($wfa, $hfa, $wfh, $bmi);

    //         // Age group classification
    //         if ($age <= 19) $summary["age_groups"]["0_19"]++;
    //         else $summary["age_groups"]["20_above"]++;

    //         // Count statuses
    //         $wfaCounts[$wfa] = ($wfaCounts[$wfa] ?? 0) + 1;
    //         $hfaCounts[$hfa] = ($hfaCounts[$hfa] ?? 0) + 1;
    //         $wfhCounts[$wfh] = ($wfhCounts[$wfh] ?? 0) + 1;
    //         $overallCounts[$overall] = ($overallCounts[$overall] ?? 0) + 1;
    //     }

    //     // 3. Prepare cleaned summary
    //     $summary["wfa_status"] = $wfaCounts;
    //     $summary["hfa_status"] = $hfaCounts;
    //     $summary["wfh_bmi_status"] = $wfhCounts;
    //     $summary["overall_status"] = $overallCounts;

    //     // Convert summary to readable text
    //     $summaryText = json_encode($summary);

    //     // 4. SHORT AI Prompt
    //     $prompt = "
    //     You are a licensed nutrition specialist. Based ONLY on this summarized dataset:

    //     SUMMARY:
    //     $summaryText

    //     Generate a Barangay Nutritional Status Solution Report including:
    //     - Clear summary of current situation
    //     - Key issues identified
    //     - High-risk groups
    //     - Recommended immediate actions
    //     - Suggested long-term strategies
    //     - Community-level programs to implement

    //     Keep the report practical and easy to understand.
    //     ";

    //     // 5. Call Gemini API
    //     $client = new Client();
    //     $response = $client->post(
    //         "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=AIzaSyCHhGWj3YK0X18P37DuhTvbJa1_lx1z6G8",
    //         [
    //             'json' => [
    //                 "contents" => [
    //                     [
    //                         "parts" => [
    //                             ["text" => $prompt]
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     );

    //     $result = json_decode($response->getBody(), true);

    //     $reportText = $result['candidates'][0]['content']['parts'][0]['text'] ?? "No report generated.";

    //     return response()->json([
    //         'report' => $reportText
    //     ]);
    // }


    public function generateReport()
    {
        // 1. Fetch all patients with their latest record
        $patients = Patient::with("latestRecord")->get();

        if ($patients->isEmpty()) {
            return response()->json([
                'report' => "No patient data found to generate a report."
            ]);
        }

        // 2. Initialize summary structure
        $summary = [
            "report_date" => now(),
            "total_population" => $patients->count(),

            "age_groups" => [
                "0_19" => 0,
                "20_above" => 0,
            ],

            "wfa_status" => [],
            "hfa_status" => [],
            "wfh_bmi_status" => [],
            "overall_status" => [],
        ];

        // Temporary count arrays
        $wfaCounts = [];
        $hfaCounts = [];
        $wfhCounts = [];
        $overallCounts = [];

        foreach ($patients as $patient) {
            $record = $patient->latestRecord;
            if (!$record) continue;

            $age = $patient->age;

            // Age grouping
            if ($age <= 19) $summary["age_groups"]["0_19"]++;
            else $summary["age_groups"]["20_above"]++;

            // Extract already computed fields
            $wfa = $record->weight_for_age;
            $hfa = $record->height_for_age;
            $wfh = $record->weight_for_ltht_status;
            $overall = $record->status;

            // Count occurrences
            $wfaCounts[$wfa] = ($wfaCounts[$wfa] ?? 0) + 1;
            $hfaCounts[$hfa] = ($hfaCounts[$hfa] ?? 0) + 1;
            $wfhCounts[$wfh] = ($wfhCounts[$wfh] ?? 0) + 1;
            $overallCounts[$overall] = ($overallCounts[$overall] ?? 0) + 1;
        }

        // Assign final counts to summary
        $summary["wfa_status"] = $wfaCounts;
        $summary["hfa_status"] = $hfaCounts;
        $summary["wfh_bmi_status"] = $wfhCounts;
        $summary["overall_status"] = $overallCounts;

        // Convert to text for AI
        $summaryText = json_encode($summary, JSON_PRETTY_PRINT);

        // 3. Final prompt for Gemini AI
        $prompt = "
    You are a licensed nutrition specialist. Based ONLY on the summarized dataset below, generate a comprehensive Barangay Nutritional Status Monitoring Report.

    ===============================================
    DATA SUMMARY
    ===============================================
    $summaryText
    ===============================================

    Your output must include:

    1. **Overall Nutritional Situation**
    - Trends, patterns, distribution of cases.

    2. **Breakdown by Age Groups**
    - 0–19 years old
    - 20 and above
    - Which age group is more at risk?

    3. **Interpretation of Status Categories**
    - Weight-for-age
    - Height-for-age
    - Weight-for-length/height or BMI
    - Overall health status categories

    4. **High-Risk Groups**
    - Identify severely wasted/stunted/underweight individuals.
    - Explain why these groups are critical.

    5. **Key Problems Identified**
    - Major concerns observed based on the distribution.

    6. **Immediate Actions (Short-Term)**
    - What the barangay must do in the next 30–60 days.

    7. **Long-Term Interventions**
    - Sustainable programs for nutrition improvement.

    8. **Recommended Barangay Programs**
    - Feeding programs
    - Nutrition education
    - Health screening
    - Community campaigns

    Use simple, clear language understandable by barangay officials and community workers.
    ";

        // 4. Call Gemini API
        $client = new Client();
        $response = $client->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=AIzaSyCHhGWj3YK0X18P37DuhTvbJa1_lx1z6G8",
            [
                'json' => [
                    "contents" => [
                        [
                            "parts" => [
                                ["text" => $prompt]
                            ]
                        ]
                    ]
                ]
            ]
        );

        $result = json_decode($response->getBody(), true);

        $reportText = $result['candidates'][0]['content']['parts'][0]['text']
            ?? "No report generated. Please try again.";

        
        Report::create([
            'body' => $reportText,
        ]);

        return response()->json([
            'report' => $reportText
        ]);
    }
}
