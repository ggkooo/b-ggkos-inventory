<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $authenticatedUser = $this->user();

        /** @var User|null $targetUser */
        $targetUser = $this->route('user');

        if (! $authenticatedUser instanceof User || ! $targetUser instanceof User) {
            return false;
        }

        return (bool) $authenticatedUser->admin && ! $authenticatedUser->is($targetUser);
    }

    public function rules(): array
    {
        return [
            'admin' => ['required', 'boolean'],
        ];
    }
}
