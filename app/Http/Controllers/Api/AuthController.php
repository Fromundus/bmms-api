<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'contact_number' => 'required|string|unique:users,contact_number|min:11|max:11',
            'barangay_id' => 'required',
            // 'area' => 'required|string|max:255',
            'password' => 'required|confirmed|string|min:4',
            'role' => 'required|string',
        ]);

        $user = User::create([
            'name' => $data["name"],
            'email' => $data["email"],
            'contact_number' => $data["contact_number"],
            'barangay_id' => $data["barangay_id"],
            // 'area' => $data["area"],
            'password' => Hash::make($data["password"]),
            'role' => $data["role"],
            'status' => 'pending',
        ]);

        $user->assignRole($data['role']);

        return response()->json($user, 201);
    }

    public function login(Request $request, ActivityLogService $activityLogService)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'name' => ['The provided credentials are incorrect.'],
            ]);
        }

        if($user && $user->status !== "active"){
            if($user->status == "pending"){
                throw ValidationException::withMessages([
                    'name' => ['We are reviewing your account. Try again later.'],
                ]);
            } else {
                throw ValidationException::withMessages([
                    'name' => ['Invalid Account.'],
                ]);
            }
        }

        
        $token = $user->createToken('api_token')->plainTextToken;

        // ActivityLog::create([
        //     'user_id' => $user->id,
        //     'action' => 'login',
        //     'message' => 'Login',
        //     'ip_address' => $request->ip(),
        //     'user_agent' => $request->header('User-Agent'),
        //     'logged_at' => now(),
        // ]);

        $activityLogService->log(
            $user->id,
            'login',
            "{$user->name} logged in.",
            $request
        );

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    // public function me(Request $request)
    // {
    //     return response()->json($request->user());
    // }

    public function me(Request $request)
    {
        $user = $request->user()->load('barangay');

        return response()->json(
            array_merge(
                $user->toArray(),
                [
                    'roles' => $user->getRoleNames()->values()->all(),

                    'permissions' => $user->getAllPermissions()
                        ->pluck('name')
                        ->values()
                        ->all(),
                ]
            )
        );
    }

    public function logout(Request $request, ActivityLogService $activityLogService)
    {
        // ActivityLog::create([
        //     'user_id' => auth()->id(),
        //     'action' => 'logout',
        //     'ip_address' => $request->ip(),
        //     'user_agent' => $request->header('User-Agent'),
        //     'logged_at' => now(),
        // ]);

        $activityLogService->log(
            auth()->id(),
            'logout',
            "{$request->user()->name} logged out.",
            $request
        );
        
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }
}
