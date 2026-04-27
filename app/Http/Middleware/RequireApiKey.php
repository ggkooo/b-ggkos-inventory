<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RequireApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredApiKey = config('app.api_key');
        $providedApiKey = $request->header('X-API-KEY');

        if (! is_string($configuredApiKey) || $configuredApiKey === '') {
            return new JsonResponse([
                'message' => 'API key is not configured.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (! is_string($providedApiKey) || ! hash_equals($configuredApiKey, $providedApiKey)) {
            return new JsonResponse([
                'message' => 'Invalid API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
