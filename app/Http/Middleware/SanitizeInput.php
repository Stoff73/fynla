<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to sanitize input data
 *
 * This middleware provides basic XSS protection by:
 * - Trimming whitespace from string inputs
 * - Stripping HTML tags from text fields (configurable)
 * - Converting empty strings to null (configurable)
 *
 * SECURITY: Helps prevent XSS attacks by sanitizing user input
 *
 * Note: This works alongside Laravel's built-in TrimStrings and ConvertEmptyStringsToNull
 */
class SanitizeInput
{
    /**
     * Fields that should NOT have HTML stripped (e.g., rich text editors)
     * Add field names here if they need to allow HTML content
     */
    protected array $htmlAllowedFields = [
        // 'content', 'description', 'body' - add as needed
    ];

    /**
     * Fields that should be completely exempt from sanitization
     */
    protected array $exemptFields = [
        'password',
        'password_confirmation',
        'current_password',
        'code',
        'challenge_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'recovery_code',
        'token',
        'access_token',
        'mfa_token',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        $sanitized = $this->sanitizeArray($input);

        $request->merge($sanitized);

        return $next($request);
    }

    /**
     * Recursively sanitize an array of input values
     */
    protected function sanitizeArray(array $input, string $prefix = ''): array
    {
        $sanitized = [];

        foreach ($input as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value, $fullKey);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value, (string) $key);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a string value
     */
    protected function sanitizeString(string $value, string $key): string
    {
        // Don't sanitize exempt fields
        if (in_array($key, $this->exemptFields)) {
            return $value;
        }

        // Trim whitespace
        $value = trim($value);

        // Strip HTML tags unless field is in allowed list
        if (! in_array($key, $this->htmlAllowedFields)) {
            $value = strip_tags($value);
        }

        return $value;
    }
}
