<?php

namespace App\Services;

class BackendServerRegistry
{
    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_values(array_filter(
            config('backends.servers', []),
            static fn (mixed $server): bool => is_string($server) && $server !== ''
        ));
    }
}
