<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BackendHealthReporter
{
    public function __construct(
        public BackendServerRegistry $servers,
        public BackendRoundRobinCounter $counter,
        public BackendHttpRequestFactory $requestFactory,
        public CacheFactory $cacheFactory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $ttl = max((int) config('backends.health_report_cache_ttl_seconds', 2), 0);

        if ($ttl === 0) {
            return $this->buildReport();
        }

        return $this->cache()->remember(
            $this->healthReportCacheKey(),
            now()->addSeconds($ttl),
            fn (): array => $this->buildReport(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(): array
    {
        $overallStart = hrtime(true);
        $servers = $this->servers->all();
        $dependencyChecks = $this->dependencyChecks();
        $serverChecks = $this->serverChecks($servers);
        $summary = $this->summary($servers, $serverChecks, $overallStart, $dependencyChecks);

        return [
            'timestamp' => now()->toIso8601String(),
            'application' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'timezone' => config('app.timezone'),
                'cache_store' => config('cache.default'),
                'queue_connection' => config('queue.default'),
                'database_connection' => config('database.default'),
            ],
            'round_robin' => $this->counter->state(count($servers)),
            'dependencies' => $dependencyChecks,
            'summary' => $summary,
            'servers' => $serverChecks,
        ];
    }

    private function healthReportCacheKey(): string
    {
        return (string) config('backends.health_report_cache_key', 'backends:health:report');
    }

    private function cache(): CacheRepository
    {
        return $this->cacheFactory->store($this->cacheStore());
    }

    private function cacheStore(): string
    {
        return (string) config('backends.cache_store', config('cache.default'));
    }

    /**
     * @param  array<int, string>  $servers
     * @return array<int, array<string, mixed>>
     */
    private function serverChecks(array $servers): array
    {
        $method = (string) config('backends.health_method', 'GET');
        $healthPath = '/'.ltrim((string) config('backends.health_path', '/up'), '/');

        return collect($servers)
            ->map(function (string $server) use ($healthPath, $method): array {
                $target = rtrim($server, '/').$healthPath;
                $startedAt = hrtime(true);

                try {
                    $response = $this->requestFactory->make()->send($method, $target);

                    return [
                        'server' => $server,
                        'probe_url' => $target,
                        'ok' => $response->successful(),
                        'http_status' => $response->status(),
                        'response_time_ms' => $this->elapsedMs($startedAt),
                        'error' => null,
                        'response_excerpt' => Str::limit($response->body(), 300),
                    ];
                } catch (ConnectionException $exception) {
                    return [
                        'server' => $server,
                        'probe_url' => $target,
                        'ok' => false,
                        'http_status' => null,
                        'response_time_ms' => $this->elapsedMs($startedAt),
                        'error' => $exception->getMessage(),
                        'response_excerpt' => null,
                    ];
                }
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function dependencyChecks(): array
    {
        return [
            'database' => $this->databaseCheck(),
            'cache' => $this->cacheCheck(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseCheck(): array
    {
        $startedAt = hrtime(true);

        try {
            DB::connection()->getPdo();

            return [
                'ok' => true,
                'response_time_ms' => $this->elapsedMs($startedAt),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'response_time_ms' => $this->elapsedMs($startedAt),
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheCheck(): array
    {
        $startedAt = hrtime(true);
        $key = 'health:cache:probe:'.Str::random(8);

        try {
            cache()->put($key, true, now()->addMinute());
            $value = cache()->get($key);
            cache()->forget($key);

            return [
                'ok' => $value === true,
                'response_time_ms' => $this->elapsedMs($startedAt),
                'error' => $value === true ? null : 'Cache read/write validation failed.',
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'response_time_ms' => $this->elapsedMs($startedAt),
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<int, string>  $servers
     * @param  array<int, array<string, mixed>>  $checks
     * @param  array<string, mixed>  $dependencyChecks
     * @return array<string, mixed>
     */
    private function summary(array $servers, array $checks, int $overallStart, array $dependencyChecks): array
    {
        $configuredServers = count($servers);
        $healthyServers = collect($checks)->where('ok', true)->count();
        $responseTimes = collect($checks)->pluck('response_time_ms')->filter(static fn (mixed $time): bool => is_int($time) || is_float($time));
        $apiDependenciesHealthy = collect($dependencyChecks)->every(static fn (array $result): bool => ($result['ok'] ?? false) === true);

        $status = 'healthy';

        if ($configuredServers === 0) {
            $status = 'misconfigured';
        } elseif ($healthyServers === 0) {
            $status = 'down';
        } elseif ($healthyServers < $configuredServers || ! $apiDependenciesHealthy) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'configured_servers' => $configuredServers,
            'healthy_servers' => $healthyServers,
            'unhealthy_servers' => $configuredServers - $healthyServers,
            'health_percentage' => $configuredServers > 0 ? round(($healthyServers / $configuredServers) * 100, 2) : 0,
            'api_dependencies_healthy' => $apiDependenciesHealthy,
            'avg_response_time_ms' => $responseTimes->isNotEmpty() ? round((float) $responseTimes->avg(), 2) : null,
            'min_response_time_ms' => $responseTimes->isNotEmpty() ? $responseTimes->min() : null,
            'max_response_time_ms' => $responseTimes->isNotEmpty() ? $responseTimes->max() : null,
            'overall_check_time_ms' => $this->elapsedMs($overallStart),
        ];
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
