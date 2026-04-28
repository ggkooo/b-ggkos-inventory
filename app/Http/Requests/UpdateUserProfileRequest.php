<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->route('user');

        return [
            'username' => ['sometimes', 'string', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['sometimes', 'string', 'min:8', 'max:20', Rule::unique('users', 'phone')->ignore($user)],
            'cpf' => ['sometimes', 'string', 'size:11', Rule::unique('users', 'cpf')->ignore($user)],
        ];
    }
}
