<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Services\Stores\Exceptions\StoreValidationException;

class TaxConfigNormaliser
{
    /**
     * Map an admin/seeder payload to the canonical array shape consumed by
     * TaxConfigStore::create() / update().
     *
     * Fields mirror the tax_configurations table schema:
     *   tax_year (string, /^\d{4}\/\d{2}$/), effective_from (date), effective_to (date),
     *   config_data (array), is_active (bool, default false), notes (string, nullable).
     *
     * tax_year format is "YYYY/YY" (e.g. "2026/27"). effective_from must precede effective_to.
     * config_data is intentionally permissive — it's a heterogeneous JSON document of UK
     * tax rules whose internal shape is owned by TaxConfigService consumers. The store
     * accepts any associative array; downstream services validate their own subsections.
     */
    public function fromAdmin(array $input): array
    {
        $errors = [];
        $required = ['tax_year', 'effective_from', 'effective_to', 'config_data'];

        foreach ($required as $field) {
            if (! array_key_exists($field, $input) || $input[$field] === null || $input[$field] === '') {
                $errors[$field] = 'required';
            }
        }

        if ($errors) {
            throw new StoreValidationException($errors);
        }

        $taxYear = trim((string) $input['tax_year']);
        if (! preg_match('/^\d{4}\/\d{2}$/', $taxYear)) {
            throw new StoreValidationException(['tax_year' => 'must match format YYYY/YY (e.g. 2026/27)']);
        }

        $effectiveFrom = $this->canonicaliseDate($input['effective_from'], 'effective_from');
        $effectiveTo = $this->canonicaliseDate($input['effective_to'], 'effective_to');

        if (strcmp($effectiveFrom, $effectiveTo) >= 0) {
            throw new StoreValidationException(['effective_to' => 'must be after effective_from']);
        }

        if (! is_array($input['config_data'])) {
            throw new StoreValidationException(['config_data' => 'must be an array']);
        }

        return [
            'tax_year' => $taxYear,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'config_data' => $input['config_data'],
            'is_active' => isset($input['is_active']) ? (bool) $input['is_active'] : false,
            'notes' => isset($input['notes']) && $input['notes'] !== ''
                ? trim((string) $input['notes'])
                : null,
        ];
    }

    /**
     * Accept either a Y-m-d string, a full datetime string, or anything strtotime understands;
     * return a canonical Y-m-d string. Carbon date casts on the model emit ISO 8601 on
     * toArray(); canonicalising here ensures the partial-merge → persist path round-trips.
     */
    private function canonicaliseDate(mixed $value, string $field): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $string = trim((string) $value);
        $timestamp = strtotime($string);

        if ($timestamp === false) {
            throw new StoreValidationException([$field => 'must be a valid date']);
        }

        return date('Y-m-d', $timestamp);
    }
}
