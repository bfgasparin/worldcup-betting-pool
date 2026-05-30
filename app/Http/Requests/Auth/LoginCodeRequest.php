<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;

class LoginCodeRequest extends LoginRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Passwordless login verifies an emailed six-digit code instead of a password.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            Fortify::username() => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
        ];
    }
}
