<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BackendProxyTelemetryLogger
{
    public function logCompleted(
        string $requestId,
        string $method,
        string $incomingPath,
        string $resolvedPath,
        string $backend,
        int $status,
        int $durationMs,
    ): void {
        if (! $this->shouldLog()) {
            return;
        }

        Log::info('backend_proxy_request_completed', [
            'request_id' => $requestId,
            'method' => $method,
            'incoming_path' => $incomingPath,
            'resolved_path' => $resolvedPath,
            'backend' => $backend,
            'status' => $status,
            'duration_ms' => $durationMs,
        ]);
    }

    public function logFailed(
        string $requestId,
        string $method,
        string $incomingPath,
        string $resolvedPath,
        int $status,
        string $reason,
        int $durationMs,
    ): void {
        if (! $this->shouldLog()) {
            return;
        }

        Log::warning('backend_proxy_request_failed', [
            'request_id' => $requestId,
            'method' => $method,
            'incoming_path' => $incomingPath,
            'resolved_path' => $resolvedPath,
            'status' => $status,
            'reason' => $reason,
            'duration_ms' => $durationMs,
        ]);
    }

    private function shouldLog(): bool
    {
        if (! (bool) config('backends.telemetry_enabled', true)) {
            return false;
        }

        $sampleRate = (int) config('backends.telemetry_sample_rate_percentage', 100);

        if ($sampleRate >= 100) {
            return true;
        }

        if ($sampleRate <= 0) {
            return false;
        }

        return random_int(1, 100) <= $sampleRate;
    }
}
