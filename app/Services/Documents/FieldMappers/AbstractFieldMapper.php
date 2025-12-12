<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

use Carbon\Carbon;

abstract class AbstractFieldMapper implements FieldMapperInterface
{
    /**
     * Field mappings: extraction_field => model_field
     */
    protected array $fieldMappings = [];

    /**
     * Field transformations: field => callable
     */
    protected array $transformations = [];

    /**
     * Map extracted fields to model-compatible array.
     */
    public function map(array $extractedFields): array
    {
        $mapped = [];

        foreach ($this->fieldMappings as $extractedKey => $modelKey) {
            if (isset($extractedFields[$extractedKey])) {
                $value = $extractedFields[$extractedKey];

                // Apply transformation if defined
                if (isset($this->transformations[$extractedKey])) {
                    $value = call_user_func($this->transformations[$extractedKey], $value);
                }

                // Only include non-null values
                if ($value !== null) {
                    $mapped[$modelKey] = $value;
                }
            }
        }

        return $mapped;
    }

    /**
     * Validate mapped data.
     */
    public function validate(array $mappedData): array
    {
        $errors = [];

        // Check required fields
        foreach ($this->getRequiredFields() as $field) {
            if (! isset($mappedData[$field]) || $mappedData[$field] === null || $mappedData[$field] === '') {
                $errors[$field] = "Required field '{$field}' is missing";
            }
        }

        return $errors;
    }

    /**
     * Parse a date string to Y-m-d format.
     */
    protected function parseDate(?string $date): ?string
    {
        if (! $date || trim($date) === '') {
            return null;
        }

        try {
            // Handle various UK date formats
            $formats = [
                'Y-m-d',      // ISO format (already correct)
                'd/m/Y',      // UK format
                'd-m-Y',      // UK format with dashes
                'd/m/y',      // UK short year
                'd M Y',      // 25 Dec 2024
                'd F Y',      // 25 December 2024
                'j M Y',      // 5 Dec 2024
                'j F Y',      // 5 December 2024
            ];

            foreach ($formats as $format) {
                $parsed = \DateTime::createFromFormat($format, trim($date));
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            }

            // Try Carbon's flexible parsing as fallback
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse a decimal/currency value.
     */
    protected function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            // Remove currency symbols, spaces, and commas
            $cleaned = preg_replace('/[£$€\s,]/', '', $value);

            // Handle pence notation (e.g., "100p")
            if (preg_match('/^([\d.]+)p$/i', $cleaned, $matches)) {
                return floatval($matches[1]) / 100;
            }

            if (is_numeric($cleaned)) {
                return (float) $cleaned;
            }
        }

        return null;
    }

    /**
     * Parse a percentage value (returns as decimal, e.g., 5% = 0.05).
     */
    protected function parsePercentage(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            // If already a small decimal (< 1), assume it's already in decimal form
            $floatVal = (float) $value;
            if ($floatVal > 1) {
                // Likely a whole number percentage, convert to decimal
                return $floatVal / 100;
            }

            return $floatVal;
        }

        if (is_string($value)) {
            // Remove % symbol and whitespace
            $cleaned = str_replace(['%', ' '], '', $value);

            if (is_numeric($cleaned)) {
                $floatVal = (float) $cleaned;
                // Values > 1 are likely whole percentages
                if ($floatVal > 1) {
                    return $floatVal / 100;
                }

                return $floatVal;
            }
        }

        return null;
    }

    /**
     * Parse an integer value.
     */
    protected function parseInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $cleaned = preg_replace('/[^0-9-]/', '', $value);
            if (is_numeric($cleaned)) {
                return (int) $cleaned;
            }
        }

        return null;
    }

    /**
     * Parse a boolean value.
     */
    protected function parseBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));

            return match ($lower) {
                'true', 'yes', '1', 'y' => true,
                'false', 'no', '0', 'n' => false,
                default => null,
            };
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return null;
    }

    /**
     * Normalize a string value.
     */
    protected function normalizeString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * Parse an enum value against allowed values.
     */
    protected function parseEnum(mixed $value, array $allowedValues, ?string $default = null): ?string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $value = strtolower(trim((string) $value));

        // Direct match
        if (in_array($value, $allowedValues, true)) {
            return $value;
        }

        // Try to find a partial match
        foreach ($allowedValues as $allowed) {
            if (str_contains($value, $allowed) || str_contains($allowed, $value)) {
                return $allowed;
            }
        }

        return $default;
    }
}
