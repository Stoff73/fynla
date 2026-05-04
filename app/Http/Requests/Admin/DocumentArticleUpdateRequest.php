<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DocumentArticleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && (bool) $user->is_admin;
    }

    public function rules(): array
    {
        $articleId = $this->route('document')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('document_articles', 'slug')->ignore($articleId),
            ],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'author_byline' => ['nullable', 'string', 'max:255'],
            'cover_image_path' => ['nullable', 'string', 'max:500', 'starts_with:document-articles/'],
            'html_body' => ['required', 'string', 'max:1048576'],
        ];
    }
}
