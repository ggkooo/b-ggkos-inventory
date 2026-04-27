<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;

class BackendRoundRobinClient
{
    public function __construct(
        public BackendServerRegistry $servers,
        public BackendRoundRobinCounter $counter,
        public BackendHttpRequestFactory $requestFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{response: Response, backend: string}
     */
    public function send(string $method, string $path = '/', array $options = []): array
    {
        $startedAt = hrtime(true);
        $maxTotalDurationMs = max((int) config('backends.max_total_duration_ms', 25000), 1000);
        $servers = $this->servers->all();

        if ($servers === []) {
            throw new RuntimeException('No backend servers configured.');
        }

        $startIndex = $this->counter->nextStartIndex(count($servers));
        $lastResponse = null;
        $lastBackend = null;

        for ($attempt = 0; $attempt < count($servers); $attempt++) {
            $remainingMs = $maxTotalDurationMs - $this->elapsedMs($startedAt);

            if ($remainingMs <= 0) {
                break;
            }

            $serverIndex = ($startIndex + $attempt) % count($servers);
            $server = rtrim($servers[$serverIndex], '/');

            try {
                $response = $this->requestFactory->make($server, $remainingMs)->send($method, '/'.ltrim($path, '/'), $options);

                if (! $response->serverError()) {
                    return [
                        'response' => $response,
                        'backend' => $server,
                    ];
                }

                $lastResponse = $response;
                $lastBackend = $server;
            } catch (ConnectionException) {
                $lastBackend = $server;
            }
        }

        if ($lastResponse !== null && $lastBackend !== null) {
            return [
                'response' => $lastResponse,
                'backend' => $lastBackend,
            ];
        }

        throw new ConnectionException('All backend servers failed to respond.');
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
