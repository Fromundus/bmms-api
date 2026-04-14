<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    protected $fillable = [
        'report_type',
        'start_date',
        'end_date',
        'total_population',

        // Children
        'child_total',
        'child_underweight',
        'child_stunted',
        'child_wasted',
        'child_overweight',
        'child_obese',
        'child_healthy',
        'child_at_risk',
        'child_moderate',
        'child_severe',

        // Adults
        'adult_total',
        'adult_wasted',
        'adult_overweight',
        'adult_obese',
        'adult_healthy',
        'adult_at_risk',
        'adult_moderate',
        'adult_severe',

        // DSS
        'remark',
        'recommendations',
        'chart_data'
    ];

    protected $casts = [
        'recommendations' => 'array',
        'chart_data' => 'array',
    ];
}
