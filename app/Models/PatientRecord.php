<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientRecord extends Model
{
    protected $fillable = [
        "patient_id",
        "date_measured",
        "weight",
        "height",
        "age",
        "weight_for_age",
        "height_for_age",
        "weight_for_ltht_status",

        "immunizations",
        "last_deworming_date",
        "allergies",
        "medical_history",
        "notes",

        "status",
    ];

    public function patient(){
        return $this->belongsTo(Patient::class);
    }
}
