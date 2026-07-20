<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Modules\Authentication\DTOs\RegisterClinicAdminData;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function toDto(): RegisterClinicAdminData
    {
        return new RegisterClinicAdminData(
            tenantName: $this->string('tenant_name')->toString(),
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
