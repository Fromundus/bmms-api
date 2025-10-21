<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\SMSService;
use Illuminate\Http\Request;

class SMSMessageController extends Controller
{
    public function sendSchedule(Request $request, SMSService $smsservice){
        $patients = Patient::all();

        $patientMessage = "Hello! A community nutrition check-up is scheduled on {$request->date} at {$request->location}. Please attend. Thank you.";

        foreach($patients as $patient){
            $smsservice->sendSms("{$patient->contact_number}", $patientMessage);
        }

        return response()->json([
            "message" => "Success"
        ], 200);
    }
}
