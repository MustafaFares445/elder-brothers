<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetApiLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [SetApiLocale::class]);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*')
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.validation_failed'),
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || $e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'RESOURCE_NOT_FOUND',
                409 => 'RESOURCE_CONFLICT',
                429 => 'TOO_MANY_REQUESTS',
                default => $status >= 500 ? 'SERVER_ERROR' : 'BAD_REQUEST',
            };

            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $status >= 500 && ! config('app.debug')
                    ? __('api.server_error')
                    : ($e->getMessage() ?: __('api.request_failed')),
            ], $status);
        });
    })->create();
