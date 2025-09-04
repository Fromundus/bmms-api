<?php

use App\Exports\RegisteredMembersExport;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\RegisteredMemberController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
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
        
        Route::prefix('/users')->group(function(){
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::delete('/', [UserController::class, 'delete']);
        });

        Route::prefix('/patients')->group(function(){
            Route::get('/', [PatientController::class, 'index']);
            Route::post('/', [PatientController::class, 'store']);
            Route::put('/', [PatientController::class, 'update']);
            Route::delete('/', [PatientController::class, 'delete']);
        });

        Route::prefix('/settings')->group(function(){
            Route::get('/', [SettingController::class, 'index']);
            Route::post('/', [SettingController::class, 'save']);
        });
        
    });
    
    //USER ACCOUNTS
    Route::put('/updateuser/{id}', [UserController::class, 'update']);
    Route::put('/changepassword/{id}', [UserController::class, 'changePassword']);
});

Route::post('/login', [AuthController::class, 'login']);

Route::get('/test', function(){
    return response()->json([
        "message" => "success"
    ], 200);
});