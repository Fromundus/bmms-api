<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\SMSService;
use Illuminate\Http\Request;

class SMSMessageController extends Controller
{
    // public function sendSchedule(Request $request, SMSService $smsservice){
    //     $patients = Patient::all();

    //     $patientMessage = "Hello! A community nutrition check-up is scheduled on {$request->date} at {$request->location}. Please attend. Thank you.";

    //     foreach($patients as $patient){
    //         $smsservice->sendSms("{$patient->contact_number}", $patientMessage);
    //     }

    //     return response()->json([
    //         "message" => "Success"
    //     ], 200);
    // }

    public function sendSchedule(Request $request, SMSService $smsservice)
    {
        $request->validate([
            'date' => 'required',
            'location' => 'required',
            'patient_ids' => 'required|array',
            'patient_ids.*' => 'exists:patients,id',
        ]);

        $patients = Patient::whereIn('id', $request->patient_ids)->get();

        $message = "Hello! A community nutrition check-up is scheduled on {$request->date} at {$request->location}. Please attend. Thank you.";

        foreach ($patients as $patient) {
            if ($patient->contact_number) {
                $smsservice->sendSms($patient->contact_number, $message);
            }
        }

        return response()->json([
            "message" => "Messages sent",
            "sent_to" => $patients->count()
        ]);
    }
}
