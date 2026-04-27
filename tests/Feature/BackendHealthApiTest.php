<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('app.api_key', 'test-api-key');
    config()->set('backends.health_path', '/up');
    config()->set('backends.health_method', 'GET');
    config()->set('backends.health_report_cache_ttl_seconds', 0);
    config()->set('backends.servers', [
        'http://backend-1.test',
        'http://backend-2.test',
        'http://backend-3.test',
        'http://backend-4.test',
        'http://backend-5.test',
    ]);
});

test('it returns detailed health diagnostics for all backends', function () {
    Http::fake([
        'http://backend-1.test/up' => Http::response(['status' => 'ok'], 200),
        'http://backend-2.test/up' => Http::response(['status' => 'ok'], 200),
        'http://backend-3.test/up' => Http::response(['status' => 'ok'], 200),
        'http://backend-4.test/up' => Http::response(['status' => 'ok'], 200),
        'http://backend-5.test/up' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->getJson('/api/gateway-health');

    $response->assertSuccessful()
        ->assertJsonPath('summary.status', 'healthy')
        ->assertJsonPath('summary.configured_servers', 5)
        ->assertJsonPath('summary.healthy_servers', 5)
        ->assertJsonPath('summary.unhealthy_servers', 0)
        ->assertJsonPath('summary.health_percentage', 100)
        ->assertJsonPath('dependencies.cache.ok', true)
        ->assertJsonPath('dependencies.database.ok', true);

    expect($response->json('servers'))->toHaveCount(5);
    expect($response->json('servers.0.response_time_ms'))->toBeInt();
});

test('it marks health as degraded when one backend is down', function () {
    Http::fake([
        'http://backend-1.test/up' => Http::response(['status' => 'ok'], 200),
        'http://backend-2.test/up' => Http::response(['status' => 'ok'], 200),
        'http://backend-3.test/up' => Http::response(['status' => 'error'], 503),
        'http://backend-4.test/up' => Http::response(['status' => 'ok'], 200),
        'http://backend-5.test/up' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->getJson('/api/gateway-health');

    $response->assertSuccessful()
        ->assertJsonPath('summary.status', 'degraded')
        ->assertJsonPath('summary.healthy_servers', 4)
        ->assertJsonPath('summary.unhealthy_servers', 1);
});

test('it returns 500 and misconfigured status when no backend is configured', function () {
    config()->set('backends.servers', []);
    Http::fake();

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->getJson('/api/gateway-health');

    $response->assertInternalServerError()
        ->assertJsonPath('summary.status', 'misconfigured')
        ->assertJsonPath('summary.configured_servers', 0)
        ->assertJsonPath('summary.healthy_servers', 0);
});
