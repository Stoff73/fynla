<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Services\Stores\Exceptions\StoreValidationException;

class CurrencyRateNormaliser
{
    /**
     * Map an admin/seeder payload to the canonical array shape consumed by
     * CurrencyRateStore::create() / update().
     *
     * Fields mirror the currency_rates table schema exactly:
     *   from_ccy (3-letter ISO 4217), to_ccy (3-letter ISO 4217),
     *   rate (float > 0, 1 from_ccy = rate to_ccy), effective_at (datetime string),
     *   source (string, default 'manual' — 'manual' | 'feed:ecb' | etc.).
     *
     * from_ccy / to_ccy are uppercased; both must be 3 alphabetic characters.
     */
    public function fromAdmin(array $input): array
    {
        $errors = [];
        $required = ['from_ccy', 'to_ccy', 'rate', 'effective_at'];

        foreach ($required as $field) {
            if (! array_key_exists($field, $input) || $input[$field] === null || $input[$field] === '') {
                $errors[$field] = 'required';
            }
        }

        if ($errors) {
            throw new StoreValidationException($errors);
        }

        $fromCcy = strtoupper(trim((string) $input['from_ccy']));
        $toCcy = strtoupper(trim((string) $input['to_ccy']));

        if (! preg_match('/^[A-Z]{3}$/', $fromCcy)) {
            throw new StoreValidationException(['from_ccy' => 'must be a 3-letter ISO 4217 code']);
        }

        if (! preg_match('/^[A-Z]{3}$/', $toCcy)) {
            throw new StoreValidationException(['to_ccy' => 'must be a 3-letter ISO 4217 code']);
        }

        if ($fromCcy === $toCcy) {
            throw new StoreValidationException(['to_ccy' => 'must differ from from_ccy']);
        }

        if (! is_numeric($input['rate']) || (float) $input['rate'] <= 0.0) {
            throw new StoreValidationException(['rate' => 'must be numeric and > 0']);
        }

        return [
            'from_ccy' => $fromCcy,
            'to_ccy' => $toCcy,
            'rate' => (float) $input['rate'],
            'effective_at' => trim((string) $input['effective_at']),
            'source' => isset($input['source']) && $input['source'] !== ''
                ? trim((string) $input['source'])
                : 'manual',
        ];
    }
}
