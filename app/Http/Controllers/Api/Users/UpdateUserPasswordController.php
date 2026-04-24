<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UpdateUserPasswordController extends Controller
{
    public function __invoke(UpdateUserPasswordRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'message' => 'User password updated successfully.',
            'user' => $user->fresh(),
        ]);
    }
}
