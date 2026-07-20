<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\DTOs\LoginAttemptData;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function toDto(): LoginAttemptData
    {
        return new LoginAttemptData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
