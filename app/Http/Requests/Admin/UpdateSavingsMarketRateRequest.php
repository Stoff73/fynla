<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavingsMarketRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route group already enforces auth:sanctum + permission:admin.access via
        // HasPermission middleware (uses PermissionService::isAdmin). Authorisation
        // here is redundant; allow the request through.
        return true;
    }

    public function rules(): array
    {
        return [
            'rate_key' => 'sometimes|string|max:255',
            'label' => 'sometimes|string|max:255',
            'rate' => 'sometimes|numeric|min:0|max:1',
            'tax_year' => 'sometimes|string|max:10',
            'effective_from' => 'sometimes|date',
        ];
    }
}
