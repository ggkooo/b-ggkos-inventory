<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BackendProxyRequestBuilder
{
    /**
     * @return array{path: string, options: array<string, mixed>, request_id: string}
     */
    public function build(Request $request, string $path = ''): array
    {
        $requestId = $this->resolveRequestId($request);

        return [
            'path' => $this->resolvePath($request, $path),
            'options' => $this->requestOptions($request, $requestId),
            'request_id' => $requestId,
        ];
    }

    private function resolveRequestId(Request $request): string
    {
        $requestId = $request->header('X-Request-Id');

        if (is_string($requestId) && trim($requestId) !== '') {
            return $requestId;
        }

        return (string) Str::uuid();
    }

    private function resolvePath(Request $request, string $path): string
    {
        $normalizedPath = ltrim($path, '/');
        $prependApiPrefix = (bool) $request->route('prepend_api_prefix', false);

        if (! $prependApiPrefix) {
            return $normalizedPath;
        }

        if ($normalizedPath === '') {
            return 'api';
        }

        if (str_starts_with($normalizedPath, 'api/')) {
            return $normalizedPath;
        }

        return 'api/'.$normalizedPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestOptions(Request $request, string $requestId): array
    {
        $headers = $this->forwardHeaders($request, $requestId);
        $backendApiKey = $this->resolveBackendApiKey($request);

        if ($backendApiKey !== null) {
            $headers['X-API-KEY'] = $backendApiKey;
        }

        $options = [
            'query' => $request->query(),
            'headers' => $headers,
        ];

        if ($request->isMethod('GET')) {
            return $options;
        }

        $options['json'] = $request->json()->all() ?: $request->all();

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function forwardHeaders(Request $request, string $requestId): array
    {
        $ignoredHeaders = [
            'host',
            'x-api-key',
            'x-request-id',
            'content-length',
            'connection',
        ];

        return collect($request->headers->all())
            ->mapWithKeys(static fn (array $values, string $key): array => [$key => implode(',', $values)])
            ->except($ignoredHeaders)
            ->put('X-Request-Id', $requestId)
            ->all();
    }

    private function resolveBackendApiKey(Request $request): ?string
    {
        $configuredApiKey = config('backends.api_key');

        if (is_string($configuredApiKey) && trim($configuredApiKey) !== '') {
            return $configuredApiKey;
        }

        if (! (bool) config('backends.forward_client_api_key', false)) {
            return null;
        }

        $incomingApiKey = $request->header('X-API-KEY');

        if (is_string($incomingApiKey) && trim($incomingApiKey) !== '') {
            return $incomingApiKey;
        }

        return null;
    }
}
