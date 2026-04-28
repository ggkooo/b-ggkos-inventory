<?php

use App\Http\Controllers\Api\Auth\RegisterUserController;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\UpdateUserAdminRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('register request rejects admin field in payload', function () {
    $validator = Validator::make([
        'username' => 'new-owner',
        'email' => 'owner@example.com',
        'phone' => '11999999999',
        'cpf' => '12345678901',
        'password' => 'secret1234',
        'admin' => true,
    ], (new RegisterUserRequest)->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('admin'))->toBeTrue();
});

test('register controller always creates non admin users', function () {
    $request = Mockery::mock(RegisterUserRequest::class);
    $request->shouldReceive('validated')->once()->andReturn([
        'username' => 'new-owner',
        'email' => 'owner@example.com',
        'phone' => '11999999999',
        'cpf' => '12345678901',
        'password' => 'secret1234',
        'admin' => true,
    ]);

    $response = app(RegisterUserController::class)($request);

    expect($response->getStatusCode())->toBe(201);
    $createdUser = User::query()->firstOrFail();

    expect($createdUser->admin)->toBeFalse();
});

test('admin cannot change own admin status', function () {
    $admin = User::factory()->create([
        'admin' => true,
    ]);

    $request = UpdateUserAdminRequest::create('/api/users/'.$admin->uuid.'/admin', 'PATCH', [
        'admin' => false,
    ]);

    $request->setUserResolver(static fn () => $admin);

    $request->setRouteResolver(static fn () => new class($admin)
    {
        public function __construct(private readonly User $user) {}

        public function parameter(string $key): ?User
        {
            return $key === 'user' ? $this->user : null;
        }
    });

    expect($request->authorize())->toBeFalse();
});

test('admin can change another user admin status', function () {
    $admin = User::factory()->create([
        'admin' => true,
    ]);

    $targetUser = User::factory()->create([
        'admin' => false,
    ]);

    $request = UpdateUserAdminRequest::create('/api/users/'.$targetUser->uuid.'/admin', 'PATCH', [
        'admin' => true,
    ]);

    $request->setUserResolver(static fn () => $admin);

    $request->setRouteResolver(static fn () => new class($targetUser)
    {
        public function __construct(private readonly User $user) {}

        public function parameter(string $key): ?User
        {
            return $key === 'user' ? $this->user : null;
        }
    });

    expect($request->authorize())->toBeTrue();
});
