<?php

use App\Http\Controllers\Api\BackendHealthController;
use App\Http\Controllers\Api\BackendProxyController;
use Illuminate\Support\Facades\Route;

Route::get('/gateway-health', BackendHealthController::class)->name('gateway.health');
Route::any('/proxy/{path?}', BackendProxyController::class)
    ->where('path', '.*')
    ->name('gateway.proxy.explicit');

Route::any('/{path?}', BackendProxyController::class)
    ->where('path', '.*')
    ->defaults('prepend_api_prefix', true)
    ->name('gateway.proxy.catch_all');
