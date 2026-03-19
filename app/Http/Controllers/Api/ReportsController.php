<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $monthStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $isCurrentMonth = $month == now()->month && $year == now()->year;
        $monthEnd = $isCurrentMonth ? now() : $monthStart->copy()->endOfMonth();
        $last7days = $isCurrentMonth
            ? now()->subDays(6)->startOfDay()
            : $monthStart->copy()->endOfMonth()->subDays(6)->startOfDay();

        // 1. Partner usage (aktiv partnerler, ayliq API call sayi)
        $partnerUsage = DB::table('partners')
            ->leftJoin('api_logs', function ($join) use ($monthStart, $monthEnd) {
                $join->on('partners.id', '=', 'api_logs.partner_id')
                    ->where('api_logs.created_at', '>=', $monthStart)
                    ->where('api_logs.created_at', '<=', $monthEnd)
                    ->where('api_logs.status_code', '<', 429);
            })
            ->leftJoin('plans', 'partners.plan_id', '=', 'plans.id')
            ->where('partners.status', 'active')
            ->select(
                'partners.id',
                'partners.company_name as name',
                'plans.display_name as plan',
                'partners.monthly_limit as limit',
                DB::raw('COUNT(api_logs.id) as calls')
            )
            ->groupBy('partners.id', 'partners.company_name', 'plans.display_name', 'partners.monthly_limit')
            ->orderByDesc('calls')
            ->get()
            ->map(fn($row) => [
                'name' => $row->name,
                'plan' => $row->plan ?? 'Free',
                'calls' => (int) $row->calls,
                'limit' => (int) $row->limit,
            ]);

        // 2. Plan distribution
        $planDistribution = DB::table('partners')
            ->leftJoin('plans', 'partners.plan_id', '=', 'plans.id')
            ->select(
                DB::raw("COALESCE(plans.display_name, 'Free') as name"),
                DB::raw('COUNT(*) as value')
            )
            ->groupBy('plans.display_name')
            ->get()
            ->values()
            ->map(fn($row) => [
                'name' => $row->name,
                'value' => (int) $row->value,
            ]);

        // 3. Error rate (son 7 gun)
        $errorRateData = ApiLog::where('created_at', '>=', $last7days)
            ->where('created_at', '<=', $monthEnd)
            ->select(
                DB::raw("DATE(created_at) as date"),
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END) as count_4xx"),
                DB::raw("SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as count_5xx")
            )
            ->groupBy(DB::raw("DATE(created_at)"))
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => \Carbon\Carbon::parse($row->date)->format('M d'),
                'rate_4xx' => $row->total > 0 ? round(($row->count_4xx / $row->total) * 100, 2) : 0,
                'rate_5xx' => $row->total > 0 ? round(($row->count_5xx / $row->total) * 100, 2) : 0,
            ]);

        // 4. Response time by endpoint (bu ay)
        $responseTimeByEndpoint = ApiLog::where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $monthEnd)
            ->select(
                'endpoint',
                DB::raw("AVG(response_time_ms) as avg_ms")
            )
            ->groupBy('endpoint')
            ->orderByDesc('avg_ms')
            ->limit(6)
            ->get()
            ->map(fn($row) => [
                'endpoint' => $row->endpoint,
                'avg_ms' => (int) $row->avg_ms,
            ]);

        return response()->json([
            'partner_usage' => $partnerUsage,
            'plan_distribution' => $planDistribution,
            'error_rate' => $errorRateData,
            'response_time_by_endpoint' => $responseTimeByEndpoint,
            'month' => $month,
            'year' => $year,
        ]);
    }
}
