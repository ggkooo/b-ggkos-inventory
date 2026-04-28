<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('app.api_key', 'test-api-key');
});

test('it creates categories and items with dynamic attributes and stock locations', function () {
    $user = User::factory()->create();

    $categoryResponse = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/categories', [
            'name' => 'Dispositivos',
            'description' => 'Componentes e placas programaveis.',
        ]);

    $categoryResponse->assertCreated()
        ->assertJsonPath('data.name', 'Dispositivos');

    $itemResponse = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/items', [
            'category_id' => $categoryResponse->json('data.id'),
            'name' => 'ESP32-CAM',
            'brand' => 'Espressif',
            'model' => 'AI-Thinker',
            'details' => [
                ['key' => 'mac_address', 'value' => 'AA:BB:CC:DD:EE:FF'],
                ['key' => 'connector_type', 'value' => 'Macho-Femea'],
                ['key' => 'color', 'value' => 'Preto'],
            ],
            'inventories' => [
                ['location' => 'Gaveta A1', 'quantity' => 5, 'min_quantity' => 2],
                ['location' => 'Prateleira B2', 'quantity' => 1, 'min_quantity' => 3],
            ],
        ]);

    $itemResponse->assertCreated()
        ->assertJsonPath('data.name', 'ESP32-CAM')
        ->assertJsonCount(3, 'data.details')
        ->assertJsonCount(2, 'data.inventories');

    $lowStockResponse = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->getJson('/api/inventory/low-stock');

    $lowStockResponse->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'ESP32-CAM');
});

test('it rejects invalid mac address for mac_address detail', function () {
    $user = User::factory()->create();

    $categoryId = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/categories', [
            'name' => 'Microcontroladores',
        ])->json('data.id');

    $response = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/items', [
            'category_id' => $categoryId,
            'name' => 'ESP32 DevKit',
            'details' => [
                ['key' => 'mac_address', 'value' => 'INVALID-MAC'],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['details.0.value']);
});

test('it tracks which circuit uses each item', function () {
    $user = User::factory()->create();

    $categoryId = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/categories', [
            'name' => 'Sensores',
        ])->json('data.id');

    $itemId = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/items', [
            'category_id' => $categoryId,
            'name' => 'BME280',
            'inventories' => [
                ['location' => 'Gaveta C3', 'quantity' => 8, 'min_quantity' => 2],
            ],
        ])->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/circuits', [
            'name' => 'Estacao de monitoramento',
            'location' => 'Bancada 1',
            'used_items' => [
                [
                    'item_id' => $itemId,
                    'quantity_used' => 2,
                    'notes' => 'Sensor ambiental principal',
                ],
            ],
        ])->assertCreated();

    $usageResponse = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->getJson("/api/inventory/items/{$itemId}/usage");

    $usageResponse->assertSuccessful()
        ->assertJsonPath('item.id', $itemId)
        ->assertJsonCount(1, 'used_in_circuits')
        ->assertJsonPath('used_in_circuits.0.quantity_used', 2)
        ->assertJsonPath('used_in_circuits.0.name', 'Estacao de monitoramento');
});
