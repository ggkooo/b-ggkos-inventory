<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('inventory tables expose the expected columns', function () {
    expect(Schema::hasTable('categories'))->toBeTrue()
        ->and(Schema::hasColumns('categories', ['id', 'name', 'description', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasTable('items'))->toBeTrue()
        ->and(Schema::hasColumns('items', ['id', 'category_id', 'name', 'brand', 'model', 'description', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasTable('item_details'))->toBeTrue()
        ->and(Schema::hasColumns('item_details', ['id', 'item_id', 'key', 'value', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasTable('inventories'))->toBeTrue()
        ->and(Schema::hasColumns('inventories', ['id', 'item_id', 'quantity', 'min_quantity', 'location', 'created_at', 'updated_at']))->toBeTrue();
});

test('an item can persist dynamic details and stock entries', function () {
    $category = Category::query()->create([
        'name' => 'Microcontroladores',
        'description' => 'Placas e kits de desenvolvimento.',
    ]);

    $item = Item::query()->create([
        'category_id' => $category->id,
        'name' => 'ESP32 DevKit',
        'brand' => 'Espressif',
        'model' => 'WROOM-32',
        'description' => 'Placa principal para projetos IoT.',
    ]);

    ItemDetail::query()->create([
        'item_id' => $item->id,
        'key' => 'mac_address',
        'value' => 'AA:BB:CC:DD:EE:FF',
    ]);

    ItemDetail::query()->create([
        'item_id' => $item->id,
        'key' => 'connector_type',
        'value' => 'Macho-Macho',
    ]);

    Inventory::query()->create([
        'item_id' => $item->id,
        'quantity' => 4,
        'min_quantity' => 2,
        'location' => 'Gaveta A1',
    ]);

    $item->load(['category', 'details', 'inventories']);

    expect($item->category->name)->toBe('Microcontroladores')
        ->and($item->details)->toHaveCount(2)
        ->and($item->details->pluck('value')->all())->toContain('AA:BB:CC:DD:EE:FF', 'Macho-Macho')
        ->and($item->inventories)->toHaveCount(1)
        ->and($item->inventories->first()->quantity)->toBe(4)
        ->and($item->inventories->first()->location)->toBe('Gaveta A1');
});
