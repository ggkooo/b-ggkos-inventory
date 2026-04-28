<?php

use Illuminate\Support\Facades\Http;

test('api routes require a valid api key', function () {
    config()->set('app.api_key', 'test-api-key');

    config()->set('backends.servers', [
        'http://backend-1.test',
    ]);
    config()->set('backends.health_path', '/up');
    config()->set('backends.health_report_cache_ttl_seconds', 0);

    Http::fake([
        'http://backend-1.test/up' => Http::response(['status' => 'ok'], 200),
    ]);

    $this->getJson('/api/gateway-health')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid API key.',
        ]);

    $this->withHeaders([
        'X-API-KEY' => 'wrong-key',
    ])->getJson('/api/gateway-health')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid API key.',
        ]);

    $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->getJson('/api/gateway-health')
        ->assertSuccessful()
        ->assertJsonPath('summary.status', 'healthy');
});
