<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'superadmin' && $user->role !== 'admin') {
            // Revoke the current token
            // $request->user()->currentAccessToken()->delete();
            $request->user()->tokens()->delete();

            // Clear session/cookie if necessary
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
