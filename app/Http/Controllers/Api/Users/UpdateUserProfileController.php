<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UpdateUserProfileController extends Controller
{
    public function __invoke(UpdateUserProfileRequest $request, User $user): JsonResponse
    {
        $userData = $request->validated();

        if (array_key_exists('username', $userData)) {
            $userData['name'] = $userData['username'];
        }

        $user->update($userData);

        return response()->json([
            'message' => 'User profile updated successfully.',
            'user' => $user->fresh(),
        ]);
    }
}
