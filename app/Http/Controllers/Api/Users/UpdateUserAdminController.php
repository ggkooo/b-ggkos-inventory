<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserAdminRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UpdateUserAdminController extends Controller
{
    public function __invoke(UpdateUserAdminRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'message' => 'User admin status updated successfully.',
            'user' => $user->fresh(),
        ]);
    }
}
