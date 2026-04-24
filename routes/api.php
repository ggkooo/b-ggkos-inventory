<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterUserController;
use App\Http\Controllers\Api\Users\UpdateUserAdminController;
use App\Http\Controllers\Api\Users\UpdateUserPasswordController;
use App\Http\Controllers\Api\Users\UpdateUserProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/register', RegisterUserController::class);
Route::post('/login', LoginController::class);

Route::prefix('/users/{user}')->group(function () {
    Route::patch('/profile', UpdateUserProfileController::class);
    Route::patch('/password', UpdateUserPasswordController::class);
    Route::patch('/admin', UpdateUserAdminController::class);
});
