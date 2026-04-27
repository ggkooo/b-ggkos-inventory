<?php

namespace App\Services;

use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Response;

class BackendProxyResponseFactory
{
    public function make(ClientResponse $backendResponse, string $backend, string $requestId): Response
    {
        [$content, $contentType] = $this->contentWithServer($backendResponse, $backend, $requestId);

        return response(
            content: $content,
            status: $backendResponse->status(),
            headers: [
                'Content-Type' => $contentType,
                'X-Backend-Url' => $backend,
                'X-Request-Id' => $requestId,
            ],
        );
    }

    public function makeError(string $message, int $status, string $requestId): Response
    {
        return response([
            'message' => $message,
            'request_id' => $requestId,
        ], $status, [
            'X-Request-Id' => $requestId,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function contentWithServer(ClientResponse $backendResponse, string $backend, string $requestId): array
    {
        $contentType = $backendResponse->header('Content-Type', 'application/json');

        if (! is_string($contentType) || ! str_contains(strtolower($contentType), 'application/json')) {
            return [$backendResponse->body(), is_string($contentType) ? $contentType : 'application/octet-stream'];
        }

        $payload = $backendResponse->json();

        if (! is_array($payload)) {
            return [$backendResponse->body(), $contentType];
        }

        $payload['processed_by_server'] = $backend;
        $payload['request_id'] = $requestId;

        return [json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $backendResponse->body(), $contentType];
    }
}
