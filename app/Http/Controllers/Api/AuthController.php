<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
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
            'area' => 'required|string|max:255',
            'password' => 'required|confirmed|string|min:6',
            'role' => 'required|string',
        ]);

        $user = User::create([
            'name' => $data["name"],
            'email' => $data["email"],
            'contact_number' => $data["contact_number"],
            'area' => $data["area"],
            'password' => Hash::make($data["password"]),
            'role' => $data["role"],
            'status' => 'pending',
        ]);

        return response()->json($user, 201);
    }

    public function login(Request $request)
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

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }
}
