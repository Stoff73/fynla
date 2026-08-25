<?php

declare(strict_types=1);

namespace App\Http\Requests\Chattel;

use App\Http\Traits\ValidatesSharedOwnership;
use App\Models\Chattel;
use App\Support\SharedOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateChattelRequest extends FormRequest
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
            'chattel_type' => ['sometimes', Rule::in(['vehicle', 'art', 'antique', 'jewelry', 'collectible', 'other'])],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'max:255'],

            // Ownership
            'ownership_type' => ['sometimes', Rule::in(['individual', 'joint', 'trust'])],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'joint_owner_name' => ['nullable', 'string', 'max:255'],
            'household_id' => ['nullable', 'exists:households,id'],
            'trust_id' => ['nullable', 'exists:trusts,id'],

            // Valuation
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'current_value' => ['sometimes', 'numeric', 'min:0'],
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
            // A partial update may name only one half of the pair, so resolve
            // both against the stored record — otherwise sending just
            // `joint_owner_id: null` would orphan an already-joint chattel.
            $stored = Chattel::query()
                ->whereKey($this->route('id') ?? $this->route('chattel'))
                ->first();

            $ownershipType = $this->input('ownership_type', $stored?->ownership_type);

            // A stated share that is not a shared split is refused, never
            // rewritten (W-0040). Checked before the counterparty guard returns
            // so it applies to every shared update, not only the joint ones.
            $this->validateSharedOwnershipSplit($v, $ownershipType, $this->input('ownership_percentage'));

            if (! SharedOwnership::isShared($ownershipType)) {
                return;
            }

            $merged = [
                'joint_owner_id' => $this->has('joint_owner_id')
                    ? $this->input('joint_owner_id')
                    : $stored?->joint_owner_id,
                'joint_owner_name' => $this->has('joint_owner_name')
                    ? $this->input('joint_owner_name')
                    : $stored?->joint_owner_name,
            ];

            if (! SharedOwnership::namesCounterparty($merged)) {
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
            'chattel_type.in' => 'Please select a valid chattel type.',
            'current_value.numeric' => 'Current value must be a number.',
            'current_value.min' => 'Current value cannot be negative.',
            'joint_owner_id.exists' => 'The selected joint owner is not valid.',
        ];
    }
}
