<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Summary;
use App\Services\SummaryService;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function generate(Request $request, SummaryService $service)
    {
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
