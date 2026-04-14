<?php

namespace App\Services;

use App\Models\Summary;
use Illuminate\Support\Facades\DB;

class SummaryService
{
    public function generateReport($startDate = null, $endDate = null)
    {
        $latestRecords = DB::table('patient_records as pr')
            ->select('pr.*')
            ->join(DB::raw('(
                SELECT patient_id, MAX(date_measured) as latest_date
                FROM patient_records
                GROUP BY patient_id
            ) as latest'), function ($join) {
                $join->on('pr.patient_id', '=', 'latest.patient_id')
                     ->on('pr.date_measured', '=', 'latest.latest_date');
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pr.date_measured', [$startDate, $endDate]);
            })
            ->get();

        $children = $latestRecords->where('age', '<', 20);
        $adults = $latestRecords->where('age', '>=', 20);

        // 👶 CHILDREN
        $child = [
            'total' => $children->count(),
            'underweight' => 0,
            'stunted' => 0,
            'wasted' => 0,
            'overweight' => 0,
            'obese' => 0,
            'healthy' => 0,
            'at_risk' => 0,
            'moderate' => 0,
            'severe' => 0,
        ];

        foreach ($children as $r) {
            if (str_contains($r->weight_for_age, 'Underweight')) $child['underweight']++;
            if (str_contains($r->height_for_age, 'Stunted')) $child['stunted']++;
            if (str_contains($r->weight_for_ltht_status, 'Wasted')) $child['wasted']++;
            if ($r->weight_for_ltht_status === 'Overweight') $child['overweight']++;
            if ($r->weight_for_ltht_status === 'Obese') $child['obese']++;

            $child[strtolower(str_replace(' ', '_', $r->status))]++;
        }

        // 🧑 ADULTS
        $adult = [
            'total' => $adults->count(),
            'wasted' => 0,
            'overweight' => 0,
            'obese' => 0,
            'healthy' => 0,
            'at_risk' => 0,
            'moderate' => 0,
            'severe' => 0,
        ];

        foreach ($adults as $r) {
            if (str_contains($r->weight_for_ltht_status, 'Wasted')) $adult['wasted']++;
            if ($r->weight_for_ltht_status === 'Overweight') $adult['overweight']++;
            if ($r->weight_for_ltht_status === 'Obese') $adult['obese']++;

            $adult[strtolower(str_replace(' ', '_', $r->status))]++;
        }

        $total = $latestRecords->count();

        // 🤖 DSS
        $recommendations = [];

        // ===============================
        // 👶 CHILDREN ANALYSIS
        // ===============================

        $childMalnutritionRate = $child['total'] > 0 
            ? ($child['underweight'] + $child['stunted'] + $child['wasted']) / $child['total'] 
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
                    'Medical screening for severe cases'
                ]
            ];
        } elseif ($childMalnutritionRate > 0.15) {
            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'high',
                'message' => 'Moderate child malnutrition levels',
                'actions' => [
                    'Barangay feeding programs',
                    'Parent nutrition seminars',
                    'Growth monitoring campaigns'
                ]
            ];
        }

        // Stunting-specific
        if ($child['stunted'] > ($child['total'] * 0.25)) {
            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'high',
                'message' => 'High stunting prevalence',
                'actions' => [
                    'Long-term nutrition programs',
                    'Maternal health education',
                    'Early childhood intervention'
                ]
            ];
        }

        // Obesity in children
        if ($child['obese'] > ($child['total'] * 0.10)) {
            $recommendations[] = [
                'group' => 'Children',
                'priority' => 'medium',
                'message' => 'Increasing childhood obesity',
                'actions' => [
                    'Promote physical activity in schools',
                    'Healthy eating campaigns',
                    'Reduce sugary food consumption'
                ]
            ];
        }

        // ===============================
        // 🧑 ADULT ANALYSIS
        // ===============================

        $adultObesityRate = $adult['total'] > 0 
            ? $adult['obese'] / $adult['total'] 
            : 0;

        $adultSevereRate = $adult['total'] > 0 
            ? $adult['severe'] / $adult['total'] 
            : 0;

        // Obesity
        if ($adultObesityRate > 0.25) {
            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'high',
                'message' => 'High adult obesity rate',
                'actions' => [
                    'Community fitness programs',
                    'Diet monitoring initiatives',
                    'Workplace wellness campaigns'
                ]
            ];
        } elseif ($adultObesityRate > 0.15) {
            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'medium',
                'message' => 'Rising obesity cases',
                'actions' => [
                    'Exercise programs',
                    'Diet awareness campaigns'
                ]
            ];
        }

        // Underweight / wasted adults
        if ($adult['wasted'] > ($adult['total'] * 0.10)) {
            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'medium',
                'message' => 'Adult undernutrition detected',
                'actions' => [
                    'Food assistance programs',
                    'Nutritional counseling',
                    'Health check-ups'
                ]
            ];
        }

        // At risk group
        if ($adult['at_risk'] > ($adult['total'] * 0.20)) {
            $recommendations[] = [
                'group' => 'Adults',
                'priority' => 'low',
                'message' => 'Large at-risk population',
                'actions' => [
                    'Preventive health education',
                    'Regular BMI monitoring',
                    'Lifestyle awareness programs'
                ]
            ];
        }

        // ===============================
        // 🧠 SMART REMARK SYSTEM
        // ===============================

        $remark = "";
        $riskScore = 0;

        // scoring system
        $riskScore += $childMalnutritionRate * 50;
        $riskScore += $adultObesityRate * 30;
        $riskScore += $adultSevereRate * 20;

        // classification
        if ($riskScore >= 50) {
            $remark = "Critical nutrition situation. Immediate intervention required.";
        } elseif ($riskScore >= 30) {
            $remark = "Moderate nutrition risk. Strengthen barangay programs.";
        } elseif ($riskScore >= 15) {
            $remark = "Mild nutrition concerns detected. Preventive actions recommended.";
        } else {
            $remark = "Barangay nutrition status is stable.";
        }

        // 📊 CHART DATA
        $chartData = [
            'children' => $child,
            'adults' => $adult
        ];

        // 💾 SAVE
        return Summary::create([
            'report_type' => 'barangay',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_population' => $total,

            // CHILD
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

            // ADULT
            'adult_total' => $adult['total'],
            'adult_wasted' => $adult['wasted'],
            'adult_overweight' => $adult['overweight'],
            'adult_obese' => $adult['obese'],
            'adult_healthy' => $adult['healthy'],
            'adult_at_risk' => $adult['at_risk'],
            'adult_moderate' => $adult['moderate'],
            'adult_severe' => $adult['severe'],

            'remark' => $remark,
            'recommendations' => $recommendations,
            'chart_data' => $chartData
        ]);
    }
}
