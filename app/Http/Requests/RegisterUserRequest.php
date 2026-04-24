<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:8', 'max:20', 'unique:users,phone'],
            'cpf' => ['required', 'string', 'size:11', 'unique:users,cpf'],
            'password' => ['required', 'string', Password::min(8)],
            'admin' => ['required', 'boolean'],
        ];
    }
}
