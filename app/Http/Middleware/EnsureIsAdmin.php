<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!($request->user() instanceof User)) {
            return response()->json([
                'status' => 403,
                'message' => __('api.admin_only'),
            ], 403);
        }

        return $next($request);
    }
}
