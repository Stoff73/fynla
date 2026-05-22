<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActuarialLifeTableRequest extends FormRequest
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
            'age' => 'required|integer|min:0|max:125',
            'gender' => ['required', Rule::in(['male', 'female'])],
            'life_expectancy_years' => 'required|numeric|min:0.1|max:120',
            'probability_of_death' => 'required|numeric|min:0|max:1',
            'table_year' => 'required|string|max:20',
            'table_source' => 'required|string|max:255',
        ];
    }
}
