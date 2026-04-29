<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Models\Partner;
use App\Models\PartnerToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PartnerApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Partner API həmişə JSON qaytarsın — Accept başlığı nə olursa olsun.
        // (Validation səhvlərində browser-style redirect-lərin qarşısını alır.)
        $request->headers->set('Accept', 'application/json');

        // Dil parametri: ?lang=az|ru|en (default: en)
        $lang = $request->input('lang', $request->header('Accept-Language', 'en'));
        if (in_array($lang, ['az', 'ru', 'en'])) {
            app()->setLocale($lang);
        }

        $startTime = microtime(true);
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $method = $request->method();
        $endpoint = $this->getEndpointPattern($request);
        $partnerId = null;
        $tokenId = null;

        // 1. Token yoxla
        $bearer = $request->bearerToken();
        if (!$bearer) {
            $this->log($partnerId, $tokenId, $endpoint, $method, 401, $startTime, $ip, $userAgent, 'Token missing');
            return response()->json([
                'status' => 401,
                'message' => __('api.token_required'),
            ], 401);
        }

        $tokenRecord = PartnerToken::where('token_key', $bearer)
            ->where('status', 'active')
            ->first();

        if (!$tokenRecord) {
            $this->log($partnerId, $tokenId, $endpoint, $method, 401, $startTime, $ip, $userAgent, 'Invalid token');
            return response()->json([
                'status' => 401,
                'message' => __('api.token_invalid'),
            ], 401);
        }

        $tokenId = $tokenRecord->id;
        $partnerId = $tokenRecord->partner_id;

        // Token vaxtı bitibmi
        if ($tokenRecord->expires_at && $tokenRecord->expires_at->isPast()) {
            $tokenRecord->update(['status' => 'expired']);
            $this->log($partnerId, $tokenId, $endpoint, $method, 401, $startTime, $ip, $userAgent, 'Token expired');
            return response()->json([
                'status' => 401,
                'message' => __('api.token_expired'),
            ], 401);
        }

        // 2. Partner yoxla
        $partner = Partner::find($partnerId);
        if (!$partner || $partner->status !== 'active') {
            $this->log($partnerId, $tokenId, $endpoint, $method, 403, $startTime, $ip, $userAgent, 'Partner inactive');
            return response()->json([
                'status' => 403,
                'message' => __('api.partner_inactive'),
            ], 403);
        }

        // 3. IP Whitelist yoxla
        if (!empty($tokenRecord->ip_whitelist) && !in_array($ip, $tokenRecord->ip_whitelist)) {
            $this->log($partnerId, $tokenId, $endpoint, $method, 403, $startTime, $ip, $userAgent, "IP not allowed: {$ip}");
            return response()->json([
                'status' => 403,
                'message' => __('api.ip_not_allowed', ['ip' => $ip]),
            ], 403);
        }

        // 4. Endpoint icazəsi yoxla — icazə verilməyibsə BLOKLA
        $allowedEndpoints = $partner->allowedEndpoints()->pluck('endpoint')->toArray();
        if (empty($allowedEndpoints)) {
            $this->log($partnerId, $tokenId, $endpoint, $method, 403, $startTime, $ip, $userAgent, 'No endpoint permission');
            return response()->json([
                'status' => 403,
                'message' => __('api.no_endpoint_permission'),
            ], 403);
        }

        if (!in_array($endpoint, $allowedEndpoints)) {
            $this->log($partnerId, $tokenId, $endpoint, $method, 403, $startTime, $ip, $userAgent, "Endpoint not allowed: {$endpoint}");
            return response()->json([
                'status' => 403,
                'message' => __('api.endpoint_not_allowed', ['endpoint' => $endpoint]),
            ], 403);
        }

        // 4b. Kateqoriya icazəsi yoxla (categoryId olan route-lar üçün)
        $categoryId = $request->route('categoryId');
        if ($categoryId) {
            $allowedCategoryIds = $partner->allowedCategories()
                ->pluck('categories_1688.category_id')
                ->map(fn($v) => (int) $v)
                ->toArray();

            if (empty($allowedCategoryIds)) {
                $this->log($partnerId, $tokenId, $endpoint, $method, 403, $startTime, $ip, $userAgent, 'No category permission');
                return response()->json([
                    'status' => 403,
                    'message' => __('api.no_category_permission'),
                ], 403);
            }

            if (!in_array((int) $categoryId, $allowedCategoryIds)) {
                $this->log($partnerId, $tokenId, $endpoint, $method, 403, $startTime, $ip, $userAgent, "Category not allowed: {$categoryId}");
                return response()->json([
                    'status' => 403,
                    'message' => __('api.category_not_allowed', ['id' => $categoryId]),
                ], 403);
            }
        }

        // 5. Rate limit — api_logs-dan hesablanır, limits_reset_at nəzərə alınır
        $resetAt = $partner->limits_reset_at;

        // RPM
        if ($partner->rpm_limit > 0) {
            $since = now()->subMinute();
            $currentRpm = ApiLog::where('partner_id', $partnerId)
                ->where('created_at', '>=', $since)
                ->where('status_code', '<', 429)
                ->count();

            if ($currentRpm >= $partner->rpm_limit) {
                $this->log($partnerId, $tokenId, $endpoint, $method, 429, $startTime, $ip, $userAgent, "RPM limit exceeded: {$currentRpm}/{$partner->rpm_limit}");
                return response()->json([
                    'status' => 429,
                    'message' => __('api.rpm_exceeded', ['limit' => $partner->rpm_limit]),
                    'retry_after' => 60,
                ], 429)->header('Retry-After', 60);
            }
        }

        // Gündəlik limit
        if ($partner->daily_limit > 0) {
            $dailySince = $resetAt && $resetAt->isToday() ? $resetAt : now()->startOfDay();
            $currentDaily = ApiLog::where('partner_id', $partnerId)
                ->where('created_at', '>=', $dailySince)
                ->where('status_code', '<', 429)
                ->count();

            if ($currentDaily >= $partner->daily_limit) {
                $this->log($partnerId, $tokenId, $endpoint, $method, 429, $startTime, $ip, $userAgent, "Daily limit exceeded: {$currentDaily}/{$partner->daily_limit}");
                return response()->json([
                    'status' => 429,
                    'message' => __('api.daily_exceeded', ['limit' => $partner->daily_limit]),
                ], 429);
            }
        }

        // Aylıq limit
        if ($partner->monthly_limit > 0) {
            $monthlySince = $resetAt && $resetAt->isCurrentMonth() ? $resetAt : now()->startOfMonth();
            $currentMonthly = ApiLog::where('partner_id', $partnerId)
                ->where('created_at', '>=', $monthlySince)
                ->where('status_code', '<', 429)
                ->count();

            if ($currentMonthly >= $partner->monthly_limit) {
                $this->log($partnerId, $tokenId, $endpoint, $method, 429, $startTime, $ip, $userAgent, "Monthly limit exceeded: {$currentMonthly}/{$partner->monthly_limit}");
                return response()->json([
                    'status' => 429,
                    'message' => __('api.monthly_exceeded', ['limit' => $partner->monthly_limit]),
                ], 429);
            }
        }

        // Token son istifadə vaxtını yenilə
        $tokenRecord->update(['last_used_at' => now()]);

        // Request-ə partner və token məlumatlarını əlavə et
        $request->merge([
            '_partner' => $partner,
            '_partner_token' => $tokenRecord,
            '_is_sandbox' => $tokenRecord->token_type === 'sandbox',
        ]);

        // Response-u al və logla
        $response = $next($request);

        $this->log(
            $partnerId,
            $tokenId,
            $endpoint,
            $method,
            $response->getStatusCode(),
            $startTime,
            $ip,
            $userAgent,
            $response->getStatusCode() >= 400 ? substr($response->getContent(), 0, 500) : null,
            $request->except(['_partner', '_partner_token'])
        );

        return $response;
    }

    /**
     * Route URI-dən endpoint pattern çıxar
     * api/v1/categories/{categoryId}/products → /categories/{categoryId}/products
     */
    private function getEndpointPattern(Request $request): string
    {
        $uri = $request->route()?->uri() ?? $request->path();
        return '/' . preg_replace('#^api/v1/?#', '', $uri);
    }

    /**
     * API log yaz
     */
    private function log(
        ?string $partnerId,
        ?int $tokenId,
        string $endpoint,
        string $method,
        int $statusCode,
        float $startTime,
        string $ip,
        ?string $userAgent,
        ?string $errorMessage = null,
        ?array $requestParams = null
    ): void {
        if (!$partnerId) return;

        try {
            ApiLog::create([
                'partner_id' => $partnerId,
                'token_id' => $tokenId,
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode,
                'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'ip' => $ip,
                'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
                'request_params' => $requestParams ? json_encode($requestParams, JSON_UNESCAPED_UNICODE) : null,
                'error_message' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to write API log: ' . $e->getMessage());
        }
    }
}
