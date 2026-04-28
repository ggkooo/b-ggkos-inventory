<?php

use App\Http\Controllers\Api\Inventory\InventoryCategoryController;
use App\Http\Controllers\Api\Inventory\InventoryCircuitController;
use App\Http\Controllers\Api\Inventory\InventoryItemController;
use App\Http\Controllers\Api\Inventory\InventoryItemUsageController;
use App\Http\Controllers\Api\Inventory\InventoryTeamMemberController;
use App\Http\Controllers\Api\Inventory\LowStockItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:backend-write'])->group(function (): void {
    Route::apiResource('/inventory/categories', InventoryCategoryController::class);
    Route::apiResource('/inventory/items', InventoryItemController::class);
    Route::apiResource('/inventory/circuits', InventoryCircuitController::class);

    Route::get('/inventory/items/{item}/usage', InventoryItemUsageController::class)
        ->name('inventory.items.usage');

    Route::get('/inventory/low-stock', LowStockItemController::class)
        ->name('inventory.items.low-stock');

    Route::get('/inventory/team-members', [InventoryTeamMemberController::class, 'index'])
        ->name('inventory.team-members.index');

    Route::post('/inventory/team-members', [InventoryTeamMemberController::class, 'store'])
        ->name('inventory.team-members.store');
});
