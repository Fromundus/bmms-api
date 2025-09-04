<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        "wfa_underweight",
        "wfa_normal",
        "wfa_overweight",
        "hfa_stunted",
        "hfa_normal",
        "hfa_tall",
        "wfs_wasted",
        "wfs_normal",
        "wfs_obese",
    ];
}
