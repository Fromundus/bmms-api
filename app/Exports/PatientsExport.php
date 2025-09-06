<?php

// app/Exports/PatientsExport.php
namespace App\Exports;

use App\Models\Patient;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PatientsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Patient::all([
            'id',
            'name',
            'address',
            'belongs_to_ip',
            'sex',
            'birthday',
            'date_measured',
            'weight',
            'height',
            'age',
            'weight_for_age',
            'height_for_age',
            'weight_for_ltht_status',
            'contact_number',
            'immunizations',
            'last_deworming_date',
            'allergies',
            'medical_history',
            'notes',
            'status',
        ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Address',
            'Belongs To IP',
            'Sex',
            'Birthday',
            'Date Measured',
            'Weight',
            'Height',
            'Age',
            'Weight for Age',
            'Height for Age',
            'Weight for LTHt Status',
            'Contact Number',
            'Immunizations',
            'Last Deworming Date',
            'Allergies',
            'Medical History',
            'Notes',
            'Status',
        ];
    }
}
