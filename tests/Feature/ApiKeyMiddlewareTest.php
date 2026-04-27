<?php

use Illuminate\Support\Facades\Route;

test('api routes require a valid api key', function () {
    config()->set('app.api_key', 'test-api-key');

    Route::middleware('api')->get('/api/test-api-key', fn () => response()->json([
        'ok' => true,
    ]));

    $this->getJson('/api/test-api-key')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid API key.',
        ]);

    $this->withHeaders([
        'X-API-KEY' => 'wrong-key',
    ])->getJson('/api/test-api-key')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid API key.',
        ]);

    $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->getJson('/api/test-api-key')
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
        ]);
});
