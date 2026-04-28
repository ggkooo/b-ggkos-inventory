<?php

test('the gateway health endpoint returns a successful response', function () {
    config()->set('app.api_key', 'test-api-key');

    $response = $this->withHeaders(['X-API-KEY' => 'test-api-key'])->get('/api/gateway-health');

    $response->assertStatus(200);
});
