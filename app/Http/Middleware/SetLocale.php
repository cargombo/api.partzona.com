<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $available = config('app.available_locales', ['az', 'en', 'ru']);

        // 1. Query parameter ?lang=en
        $locale = $request->query('lang');

        // 2. Accept-Language header
        if (!$locale || !in_array($locale, $available)) {
            $locale = $request->getPreferredLanguage($available);
        }

        // 3. Fallback to app locale
        if (!$locale || !in_array($locale, $available)) {
            $locale = config('app.locale', 'az');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
