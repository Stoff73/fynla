<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Cast for encrypting/decrypting string values at rest.
 * Uses Laravel's built-in AES-256-CBC encryption.
 */
class EncryptedString implements CastsAttributes
{
    /**
     * Cast the given value (decrypt on retrieval).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // If decryption fails, return the original value (for backwards compatibility)
            // This handles the case where data was stored before encryption was enabled
            \Log::warning("Failed to decrypt {$key} for model {$model->getTable()}:{$model->getKey()}", [
                'error' => $e->getMessage(),
            ]);

            return $value;
        }
    }

    /**
     * Prepare the given value for storage (encrypt on save).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }
}
