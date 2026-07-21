<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TaxStrategyCalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pension_contribution_percent' => ['nullable', 'numeric', 'between:0,100'],
            'salary_sacrifice' => ['nullable', 'boolean'],
            'isa_additional_deposit' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'marriage_allowance_claimed' => ['nullable', 'boolean'],
            'asset_shift_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
