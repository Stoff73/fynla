<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActuarialLifeTableRequest extends FormRequest
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
            'age' => 'sometimes|integer|min:0|max:125',
            'gender' => ['sometimes', Rule::in(['male', 'female'])],
            'life_expectancy_years' => 'sometimes|numeric|min:0.1|max:120',
            'probability_of_death' => 'sometimes|numeric|min:0|max:1',
            'table_year' => 'sometimes|string|max:20',
            'table_source' => 'sometimes|string|max:255',
        ];
    }
}
