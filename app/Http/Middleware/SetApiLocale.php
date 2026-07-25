<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = strtolower((string) $request->header('Accept-Language', config('app.locale')));
        $locale = str_starts_with($locale, 'en') ? 'en' : 'ar';
        app()->setLocale($locale);

        return $next($request);
    }
}
