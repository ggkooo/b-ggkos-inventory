<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInventory() ?? false;
    }

    public function rules(): array
    {
        $company = $this->user()?->getOrCreateCompany();

        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => ['sometimes', 'string', 'max:120', Rule::unique('categories', 'name')->where('company_id', $company?->id)->ignore($category)],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
