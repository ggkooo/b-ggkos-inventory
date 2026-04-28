<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InventoryItemController extends Controller
{
    public function index(): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canViewInventory(), Response::HTTP_FORBIDDEN);

        $company = $user->getOrCreateCompany();

        $items = Item::query()
            ->where('company_id', $company->id)
            ->with(['category', 'details', 'inventories'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        $item = DB::transaction(function () use ($request): Item {
            $validated = $request->validated();
            $company = $request->user()->getOrCreateCompany();

            $item = Item::query()->create([
                'company_id' => $company->id,
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'brand' => $validated['brand'] ?? null,
                'model' => $validated['model'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            $item->details()->createMany($validated['details'] ?? []);
            $item->inventories()->createMany($validated['inventories'] ?? []);

            return $item;
        });

        return response()->json([
            'message' => 'Item created successfully.',
            'data' => $item->load(['category', 'details', 'inventories']),
        ], JsonResponse::HTTP_CREATED);
    }

    public function show(Item $item): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canViewInventory(), Response::HTTP_FORBIDDEN);
        abort_if($item->company_id !== $user->getOrCreateCompany()->id, Response::HTTP_NOT_FOUND);

        return response()->json([
            'data' => $item->load([
                'category',
                'details',
                'inventories',
                'circuits:id,name',
            ]),
        ]);
    }

    public function update(UpdateInventoryItemRequest $request, Item $item): JsonResponse
    {
        $company = $request->user()->getOrCreateCompany();

        abort_if($item->company_id !== $company->id, Response::HTTP_NOT_FOUND);

        DB::transaction(function () use ($request, $item): void {
            $validated = $request->validated();

            $item->update([
                'category_id' => $validated['category_id'] ?? $item->category_id,
                'name' => $validated['name'] ?? $item->name,
                'brand' => $validated['brand'] ?? $item->brand,
                'model' => $validated['model'] ?? $item->model,
                'description' => $validated['description'] ?? $item->description,
            ]);

            if (array_key_exists('details', $validated)) {
                $item->details()->delete();
                $item->details()->createMany($validated['details']);
            }

            if (array_key_exists('inventories', $validated)) {
                $item->inventories()->delete();
                $item->inventories()->createMany($validated['inventories']);
            }
        });

        return response()->json([
            'message' => 'Item updated successfully.',
            'data' => $item->fresh()->load(['category', 'details', 'inventories']),
        ]);
    }

    public function destroy(Item $item): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canManageInventory(), Response::HTTP_FORBIDDEN);
        abort_if($item->company_id !== $user->getOrCreateCompany()->id, Response::HTTP_NOT_FOUND);

        $item->delete();

        return response()->json([], JsonResponse::HTTP_NO_CONTENT);
    }
}
