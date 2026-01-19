<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Cast for encrypting/decrypting decimal values at rest.
 * Values are encrypted as strings and decrypted back to floats.
 * Uses Laravel's built-in AES-256-CBC encryption.
 */
class EncryptedDecimal implements CastsAttributes
{
    /**
     * Cast the given value (decrypt on retrieval).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($value);

            return (float) $decrypted;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // If decryption fails, try to cast as float (backwards compatibility)
            // This handles the case where data was stored before encryption was enabled
            if (is_numeric($value)) {
                return (float) $value;
            }

            \Log::warning("Failed to decrypt {$key} for model {$model->getTable()}:{$model->getKey()}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Prepare the given value for storage (encrypt on save).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // Convert to string with full precision before encrypting
        $stringValue = sprintf('%.10f', (float) $value);

        // Trim trailing zeros for cleaner storage
        $stringValue = rtrim(rtrim($stringValue, '0'), '.');

        return Crypt::encryptString($stringValue);
    }
}
