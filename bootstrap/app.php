<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\DetectLocaleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(DetectLocaleMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Handle custom ApiException
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $e->render();
            }
        });

        // Handle AuthenticationException
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'code' => 'unauthenticated',
                    'message' => __('auth.unauthenticated'),
                ], 401);
            }
        });

        // Handle ModelNotFoundException and NotFoundHttpException
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'code' => 'resource_not_found',
                    'message' => __('auth.resource_not_found'),
                ], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'code' => 'resource_not_found',
                    'message' => __('auth.resource_not_found'),
                ], 404);
            }
        });

        // Handle ThrottleRequestsException
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

                return response()->json([
                    'code' => 'too_many_requests',
                    'message' => __('auth.too_many_requests'),
                    'retry_after' => $retryAfter ? (int) $retryAfter : null,
                ], 429)->header('Retry-After', $retryAfter);
            }
        });

        // Handle ValidationException
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $errors = $e->errors();

                // The current-password rule maps to a stable business code so the
                // front-end can distinguish it from generic form validation errors.
                $isCurrentPasswordFailure = false;

                foreach ($e->validator->failed() as $rules) {
                    foreach ($rules as $rule => $parameters) {
                        if (strtolower($rule) === 'currentpassword') {
                            $isCurrentPasswordFailure = true;
                            break 2;
                        }
                    }
                }

                return response()->json([
                    'code' => $isCurrentPasswordFailure ? 'password_current_incorrect' : 'validation_failed',
                    'message' => $e->getMessage(),
                    'errors' => $errors,
                ], $e->status);
            }
        });
    })->create();
