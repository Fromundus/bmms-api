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
        "date_measured",
        "weight",
        "height",
        "age",
        "weight_for_age",
        "height_for_age",
        "weight_for_ltht_status",
        "contact_number",

        "immunizations",
        "last_deworming_date",
        "allergies",
        "medical_history",
        "notes",
    ];
}
