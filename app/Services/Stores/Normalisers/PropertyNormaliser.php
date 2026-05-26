<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

class PropertyNormaliser
{
    private const ALLOWED_PROPERTY_TYPES = ['main_residence', 'secondary_residence', 'buy_to_let'];

    private const ALLOWED_OWNERSHIP_TYPES = ['individual', 'joint', 'tenants_in_common', 'trust'];

    private const ALLOWED_JOINT_OWNERSHIP_TYPES = ['joint_tenancy', 'tenants_in_common'];

    private const ALLOWED_TENURE_TYPES = ['freehold', 'leasehold'];

    /**
     * Form ingest — HTTP form-request validated payload.
     *
     * StorePropertyRequest / UpdatePropertyRequest already validates the
     * inbound shape (the outer layer per spec §7.2); the normaliser only
     * needs to canonicalise enum values, default ownership defaults, and
     * strip the mortgage_* fields that PropertyController handles via
     * direct Mortgage::create (Pass 5 will route those through MortgageStore).
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function fromForm(array $request): array
    {
        $data = $request;

        // mortgage_* fields stay in the controller (Pass 5).
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'mortgage_')) {
                unset($data[$key]);
            }
        }

        $data['property_type'] = $this->canonicalPropertyType($data['property_type'] ?? null);
        $data['ownership_type'] = $this->canonicalOwnershipType($data['ownership_type'] ?? null);
        $data['joint_ownership_type'] = $this->canonicalJointOwnershipType($data['joint_ownership_type'] ?? null);
        $data['tenure_type'] = $this->canonicalTenureType($data['tenure_type'] ?? null);

        // ownership_percentage defaults to 100 for individual / trust; required for joint*.
        if (in_array($data['ownership_type'], ['individual', 'trust'], true)) {
            $data['ownership_percentage'] = $data['ownership_percentage'] ?? 100.00;
        }

        return $data;
    }

    /**
     * Fyn AI ingest — tool-call parameters from CoordinatingAgent::handleCreateProperty.
     *
     * Fyn's vocabulary is looser than the form: it may pass `address` instead
     * of `address_line_1`, `value` instead of `current_value`, `is_joint` as a
     * boolean inferred from natural language. Map these onto the canonical
     * shape.
     *
     * @param  array<string, mixed>  $toolParams
     * @return array<string, mixed>
     */
    public function fromFyn(array $toolParams): array
    {
        $canonical = [];

        // Address — Fyn may pass `address` as a single string OR pre-split fields.
        if (isset($toolParams['address']) && ! isset($toolParams['address_line_1'])) {
            $canonical['address_line_1'] = (string) $toolParams['address'];
        } else {
            foreach (['address_line_1', 'address_line_2', 'city', 'county', 'postcode'] as $field) {
                if (isset($toolParams[$field])) {
                    $canonical[$field] = (string) $toolParams[$field];
                }
            }
        }

        // Financial — Fyn may use `value` as shorthand for `current_value`.
        if (isset($toolParams['value']) && ! isset($toolParams['current_value'])) {
            $canonical['current_value'] = (float) $toolParams['value'];
        }
        foreach (['current_value', 'purchase_price', 'outstanding_mortgage', 'monthly_rental_income', 'sdlt_paid'] as $field) {
            if (isset($toolParams[$field]) && is_numeric($toolParams[$field])) {
                $canonical[$field] = (float) $toolParams[$field];
            }
        }

        // Dates
        foreach (['purchase_date', 'valuation_date', 'lease_start_date', 'lease_end_date', 'lease_expiry_date'] as $field) {
            if (isset($toolParams[$field]) && $toolParams[$field] !== '') {
                $canonical[$field] = (string) $toolParams[$field];
            }
        }

        // Enums
        $canonical['property_type'] = $this->canonicalPropertyType($toolParams['property_type'] ?? null);
        $canonical['ownership_type'] = $this->canonicalOwnershipType($toolParams['ownership_type'] ?? null);
        $canonical['joint_ownership_type'] = $this->canonicalJointOwnershipType($toolParams['joint_ownership_type'] ?? null);
        $canonical['tenure_type'] = $this->canonicalTenureType($toolParams['tenure_type'] ?? null);

        // is_joint shorthand → ownership_type='joint'.
        if (! isset($toolParams['ownership_type']) && ! empty($toolParams['is_joint'])) {
            $canonical['ownership_type'] = 'joint';
            $canonical['joint_ownership_type'] = $canonical['joint_ownership_type'] ?? 'joint_tenancy';
        }

        // ownership_percentage
        if (isset($toolParams['ownership_percentage']) && is_numeric($toolParams['ownership_percentage'])) {
            $canonical['ownership_percentage'] = (float) $toolParams['ownership_percentage'];
        } elseif (in_array($canonical['ownership_type'], ['individual', 'trust'], true)) {
            $canonical['ownership_percentage'] = 100.00;
        }

        // Lease numeric
        if (isset($toolParams['lease_remaining_years']) && is_numeric($toolParams['lease_remaining_years'])) {
            $canonical['lease_remaining_years'] = (int) $toolParams['lease_remaining_years'];
        }

        // Country (UK default applied at the controller level historically; mirror that).
        if (isset($toolParams['country']) && $toolParams['country'] !== '') {
            $canonical['country'] = (string) $toolParams['country'];
        }

        // Joint-owner identity (linked or free-text)
        if (isset($toolParams['joint_owner_id']) && is_numeric($toolParams['joint_owner_id'])) {
            $canonical['joint_owner_id'] = (int) $toolParams['joint_owner_id'];
        }
        if (isset($toolParams['joint_owner_name']) && $toolParams['joint_owner_name'] !== '') {
            $canonical['joint_owner_name'] = (string) $toolParams['joint_owner_name'];
        }

        // Trust
        if (isset($toolParams['trust_id']) && is_numeric($toolParams['trust_id'])) {
            $canonical['trust_id'] = (int) $toolParams['trust_id'];
        }
        if (isset($toolParams['trust_name']) && $toolParams['trust_name'] !== '') {
            $canonical['trust_name'] = (string) $toolParams['trust_name'];
        }

        // Notes
        if (isset($toolParams['notes']) && $toolParams['notes'] !== '') {
            $canonical['notes'] = (string) $toolParams['notes'];
        }

        return $canonical;
    }

    /**
     * Upload ingest — document-extraction shape via DocumentProcessor + PropertyMapper.
     *
     * The mapper produces a fairly clean shape already; the normaliser is
     * mostly canonicalising enums + defaulting ownership.
     *
     * @param  array<string, mixed>  $extraction
     * @return array<string, mixed>
     */
    public function fromUpload(array $extraction): array
    {
        $canonical = [];

        foreach (['address_line_1', 'address_line_2', 'city', 'county', 'postcode'] as $field) {
            if (isset($extraction[$field]) && $extraction[$field] !== '') {
                $canonical[$field] = (string) $extraction[$field];
            }
        }
        foreach (['current_value', 'purchase_price', 'outstanding_mortgage', 'monthly_rental_income', 'sdlt_paid'] as $field) {
            if (isset($extraction[$field]) && is_numeric($extraction[$field])) {
                $canonical[$field] = (float) $extraction[$field];
            }
        }
        foreach (['purchase_date', 'valuation_date'] as $field) {
            if (isset($extraction[$field]) && $extraction[$field] !== '') {
                $canonical[$field] = (string) $extraction[$field];
            }
        }

        $canonical['property_type'] = $this->canonicalPropertyType($extraction['property_type'] ?? null);
        $canonical['ownership_type'] = $this->canonicalOwnershipType($extraction['ownership_type'] ?? null);
        $canonical['tenure_type'] = $this->canonicalTenureType($extraction['tenure_type'] ?? null);

        // Uploads default to individual ownership at 100% unless specified.
        if ($canonical['ownership_type'] === 'individual' && ! isset($canonical['ownership_percentage'])) {
            $canonical['ownership_percentage'] = 100.00;
        } elseif (isset($extraction['ownership_percentage']) && is_numeric($extraction['ownership_percentage'])) {
            $canonical['ownership_percentage'] = (float) $extraction['ownership_percentage'];
        }

        if (isset($extraction['country']) && $extraction['country'] !== '') {
            $canonical['country'] = (string) $extraction['country'];
        }

        if (isset($extraction['lease_remaining_years']) && is_numeric($extraction['lease_remaining_years'])) {
            $canonical['lease_remaining_years'] = (int) $extraction['lease_remaining_years'];
        }

        return $canonical;
    }

    private function canonicalPropertyType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array($value, self::ALLOWED_PROPERTY_TYPES, true) ? $value : 'main_residence';
    }

    private function canonicalOwnershipType(?string $value): string
    {
        return in_array($value, self::ALLOWED_OWNERSHIP_TYPES, true) ? $value : 'individual';
    }

    private function canonicalJointOwnershipType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array($value, self::ALLOWED_JOINT_OWNERSHIP_TYPES, true) ? $value : null;
    }

    private function canonicalTenureType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array($value, self::ALLOWED_TENURE_TYPES, true) ? $value : 'freehold';
    }
}
