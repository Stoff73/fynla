<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_name' => 'nullable|string|max:255',
            'target_amount' => 'nullable|numeric|min:0',
            'current_saved' => 'nullable|numeric|min:0',
            'target_date' => 'nullable|date|after:today',
            'priority' => 'nullable|in:high,medium,low',
            'linked_account_id' => 'nullable|exists:savings_accounts,id',
            'auto_transfer_amount' => 'nullable|numeric|min:0',
        ];
    }
}
