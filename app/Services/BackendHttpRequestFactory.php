<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;

class BackendHttpRequestFactory
{
    public function __construct(public HttpFactory $http) {}

    public function make(?string $baseUrl = null, ?int $maxDurationMs = null): PendingRequest
    {
        $baseTimeout = (int) config('backends.timeout', 10);
        $baseConnectTimeout = (int) config('backends.connect_timeout', 3);
        $timeoutSeconds = $baseTimeout;
        $connectTimeoutSeconds = $baseConnectTimeout;
        $retries = max((int) config('backends.retries', 2), 0);

        if (is_int($maxDurationMs)) {
            $boundedTimeout = max($maxDurationMs / 1000, 0.2);
            $timeoutSeconds = min($baseTimeout, $boundedTimeout);
            $connectTimeoutSeconds = min($baseConnectTimeout, $timeoutSeconds);
            $retries = 0;
        }

        $request = $this->http
            ->acceptJson()
            ->connectTimeout($connectTimeoutSeconds)
            ->timeout($timeoutSeconds);

        if (is_string($baseUrl) && $baseUrl !== '') {
            $request = $request->baseUrl($baseUrl);
        }

        if ($retries > 0) {
            $request = $request->retry(
                $retries,
                (int) config('backends.retry_sleep_ms', 150),
                throw: false,
            );
        }

        return $request;
    }
}
