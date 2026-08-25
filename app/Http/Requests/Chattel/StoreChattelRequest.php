<?php

declare(strict_types=1);

namespace App\Http\Requests\Chattel;

use App\Http\Traits\ValidatesSharedOwnership;
use App\Support\SharedOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChattelRequest extends FormRequest
{
    use ValidatesSharedOwnership;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The chattels.country column is NOT NULL DEFAULT 'United Kingdom'.
     * Drop the key when it arrives null/empty so the DB default kicks in instead
     * of an integrity-constraint 500. Same pattern as PR #269 (investment_accounts).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('country') && in_array($this->input('country'), [null, ''], true)) {
            $this->offsetUnset('country');
        }
    }

    public function rules(): array
    {
        return [
            // Core chattel info
            'chattel_type' => ['nullable', Rule::in(['vehicle', 'art', 'antique', 'jewelry', 'collectible', 'other'])],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'max:255'],

            // Ownership
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'trust'])],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'joint_owner_name' => ['nullable', 'string', 'max:255'],
            'household_id' => ['nullable', 'exists:households,id'],
            'trust_id' => ['nullable', 'exists:trusts,id'],

            // Valuation
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'valuation_date' => ['nullable', 'date'],

            // Vehicle-specific (conditional)
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'registration_number' => ['nullable', 'string', 'max:20'],

            // Notes
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * A shared asset must name the other party.
     *
     * Selecting "Joint" and leaving the Joint Owner select untouched used to
     * save with 200/201 and no error, producing a chattel 50% owned by the
     * saver and 50% owned by nobody — invisible to the spouse, and missing from
     * every household total (W-0025). The predicate lives in
     * App\Support\SharedOwnership so chattels, property and mortgages all ask
     * the same question.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ownershipType = $this->input('ownership_type', 'individual');

            // A stated share that is not a shared split is refused, never
            // rewritten (W-0040).
            $this->validateSharedOwnershipSplit($v, $ownershipType, $this->input('ownership_percentage'));

            if (! SharedOwnership::isShared($ownershipType)) {
                return;
            }

            if (! SharedOwnership::namesCounterparty($this->all())) {
                $v->errors()->add(
                    'joint_owner_id',
                    'Choose who this is owned with, or enter their name.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'chattel_type.required' => 'Please select a type.',
            'chattel_type.in' => 'Please select a valid type.',
            'name.required' => 'Please provide a name for this item.',
            'current_value.required' => 'Please provide the current value.',
            'current_value.numeric' => 'Current value must be a number.',
            'current_value.min' => 'Current value cannot be negative.',
            'joint_owner_id.exists' => 'The selected joint owner is not valid.',
        ];
    }
}
