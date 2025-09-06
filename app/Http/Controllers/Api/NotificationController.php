<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(){
        $notif = Notification::whereDate('created_at', Carbon::today())
        ->latest()
        ->take(2)
        ->get();

        return response()->json(["data" => $notif]);
    }
}
