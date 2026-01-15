<?php

namespace App\Http\Controllers\Api;

use App\Exports\PatientRecordsExport;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
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

    public function generateReport()
    {
        // 1. Fetch database records
        $children = Patient::with("latestRecord")->get();

        // 2. Convert dataset to JSON
        $dataset = json_encode($children);

        // 3. Build prompt
        $prompt = "
            You are a nutrition specialist. Based on the following nutritional dataset,
            generate a Barangay Nutritional Status Solution Report.

            DATA:
            $dataset

            Include:
            - Summary of statistics (weight, height, BMI, age groups)
            - Underweight, stunting, wasting prevalence
            - Identify high-risk age groups
            - Nutritional deficiencies observed
            - Recommended immediate interventions
            - Long-term barangay-level strategies
            - Suggested feeding programs
        ";

        // 4. Call Gemini API
        $client = new Client();
        $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . 'AIzaSyCHhGWj3YK0X18P37DuhTvbJa1_lx1z6G8', [
            'json' => [
                "contents" => [
                    ["parts" => [["text" => $prompt]]]
                ]
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        // Extract generated text
        $reportText = $result['candidates'][0]['content']['parts'][0]['text'] ?? "No report generated";

        return response()->json([
            'report' => $reportText
        ]);
    }

}
