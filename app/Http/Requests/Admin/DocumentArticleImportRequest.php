<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DocumentArticleImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && (bool) $user->is_admin;
    }

    public function rules(): array
    {
        return [
            'docx' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimes:docx',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'html' => ['required', 'string', 'max:1048576'], // 1 MB cap
            'images' => ['array'],
            'images.*' => ['file', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:5120'],
            'metadata' => ['array'],
            'metadata.title' => ['nullable', 'string', 'max:255'],
            'metadata.subtitle' => ['nullable', 'string', 'max:255'],
            'metadata.description' => ['nullable', 'string', 'max:2000'],
            'metadata.keywords' => ['nullable', 'string', 'max:500'],
            'metadata.author_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
