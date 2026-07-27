<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CMS\DTOs\PageData;
use Modules\CMS\Enums\PageStatus;

class SavePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorização de papel/permissão fica na Policy, invocada no Controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[\pL\pN\-]+$/u'],
            'status' => ['required', Rule::enum(PageStatus::class)],
            'is_home' => ['required', 'boolean'],
            'html' => ['nullable', 'string'],
            'css' => ['nullable', 'string'],
            'project_data' => ['nullable', 'array'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toDto(): PageData
    {
        return new PageData(
            title: $this->string('title')->toString(),
            slug: $this->filled('slug') ? $this->string('slug')->toString() : null,
            status: PageStatus::from($this->string('status')->toString()),
            isHome: $this->boolean('is_home'),
            html: $this->input('html'),
            css: $this->input('css'),
            projectData: $this->input('project_data'),
            metaTitle: $this->input('meta_title'),
            metaDescription: $this->input('meta_description'),
        );
    }
}
