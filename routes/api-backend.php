<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterUserController;
use App\Http\Controllers\Api\Users\UpdateUserAdminController;
use App\Http\Controllers\Api\Users\UpdateUserPasswordController;
use App\Http\Controllers\Api\Users\UpdateUserProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:backend-auth')->group(function (): void {
    Route::post('/login', LoginController::class)->name('backend.login');
    Route::post('/register', RegisterUserController::class)->name('backend.register');
});

Route::middleware(['auth:sanctum', 'throttle:backend-write'])->group(function (): void {
    Route::patch('/users/{user:uuid}/profile', UpdateUserProfileController::class)->name('backend.users.profile.update');
    Route::patch('/users/{user:uuid}/password', UpdateUserPasswordController::class)->name('backend.users.password.update');
    Route::patch('/users/{user:uuid}/admin', UpdateUserAdminController::class)->name('backend.users.admin.update');
});

require __DIR__.'/api-inventory.php';
