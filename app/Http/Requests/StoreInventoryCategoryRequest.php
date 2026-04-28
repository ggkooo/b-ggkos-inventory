<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInventory() ?? false;
    }

    public function rules(): array
    {
        $company = $this->user()?->getOrCreateCompany();

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->where('company_id', $company?->id)],
            'description' => ['nullable', 'string'],
        ];
    }
}
