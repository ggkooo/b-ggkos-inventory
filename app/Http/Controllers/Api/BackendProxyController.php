<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackendProxyRequestBuilder;
use App\Services\BackendProxyResponseFactory;
use App\Services\BackendProxyTelemetryLogger;
use App\Services\BackendRoundRobinClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class BackendProxyController extends Controller
{
    public function __construct(
        public BackendRoundRobinClient $backendClient,
        public BackendProxyRequestBuilder $requestBuilder,
        public BackendProxyResponseFactory $responseFactory,
        public BackendProxyTelemetryLogger $telemetry,
    ) {}

    public function __invoke(Request $request, string $path = ''): Response
    {
        $startedAt = hrtime(true);
        $proxyRequest = $this->requestBuilder->build($request, $path);
        $requestId = $proxyRequest['request_id'];
        $resolvedPath = $proxyRequest['path'];
        $incomingPath = '/'.ltrim($path, '/');
        $method = $request->method();

        try {
            $result = $this->backendClient->send(
                method: $method,
                path: $resolvedPath,
                options: $proxyRequest['options'],
            );
        } catch (RuntimeException $exception) {
            $this->telemetry->logFailed(
                requestId: $requestId,
                method: $method,
                incomingPath: $incomingPath,
                resolvedPath: $resolvedPath,
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                reason: $exception->getMessage(),
                durationMs: $this->elapsedMs($startedAt),
            );

            return $this->responseFactory->makeError(
                message: $exception->getMessage(),
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                requestId: $requestId,
            );
        } catch (ConnectionException) {
            $this->telemetry->logFailed(
                requestId: $requestId,
                method: $method,
                incomingPath: $incomingPath,
                resolvedPath: $resolvedPath,
                status: Response::HTTP_SERVICE_UNAVAILABLE,
                reason: 'all_backends_unavailable',
                durationMs: $this->elapsedMs($startedAt),
            );

            return $this->responseFactory->makeError(
                message: 'All backend servers are unavailable.',
                status: Response::HTTP_SERVICE_UNAVAILABLE,
                requestId: $requestId,
            );
        }

        $this->telemetry->logCompleted(
            requestId: $requestId,
            method: $method,
            incomingPath: $incomingPath,
            resolvedPath: $resolvedPath,
            backend: $result['backend'],
            status: $result['response']->status(),
            durationMs: $this->elapsedMs($startedAt),
        );

        return $this->responseFactory->make(
            backendResponse: $result['response'],
            backend: $result['backend'],
            requestId: $requestId,
        );
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
