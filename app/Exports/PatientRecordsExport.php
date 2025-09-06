<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PatientRecordsExport implements FromView
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function view(): View
    {
        return view('reports.patients_excel', [
            'records' => $this->records
        ]);
    }
}
