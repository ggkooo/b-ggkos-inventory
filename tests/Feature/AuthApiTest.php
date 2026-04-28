<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('app.api_key', 'test-api-key');
    config()->set('backends.round_robin_cache_key', 'tests:gateway-auth:index');
    config()->set('backends.servers', [
        'http://backend-1.test',
        'http://backend-2.test',
    ]);

    Cache::forget('tests:gateway-auth:index');
});

test('it proxies login route through load balancer', function () {
    Http::fake([
        'http://backend-1.test/api/login' => Http::response([
            'message' => 'Login successful.',
            'backend' => 'backend-1',
        ], 200),
        'http://backend-2.test/api/login' => Http::response([
            'message' => 'Login successful.',
            'backend' => 'backend-2',
        ], 200),
    ]);

    $firstResponse = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->postJson('/api/login', [
        'login' => 'giordano',
        'password' => 'secret1234',
    ]);

    $secondResponse = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->postJson('/api/login', [
        'login' => 'giordano',
        'password' => 'secret1234',
    ]);

    $firstResponse->assertSuccessful()
        ->assertJsonPath('backend', 'backend-1')
        ->assertJsonPath('processed_by_server', 'http://backend-1.test')
        ->assertJsonPath('request_id', $firstResponse->headers->get('X-Request-Id'))
        ->assertHeader('X-Backend-Url', 'http://backend-1.test');

    $secondResponse->assertSuccessful()
        ->assertJsonPath('backend', 'backend-2')
        ->assertJsonPath('processed_by_server', 'http://backend-2.test')
        ->assertJsonPath('request_id', $secondResponse->headers->get('X-Request-Id'))
        ->assertHeader('X-Backend-Url', 'http://backend-2.test');
});

test('it proxies register route with api prefix automatically', function () {
    Http::fake([
        'http://backend-1.test/api/register' => Http::response([
            'message' => 'User registered successfully.',
            'backend' => 'backend-1',
        ], 201),
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->postJson('/api/register', [
        'username' => 'giordano',
        'email' => 'giordano@example.com',
        'password' => 'secret1234',
    ]);

    $response->assertCreated()
        ->assertJsonPath('backend', 'backend-1')
        ->assertJsonPath('processed_by_server', 'http://backend-1.test')
        ->assertJsonPath('request_id', $response->headers->get('X-Request-Id'))
        ->assertHeader('X-Backend-Url', 'http://backend-1.test');
});

test('it proxies users update routes through the load balancer', function () {
    Http::fake([
        'http://backend-1.test/api/users/99/profile' => Http::response([
            'message' => 'Profile updated.',
            'backend' => 'backend-1',
        ], 200),
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->patchJson('/api/users/99/profile', [
        'username' => 'novo-username',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('backend', 'backend-1')
        ->assertJsonPath('processed_by_server', 'http://backend-1.test')
        ->assertJsonPath('request_id', $response->headers->get('X-Request-Id'))
        ->assertHeader('X-Backend-Url', 'http://backend-1.test');
});

test('it preserves a client provided request id and forwards it to the backend', function () {
    config()->set('backends.servers', [
        'http://backend-1.test',
    ]);

    Http::fake([
        'http://backend-1.test/api/login' => Http::response([
            'message' => 'Login successful.',
        ], 200),
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
        'X-Request-Id' => 'req-12345',
    ])->postJson('/api/login', [
        'login' => 'giordano',
        'password' => 'secret1234',
    ]);

    $response->assertSuccessful()
        ->assertHeader('X-Request-Id', 'req-12345')
        ->assertJsonPath('request_id', 'req-12345');

    Http::assertSent(static fn (Request $request): bool => $request->url() === 'http://backend-1.test/api/login'
        && $request->hasHeader('X-Request-Id', 'req-12345')
    );
});

test('it forwards configured backend api key to backend requests', function () {
    config()->set('backends.servers', [
        'http://backend-1.test',
    ]);
    config()->set('backends.api_key', 'backend-key-123');

    Http::fake([
        'http://backend-1.test/api/login' => Http::response([
            'message' => 'Login successful.',
        ], 200),
    ]);

    $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->postJson('/api/login', [
        'login' => 'giordano',
        'password' => 'secret1234',
    ])->assertSuccessful();

    Http::assertSent(static fn (Request $request): bool => $request->url() === 'http://backend-1.test/api/login'
        && $request->hasHeader('X-API-KEY', 'backend-key-123')
    );
});

test('it can forward client api key to backend when enabled', function () {
    config()->set('backends.servers', [
        'http://backend-1.test',
    ]);
    config()->set('backends.api_key', null);
    config()->set('backends.forward_client_api_key', true);

    Http::fake([
        'http://backend-1.test/api/login' => Http::response([
            'message' => 'Login successful.',
        ], 200),
    ]);

    $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->postJson('/api/login', [
        'login' => 'giordano',
        'password' => 'secret1234',
    ])->assertSuccessful();

    Http::assertSent(static fn (Request $request): bool => $request->url() === 'http://backend-1.test/api/login'
        && $request->hasHeader('X-API-KEY', 'test-api-key')
    );
});
