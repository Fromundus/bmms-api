<?php

use App\Exports\PatientsExport;
use App\Exports\RegisteredMembersExport;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\RegisteredMemberController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SMSMessageController;
use App\Http\Controllers\Api\UserController;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Services\SMSService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::middleware(['auth:sanctum', 'active'])->group(function(){
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    
    Route::middleware('admin')->group(function(){
        //USER ACCOUNTS
        // Route::get('/accounts', [UserController::class, 'index']);
        // Route::post('/account', [AuthController::class, 'register']);
        // Route::post('/account/batch-delete', [UserController::class, 'batchDelete']);
        // Route::post('/account/batch-update', [UserController::class, 'batchUpdateStatus']);

        Route::put('/update-status', [UserController::class, 'updateStatus']);
        
        Route::prefix('/users')->group(function(){
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::delete('/', [UserController::class, 'delete']);
        });

        Route::get('/getStats', [PatientController::class, 'getStats']);

        Route::prefix('/patients')->group(function(){
            Route::get('/', [PatientController::class, 'index']);
            Route::post('/', [PatientController::class, 'store']);
            Route::get('/{id}', [PatientController::class, 'show']);
            Route::get('/history/{id}', [PatientController::class, 'history']);
            Route::put('/{id}', [PatientController::class, 'updateOrAddRecords']);
            Route::put('/updateinfo/{id}', [PatientController::class, 'updateInformation']);
            Route::delete('/', [PatientController::class, 'delete']);
        });

        
        //EXPORTS
        Route::get('/reports/patients/export/{type}', [ReportController::class, 'export']);

        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        Route::post('/send-schedule', [SMSMessageController::class, 'sendSchedule']);

        Route::get('/generate-report', [ReportController::class, 'generateReport']);
    });
    
    //USER ACCOUNTS
    Route::put('/updateuser/{id}', [UserController::class, 'update']);
    Route::put('/changepassword/{id}', [UserController::class, 'changePassword']);
});
Route::get('/notifications', [NotificationController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/test', function(){
    return response()->json([
        "message" => "success"
    ], 200);
});

Route::get('/test/sms-iprog', function(SMSService $smsservice){
    $response = $smsservice->sendSms('09664574089', 'Hello John Carl Cueva, your new nutrition record from 2025-10-20 has been added.');
    return response()->json($response);
});