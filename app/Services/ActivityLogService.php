<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function log(
        int $userId,
        string $action,
        string $message,
        ?Request $request = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'message' => $message,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'logged_at' => now(),
        ]);
    }
}