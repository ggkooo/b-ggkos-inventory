<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $authenticatedUser = $this->user();

        return $authenticatedUser instanceof User && (bool) $authenticatedUser->admin;
    }

    public function rules(): array
    {
        return [
            'admin' => ['required', 'boolean'],
        ];
    }
}
