<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Services\Stores\Exceptions\StoreValidationException;

class SavingsMarketRateNormaliser
{
    /**
     * Map an admin payload to the canonical array shape consumed by
     * SavingsMarketRateStore::create() / update().
     *
     * Fields mirror the savings_market_rates table schema exactly:
     *   rate_key, label, rate (fraction, e.g. 0.045 = 4.5% AER),
     *   tax_year (e.g. '2026/27'), effective_from (date string).
     *
     * rate_key is an open identifier — admins may add new product types,
     * so no allowlist is enforced here.
     */
    public function fromAdmin(array $input): array
    {
        $errors = [];
        $required = ['rate_key', 'label', 'rate', 'tax_year', 'effective_from'];

        foreach ($required as $field) {
            if (! array_key_exists($field, $input) || $input[$field] === null || $input[$field] === '') {
                $errors[$field] = 'required';
            }
        }

        if ($errors) {
            throw new StoreValidationException($errors);
        }

        if (! is_numeric($input['rate']) || (float) $input['rate'] < 0.0) {
            throw new StoreValidationException(['rate' => 'must be numeric and >= 0']);
        }

        return [
            'rate_key' => trim((string) $input['rate_key']),
            'label' => trim((string) $input['label']),
            'rate' => (float) $input['rate'],
            'tax_year' => trim((string) $input['tax_year']),
            'effective_from' => trim((string) $input['effective_from']),
        ];
    }
}
