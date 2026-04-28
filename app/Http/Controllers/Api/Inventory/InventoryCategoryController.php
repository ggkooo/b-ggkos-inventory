<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryCategoryRequest;
use App\Http\Requests\UpdateInventoryCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InventoryCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canViewInventory(), Response::HTTP_FORBIDDEN);

        $company = $user->getOrCreateCompany();

        $categories = Category::query()
            ->where('company_id', $company->id)
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function store(StoreInventoryCategoryRequest $request): JsonResponse
    {
        $company = $request->user()->getOrCreateCompany();

        $category = Category::query()->create([
            ...$request->validated(),
            'company_id' => $company->id,
        ]);

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => $category,
        ], JsonResponse::HTTP_CREATED);
    }

    public function show(Category $category): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canViewInventory(), Response::HTTP_FORBIDDEN);

        $company = $user->getOrCreateCompany();

        abort_if($category->company_id !== $company->id, Response::HTTP_NOT_FOUND);

        return response()->json([
            'data' => $category->loadCount('items'),
        ]);
    }

    public function update(UpdateInventoryCategoryRequest $request, Category $category): JsonResponse
    {
        $company = $request->user()->getOrCreateCompany();

        abort_if($category->company_id !== $company->id, Response::HTTP_NOT_FOUND);

        $category->update($request->validated());

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => $category->fresh(),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $user = request()->user();

        abort_unless($user?->canManageInventory(), Response::HTTP_FORBIDDEN);

        $company = $user->getOrCreateCompany();

        abort_if($category->company_id !== $company->id, Response::HTTP_NOT_FOUND);

        $category->delete();

        return response()->json([], JsonResponse::HTTP_NO_CONTENT);
    }
}
