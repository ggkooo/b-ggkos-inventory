<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $authenticatedUser = $this->user();
        $targetUser = $this->route('user');

        if (! $authenticatedUser instanceof User || ! $targetUser instanceof User) {
            return false;
        }

        return $authenticatedUser->is($targetUser) || (bool) $authenticatedUser->admin;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::min(8)],
        ];
    }
}
