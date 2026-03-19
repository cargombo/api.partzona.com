<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;

class EnsureIsPartner
{
    public function handle(Request $request, Closure $next)
    {
        if (!($request->user() instanceof Partner)) {
            return response()->json([
                'status' => 403,
                'message' => __('api.partner_only'),
            ], 403);
        }

        return $next($request);
    }
}
