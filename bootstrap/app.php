<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => \PHPOpenSourceSaver\JWTAuth\Http\Middleware\Authenticate::class,
            'jwt.auth' => \PHPOpenSourceSaver\JWTAuth\Http\Middleware\Authenticate::class,
            'jwt.refresh' => \PHPOpenSourceSaver\JWTAuth\Http\Middleware\RefreshToken::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'admin.auth' => \App\Http\Middleware\AdminAuthMiddleware::class,
            'customer.auth' => \App\Http\Middleware\CustomerAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Handle JWT / Auth exceptions (unauthenticated)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error' => 'Token not provided or invalid',
                ], 401);
            }
        });

        // Convert validation errors to JSON for APIs
        $exceptions->render(function (ValidationException $e) {
            return new JsonResponse([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        });

        // Catch-all exception format for API
        $exceptions->render(function (\PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Token error',
                    'error' => $e->getMessage()
                ], 401);
            }
        });
    })->create();
