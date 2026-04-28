<?php

namespace App\Http\Requests;

use App\Enums\JumperConnectorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInventory() ?? false;
    }

    public function rules(): array
    {
        $company = $this->user()?->getOrCreateCompany();

        return [
            'category_id' => ['sometimes', 'integer', Rule::exists('categories', 'id')->where('company_id', $company?->id)],
            'name' => ['sometimes', 'string', 'max:160'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:120'],
            'model' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string'],
            'details' => ['sometimes', 'array'],
            'details.*.key' => ['required_with:details', 'string', 'max:100'],
            'details.*.value' => ['required_with:details', 'string', 'max:255'],
            'inventories' => ['sometimes', 'array'],
            'inventories.*.location' => ['required_with:inventories', 'string', 'max:120', 'distinct'],
            'inventories.*.quantity' => ['required_with:inventories', 'integer', 'min:0'],
            'inventories.*.min_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $details = $this->input('details', []);

                foreach ($details as $index => $detail) {
                    $key = strtolower((string) ($detail['key'] ?? ''));
                    $value = (string) ($detail['value'] ?? '');

                    if ($key === 'mac_address' && ! preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $value)) {
                        $validator->errors()->add("details.$index.value", 'The MAC address must use format AA:BB:CC:DD:EE:FF.');
                    }

                    if ($key === 'connector_type' && ! in_array($value, array_column(JumperConnectorType::cases(), 'value'), true)) {
                        $validator->errors()->add("details.$index.value", 'Connector type must be Macho-Macho, Macho-Femea, or Femea-Femea.');
                    }
                }
            },
        ];
    }
}
