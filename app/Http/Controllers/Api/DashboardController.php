<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\Partner;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $today = now()->startOfDay();
        $last24h = now()->subHours(24);
        $last7days = now()->subDays(7)->startOfDay();
        $monthStart = now()->startOfMonth();

        // Partner stats
        $totalPartners = Partner::count();
        $activePartners = Partner::where('status', 'active')->count();
        $pendingPartners = Partner::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'company_name', 'contact_name', 'email', 'created_at']);

        // API call stats (today)
        $todayCalls = ApiLog::where('created_at', '>=', $today)->count();
        $todayErrors = ApiLog::where('created_at', '>=', $today)
            ->where('status_code', '>=', 400)
            ->count();

        // Avg response time (last 24h)
        $avgResponseMs = (int) ApiLog::where('created_at', '>=', $last24h)
            ->avg('response_time_ms') ?: 0;

        // API uptime (last 24h) = (total - 5xx) / total * 100
        $total24h = ApiLog::where('created_at', '>=', $last24h)->count();
        $server5xx = ApiLog::where('created_at', '>=', $last24h)
            ->where('status_code', '>=', 500)
            ->count();
        $apiUptime = $total24h > 0
            ? round((($total24h - $server5xx) / $total24h) * 100, 2)
            : 100;

        // 1688 latency (avg response time for external calls in last 24h)
        $api1688Latency = (int) ApiLog::where('created_at', '>=', $last24h)
            ->where('status_code', '<', 500)
            ->avg('response_time_ms') ?: 0;

        // Financial stats
        $totalDeposits = (float) Partner::sum('deposit_balance');
        $totalOutstanding = (float) Partner::sum('outstanding_balance');

        // Daily calls chart (last 7 days)
        $dailyCalls = ApiLog::where('created_at', '>=', $last7days)
            ->select(
                DB::raw("DATE(created_at) as date"),
                DB::raw("COUNT(*) as calls"),
                DB::raw("SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors")
            )
            ->groupBy(DB::raw("DATE(created_at)"))
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => \Carbon\Carbon::parse($row->date)->format('M d'),
                'calls' => (int) $row->calls,
                'errors' => (int) $row->errors,
            ]);

        // Top endpoints (this month)
        $topEndpoints = ApiLog::where('created_at', '>=', $monthStart)
            ->select(
                'endpoint',
                DB::raw("COUNT(*) as calls"),
                DB::raw("AVG(response_time_ms) as avg_ms")
            )
            ->groupBy('endpoint')
            ->orderByDesc('calls')
            ->limit(6)
            ->get()
            ->map(fn($row) => [
                'endpoint' => $row->endpoint,
                'calls' => (int) $row->calls,
                'avg_ms' => (int) $row->avg_ms,
            ]);

        return response()->json([
            'stats' => [
                'total_partners' => $totalPartners,
                'active_partners' => $activePartners,
                'today_calls' => $todayCalls,
                'today_errors' => $todayErrors,
                'avg_response_ms' => $avgResponseMs,
                'api_uptime' => $apiUptime,
                'api_latency_1688' => $api1688Latency,
            ],
            'financial' => [
                'total_deposits' => $totalDeposits,
                'total_outstanding' => $totalOutstanding,
            ],
            'daily_calls' => $dailyCalls,
            'top_endpoints' => $topEndpoints,
            'pending_partners' => $pendingPartners,
        ]);
    }
}
