<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRateRequest extends FormRequest
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
            'from_ccy' => 'required|string|size:3',
            'to_ccy' => 'required|string|size:3|different:from_ccy',
            'rate' => 'required|numeric|min:0.00000001|max:1000000',
            'effective_at' => 'required|date',
            'source' => 'sometimes|string|max:64',
        ];
    }
}
