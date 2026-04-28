<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryCircuitRequest;
use App\Http\Requests\UpdateInventoryCircuitRequest;
use App\Models\Circuit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InventoryCircuitController extends Controller
{
    public function index(): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canViewInventory(), Response::HTTP_FORBIDDEN);

        $company = $user->getOrCreateCompany();

        $circuits = Circuit::query()
            ->where('company_id', $company->id)
            ->with(['itemUsages.item:id,name,brand,model'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $circuits,
        ]);
    }

    public function store(StoreInventoryCircuitRequest $request): JsonResponse
    {
        $circuit = DB::transaction(function () use ($request): Circuit {
            $validated = $request->validated();
            $company = $request->user()->getOrCreateCompany();

            $circuit = Circuit::query()->create([
                'company_id' => $company->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'location' => $validated['location'] ?? null,
                'assembled_at' => $validated['assembled_at'] ?? null,
            ]);

            $this->syncUsages($circuit, $validated['used_items'] ?? []);

            return $circuit;
        });

        return response()->json([
            'message' => 'Circuit created successfully.',
            'data' => $circuit->load(['itemUsages.item:id,name,brand,model']),
        ], JsonResponse::HTTP_CREATED);
    }

    public function show(Circuit $circuit): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canViewInventory(), Response::HTTP_FORBIDDEN);
        abort_if($circuit->company_id !== $user->getOrCreateCompany()->id, Response::HTTP_NOT_FOUND);

        return response()->json([
            'data' => $circuit->load(['itemUsages.item:id,name,brand,model']),
        ]);
    }

    public function update(UpdateInventoryCircuitRequest $request, Circuit $circuit): JsonResponse
    {
        $company = $request->user()->getOrCreateCompany();

        abort_if($circuit->company_id !== $company->id, Response::HTTP_NOT_FOUND);

        DB::transaction(function () use ($request, $circuit): void {
            $validated = $request->validated();

            $circuit->update([
                'name' => $validated['name'] ?? $circuit->name,
                'description' => $validated['description'] ?? $circuit->description,
                'location' => $validated['location'] ?? $circuit->location,
                'assembled_at' => $validated['assembled_at'] ?? $circuit->assembled_at,
            ]);

            if (array_key_exists('used_items', $validated)) {
                $this->syncUsages($circuit, $validated['used_items']);
            }
        });

        return response()->json([
            'message' => 'Circuit updated successfully.',
            'data' => $circuit->fresh()->load(['itemUsages.item:id,name,brand,model']),
        ]);
    }

    public function destroy(Circuit $circuit): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canManageInventory(), Response::HTTP_FORBIDDEN);
        abort_if($circuit->company_id !== $user->getOrCreateCompany()->id, Response::HTTP_NOT_FOUND);

        $circuit->delete();

        return response()->json([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @param  array<int, array<string, mixed>>  $usedItems
     */
    private function syncUsages(Circuit $circuit, array $usedItems): void
    {
        $circuit->itemUsages()->delete();

        if ($usedItems === []) {
            return;
        }

        $circuit->itemUsages()->createMany(array_map(static function (array $usage): array {
            return [
                'item_id' => $usage['item_id'],
                'quantity_used' => $usage['quantity_used'],
                'notes' => $usage['notes'] ?? null,
            ];
        }, $usedItems));
    }
}
