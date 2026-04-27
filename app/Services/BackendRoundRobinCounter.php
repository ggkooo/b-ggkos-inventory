<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class BackendRoundRobinCounter
{
    public function __construct(public CacheFactory $cacheFactory) {}

    public function nextStartIndex(int $totalServers): int
    {
        if (! is_int($this->cache()->get($this->cacheKey()))) {
            $this->cache()->forever($this->cacheKey(), -1);
        }

        $counter = (int) $this->cache()->increment($this->cacheKey());

        return $counter % $totalServers;
    }

    /**
     * @return array{cache_key: string, counter: int|null, next_index: int|null, configured_servers: int}
     */
    public function state(int $serverCount): array
    {
        $counter = $this->cache()->get($this->cacheKey());

        return [
            'cache_key' => $this->cacheKey(),
            'cache_store' => $this->cacheStore(),
            'counter' => is_int($counter) ? $counter : null,
            'next_index' => is_int($counter) && $serverCount > 0 ? (($counter + 1) % $serverCount) : null,
            'configured_servers' => $serverCount,
        ];
    }

    private function cache(): CacheRepository
    {
        return $this->cacheFactory->store($this->cacheStore());
    }

    private function cacheStore(): string
    {
        return (string) config('backends.cache_store', config('cache.default'));
    }

    private function cacheKey(): string
    {
        return (string) config('backends.round_robin_cache_key', 'backends:round-robin:index');
    }
}
