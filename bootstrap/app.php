<?php

use App\Http\Middleware\RequireApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            RequireApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sempre retornar JSON para exceções
        $exceptions->shouldRenderJsonWhen(function () {
            return true;
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request): ?JsonResponse {
            if ($exception->getModel() !== User::class) {
                return null;
            }

            $routeUser = $request->route('user');

            if (! is_string($routeUser) || ! ctype_digit($routeUser)) {
                return null;
            }

            return response()->json([
                'message' => 'User not found. Use user UUID in the URL (for example: /api/users/{user_uuid}/profile).',
            ], 404);
        });
    })->create();
