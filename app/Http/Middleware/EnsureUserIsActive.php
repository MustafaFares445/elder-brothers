<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $status = $request->user()?->status;

        if ($status !== 'active') {
            $inactive = $status === 'inactive';

            return response()->json([
                'success' => false,
                'code' => $inactive ? 'ACCOUNT_INACTIVE' : 'ACCOUNT_SUSPENDED',
                'message' => $inactive ? __('api.account_inactive') : __('api.account_suspended'),
            ], 403);
        }

        return $next($request);
    }
}