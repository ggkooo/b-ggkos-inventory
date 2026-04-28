<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InventoryItemUsageController extends Controller
{
    public function __invoke(Item $item): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canViewInventory(), Response::HTTP_FORBIDDEN);

        $company = $user->getOrCreateCompany();

        abort_if($item->company_id !== $company->id, Response::HTTP_NOT_FOUND);

        $usedInCircuits = $item->circuits()
            ->where('circuits.company_id', $company->id)
            ->orderByDesc('circuits.id')
            ->get()
            ->map(static fn ($circuit): array => [
                'id' => $circuit->id,
                'name' => $circuit->name,
                'location' => $circuit->location,
                'assembled_at' => $circuit->assembled_at,
                'quantity_used' => $circuit->pivot->quantity_used,
                'notes' => $circuit->pivot->notes,
            ])
            ->values();

        return response()->json([
            'item' => $item->only(['id', 'name', 'brand', 'model']),
            'used_in_circuits' => $usedInCircuits,
        ]);
    }
}
