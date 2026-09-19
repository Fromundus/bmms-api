<?php

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class WHOAnthropometryService
{
    /**
     * Base WHO reference directory.
     *
     * storage/app/who/
     */
    protected string $basePath;

    /**
     * In-memory reference cache.
     *
     * This prevents the same Excel file from being loaded
     * repeatedly during the same PHP request.
     */
    protected array $referenceCache = [];

    public function __construct()
    {
        $this->basePath = storage_path('app/who');
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC API
    |--------------------------------------------------------------------------
    */

    /**
     * Main WHO anthropometric assessment.
     *
     * Expected input:
     *
     * [
     *     'sex' => 'male',
     *     'date_of_birth' => '2020-01-01',
     *     'assessment_date' => '2025-01-01',
     *     'weight' => 18.5,
     *     'height' => 108.2,
     *     'measurement_type' => 'height',
     * ]
     *
     * OR:
     *
     * [
     *     'sex' => 'male',
     *     'age_months' => 72,
     *     'age_days' => 2191,
     *     ...
     * ]
     *
     * Recommended:
     *
     * For children under 5, provide date_of_birth and
     * assessment_date so the exact age in days can be used.
     *
     * For 5-19 years, completed age in months is used.
     */
    public function assess(array $data): array
    {
        $sex = $this->normalizeSex($data['sex'] ?? null);

        if ($sex === null) {
            throw new InvalidArgumentException(
                'Invalid sex. Use male/female, boys/girls, M/F, etc.'
            );
        }

        $weight = $this->toFloat($data['weight'] ?? null);
        $height = $this->toFloat(
            $data['height']
                ?? $data['length']
                ?? null
        );

        if ($weight === null || $weight <= 0) {
            throw new InvalidArgumentException(
                'Weight must be a valid value in kilograms.'
            );
        }

        if ($height === null || $height <= 0) {
            throw new InvalidArgumentException(
                'Height/length must be a valid value in centimeters.'
            );
        }

        /*
         * Resolve exact age.
         */
        $age = $this->resolveAge($data);

        $ageDays = $age['age_days'];
        $ageMonths = $age['age_months'];
        $ageYears = $age['age_years'];

        /*
         * BMI is calculated for all ages where weight and height
         * are available.
         */
        $bmi = $this->calculateBmi(
            $weight,
            $height
        );

        /*
        |--------------------------------------------------------------------------
        | 0-60 COMPLETED MONTHS
        |--------------------------------------------------------------------------
        */

        if ($ageMonths <= 60) {
            return $this->assessUnderFive(
                $sex,
                $ageDays,
                $ageMonths,
                $ageYears,
                $weight,
                $height,
                $bmi,
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 61-228 COMPLETED MONTHS
        |--------------------------------------------------------------------------
        */

        if ($ageMonths <= 228) {
            return $this->assessFiveToNineteen(
                $sex,
                $ageMonths,
                $ageYears,
                $weight,
                $height,
                $bmi
            );
        }

        /*
        |--------------------------------------------------------------------------
        | AGE > 19 YEARS
        |--------------------------------------------------------------------------
        */

        return $this->assessAdult(
            $sex,
            $ageMonths,
            $ageYears,
            $weight,
            $height,
            $bmi
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UNDER 5
    |--------------------------------------------------------------------------
    */

    /**
     * WHO Child Growth Standards: 0-60 completed months.
     *
     * Indicators:
     *
     * WFA
     * HFA
     * WFL/WFH
     * BFA
     */
    protected function assessUnderFive(
        string $sex,
        int $ageDays,
        int $ageMonths,
        float $ageYears,
        float $weight,
        float $height,
        float $bmi,
        array $data
    ): array {
        /*
         * WHO measurement conversion.
         *
         * <24 months:
         *     use recumbent length.
         *
         * >=24 months:
         *     use standing height.
         */
        $measurementType = strtolower(
            trim((string) (
                $data['measurement_type'] ?? ''
            ))
        );

        $anthroMeasurement = $this->prepareUnderFiveMeasurement(
            $height,
            $ageMonths,
            $measurementType
        );

        /*
         * WFA.
         */
        $wfaZ = $this->calculateAgeBasedZScore(
            sex: $sex,
            referenceSet: '0-5',
            indicator: 'wfa',
            age: $ageDays,
            measurement: $weight
        );

        /*
         * HFA.
         */
        $hfaZ = $this->calculateAgeBasedZScore(
            sex: $sex,
            referenceSet: '0-5',
            indicator: 'hfa',
            age: $ageDays,
            measurement: $anthroMeasurement
        );

        /*
         * WFL/WFH.
         *
         * The child's length/height selects the LMS row.
         * Weight is the measurement converted to z-score.
         */
        $wflZ = $this->calculateLengthBasedZScore(
            sex: $sex,
            referenceSet: '0-5',
            indicator: 'wfl',
            length: $anthroMeasurement,
            measurement: $weight
        );

        /*
         * BFA.
         *
         * BMI is calculated using the converted WHO measurement
         * where necessary.
         */
        $bmiForAge = $this->calculateBmi(
            $weight,
            $anthroMeasurement
        );

        $bfaZ = $this->calculateAgeBasedZScore(
            sex: $sex,
            referenceSet: '0-5',
            indicator: 'bfa',
            age: $ageDays,
            measurement: $bmiForAge
        );

        /*
         * WHO classifications.
         */
        $wfaStatus = $this->classifyUnderFiveWFA($wfaZ);
        $hfaStatus = $this->classifyUnderFiveHFA($hfaZ);
        $wflStatus = $this->classifyUnderFiveWFL($wflZ);
        $bfaStatus = $this->classifyUnderFiveBFA($bfaZ);

        return [
            'reference' => 'WHO Child Growth Standards 0-5 years',

            'age' => [
                'days' => $ageDays,
                'completed_months' => $ageMonths,
                'years' => round($ageYears, 4),
            ],

            'sex' => $sex,

            'measurements' => [
                'weight_kg' => round($weight, 3),
                'height_cm' => round($height, 3),
                'anthropometric_length_height_cm' =>
                    round($anthroMeasurement, 3),
                'bmi' => round($bmi, 3),
                'bmi_for_age_calculated' =>
                    round($bmiForAge, 3),
            ],

            /*
             * Existing application fields.
             */
            'weight_for_age' => [
                'zscore' => $this->roundZ($wfaZ),
                'status' => $wfaStatus,
            ],

            'height_for_age' => [
                'zscore' => $this->roundZ($hfaZ),
                'status' => $hfaStatus,
            ],

            'weight_for_ltht_status' => [
                'zscore' => $this->roundZ($wflZ),
                'status' => $wflStatus,
            ],

            'bmi_for_age' => [
                'zscore' => $this->roundZ($bfaZ),
                'status' => $bfaStatus,
            ],

            /*
             * Convenient flat fields for database storage.
             */
            'wfa_zscore' => $this->roundZ($wfaZ),
            'hfa_zscore' => $this->roundZ($hfaZ),
            'wfl_zscore' => $this->roundZ($wflZ),
            'bfa_zscore' => $this->roundZ($bfaZ),

            'wfa_status' => $wfaStatus,
            'hfa_status' => $hfaStatus,
            'wfl_status' => $wflStatus,
            'bfa_status' => $bfaStatus,

            /*
             * This is an application-level summary, NOT a WHO
             * indicator itself.
             */
            'overall_status' => $this->computeOverallUnderFive(
                $wfaZ,
                $hfaZ,
                $wflZ,
                $bfaZ
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 5-19 YEARS
    |--------------------------------------------------------------------------
    */

    /**
     * WHO 2007 Reference: 61-228 completed months.
     *
     * 61-120:
     *     WFA
     *     HFA
     *     BFA
     *
     * 121-228:
     *     HFA
     *     BFA
     *
     * There is no WFL/WFH indicator in the 5-19 reference.
     */
    protected function assessFiveToNineteen(
        string $sex,
        int $ageMonths,
        float $ageYears,
        float $weight,
        float $height,
        float $bmi
    ): array {
        /*
         * HFA.
         */
        $hfaZ = $this->calculateAgeBasedZScore(
            sex: $sex,
            referenceSet: '5-19',
            indicator: 'hfa',
            age: $ageMonths,
            measurement: $height
        );

        /*
         * BMI-for-age.
         */
        $bfaZ = $this->calculateAgeBasedZScore(
            sex: $sex,
            referenceSet: '5-19',
            indicator: 'bfa',
            age: $ageMonths,
            measurement: $bmi
        );

        $hfaStatus = $this->classifyFiveToNineteenHFA(
            $hfaZ
        );

        $bfaStatus = $this->classifyFiveToNineteenBFA(
            $bfaZ
        );

        /*
         * WFA exists only through 120 completed months.
         */
        $wfaZ = null;
        $wfaStatus = null;

        if ($ageMonths <= 120) {
            $wfaZ = $this->calculateAgeBasedZScore(
                sex: $sex,
                referenceSet: '5-19',
                indicator: 'wfa',
                age: $ageMonths,
                measurement: $weight
            );

            $wfaStatus = $this->classifyFiveToNineteenWFA(
                $wfaZ
            );
        }

        /*
         * There is deliberately NO WFL/WFH for 5-19.
         */
        return [
            'reference' => 'WHO 2007 Growth Reference 5-19 years',

            'age' => [
                'completed_months' => $ageMonths,
                'years' => round($ageYears, 4),
            ],

            'sex' => $sex,

            'measurements' => [
                'weight_kg' => round($weight, 3),
                'height_cm' => round($height, 3),
                'bmi' => round($bmi, 3),
            ],

            /*
             * WFA.
             *
             * null after 120 completed months because WHO does not
             * provide WFA beyond age 10.
             */
            'weight_for_age' => $wfaZ === null
                ? null
                : [
                    'zscore' => $this->roundZ($wfaZ),
                    'status' => $wfaStatus,
                ],

            /*
             * HFA.
             */
            'height_for_age' => [
                'zscore' => $this->roundZ($hfaZ),
                'status' => $hfaStatus,
            ],

            /*
             * WFL/WFH does not apply to 5-19.
             */
            'weight_for_ltht_status' => null,

            /*
             * BMI-for-age.
             */
            'bmi_for_age' => [
                'zscore' => $this->roundZ($bfaZ),
                'status' => $bfaStatus,
            ],

            /*
             * Flat fields.
             */
            'wfa_zscore' =>
                $wfaZ === null
                    ? null
                    : $this->roundZ($wfaZ),

            'hfa_zscore' =>
                $this->roundZ($hfaZ),

            'wfl_zscore' => null,

            'bfa_zscore' =>
                $this->roundZ($bfaZ),

            'wfa_status' => $wfaStatus,

            'hfa_status' => $hfaStatus,

            'wfl_status' => null,

            'bfa_status' => $bfaStatus,

            /*
             * For 5-19, this is an application summary.
             */
            'overall_status' => $this->computeOverallFiveToNineteen(
                $hfaZ,
                $bfaZ,
                $wfaZ
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ADULT
    |--------------------------------------------------------------------------
    */

    protected function assessAdult(
        string $sex,
        int $ageMonths,
        float $ageYears,
        float $weight,
        float $height,
        float $bmi
    ): array {
        $bmiStatus = $this->classifyAdultBMI($bmi);

        return [
            'reference' => 'WHO adult BMI classification',

            'age' => [
                'completed_months' => $ageMonths,
                'years' => round($ageYears, 4),
            ],

            'sex' => $sex,

            'measurements' => [
                'weight_kg' => round($weight, 3),
                'height_cm' => round($height, 3),
                'bmi' => round($bmi, 3),
            ],

            /*
             * Pediatric indicators are not applicable.
             */
            'weight_for_age' => null,
            'height_for_age' => null,
            'weight_for_ltht_status' => null,
            'bmi_for_age' => null,

            'wfa_zscore' => null,
            'hfa_zscore' => null,
            'wfl_zscore' => null,
            'bfa_zscore' => null,

            'wfa_status' => null,
            'hfa_status' => null,
            'wfl_status' => null,
            'bfa_status' => null,

            'bmi_status' => $bmiStatus,

            'overall_status' => $this->computeOverallAdult($bmi),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | AGE CALCULATION
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve exact age.
     *
     * Returns:
     *
     * age_days
     * age_months
     * age_years
     *
     * Priority:
     *
     * 1. date_of_birth + assessment_date
     * 2. age_days
     * 3. age_months
     */
    protected function resolveAge(array $data): array
    {
        /*
         * Best option:
         * exact dates.
         */
        if (!empty($data['date_of_birth'])) {
            $dob = new DateTimeImmutable(
                (string) $data['date_of_birth']
            );

            $assessmentDate = !empty($data['assessment_date'])
                ? new DateTimeImmutable(
                    (string) $data['assessment_date']
                )
                : new DateTimeImmutable('today');

            if ($assessmentDate < $dob) {
                throw new InvalidArgumentException(
                    'Assessment date cannot be before date of birth.'
                );
            }

            /*
             * Exact number of days.
             */
            $ageDays = (int) $dob
                ->diff($assessmentDate)
                ->days;

            /*
             * Completed calendar months.
             *
             * This is important for WHO 5-19 tables because
             * their first column is completed months.
             */
            $ageMonths = $this->completedMonths(
                $dob,
                $assessmentDate
            );

            return [
                'age_days' => $ageDays,
                'age_months' => $ageMonths,
                'age_years' => $ageDays / 365.2425,
            ];
        }

        /*
         * Explicit age_days.
         */
        if (isset($data['age_days'])) {
            $ageDays = (int) round(
                (float) $data['age_days']
            );

            if ($ageDays < 0) {
                throw new InvalidArgumentException(
                    'Age in days cannot be negative.'
                );
            }

            /*
             * If only days are supplied, calculate an approximate
             * completed month value for choosing the reference set.
             *
             * For exact 5-19 calculations, date_of_birth is preferred.
             */
            $ageMonths = (int) floor(
                $ageDays / 30.4375
            );

            return [
                'age_days' => $ageDays,
                'age_months' => $ageMonths,
                'age_years' => $ageDays / 365.2425,
            ];
        }

        /*
         * Explicit age_months.
         *
         * This is appropriate for your 5-19 WHO Excel files.
         */
        if (isset($data['age_months'])) {
            $ageMonths = (int) round(
                (float) $data['age_months']
            );

            if ($ageMonths < 0) {
                throw new InvalidArgumentException(
                    'Age in months cannot be negative.'
                );
            }

            return [
                'age_days' => (int) round(
                    $ageMonths * 30.4375
                ),
                'age_months' => $ageMonths,
                'age_years' => $ageMonths / 12,
            ];
        }

        /*
         * Generic "age" fallback.
         *
         * Treat it as months.
         */
        if (isset($data['age'])) {
            $ageMonths = (int) round(
                (float) $data['age']
            );

            return [
                'age_days' => (int) round(
                    $ageMonths * 30.4375
                ),
                'age_months' => $ageMonths,
                'age_years' => $ageMonths / 12,
            ];
        }

        throw new InvalidArgumentException(
            'Age is required. Provide date_of_birth, age_days, or age_months.'
        );
    }

    /**
     * Completed calendar months.
     */
    protected function completedMonths(
        DateTimeImmutable $dob,
        DateTimeImmutable $date
    ): int {
        $years = (int) $date->format('Y')
            - (int) $dob->format('Y');

        $months = (int) $date->format('n')
            - (int) $dob->format('n');

        $totalMonths = ($years * 12) + $months;

        /*
         * If the day of the assessment is before the birth day,
         * the latest month has not been completed.
         */
        if (
            (int) $date->format('j')
            < (int) $dob->format('j')
        ) {
            $totalMonths--;
        }

        return max(0, $totalMonths);
    }

    /*
    |--------------------------------------------------------------------------
    | MEASUREMENT HANDLING
    |--------------------------------------------------------------------------
    */

    /**
     * Prepare length/height for WHO 0-5 calculations.
     *
     * WHO:
     *
     * <24 months:
     *     recumbent length
     *
     * 24-60 months:
     *     standing height
     *
     * If standing height was measured in a child <24 months:
     *     add 0.7 cm
     *
     * If recumbent length was measured in a child >=24 months:
     *     subtract 0.7 cm
     */
    protected function prepareUnderFiveMeasurement(
        float $measurement,
        int $ageMonths,
        string $measurementType
    ): float {
        /*
         * If the user did not specify whether the measurement
         * was length or height, assume the supplied value is already
         * the correct WHO measurement.
         */
        if ($measurementType === '') {
            return $measurement;
        }

        /*
         * Child younger than 24 completed months.
         *
         * Required reference measurement = length.
         */
        if (
            $ageMonths < 24 &&
            $measurementType === 'height'
        ) {
            return $measurement + 0.7;
        }

        /*
         * Child 24 months or older.
         *
         * Required reference measurement = standing height.
         */
        if (
            $ageMonths >= 24 &&
            $measurementType === 'length'
        ) {
            return $measurement - 0.7;
        }

        return $measurement;
    }

    /**
     * BMI calculation.
     *
     * weight = kg
     * height = cm
     */
    protected function calculateBmi(
        float $weight,
        float $heightCm
    ): float {
        if ($weight <= 0 || $heightCm <= 0) {
            throw new InvalidArgumentException(
                'Weight and height must be greater than zero.'
            );
        }

        $heightM = $heightCm / 100;

        return $weight / ($heightM * $heightM);
    }

    /*
    |--------------------------------------------------------------------------
    | LMS Z-SCORE
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate an age-based LMS z-score.
     *
     * Used by:
     *
     * WFA
     * HFA
     * BFA
     *
     * For 0-5:
     *     age = day
     *
     * For 5-19:
     *     age = completed month
     */
    protected function calculateAgeBasedZScore(
        string $sex,
        string $referenceSet,
        string $indicator,
        int $age,
        float $measurement
    ): float {
        $reference = $this->getAgeReference(
            sex: $sex,
            referenceSet: $referenceSet,
            indicator: $indicator,
            age: $age
        );

        return $this->calculateLmsZScore(
            x: $measurement,
            L: $reference['L'],
            M: $reference['M'],
            S: $reference['S']
        );
    }

    /**
     * Calculate WFL/WFH LMS z-score.
     *
     * Length/height selects the row.
     * Weight is X.
     */
    protected function calculateLengthBasedZScore(
        string $sex,
        string $referenceSet,
        string $indicator,
        float $length,
        float $measurement
    ): float {
        $reference = $this->getLengthReference(
            sex: $sex,
            referenceSet: $referenceSet,
            indicator: $indicator,
            length: $length
        );

        return $this->calculateLmsZScore(
            x: $measurement,
            L: $reference['L'],
            M: $reference['M'],
            S: $reference['S']
        );
    }

    /**
     * WHO LMS formula.
     *
     * Z = (((X/M)^L) - 1) / (L*S)
     *
     * If L = 0:
     *
     * Z = ln(X/M) / S
     */
    protected function calculateLmsZScore(
        float $x,
        float $L,
        float $M,
        float $S
    ): float {
        if ($x <= 0) {
            throw new InvalidArgumentException(
                'Measurement must be greater than zero.'
            );
        }

        if ($M <= 0) {
            throw new RuntimeException(
                'Invalid WHO LMS reference: M must be greater than zero.'
            );
        }

        if ($S <= 0) {
            throw new RuntimeException(
                'Invalid WHO LMS reference: S must be greater than zero.'
            );
        }

        /*
         * L = 0 case.
         */
        if (abs($L) < 0.000000001) {
            return log($x / $M) / $S;
        }

        return (
            pow($x / $M, $L) - 1
        ) / ($L * $S);
    }

    /*
    |--------------------------------------------------------------------------
    | REFERENCE LOOKUP
    |--------------------------------------------------------------------------
    */

    /**
     * Get an age-based WHO LMS reference.
     *
     * 0-5:
     *     A = Day
     *
     * 5-19:
     *     A = Month
     */
    protected function getAgeReference(
        string $sex,
        string $referenceSet,
        string $indicator,
        int $age
    ): array {
        $rows = $this->loadReference(
            sex: $sex,
            referenceSet: $referenceSet,
            indicator: $indicator
        );

        if (empty($rows)) {
            throw new RuntimeException(
                "WHO reference table is empty: {$referenceSet}/{$sex}/{$indicator}"
            );
        }

        /*
         * Exact match first.
         */
        foreach ($rows as $row) {
            if ((float) $row['key'] === (float) $age) {
                return [
                    'L' => $row['L'],
                    'M' => $row['M'],
                    'S' => $row['S'],
                ];
            }
        }

        /*
         * Find surrounding rows.
         *
         * This is mainly useful if the source table does not contain
         * every possible age value.
         */
        $lower = null;
        $upper = null;

        foreach ($rows as $row) {
            $key = (float) $row['key'];

            if ($key < $age) {
                $lower = $row;
                continue;
            }

            if ($key > $age) {
                $upper = $row;
                break;
            }
        }

        /*
         * Before first available value.
         */
        if ($lower === null) {
            return [
                'L' => $rows[0]['L'],
                'M' => $rows[0]['M'],
                'S' => $rows[0]['S'],
            ];
        }

        /*
         * After last available value.
         */
        if ($upper === null) {
            $last = $rows[count($rows) - 1];

            return [
                'L' => $last['L'],
                'M' => $last['M'],
                'S' => $last['S'],
            ];
        }

        /*
         * Interpolate LMS values.
         */
        return [
            'L' => $this->interpolate(
                $lower['key'],
                $lower['L'],
                $upper['key'],
                $upper['L'],
                $age
            ),

            'M' => $this->interpolate(
                $lower['key'],
                $lower['M'],
                $upper['key'],
                $upper['M'],
                $age
            ),

            'S' => $this->interpolate(
                $lower['key'],
                $lower['S'],
                $upper['key'],
                $upper['S'],
                $age
            ),
        ];
    }

    /**
     * Get a length-based reference.
     *
     * Used by 0-5 WFL.
     */
    protected function getLengthReference(
        string $sex,
        string $referenceSet,
        string $indicator,
        float $length
    ): array {
        $rows = $this->loadReference(
            sex: $sex,
            referenceSet: $referenceSet,
            indicator: $indicator
        );

        if (empty($rows)) {
            throw new RuntimeException(
                "WHO reference table is empty: {$referenceSet}/{$sex}/{$indicator}"
            );
        }

        /*
         * Exact length.
         */
        foreach ($rows as $row) {
            if (
                abs(
                    (float) $row['key'] - $length
                ) < 0.000001
            ) {
                return [
                    'L' => $row['L'],
                    'M' => $row['M'],
                    'S' => $row['S'],
                ];
            }
        }

        /*
         * Find surrounding lengths.
         */
        $lower = null;
        $upper = null;

        foreach ($rows as $row) {
            $key = (float) $row['key'];

            if ($key < $length) {
                $lower = $row;
                continue;
            }

            if ($key > $length) {
                $upper = $row;
                break;
            }
        }

        if ($lower === null) {
            return [
                'L' => $rows[0]['L'],
                'M' => $rows[0]['M'],
                'S' => $rows[0]['S'],
            ];
        }

        if ($upper === null) {
            $last = $rows[count($rows) - 1];

            return [
                'L' => $last['L'],
                'M' => $last['M'],
                'S' => $last['S'],
            ];
        }

        return [
            'L' => $this->interpolate(
                $lower['key'],
                $lower['L'],
                $upper['key'],
                $upper['L'],
                $length
            ),

            'M' => $this->interpolate(
                $lower['key'],
                $lower['M'],
                $upper['key'],
                $upper['M'],
                $length
            ),

            'S' => $this->interpolate(
                $lower['key'],
                $lower['S'],
                $upper['key'],
                $upper['S'],
                $length
            ),
        ];
    }

    /**
     * Linear interpolation.
     */
    protected function interpolate(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x
    ): float {
        if ($x1 === $x2) {
            return $y1;
        }

        return $y1
            + (
                (($x - $x1) / ($x2 - $x1))
                * ($y2 - $y1)
            );
    }

    /*
    |--------------------------------------------------------------------------
    | EXCEL LOADER
    |--------------------------------------------------------------------------
    */

    /**
     * Load one WHO Excel reference.
     *
     * Expected:
     *
     * 0-5 WFA/HFA/BFA:
     *
     * Day | L | M | S
     *
     * 0-5 WFL:
     *
     * Length | L | M | S
     *
     * 5-19:
     *
     * Month | L | M | S
     */
    protected function loadReference(
        string $sex,
        string $referenceSet,
        string $indicator
    ): array {
        $cacheKey =
            "{$referenceSet}.{$sex}.{$indicator}";

        if (isset($this->referenceCache[$cacheKey])) {
            return $this->referenceCache[$cacheKey];
        }

        $allowedSexes = [
            'boys',
            'girls',
        ];

        $allowedReferenceSets = [
            '0-5',
            '5-19',
        ];

        $allowedIndicators = [
            'wfa',
            'hfa',
            'wfl',
            'bfa',
        ];

        if (!in_array($sex, $allowedSexes, true)) {
            throw new RuntimeException(
                "Invalid sex reference: {$sex}"
            );
        }

        if (
            !in_array(
                $referenceSet,
                $allowedReferenceSets,
                true
            )
        ) {
            throw new RuntimeException(
                "Invalid WHO reference set: {$referenceSet}"
            );
        }

        if (
            !in_array(
                $indicator,
                $allowedIndicators,
                true
            )
        ) {
            throw new RuntimeException(
                "Invalid WHO indicator: {$indicator}"
            );
        }

        /*
         * 5-19 does NOT have WFL.
         */
        if (
            $referenceSet === '5-19' &&
            $indicator === 'wfl'
        ) {
            throw new RuntimeException(
                'WFL/WFH is not available in the WHO 2007 5-19 reference.'
            );
        }

        /*
         * Construct:
         *
         * storage/app/who/0-5/boys/wfa.xlsx
         */
        $file = $this->basePath
            .DIRECTORY_SEPARATOR
            .$referenceSet
            .DIRECTORY_SEPARATOR
            .$sex
            .DIRECTORY_SEPARATOR
            .$indicator
            .'.xlsx';

        if (!is_file($file)) {
            throw new RuntimeException(
                "WHO reference Excel file not found: {$file}"
            );
        }

        /*
         * Load first worksheet.
         */
        $spreadsheet = IOFactory::load($file);

        $sheet = $spreadsheet->getSheet(0);

        $highestRow = $sheet->getHighestRow();

        $rows = [];

        /*
         * Start at row 2 because row 1 is:
         *
         * Day/Month/Length | L | M | S
         */
        for (
            $rowNumber = 2;
            $rowNumber <= $highestRow;
            $rowNumber++
        ) {
            $key = $sheet
                ->getCell("A{$rowNumber}")
                ->getCalculatedValue();

            $L = $sheet
                ->getCell("B{$rowNumber}")
                ->getCalculatedValue();

            $M = $sheet
                ->getCell("C{$rowNumber}")
                ->getCalculatedValue();

            $S = $sheet
                ->getCell("D{$rowNumber}")
                ->getCalculatedValue();

            /*
             * Ignore blank/non-data rows.
             */
            if (
                !is_numeric($key) ||
                !is_numeric($L) ||
                !is_numeric($M) ||
                !is_numeric($S)
            ) {
                continue;
            }

            $rows[] = [
                'key' => (float) $key,
                'L' => (float) $L,
                'M' => (float) $M,
                'S' => (float) $S,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        unset($spreadsheet);

        if (empty($rows)) {
            throw new RuntimeException(
                "No LMS data found in {$file}"
            );
        }

        /*
         * Ensure ascending order.
         */
        usort(
            $rows,
            static function (
                array $a,
                array $b
            ): int {
                return $a['key'] <=> $b['key'];
            }
        );

        $this->referenceCache[$cacheKey] = $rows;

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | WHO CLASSIFICATIONS: 0-5
    |--------------------------------------------------------------------------
    */

    /**
     * Weight-for-age.
     */
    protected function classifyUnderFiveWFA(
        float $z
    ): string {
        if ($z < -3) {
            return 'Severe underweight';
        }

        if ($z < -2) {
            return 'Underweight';
        }

        return 'Normal';
    }

    /**
     * Length/height-for-age.
     */
    protected function classifyUnderFiveHFA(
        float $z
    ): string {
        if ($z < -3) {
            return 'Severe stunting';
        }

        if ($z < -2) {
            return 'Stunting';
        }

        return 'Normal';
    }

    /**
     * Weight-for-length/height.
     */
    protected function classifyUnderFiveWFL(
        float $z
    ): string {
        if ($z < -3) {
            return 'Severe wasting';
        }

        if ($z < -2) {
            return 'Wasting';
        }

        if ($z > 3) {
            return 'Obesity';
        }

        if ($z > 2) {
            return 'Overweight';
        }

        return 'Normal';
    }

    /**
     * BMI-for-age, 0-5 years.
     */
    protected function classifyUnderFiveBFA(
        float $z
    ): string {
        if ($z < -3) {
            return 'Severe wasting';
        }

        if ($z < -2) {
            return 'Wasting';
        }

        if ($z > 3) {
            return 'Obesity';
        }

        if ($z > 2) {
            return 'Overweight';
        }

        return 'Normal';
    }

    /*
    |--------------------------------------------------------------------------
    | WHO CLASSIFICATIONS: 5-19
    |--------------------------------------------------------------------------
    */

    /**
     * Weight-for-age, 5-10 years.
     *
     * The interpretation of WFA is the same basic
     * underweight/stunting framework used by WHO.
     */
    protected function classifyFiveToNineteenWFA(
        float $z
    ): string {
        if ($z < -3) {
            return 'Severe underweight';
        }

        if ($z < -2) {
            return 'Underweight';
        }

        return 'Normal';
    }

    /**
     * Height-for-age, 5-19.
     */
    protected function classifyFiveToNineteenHFA(
        float $z
    ): string {
        if ($z < -3) {
            return 'Severe stunting';
        }

        if ($z < -2) {
            return 'Stunting';
        }

        return 'Normal';
    }

    /**
     * BMI-for-age, 5-19.
     *
     * WHO 2007:
     *
     * < -3 SD = Severe thinness
     * < -2 SD = Thinness
     * > +1 SD = Overweight
     * > +2 SD = Obesity
     */
    protected function classifyFiveToNineteenBFA(
        float $z
    ): string {
        if ($z < -3) {
            return 'Severe thinness';
        }

        if ($z < -2) {
            return 'Thinness';
        }

        if ($z > 2) {
            return 'Obesity';
        }

        if ($z > 1) {
            return 'Overweight';
        }

        return 'Normal';
    }

    /*
    |--------------------------------------------------------------------------
    | ADULT BMI
    |--------------------------------------------------------------------------
    */

    protected function classifyAdultBMI(
        float $bmi
    ): string {
        if ($bmi < 16) {
            return 'Severe underweight';
        }

        if ($bmi < 18.5) {
            return 'Underweight';
        }

        if ($bmi < 25) {
            return 'Normal';
        }

        if ($bmi < 30) {
            return 'Overweight';
        }

        return 'Obesity';
    }

    /*
    |--------------------------------------------------------------------------
    | APPLICATION OVERALL STATUS
    |--------------------------------------------------------------------------
    */

    /**
     * Application-level summary for 0-5.
     *
     * This is NOT an official WHO composite indicator.
     */
    // protected function computeOverallUnderFive(
    //     float $wfaZ,
    //     float $hfaZ,
    //     float $wflZ,
    //     float $bfaZ
    // ): string {
    //     /*
    //      * Severe.
    //      */
    //     if (
    //         $wfaZ < -3 ||
    //         $hfaZ < -3 ||
    //         $wflZ < -3 ||
    //         $bfaZ < -3 ||
    //         $wflZ > 3 ||
    //         $bfaZ > 3
    //     ) {
    //         return 'Severe';
    //     }

    //     /*
    //      * Moderate.
    //      */
    //     if (
    //         $wfaZ < -2 ||
    //         $hfaZ < -2 ||
    //         $wflZ < -2 ||
    //         $bfaZ < -2 ||
    //         $wflZ > 2 ||
    //         $bfaZ > 2
    //     ) {
    //         return 'Moderate';
    //     }

    //     return 'Healthy';
    // }

    // /**
    //  * Application-level summary for 5-19.
    //  *
    //  * This is NOT an official WHO composite indicator.
    //  */
    // protected function computeOverallFiveToNineteen(
    //     float $hfaZ,
    //     float $bfaZ,
    //     ?float $wfaZ
    // ): string {
    //     /*
    //      * Severe.
    //      */
    //     if (
    //         $hfaZ < -3 ||
    //         $bfaZ < -3
    //     ) {
    //         return 'Severe';
    //     }

    //     /*
    //      * Moderate.
    //      */
    //     if (
    //         $hfaZ < -2 ||
    //         $bfaZ < -2 ||
    //         $bfaZ > 2
    //     ) {
    //         return 'Moderate';
    //     }

    //     /*
    //      * WFA is only available through 120 months.
    //      */
    //     if (
    //         $wfaZ !== null &&
    //         $wfaZ < -3
    //     ) {
    //         return 'Severe';
    //     }

    //     if (
    //         $wfaZ !== null &&
    //         $wfaZ < -2
    //     ) {
    //         return 'Moderate';
    //     }

    //     return 'Healthy';
    // }

    // /**
    //  * Application-level summary for adults.
    //  *
    //  * This is an application-level grouping of WHO adult BMI categories.
    //  * It is NOT an official WHO "overall status" indicator.
    //  *
    //  * Severe:
    //  *   BMI < 16.0
    //  *
    //  * Moderate:
    //  *   BMI 16.0 - 18.49
    //  *   BMI >= 25.0
    //  *
    //  * Healthy:
    //  *   BMI 18.5 - 24.99
    //  */
    // protected function computeOverallAdult(float $bmi): string
    // {
    //     // Severe undernutrition
    //     if ($bmi < 16.0) {
    //         return 'Severe';
    //     }

    //     // Underweight
    //     if ($bmi < 18.5) {
    //         return 'Moderate';
    //     }

    //     // Overweight / obesity
    //     if ($bmi >= 25.0) {
    //         return 'Moderate';
    //     }

    //     // Normal BMI: 18.5 - 24.99
    //     return 'Healthy';
    // }

    /**
     * Application-level summary for children under 5 years.
     *
     * This is NOT an official WHO composite indicator.
     *
     * Severe:
     *   Any relevant indicator < -3 SD
     *   WFL/BFA > +3 SD
     *
     * Moderate:
     *   Any relevant indicator < -2 SD
     *   WFL/BFA > +2 SD
     *
     * At Risk:
     *   Not applicable in the current WHO z-score grouping.
     *
     * Healthy:
     *   All indicators are within the normal WHO ranges.
     */
    protected function computeOverallUnderFive(
        float $wfaZ,
        float $hfaZ,
        float $wflZ,
        float $bfaZ
    ): string {
        // Severe
        if (
            $wfaZ < -3 ||
            $hfaZ < -3 ||
            $wflZ < -3 ||
            $bfaZ < -3 ||
            $wflZ > 3 ||
            $bfaZ > 3
        ) {
            return 'Severe';
        }

        // Moderate
        if (
            $wfaZ < -2 ||
            $hfaZ < -2 ||
            $wflZ < -2 ||
            $bfaZ < -2 ||
            $wflZ > 2 ||
            $bfaZ > 2
        ) {
            return 'Moderate';
        }

        return 'Healthy';
    }


    /**
     * Application-level summary for children 5-19 years.
     *
     * This is NOT an official WHO composite indicator.
     *
     * WHO BMI-for-age:
     *   < -3 SD = severe thinness
     *   < -2 SD = thinness
     *   > +1 SD = overweight
     *   > +2 SD = obesity
     *
     * For this application's four-level summary:
     *   Severe  = severe thinness / severe stunting
     *   Moderate = thinness / stunting
     *   At Risk = overweight / obesity
     *   Healthy = otherwise
     */
    protected function computeOverallFiveToNineteen(
        float $hfaZ,
        float $bfaZ,
        ?float $wfaZ
    ): string {
        // Severe
        if (
            $hfaZ < -3 ||
            $bfaZ < -3 ||
            ($wfaZ !== null && $wfaZ < -3)
        ) {
            return 'Severe';
        }

        // Moderate
        if (
            $hfaZ < -2 ||
            $bfaZ < -2 ||
            ($wfaZ !== null && $wfaZ < -2)
        ) {
            return 'Moderate';
        }

        // At Risk
        //
        // WHO 5-19:
        // > +1 SD = overweight
        // > +2 SD = obesity
        //
        // Both are grouped as "At Risk" at the application level.
        if ($bfaZ > 1) {
            return 'At Risk';
        }

        return 'Healthy';
    }


    /**
     * Application-level summary for adults.
     *
     * This is an application-level grouping of WHO adult BMI categories.
     * It is NOT an official WHO "overall status" indicator.
     *
     * Severe:
     *   BMI < 16.0
     *
     * Moderate:
     *   BMI 16.0 - <18.5
     *
     * Healthy:
     *   BMI 18.5 - <25.0
     *
     * At Risk:
     *   BMI >= 25.0
     *
     * WHO adult BMI:
     *   <16.0     = severe thinness
     *   16.0-16.9 = moderate thinness
     *   17.0-18.4 = mild thinness
     *   18.5-24.9 = normal
     *   25.0-29.9 = overweight
     *   >=30.0    = obesity
     */
    protected function computeOverallAdult(float $bmi): string
    {
        // Severe
        if ($bmi < 16.0) {
            return 'Severe';
        }

        // Moderate
        if ($bmi < 18.5) {
            return 'Moderate';
        }

        // Healthy
        if ($bmi < 25.0) {
            return 'Healthy';
        }

        // Overweight / obesity
        return 'At Risk';
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    protected function normalizeSex(
        mixed $sex
    ): ?string {
        if ($sex === null) {
            return null;
        }

        $value = strtolower(
            trim((string) $sex)
        );

        return match ($value) {
            'male',
            'm',
            'boy',
            'boys',
            '1' => 'boys',

            'female',
            'f',
            'girl',
            'girls',
            '0' => 'girls',

            default => null,
        };
    }

    protected function toFloat(
        mixed $value
    ): ?float {
        if (
            $value === null ||
            $value === ''
        ) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected function roundZ(
        float $z
    ): float {
        return round($z, 2);
    }
}