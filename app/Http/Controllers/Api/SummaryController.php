<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Summary;
use App\Services\ActivityLogService;
use App\Services\SummaryService;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function generate(Request $request, SummaryService $service, ActivityLogService $activityLogService)
    {
        $activityLogService->log(
            auth()->id(),
            'generate',
            "{$request->user()->name} generated a report.",
            $request
        );

        return response()->json(
            $service->generateReport(
                $request->start_date,
                $request->end_date
            )
        );
    }

    public function index()
    {
        return response()->json(
            Summary::latest()->get()
        );
    }
}
