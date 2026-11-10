<?php

namespace App\Services;

use App\Models\Summary;
use Illuminate\Support\Facades\DB;

class SummaryService
{
    public function generateReport($startDate = null, $endDate = null)
    {
        /*
        |--------------------------------------------------------------------------
        | GET LATEST PATIENT RECORDS
        |--------------------------------------------------------------------------
        */

        $latestRecords = DB::table('patient_records as pr')
            ->join('patients as p', 'pr.patient_id', '=', 'p.id')
            ->joinSub(
                DB::table('patient_records')
                    ->select('patient_id', DB::raw('MAX(date_measured) as latest_date'))
                    ->groupBy('patient_id'),
                'latest',
                function ($join) {
                    $join->on('pr.patient_id', '=', 'latest.patient_id')
                        ->on('pr.date_measured', '=', 'latest.latest_date');
                }
            )
            ->select(
                'pr.*',
                'p.is_pregnant'
            )
            ->get();

        /*
        |--------------------------------------------------------------------------
        | SEPARATE THE THREE GROUPS
        |--------------------------------------------------------------------------
        */

        // Pregnant women are identified first, regardless of age.
        $pregnantWomen = $latestRecords->filter(
            fn ($r) => (int) $r->is_pregnant === 1
        );

        // Children who are not pregnant.
        $children = $latestRecords->filter(
            fn ($r) => (int) $r->age < 20 &&
                       (int) $r->is_pregnant !== 1
        );

        // Adults who are not pregnant.
        $adults = $latestRecords->filter(
            fn ($r) => (int) $r->age >= 20 &&
                       (int) $r->is_pregnant !== 1
        );

        /*
        |--------------------------------------------------------------------------
        | STATUS COUNTER
        |--------------------------------------------------------------------------
        */

        $countStatuses = function ($records) {
            $counts = [
                'healthy' => 0,
                'at_risk' => 0,
                'moderate' => 0,
                'severe' => 0,
            ];

            foreach ($records as $r) {
                $status = strtolower(trim((string) $r->status));

                $status = str_replace(' ', '_', $status);

                if (array_key_exists($status, $counts)) {
                    $counts[$status]++;
                }
            }

            return $counts;
        };

        /*
        |--------------------------------------------------------------------------
        | CHILDREN (0-19)
        |--------------------------------------------------------------------------
        */

        $child = array_merge([
            'total' => $children->count(),
            'underweight' => 0,
            'stunted' => 0,
            'wasted' => 0,
            'overweight' => 0,
            'obese' => 0,
        ], $countStatuses($children));

        foreach ($children as $r) {
            if (str_contains((string) $r->weight_for_age, 'Underweight')) {
                $child['underweight']++;
            }

            if (str_contains((string) $r->height_for_age, 'Stunted')) {
                $child['stunted']++;
            }

            if (str_contains((string) $r->weight_for_ltht_status, 'Wasted')) {
                $child['wasted']++;
            }

            if ($r->weight_for_ltht_status === 'Overweight') {
                $child['overweight']++;
            }

            if ($r->weight_for_ltht_status === 'Obese') {
                $child['obese']++;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ADULTS (20+)
        |--------------------------------------------------------------------------
        */

        $adult = array_merge([
            'total' => $adults->count(),
            'wasted' => 0,
            'overweight' => 0,
            'obese' => 0,
        ], $countStatuses($adults));

        foreach ($adults as $r) {
            if (str_contains((string) $r->weight_for_ltht_status, 'Wasted')) {
                $adult['wasted']++;
            }

            if ($r->weight_for_ltht_status === 'Overweight') {
                $adult['overweight']++;
            }

            if ($r->weight_for_ltht_status === 'Obese') {
                $adult['obese']++;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PREGNANT WOMEN
        |--------------------------------------------------------------------------
        */

        $pregnant = array_merge([
            'total' => $pregnantWomen->count(),
        ], $countStatuses($pregnantWomen));

        /*
        |--------------------------------------------------------------------------
        | TOTAL POPULATION
        |--------------------------------------------------------------------------
        */

        $total = $latestRecords->count();

        /*
        |--------------------------------------------------------------------------
        | DSS RECOMMENDATIONS
        |--------------------------------------------------------------------------
        */

        $recommendations = [];

        /*
        |--------------------------------------------------------------------------
        | CHILDREN ANALYSIS
        |--------------------------------------------------------------------------
        */

        $childMalnutritionRate = $child['total'] > 0
            ? (
                $child['underweight'] +
                $child['stunted'] +
                $child['wasted']
            ) / $child['total']
            : 0;

        $childSevereRate = $child['total'] > 0
            ? $child['severe'] / $child['total']
            : 0;

        if ($childMalnutritionRate > 0.30) {
            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'critical',
                'message' => 'Severe child malnutrition crisis detected',
                'actions' => [
                    'Emergency feeding programs',
                    'Nutritional supplementation (vitamins, iron)',
                    'Household-level intervention',
                    'Medical screening for severe cases',
                ],
            ];
        } elseif ($childMalnutritionRate > 0.15) {
            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'high',
                'message' => 'Moderate child malnutrition levels',
                'actions' => [
                    'Barangay feeding programs',
                    'Parent nutrition seminars',
                    'Growth monitoring campaigns',
                ],
            ];
        }

        if ($child['total'] > 0 &&
            $child['stunted'] > ($child['total'] * 0.25)) {
            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'high',
                'message' => 'High stunting prevalence',
                'actions' => [
                    'Long-term nutrition programs',
                    'Maternal health education',
                    'Early childhood intervention',
                ],
            ];
        }

        if ($child['total'] > 0 &&
            $child['obese'] > ($child['total'] * 0.10)) {
            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'medium',
                'message' => 'Increasing childhood obesity',
                'actions' => [
                    'Promote physical activity in schools',
                    'Healthy eating campaigns',
                    'Reduce sugary food consumption',
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | ADULT ANALYSIS
        |--------------------------------------------------------------------------
        */

        $adultObesityRate = $adult['total'] > 0
            ? $adult['obese'] / $adult['total']
            : 0;

        $adultSevereRate = $adult['total'] > 0
            ? $adult['severe'] / $adult['total']
            : 0;

        if ($adultObesityRate > 0.25) {
            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'high',
                'message' => 'High adult obesity rate',
                'actions' => [
                    'Community fitness programs',
                    'Diet monitoring initiatives',
                    'Workplace wellness campaigns',
                ],
            ];
        } elseif ($adultObesityRate > 0.15) {
            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'medium',
                'message' => 'Rising obesity cases',
                'actions' => [
                    'Exercise programs',
                    'Diet awareness campaigns',
                ],
            ];
        }

        if ($adult['total'] > 0 &&
            $adult['wasted'] > ($adult['total'] * 0.10)) {
            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'medium',
                'message' => 'Adult undernutrition detected',
                'actions' => [
                    'Food assistance programs',
                    'Nutritional counseling',
                    'Health check-ups',
                ],
            ];
        }

        if ($adult['total'] > 0 &&
            $adult['at_risk'] > ($adult['total'] * 0.20)) {
            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'low',
                'message' => 'Large at-risk population',
                'actions' => [
                    'Preventive health education',
                    'Regular BMI monitoring',
                    'Lifestyle awareness programs',
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | PREGNANT WOMEN ANALYSIS
        |--------------------------------------------------------------------------
        */

        $pregnantRiskRate = $pregnant['total'] > 0
            ? $pregnant['at_risk'] / $pregnant['total']
            : 0;

        $pregnantSevereRate = $pregnant['total'] > 0
            ? $pregnant['severe'] / $pregnant['total']
            : 0;

        if ($pregnant['total'] > 0 && $pregnant['severe'] > 0) {
            $recommendations[] = [
                'group' => 'Pregnant Women',
                'priority' => 'critical',
                'message' => 'Pregnant women with severe nutritional status detected',
                'actions' => [
                    'Refer identified cases for prompt assessment by qualified healthcare personnel',
                    'Prioritize prenatal follow-up',
                    'Review nutritional assessment and maternal health records',
                    'Coordinate with the barangay health center',
                ],
            ];
        }

        if ($pregnantRiskRate > 0.20) {
            $recommendations[] = [
                'group' => 'Pregnant Women',
                'priority' => 'high',
                'message' => 'High proportion of pregnant women classified as at risk',
                'actions' => [
                    'Strengthen prenatal monitoring',
                    'Provide appropriate maternal nutrition counseling',
                    'Schedule follow-up assessments',
                    'Coordinate with maternal healthcare providers',
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | SMART REMARK SYSTEM
        |--------------------------------------------------------------------------
        */

        $riskScore = 0;

        $riskScore += $childMalnutritionRate * 50;
        $riskScore += $adultObesityRate * 30;
        $riskScore += $adultSevereRate * 20;

        // Include pregnant women's severe-status rate.
        $riskScore += $pregnantSevereRate * 20;

        if ($riskScore >= 50) {
            $remark = 'Critical nutrition situation. Immediate intervention required.';
        } elseif ($riskScore >= 30) {
            $remark = 'Moderate nutrition risk. Strengthen barangay programs.';
        } elseif ($riskScore >= 15) {
            $remark = 'Mild nutrition concerns detected. Preventive actions recommended.';
        } else {
            $remark = 'Barangay nutrition status is stable.';
        }

        /*
        |--------------------------------------------------------------------------
        | CHART DATA
        |--------------------------------------------------------------------------
        */

        $chartData = [
            'children' => $child,
            'adults' => $adult,
            'pregnant_women' => $pregnant,
        ];

        /*
        |--------------------------------------------------------------------------
        | SAVE SUMMARY
        |--------------------------------------------------------------------------
        */

        return Summary::create([
            'report_type' => 'barangay',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_population' => $total,

            // CHILDREN
            'child_total' => $child['total'],
            'child_underweight' => $child['underweight'],
            'child_stunted' => $child['stunted'],
            'child_wasted' => $child['wasted'],
            'child_overweight' => $child['overweight'],
            'child_obese' => $child['obese'],
            'child_healthy' => $child['healthy'],
            'child_at_risk' => $child['at_risk'],
            'child_moderate' => $child['moderate'],
            'child_severe' => $child['severe'],

            // ADULTS
            'adult_total' => $adult['total'],
            'adult_wasted' => $adult['wasted'],
            'adult_overweight' => $adult['overweight'],
            'adult_obese' => $adult['obese'],
            'adult_healthy' => $adult['healthy'],
            'adult_at_risk' => $adult['at_risk'],
            'adult_moderate' => $adult['moderate'],
            'adult_severe' => $adult['severe'],

            // PREGNANT WOMEN
            'pregnant_total' => $pregnant['total'],
            'pregnant_healthy' => $pregnant['healthy'],
            'pregnant_at_risk' => $pregnant['at_risk'],
            'pregnant_moderate' => $pregnant['moderate'],
            'pregnant_severe' => $pregnant['severe'],

            'remark' => $remark,
            'recommendations' => $recommendations,
            'chart_data' => $chartData,
        ]);
    }
}
