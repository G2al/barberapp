<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Account non attivo.',
            ], 403);
        }

        return $next($request);
    }
}
