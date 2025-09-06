<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [
        "name",
        "address",
        "belongs_to_ip",
        "sex",
        "birthday",
        "contact_number",

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

    public function records(){
        return $this->hasMany(PatientRecord::class);
    }

    public function latestRecord()
    {
        return $this->hasOne(PatientRecord::class)->latestOfMany('date_measured');
    }

    public function latestRecordInRange($from, $to)
    {
        return $this->hasOne(PatientRecord::class)
            ->whereBetween('date_measured', [$from, $to])
            ->latestOfMany('date_measured');
    }

}
