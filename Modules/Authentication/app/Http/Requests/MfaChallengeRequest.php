<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MfaChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
