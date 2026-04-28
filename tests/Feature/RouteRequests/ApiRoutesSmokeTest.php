<?php

use App\Enums\InventoryRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('app.api_key', 'test-api-key');
    config()->set('backends.round_robin_cache_key', 'tests:api-routes-smoke:index');
    config()->set('backends.health_report_cache_ttl_seconds', 0);
    config()->set('backends.health_path', '/up');
    config()->set('backends.servers', [
        'http://backend-1.test',
    ]);

    Cache::forget('tests:api-routes-smoke:index');
});

function apiHeaders(): array
{
    return ['X-API-KEY' => 'test-api-key'];
}

test('gateway health and proxy routes smoke', function () {
    Http::fake([
        'http://backend-1.test/up' => Http::response(['status' => 'ok'], 200),
        'http://backend-1.test/health' => Http::response(['ok' => true], 200),
        'http://backend-1.test/api/login' => Http::response(['message' => 'proxied'], 200),
    ]);

    $this->withHeaders(apiHeaders())
        ->getJson('/api/gateway-health')
        ->assertSuccessful()
        ->assertJsonPath('summary.status', 'healthy');

    $this->withHeaders(apiHeaders())
        ->getJson('/api/proxy/health')
        ->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertHeader('X-Backend-Url', 'http://backend-1.test');

    $this->withHeaders(apiHeaders())
        ->postJson('/api/login', [
            'login' => 'demo',
            'password' => 'secret1234',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'proxied')
        ->assertHeader('X-Backend-Url', 'http://backend-1.test');
});

test('category routes smoke', function () {
    $manager = User::factory()->create([
        'inventory_role' => InventoryRole::Owner->value,
    ]);

    $createResponse = $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->postJson('/api/inventory/categories', [
            'name' => 'Boards',
            'description' => 'Microcontroller boards',
        ])
        ->assertCreated();

    $categoryId = $createResponse->json('data.id');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->getJson('/api/inventory/categories')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->getJson("/api/inventory/categories/{$categoryId}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $categoryId);

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->putJson("/api/inventory/categories/{$categoryId}", [
            'name' => 'Boards Updated',
            'description' => 'Updated',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Boards Updated');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->patchJson("/api/inventory/categories/{$categoryId}", [
            'description' => 'Patched',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.description', 'Patched');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->deleteJson("/api/inventory/categories/{$categoryId}")
        ->assertNoContent();
});

test('item routes, usage route, and low-stock route smoke', function () {
    $manager = User::factory()->create([
        'inventory_role' => InventoryRole::Owner->value,
    ]);

    $categoryId = $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->postJson('/api/inventory/categories', [
            'name' => 'Sensors',
        ])->json('data.id');

    $createResponse = $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->postJson('/api/inventory/items', [
            'category_id' => $categoryId,
            'name' => 'BME280',
            'brand' => 'Bosch',
            'model' => 'BME280',
            'details' => [
                ['key' => 'connector_type', 'value' => 'Macho-Femea'],
            ],
            'inventories' => [
                ['location' => 'Shelf A', 'quantity' => 1, 'min_quantity' => 2],
            ],
        ])->assertCreated();

    $itemId = $createResponse->json('data.id');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->getJson('/api/inventory/items')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->getJson("/api/inventory/items/{$itemId}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $itemId);

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->putJson("/api/inventory/items/{$itemId}", [
            'category_id' => $categoryId,
            'name' => 'BME280 v2',
            'details' => [
                ['key' => 'mac_address', 'value' => 'AA:BB:CC:DD:EE:11'],
            ],
            'inventories' => [
                ['location' => 'Shelf A', 'quantity' => 3, 'min_quantity' => 2],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'BME280 v2');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->patchJson("/api/inventory/items/{$itemId}", [
            'description' => 'Patched description',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.description', 'Patched description');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->getJson("/api/inventory/items/{$itemId}/usage")
        ->assertSuccessful()
        ->assertJsonPath('item.id', $itemId);

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->getJson('/api/inventory/low-stock')
        ->assertSuccessful();

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->deleteJson("/api/inventory/items/{$itemId}")
        ->assertNoContent();
});

test('circuit routes smoke', function () {
    $manager = User::factory()->create([
        'inventory_role' => InventoryRole::Owner->value,
    ]);

    $categoryId = $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->postJson('/api/inventory/categories', [
            'name' => 'Controllers',
        ])->json('data.id');

    $itemId = $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->postJson('/api/inventory/items', [
            'category_id' => $categoryId,
            'name' => 'ESP32',
        ])->json('data.id');

    $createResponse = $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->postJson('/api/inventory/circuits', [
            'name' => 'Weather Station',
            'used_items' => [
                ['item_id' => $itemId, 'quantity_used' => 1],
            ],
        ])
        ->assertCreated();

    $circuitId = $createResponse->json('data.id');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->getJson('/api/inventory/circuits')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->getJson("/api/inventory/circuits/{$circuitId}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $circuitId);

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->putJson("/api/inventory/circuits/{$circuitId}", [
            'name' => 'Weather Station v2',
            'used_items' => [
                ['item_id' => $itemId, 'quantity_used' => 2],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Weather Station v2');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->patchJson("/api/inventory/circuits/{$circuitId}", [
            'location' => 'Lab A',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.location', 'Lab A');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->deleteJson("/api/inventory/circuits/{$circuitId}")
        ->assertNoContent();
});

test('team-member routes smoke', function () {
    $manager = User::factory()->create([
        'inventory_role' => InventoryRole::Owner->value,
    ]);

    $manager->getOrCreateCompany();

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->postJson('/api/inventory/team-members', [
            'username' => 'buyer1',
            'email' => 'buyer1@example.com',
            'phone' => '11988887777',
            'cpf' => '12312312312',
            'password' => 'secret1234',
            'inventory_role' => InventoryRole::Purchasing->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.username', 'buyer1');

    $this->actingAs($manager, 'sanctum')
        ->withHeaders(apiHeaders())
        ->getJson('/api/inventory/team-members')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});
