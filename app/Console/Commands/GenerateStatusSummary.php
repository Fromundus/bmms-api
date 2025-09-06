<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Patient;
use App\Models\Notification;

class GenerateStatusSummary extends Command
{
    protected $signature = 'patients:summary';
    protected $description = 'Generate hourly summary notification for patient statuses';

    public function handle()
    {
        $total = Patient::count();

        $severe   = Patient::whereHas('latestRecord', fn($q) => $q->where('status', 'Severe'))->count();
        $moderate = Patient::whereHas('latestRecord', fn($q) => $q->where('status', 'Moderate'))->count();
        $atRisk   = Patient::whereHas('latestRecord', fn($q) => $q->where('status', 'At Risk'))->count();
        $healthy  = Patient::whereHas('latestRecord', fn($q) => $q->where('status', 'Healthy'))->count();

        $data = [
            'total'    => $total,
            'severe'   => $severe,
            'moderate' => $moderate,
            'at_risk'  => $atRisk,
            'healthy'  => $healthy,
        ];

        if($severe > 0 || $moderate > 0 || $moderate > 0){
            Notification::create([
                'title' => 'Summary',
                'data' => json_encode($data),
            ]);
        }

        if($severe > 0){
            Notification::create([
                'title' => 'Warning',
                'data' => json_encode($data),
            ]);
        }


        $this->info('Hourly patient summary notification created.');
    }
}
