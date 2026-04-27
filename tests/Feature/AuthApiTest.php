<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('it registers a user with required fields', function () {
    config()->set('app.api_key', 'test-api-key');

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->postJson('/api/register', [
        'username' => 'giordano',
        'email' => 'giordano@example.com',
        'phone' => '11999999999',
        'cpf' => '12345678901',
        'password' => 'secret1234',
        'admin' => false,
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.username', 'giordano')
        ->assertJsonPath('user.email', 'giordano@example.com')
        ->assertJsonPath('user.phone', '11999999999')
        ->assertJsonPath('user.cpf', '12345678901')
        ->assertJsonPath('user.admin', false);

    $this->assertDatabaseHas('users', [
        'username' => 'giordano',
        'email' => 'giordano@example.com',
        'phone' => '11999999999',
        'cpf' => '12345678901',
        'admin' => false,
    ]);
});

test('it logs in with username and password', function () {
    config()->set('app.api_key', 'test-api-key');

    User::factory()->create([
        'username' => 'giordano',
        'email' => 'giordano@example.com',
        'password' => 'secret1234',
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->postJson('/api/login', [
        'login' => 'giordano',
        'password' => 'secret1234',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('user.username', 'giordano');
});

test('it updates user admin and password', function () {
    config()->set('app.api_key', 'test-api-key');

    $user = User::factory()->create([
        'username' => 'giordano',
        'email' => 'giordano@example.com',
        'phone' => '11999999999',
        'cpf' => '12345678901',
        'password' => 'secret1234',
        'admin' => false,
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->patchJson("/api/users/{$user->id}/profile", [
        'username' => 'novo-username',
        'phone' => '11911111111',
        'cpf' => '11122233344',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('user.username', 'novo-username');

    $adminResponse = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->patchJson("/api/users/{$user->id}/admin", [
        'admin' => true,
    ]);

    $adminResponse->assertSuccessful()
        ->assertJsonPath('user.admin', true);

    $passwordResponse = $this->withHeaders([
        'X-API-KEY' => 'test-api-key',
    ])->patchJson("/api/users/{$user->id}/password", [
        'password' => 'newsecret1234',
    ]);

    $passwordResponse->assertSuccessful();

    expect(Hash::check('newsecret1234', $user->fresh()->password))->toBeTrue();
});
