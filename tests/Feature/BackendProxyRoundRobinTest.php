<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config()->set('app.api_key', 'test-api-key');
    config()->set('backends.round_robin_cache_key', 'tests:round-robin:index');
    config()->set('backends.servers', [
        'http://backend-1.test',
        'http://backend-2.test',
        'http://backend-3.test',
        'http://backend-4.test',
        'http://backend-5.test',
    ]);

    Cache::forget('tests:round-robin:index');
});

test('it distributes requests across five backends using round robin', function () {
    Http::fake([
        'http://backend-1.test/*' => Http::response(['backend' => 'backend-1'], 200),
        'http://backend-2.test/*' => Http::response(['backend' => 'backend-2'], 200),
        'http://backend-3.test/*' => Http::response(['backend' => 'backend-3'], 200),
        'http://backend-4.test/*' => Http::response(['backend' => 'backend-4'], 200),
        'http://backend-5.test/*' => Http::response(['backend' => 'backend-5'], 200),
    ]);

    $expectedBackends = [
        'http://backend-1.test',
        'http://backend-2.test',
        'http://backend-3.test',
        'http://backend-4.test',
        'http://backend-5.test',
        'http://backend-1.test',
    ];

    foreach ($expectedBackends as $expectedBackend) {
        $response = $this->withHeaders([
            'X-API-KEY' => 'test-api-key',
        ])->getJson('/api/proxy/health');

        $response->assertSuccessful();
        expect($response->headers->get('X-Backend-Url'))->toBe($expectedBackend);
        $response->assertJsonPath('processed_by_server', $expectedBackend);
        $response->assertJsonPath('request_id', $response->headers->get('X-Request-Id'));
    }
});

test('it fails over to the next backend when the selected backend is down', function () {
    Http::fake([
        'http://backend-1.test/*' => Http::response(['message' => 'down'], 503),
        'http://backend-2.test/*' => Http::response(['backend' => 'backend-2'], 200),
        'http://backend-3.test/*' => Http::response(['backend' => 'backend-3'], 200),
        'http://backend-4.test/*' => Http::response(['backend' => 'backend-4'], 200),
        'http://backend-5.test/*' => Http::response(['backend' => 'backend-5'], 200),
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->getJson('/api/proxy/health');

    $response->assertSuccessful();
    expect($response->headers->get('X-Backend-Url'))->toBe('http://backend-2.test');
    $response->assertJsonPath('backend', 'backend-2');
    $response->assertJsonPath('processed_by_server', 'http://backend-2.test');
    $response->assertJsonPath('request_id', $response->headers->get('X-Request-Id'));
});

test('it returns 500 when no backend is configured', function () {
    config()->set('backends.servers', []);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->getJson('/api/proxy/health');

    $response->assertInternalServerError()
        ->assertJson([
            'message' => 'No backend servers configured.',
        ])
        ->assertJsonPath('request_id', $response->headers->get('X-Request-Id'));
});

test('it always returns which backend processed the proxied request', function () {
    Http::fake([
        'http://backend-1.test/*' => Http::response(['ok' => true], 200),
        'http://backend-2.test/*' => Http::response(['ok' => true], 200),
        'http://backend-3.test/*' => Http::response(['ok' => true], 200),
        'http://backend-4.test/*' => Http::response(['ok' => true], 200),
        'http://backend-5.test/*' => Http::response(['ok' => true], 200),
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->postJson('/api/proxy/orders', [
        'item' => 'abc',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('X-Backend-Url'))->not->toBeNull();
    expect($response->json('processed_by_server'))->toBe($response->headers->get('X-Backend-Url'));
    expect($response->json('request_id'))->toBe($response->headers->get('X-Request-Id'));
});

test('it logs structured telemetry on successful proxied requests', function () {
    config()->set('backends.telemetry_enabled', true);
    config()->set('backends.telemetry_sample_rate_percentage', 100);

    Log::spy();

    Http::fake([
        'http://backend-1.test/*' => Http::response(['ok' => true], 200),
    ]);

    $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
        'X-Request-Id' => 'req-success-1',
    ])->postJson('/api/proxy/orders', [
        'item' => 'abc',
    ])->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(static function (string $message, array $context): bool {
            return $message === 'backend_proxy_request_completed'
                && $context['request_id'] === 'req-success-1'
                && $context['method'] === 'POST'
                && $context['incoming_path'] === '/orders'
                && $context['resolved_path'] === 'orders'
                && $context['backend'] === 'http://backend-1.test'
                && $context['status'] === 200
                && is_int($context['duration_ms']);
        });
});

test('it logs structured telemetry on failed proxied requests', function () {
    config()->set('backends.telemetry_enabled', true);
    config()->set('backends.telemetry_sample_rate_percentage', 100);

    Log::spy();
    config()->set('backends.servers', []);

    $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
        'X-Request-Id' => 'req-failed-1',
    ])->getJson('/api/proxy/health')->assertInternalServerError();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(static function (string $message, array $context): bool {
            return $message === 'backend_proxy_request_failed'
                && $context['request_id'] === 'req-failed-1'
                && $context['method'] === 'GET'
                && $context['incoming_path'] === '/health'
                && $context['resolved_path'] === 'health'
                && $context['status'] === 500
                && is_string($context['reason'])
                && is_int($context['duration_ms']);
        });
});

test('it can disable telemetry logging without affecting proxy response', function () {
    config()->set('backends.telemetry_enabled', false);

    Log::spy();
    Http::fake([
        'http://backend-1.test/*' => Http::response(['ok' => true], 200),
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->getJson('/api/proxy/health');

    $response->assertSuccessful()
        ->assertJsonPath('processed_by_server', 'http://backend-1.test');

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('warning');
});
