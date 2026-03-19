<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiLogController extends Controller
{
    /**
     * Bütün API logları (admin)
     * GET /api/api-logs
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApiLog::with('partner:id,company_name');

        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        if ($request->filled('status_code')) {
            $code = $request->status_code;
            if ($code === '2xx') {
                $query->whereBetween('status_code', [200, 299]);
            } elseif ($code === '4xx') {
                $query->whereBetween('status_code', [400, 499]);
            } elseif ($code === '5xx') {
                $query->whereBetween('status_code', [500, 599]);
            } else {
                $query->where('status_code', $code);
            }
        }

        if ($request->filled('endpoint')) {
            $query->where('endpoint', 'like', '%' . $request->endpoint . '%');
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->limit($request->input('limit', 200))
            ->get();

        return response()->json($logs);
    }

    /**
     * Partnerin öz API logları (portal)
     * GET /api/partner/api-logs
     */
    public function myLogs(Request $request): JsonResponse
    {
        $logs = ApiLog::where('partner_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json($logs);
    }
}
