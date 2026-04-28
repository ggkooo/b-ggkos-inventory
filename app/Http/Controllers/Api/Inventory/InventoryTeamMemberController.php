<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Enums\InventoryRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryTeamMemberRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InventoryTeamMemberController extends Controller
{
    public function index(): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canManageInventory(), Response::HTTP_FORBIDDEN);

        $company = $user->getOrCreateCompany();

        $members = User::query()
            ->where('company_id', $company->id)
            ->orderBy('username')
            ->get(['id', 'uuid', 'name', 'username', 'email', 'phone', 'cpf', 'inventory_role', 'created_at']);

        return response()->json([
            'data' => $members,
        ]);
    }

    public function store(StoreInventoryTeamMemberRequest $request): JsonResponse
    {
        $company = $request->user()->getOrCreateCompany();
        $validated = $request->validated();

        $member = User::query()->create([
            'company_id' => $company->id,
            'inventory_role' => $validated['inventory_role'] ?? InventoryRole::Purchasing->value,
            'name' => $validated['username'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'cpf' => $validated['cpf'],
            'password' => $validated['password'],
            'admin' => false,
        ]);

        return response()->json([
            'message' => 'Team member created successfully.',
            'data' => $member,
        ], JsonResponse::HTTP_CREATED);
    }
}
