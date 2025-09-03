<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            // Revoke the current token
            // $request->user()->currentAccessToken()->delete();
            $request->user()->tokens()->delete();

            // Clear session/cookie if necessary
            return response()->json(['message' => 'Unauthorized: Account is inactive.'], 403);
        }

        return $next($request);
    }
}
