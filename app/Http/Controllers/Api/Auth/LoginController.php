<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()
            ->where('email', $credentials['login'])
            ->orWhere('username', $credentials['login'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $token = $user->createToken('session-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user,
            'user_uuid' => $user->uuid,
            'token_type' => 'Bearer',
            'access_token' => $token,
        ]);
    }
}
