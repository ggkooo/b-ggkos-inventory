<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryCircuitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInventory() ?? false;
    }

    public function rules(): array
    {
        $company = $this->user()?->getOrCreateCompany();

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'assembled_at' => ['sometimes', 'nullable', 'date'],
            'used_items' => ['sometimes', 'array'],
            'used_items.*.item_id' => ['required_with:used_items', 'integer', Rule::exists('items', 'id')->where('company_id', $company?->id), 'distinct'],
            'used_items.*.quantity_used' => ['required_with:used_items', 'integer', 'min:1'],
            'used_items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
