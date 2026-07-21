<?php

declare(strict_types=1);

namespace App\Http\Requests\Retirement;

use App\Services\Stores\PensionStore;
use Illuminate\Foundation\Http\FormRequest;

class StoreDBPensionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For POST (create), authorization is handled by middleware
        if ($this->isMethod('POST')) {
            return true;
        }

        // For PUT/PATCH (update), check ownership via PensionStore.
        // Same pattern as StoreDCPensionRequest — find() is user-scoped,
        // and the store's controller-side firstOrFail produces 404 for
        // cross-user / non-existent records (defence in depth).
        $pensionId = $this->route('id');
        if ($pensionId !== null) {
            app(PensionStore::class)->find((int) $pensionId, 'db', $this->user());
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'scheme_name' => ['nullable', 'string', 'max:255'],
            'scheme_type' => ['nullable', 'in:final_salary,career_average,public_sector'],
            'accrued_annual_pension' => ['nullable', 'numeric', 'min:0'],
            'pensionable_service_years' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'pensionable_salary' => ['nullable', 'numeric', 'min:0'],
            'normal_retirement_age' => ['nullable', 'integer', 'min:55', 'max:75'],
            'revaluation_method' => ['nullable', 'string', 'max:255'],
            'spouse_pension_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lump_sum_entitlement' => ['nullable', 'numeric', 'min:0'],
            'inflation_protection' => ['nullable', 'in:cpi,rpi,fixed,none'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'scheme_name.required' => 'Please provide a scheme name.',
            'scheme_type.required' => 'Please select a scheme type.',
            'scheme_type.in' => 'Invalid scheme type. Must be final_salary, career_average, or public_sector.',
            'accrued_annual_pension.required' => 'Please enter the accrued annual pension amount.',
        ];
    }
}
