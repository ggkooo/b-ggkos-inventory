<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LowStockItemController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canViewInventory(), Response::HTTP_FORBIDDEN);

        $company = $user->getOrCreateCompany();

        $items = Item::query()
            ->where('company_id', $company->id)
            ->with([
                'category',
                'inventories' => fn ($query) => $query->whereColumn('quantity', '<=', 'min_quantity'),
            ])
            ->whereHas('inventories', fn ($query) => $query->whereColumn('quantity', '<=', 'min_quantity'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }
}
