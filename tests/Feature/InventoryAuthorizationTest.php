<?php

use App\Enums\InventoryRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('app.api_key', 'test-api-key');
});

test('users from another company cannot access my inventory records', function () {
    $ownerA = User::factory()->create();
    $companyA = $ownerA->getOrCreateCompany();

    $ownerB = User::factory()->create();
    $ownerB->getOrCreateCompany();

    $categoryId = $this->actingAs($ownerA, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/categories', [
            'name' => 'Internal Stock',
        ])->json('data.id');

    $itemId = $this->actingAs($ownerA, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/items', [
            'category_id' => $categoryId,
            'name' => 'ESP32-WROOM',
        ])->json('data.id');

    $this->actingAs($ownerB, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->getJson("/api/inventory/items/{$itemId}")
        ->assertNotFound();

    $this->actingAs($ownerB, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/items', [
            'category_id' => $categoryId,
            'name' => 'Cross-company write',
        ])
        ->assertUnprocessable();

    expect($ownerA->fresh()->company_id)->toBe($companyA->id);
});

test('purchasing users can view inventory but cannot manage it', function () {
    $owner = User::factory()->create();
    $company = $owner->getOrCreateCompany();

    $purchasing = User::factory()->create([
        'company_id' => $company->id,
        'inventory_role' => InventoryRole::Purchasing->value,
    ]);

    $categoryId = $this->actingAs($owner, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/categories', [
            'name' => 'Shared Category',
        ])->json('data.id');

    $this->actingAs($purchasing, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->getJson('/api/inventory/categories')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $this->actingAs($purchasing, 'sanctum')
        ->withHeaders(['X-API-KEY' => 'test-api-key'])
        ->postJson('/api/inventory/items', [
            'category_id' => $categoryId,
            'name' => 'Should fail',
        ])
        ->assertForbidden();
});
