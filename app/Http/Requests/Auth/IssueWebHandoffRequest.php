<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\WebHandoffDestination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IssueWebHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'destination' => ['required', new Enum(WebHandoffDestination::class)],
        ];
    }

    public function destination(): WebHandoffDestination
    {
        return WebHandoffDestination::from($this->validated('destination'));
    }
}
