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
        // Document article CMS — `html` (import) and `html_body` (update) carry
        // mammoth-converted Word document HTML. Downstream sanitisation runs
        // via HTMLPurifier in HTMLBodySanitiser using the `document_article`
        // profile, which is the appropriate defence-in-depth layer for rich
        // article content; middleware-level strip_tags here would erase the
        // structure HTMLPurifier is meant to validate.
        'html',
        'html_body',
    ];

    /**
     * Full dotted key paths (or path prefixes) whose string values should
     * keep their HTML intact. Matched via `str_starts_with`, so
     * `body_blocks` matches `body_blocks.0.html`, `body_blocks.2.html` etc.
     *
     * Insight article blocks have their own two-layer sanitisation (form
     * request `strip_tags` + allowlist + frontend DOMPurify), so we must
     * not strip HTML here before the form request sees it.
     */
    protected array $htmlAllowedPathPrefixes = [
        'body_blocks',
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
                $sanitized[$key] = $this->sanitizeString($value, (string) $key, $fullKey);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a string value
     */
    protected function sanitizeString(string $value, string $key, string $fullKey = ''): string
    {
        // Don't sanitize exempt fields
        if (in_array($key, $this->exemptFields)) {
            return $value;
        }

        // Trim whitespace
        $value = trim($value);

        // Strip HTML tags unless field is in allowed list (or under an allowed path prefix)
        if (! in_array($key, $this->htmlAllowedFields) && ! $this->pathAllowsHtml($fullKey)) {
            $value = strip_tags($value);
        }

        return $value;
    }

    /**
     * Check if the full dotted key path sits under any allow-listed prefix.
     */
    protected function pathAllowsHtml(string $fullKey): bool
    {
        if ($fullKey === '') {
            return false;
        }

        foreach ($this->htmlAllowedPathPrefixes as $prefix) {
            if ($fullKey === $prefix || str_starts_with($fullKey, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }
}
