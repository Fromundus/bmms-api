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

        $pregnantWomen = $latestRecords->filter(
            fn ($r) => (int) $r->is_pregnant === 1
        );

        $children = $latestRecords->filter(
            fn ($r) =>
                (int) $r->age < 20 &&
                (int) $r->is_pregnant !== 1
        );

        $adults = $latestRecords->filter(
            fn ($r) =>
                (int) $r->age >= 20 &&
                (int) $r->is_pregnant !== 1
        );

        /*
        |--------------------------------------------------------------------------
        | NORMALIZE STATUS
        |--------------------------------------------------------------------------
        |
        | This makes the report more reliable if the database stores values such
        | as "At Risk", "at_risk", "Severe", etc.
        |
        */

        $normalizeStatus = function ($status) {
            $status = strtolower(trim((string) $status));
            $status = str_replace([' ', '-'], '_', $status);

            return match ($status) {
                'healthy', 'normal' => 'healthy',
                'at_risk', 'risk', 'at_risk_status' => 'at_risk',
                'moderate', 'moderately_malnourished' => 'moderate',
                'severe', 'severely_malnourished' => 'severe',
                default => null,
            };
        };

        /*
        |--------------------------------------------------------------------------
        | STATUS COUNTER
        |--------------------------------------------------------------------------
        */

        $countStatuses = function ($records) use ($normalizeStatus) {
            $counts = [
                'healthy' => 0,
                'at_risk' => 0,
                'moderate' => 0,
                'severe' => 0,
            ];

            foreach ($records as $r) {
                $status = $normalizeStatus($r->status);

                if ($status !== null) {
                    $counts[$status]++;
                }
            }

            return $counts;
        };

        /*
        |--------------------------------------------------------------------------
        | CHILDREN (0–19)
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

            /*
            | Underweight
            */
            if (
                str_contains(
                    strtolower((string) $r->weight_for_age),
                    'underweight'
                )
            ) {
                $child['underweight']++;
            }

            /*
            | Stunting
            */
            if (
                str_contains(
                    strtolower((string) $r->height_for_age),
                    'stunt'
                )
            ) {
                $child['stunted']++;
            }

            /*
            | Wasting
            */
            if (
                str_contains(
                    strtolower((string) $r->weight_for_ltht_status),
                    'wast'
                )
            ) {
                $child['wasted']++;
            }

            /*
            | Overweight
            */
            if (
                str_contains(
                    strtolower((string) $r->weight_for_ltht_status),
                    'overweight'
                )
            ) {
                $child['overweight']++;
            }

            /*
            | Obesity
            |
            | Supports both "Obese" and "Obesity" values.
            */
            if (
                str_contains(
                    strtolower((string) $r->weight_for_ltht_status),
                    'obes'
                )
            ) {
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

            $nutritionStatus = strtolower(
                trim((string) $r->weight_for_ltht_status)
            );

            /*
            | Undernutrition
            */
            if (
                str_contains($nutritionStatus, 'wast') ||
                str_contains($nutritionStatus, 'underweight') ||
                str_contains($nutritionStatus, 'thin')
            ) {
                $adult['wasted']++;
            }

            /*
            | Overweight
            */
            if (
                str_contains($nutritionStatus, 'overweight')
            ) {
                $adult['overweight']++;
            }

            /*
            | Obesity
            */
            if (
                str_contains($nutritionStatus, 'obes')
            ) {
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
        | POPULATION-WIDE STATUS TOTALS
        |--------------------------------------------------------------------------
        |
        | This is the important correction.
        |
        | Instead of looking only at a few selected groups, calculate how many
        | people in the ENTIRE barangay are Healthy, At Risk, Moderate, or Severe.
        |
        */

        $populationStatus = [
            'total' => $total,
            'healthy' =>
                $child['healthy'] +
                $adult['healthy'] +
                $pregnant['healthy'],

            'at_risk' =>
                $child['at_risk'] +
                $adult['at_risk'] +
                $pregnant['at_risk'],

            'moderate' =>
                $child['moderate'] +
                $adult['moderate'] +
                $pregnant['moderate'],

            'severe' =>
                $child['severe'] +
                $adult['severe'] +
                $pregnant['severe'],
        ];

        /*
        |--------------------------------------------------------------------------
        | CALCULATE STATUS RATES
        |--------------------------------------------------------------------------
        */

        $healthyRate = $total > 0
            ? $populationStatus['healthy'] / $total
            : 0;

        $atRiskRate = $total > 0
            ? $populationStatus['at_risk'] / $total
            : 0;

        $moderateRate = $total > 0
            ? $populationStatus['moderate'] / $total
            : 0;

        $severeRate = $total > 0
            ? $populationStatus['severe'] / $total
            : 0;

        /*
        |--------------------------------------------------------------------------
        | TOTAL CONCERNED POPULATION
        |--------------------------------------------------------------------------
        |
        | Anyone who is At Risk, Moderate, or Severe is considered part of the
        | population requiring some level of nutrition attention.
        |
        */

        $concernedPopulation =
            $populationStatus['at_risk'] +
            $populationStatus['moderate'] +
            $populationStatus['severe'];

        $concernedRate = $total > 0
            ? $concernedPopulation / $total
            : 0;

        /*
        |--------------------------------------------------------------------------
        | CHILDREN ANALYSIS
        |--------------------------------------------------------------------------
        */

        /*
        | These indicator rates are still useful for recommendations.
        | They should not be the only basis of the overall barangay remark.
        */

        $childUnderweightRate = $child['total'] > 0
            ? $child['underweight'] / $child['total']
            : 0;

        $childStuntingRate = $child['total'] > 0
            ? $child['stunted'] / $child['total']
            : 0;

        $childWastingRate = $child['total'] > 0
            ? $child['wasted'] / $child['total']
            : 0;

        $childObesityRate = $child['total'] > 0
            ? $child['obese'] / $child['total']
            : 0;

        $childSevereRate = $child['total'] > 0
            ? $child['severe'] / $child['total']
            : 0;

        /*
        |--------------------------------------------------------------------------
        | ADULT ANALYSIS
        |--------------------------------------------------------------------------
        */

        $adultObesityRate = $adult['total'] > 0
            ? $adult['obese'] / $adult['total']
            : 0;

        $adultUndernutritionRate = $adult['total'] > 0
            ? $adult['wasted'] / $adult['total']
            : 0;

        $adultSevereRate = $adult['total'] > 0
            ? $adult['severe'] / $adult['total']
            : 0;

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

        /*
        |--------------------------------------------------------------------------
        | DSS RECOMMENDATIONS
        |--------------------------------------------------------------------------
        */

        $recommendations = [];

        /*
        |--------------------------------------------------------------------------
        | CHILDREN RECOMMENDATIONS
        |--------------------------------------------------------------------------
        */

        if ($child['total'] > 0 && $childSevereRate >= 0.10) {

            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'critical',
                'message' => 'High proportion of children with severe nutritional status',
                'actions' => [
                    'Prioritize assessment and follow-up of children with severe status',
                    'Strengthen growth monitoring',
                    'Conduct targeted nutrition interventions',
                    'Coordinate identified cases with qualified healthcare personnel',
                ],
            ];

        } elseif ($child['total'] > 0 && $childSevereRate > 0) {

            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'high',
                'message' => 'Children with severe nutritional status detected',
                'actions' => [
                    'Prioritize follow-up of identified severe cases',
                    'Continue regular growth monitoring',
                    'Provide appropriate nutrition counseling',
                ],
            ];
        }

        if ($childUnderweightRate >= 0.30) {

            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'critical',
                'message' => 'High prevalence of underweight children',
                'actions' => [
                    'Strengthen targeted nutrition programs',
                    'Conduct household-level follow-up',
                    'Provide nutrition counseling',
                    'Review children with persistent growth concerns',
                ],
            ];

        } elseif ($childUnderweightRate >= 0.15) {

            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'high',
                'message' => 'Moderate prevalence of underweight children',
                'actions' => [
                    'Strengthen barangay nutrition programs',
                    'Conduct parent nutrition education',
                    'Increase growth monitoring activities',
                ],
            ];
        }

        if ($childStuntingRate >= 0.25) {

            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'high',
                'message' => 'High stunting prevalence among children',
                'actions' => [
                    'Strengthen long-term nutrition programs',
                    'Support maternal and child nutrition education',
                    'Promote early childhood nutrition interventions',
                ],
            ];
        }

        if ($childWastingRate >= 0.10) {

            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'high',
                'message' => 'Wasting detected among children',
                'actions' => [
                    'Identify and follow up affected children',
                    'Strengthen nutrition screening',
                    'Provide appropriate nutrition counseling and referral',
                ],
            ];
        }

        if ($childObesityRate >= 0.10) {

            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'medium',
                'message' => 'Increasing childhood overweight or obesity',
                'actions' => [
                    'Promote age-appropriate physical activity',
                    'Strengthen healthy eating education',
                    'Reduce frequent consumption of sugary and highly processed foods',
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | ADULT RECOMMENDATIONS
        |--------------------------------------------------------------------------
        */

        if ($adultSevereRate >= 0.10) {

            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'critical',
                'message' => 'High proportion of adults with severe nutritional status',
                'actions' => [
                    'Prioritize assessment of identified severe cases',
                    'Strengthen health and nutrition follow-up',
                    'Coordinate with healthcare providers',
                ],
            ];

        } elseif ($adultSevereRate > 0) {

            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'high',
                'message' => 'Adults with severe nutritional status detected',
                'actions' => [
                    'Follow up identified severe cases',
                    'Provide nutrition counseling',
                    'Conduct appropriate health assessment',
                ],
            ];
        }

        if ($adultObesityRate >= 0.25) {

            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'high',
                'message' => 'High adult obesity prevalence',
                'actions' => [
                    'Promote community physical activity',
                    'Strengthen healthy diet education',
                    'Support lifestyle and weight management initiatives',
                ],
            ];

        } elseif ($adultObesityRate >= 0.15) {

            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'medium',
                'message' => 'Elevated adult obesity prevalence',
                'actions' => [
                    'Promote regular physical activity',
                    'Provide healthy lifestyle education',
                    'Encourage regular nutrition and health monitoring',
                ],
            ];
        }

        if ($adultUndernutritionRate >= 0.10) {

            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'medium',
                'message' => 'Adult undernutrition detected',
                'actions' => [
                    'Provide nutrition counseling',
                    'Assess possible contributing factors',
                    'Encourage appropriate health follow-up',
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | PREGNANT WOMEN RECOMMENDATIONS
        |--------------------------------------------------------------------------
        */

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

        if ($pregnantRiskRate >= 0.20) {

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

        // /*
        // |--------------------------------------------------------------------------
        // | POPULATION-WIDE RECOMMENDATIONS
        // |--------------------------------------------------------------------------
        // */

        // if ($total > 0 && $severeRate >= 0.10) {

        //     $recommendations[] = [
        //         'group' => 'Barangay',
        //         'priority' => 'critical',
        //         'message' => 'High proportion of the assessed population has severe nutritional status',
        //         'actions' => [
        //             'Prioritize severe cases for follow-up',
        //             'Coordinate targeted nutrition interventions',
        //             'Strengthen case monitoring and referral',
        //             'Review barangay nutrition response capacity',
        //         ],
        //     ];

        // } elseif ($total > 0 && $severeRate > 0) {

        //     $recommendations[] = [
        //         'group' => 'Barangay',
        //         'priority' => 'high',
        //         'message' => 'Severe nutritional cases are present in the assessed population',
        //         'actions' => [
        //             'Prioritize follow-up of severe cases',
        //             'Strengthen nutrition monitoring',
        //             'Coordinate appropriate referrals',
        //         ],
        //     ];
        // }

        // if ($total > 0 && $concernedRate >= 0.40) {

        //     $recommendations[] = [
        //         'group' => 'Barangay',
        //         'priority' => 'high',
        //         'message' => 'A large proportion of the assessed population requires nutrition attention',
        //         'actions' => [
        //             'Strengthen barangay-wide nutrition programs',
        //             'Increase follow-up and monitoring',
        //             'Prioritize high-risk households and individuals',
        //         ],
        //     ];
        // }

        /*
        |--------------------------------------------------------------------------
        | IMPROVED SMART REMARK SYSTEM
        |--------------------------------------------------------------------------
        |
        | The remark now directly reflects:
        |
        | 1. Severe population
        | 2. Moderate population
        | 3. At-risk population
        | 4. Total population requiring attention
        |
        | Severe cases have the highest priority.
        | A barangay with severe cases should not simply be called "stable".
        |
        */

        if ($total === 0) {

            $remark = 'No patient records are available for the selected reporting period.';

        } elseif ($populationStatus['severe'] > 0 && $severeRate >= 0.20) {

            $remark = sprintf(
                'Critical nutrition situation: %d of %d assessed individuals (%.1f%%) have severe nutritional status. Immediate targeted intervention and follow-up are recommended.',
                $populationStatus['severe'],
                $total,
                $severeRate * 100
            );

        } elseif ($populationStatus['severe'] > 0 && $concernedRate >= 0.40) {

            $remark = sprintf(
                'High nutrition concern: %d of %d assessed individuals (%.1f%%) are classified as at risk, moderate, or severe, including %d severe case(s). Strengthened barangay nutrition intervention is recommended.',
                $concernedPopulation,
                $total,
                $concernedRate * 100,
                $populationStatus['severe']
            );

        } elseif ($populationStatus['severe'] > 0) {

            $remark = sprintf(
                'Nutrition concerns require attention: %d severe case(s) were identified among %d assessed individuals. Targeted follow-up and monitoring are recommended.',
                $populationStatus['severe'],
                $total
            );

        } elseif ($moderateRate >= 0.20 || $concernedRate >= 0.40) {

            $remark = sprintf(
                'Moderate nutrition risk: %d of %d assessed individuals (%.1f%%) are classified as at risk or moderate. Barangay nutrition monitoring and preventive interventions should be strengthened.',
                $concernedPopulation,
                $total,
                $concernedRate * 100
            );

        } elseif ($atRiskRate >= 0.20 || $moderateRate > 0) {

            $remark = sprintf(
                'Nutrition concerns are present: %d of %d assessed individuals (%.1f%%) require monitoring because they are classified as at risk or moderate. Preventive action and regular follow-up are recommended.',
                $concernedPopulation,
                $total,
                $concernedRate * 100
            );

        } elseif ($atRiskRate > 0) {

            $remark = sprintf(
                'The overall nutrition situation is generally stable, but %d individual(s) are classified as at risk and should continue to receive appropriate monitoring.',
                $populationStatus['at_risk']
            );

        } else {

            $remark = sprintf(
                'The assessed population currently shows a generally stable nutrition status, with no individuals classified as at risk, moderate, or severe in the available records.'
            );
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

            /*
            | Additional overall status data for dashboards and charts.
            */
            'overall_status' => [
                'total' => $total,
                'healthy' => $populationStatus['healthy'],
                'at_risk' => $populationStatus['at_risk'],
                'moderate' => $populationStatus['moderate'],
                'severe' => $populationStatus['severe'],
                'concerned_total' => $concernedPopulation,
            ],
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

            /*
            | CHILDREN
            */
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

            /*
            | ADULTS
            */
            'adult_total' => $adult['total'],
            'adult_wasted' => $adult['wasted'],
            'adult_overweight' => $adult['overweight'],
            'adult_obese' => $adult['obese'],
            'adult_healthy' => $adult['healthy'],
            'adult_at_risk' => $adult['at_risk'],
            'adult_moderate' => $adult['moderate'],
            'adult_severe' => $adult['severe'],

            /*
            | PREGNANT WOMEN
            */
            'pregnant_total' => $pregnant['total'],
            'pregnant_healthy' => $pregnant['healthy'],
            'pregnant_at_risk' => $pregnant['at_risk'],
            'pregnant_moderate' => $pregnant['moderate'],
            'pregnant_severe' => $pregnant['severe'],

            /*
            | REPORT RESULTS
            */
            'remark' => $remark,
            'recommendations' => $recommendations,
            'chart_data' => $chartData,
        ]);
    }
}