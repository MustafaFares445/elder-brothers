<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDashboardLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get('dashboard_locale', 'ar');
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';

        app()->setLocale($locale);

        return $next($request);
    }
}
