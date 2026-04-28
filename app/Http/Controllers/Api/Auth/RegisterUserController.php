<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\InventoryRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RegisterUserController extends Controller
{
    public function __invoke(RegisterUserRequest $request): JsonResponse
    {
        $userData = $request->validated();

        $company = Company::query()->create([
            'name' => sprintf('%s-company', $userData['username']),
        ]);

        $user = User::create([
            ...$userData,
            'company_id' => $company->id,
            'inventory_role' => InventoryRole::Owner->value,
            'name' => $userData['username'],
        ]);

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => $user,
        ], JsonResponse::HTTP_CREATED);
    }
}
