<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RegisterUserController extends Controller
{
    public function __invoke(RegisterUserRequest $request): JsonResponse
    {
        $userData = $request->validated();

        $user = User::create([
            ...$userData,
            'name' => $userData['username'],
        ]);

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => $user,
        ], JsonResponse::HTTP_CREATED);
    }
}
