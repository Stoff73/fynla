<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use App\Services\Protection\EmployerBenefitsWriter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The web form's save of the user's employer benefits. The rules are the
 * writer's, so the web form and Fyn's capture accept exactly the same values.
 */
class UpdateEmployerBenefitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return EmployerBenefitsWriter::rules();
    }
}
