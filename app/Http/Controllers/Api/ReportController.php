<?php

namespace App\Http\Controllers\Api;

use App\Exports\PatientRecordsExport;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientRecord;
use Barryvdh\DomPDF\Facade\Pdf;
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

    public function export(Request $request, $type)
    {
        $from = $request->query('from_date');
        $to   = $request->query('to_date');

        $query = PatientRecord::with('patient')
            ->whereIn('id', function ($sub) use ($from, $to) {
                $sub->selectRaw('MAX(id)')
                    ->from('patient_records as pr2')
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
}
