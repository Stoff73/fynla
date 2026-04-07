<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Postcode Lookup Controller
 *
 * Provides UK address lookup by postcode using GetAddress.io API.
 * Acts as a proxy to protect the API key from client-side exposure.
 */
class PostcodeLookupController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * UK postcode regex pattern
     * Matches formats like: SW1A 1AA, SW1A1AA, sw1a 1aa
     */
    private const POSTCODE_PATTERN = '/^([A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2})$/i';

    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Look up addresses for a UK postcode
     *
     * GET /api/postcode-lookup/{postcode}
     */
    public function lookup(string $postcode): JsonResponse
    {
        // Normalise the postcode (uppercase, proper spacing)
        $postcode = $this->normalisePostcode($postcode);

        // Validate postcode format
        if (! $this->isValidPostcode($postcode)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_format',
                'message' => 'Please enter a valid UK postcode (e.g., SW1A 1AA)',
            ], 422);
        }

        // Check if API key is configured
        $apiKey = config('services.getaddress.api_key');
        if (empty($apiKey)) {
            Log::warning('GetAddress.io API key not configured');

            return response()->json([
                'success' => false,
                'error' => 'service_unavailable',
                'message' => 'Address lookup service is not configured',
            ], 503);
        }

        // Check cache first
        $cacheKey = 'postcode_lookup_'.str_replace(' ', '', strtoupper($postcode));
        $cachedResult = Cache::get($cacheKey);

        if ($cachedResult !== null) {
            return response()->json($cachedResult);
        }

        // Call GetAddress.io Autocomplete API
        try {
            $response = Http::timeout(10)
                ->get('https://api.getaddress.io/autocomplete/'.urlencode($postcode), [
                    'api-key' => $apiKey,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $addresses = $this->formatAddresses($data, $postcode);

                $result = [
                    'success' => true,
                    'postcode' => $postcode,
                    'addresses' => $addresses,
                ];

                // Cache the result
                Cache::put($cacheKey, $result, self::CACHE_TTL);

                return response()->json($result);
            }

            // Handle specific API errors
            if ($response->status() === 404) {
                return response()->json([
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'Postcode not found. Please check and try again.',
                ], 404);
            }

            if ($response->status() === 401) {
                Log::error('GetAddress.io API authentication failed');

                return response()->json([
                    'success' => false,
                    'error' => 'service_error',
                    'message' => 'Address lookup service error. Please enter address manually.',
                ], 503);
            }

            if ($response->status() === 429) {
                Log::warning('GetAddress.io API rate limit exceeded');

                return response()->json([
                    'success' => false,
                    'error' => 'rate_limited',
                    'message' => 'Address lookup temporarily unavailable. Please enter address manually.',
                ], 429);
            }

            // Generic API error
            Log::error('GetAddress.io API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'service_error',
                'message' => 'Address lookup unavailable. Please enter address manually.',
            ], 503);

        } catch (\Exception $e) {
            Log::error('GetAddress.io API exception', [
                'message' => $e->getMessage(),
                'postcode' => $postcode,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'service_error',
                'message' => 'Address lookup unavailable. Please enter address manually.',
            ], 503);
        }
    }

    /**
     * Normalise a postcode to uppercase with proper spacing
     */
    private function normalisePostcode(string $postcode): string
    {
        // Remove all spaces and convert to uppercase
        $postcode = strtoupper(str_replace(' ', '', $postcode));

        // Insert space before last 3 characters (outward code)
        if (strlen($postcode) >= 5) {
            $postcode = substr($postcode, 0, -3).' '.substr($postcode, -3);
        }

        return $postcode;
    }

    /**
     * Check if a postcode matches valid UK format
     */
    private function isValidPostcode(string $postcode): bool
    {
        return (bool) preg_match(self::POSTCODE_PATTERN, $postcode);
    }

    /**
     * Format addresses from GetAddress.io autocomplete response
     *
     * Autocomplete returns: {"suggestions": [{"address": "1 Street, Town, County, Postcode", "id": "..."}]}
     */
    private function formatAddresses(array $data, string $postcode): array
    {
        $addresses = [];

        if (isset($data['suggestions']) && is_array($data['suggestions'])) {
            foreach ($data['suggestions'] as $suggestion) {
                if (isset($suggestion['address'])) {
                    $parsed = $this->parseAddressString($suggestion['address'], $postcode);
                    $addresses[] = $parsed;
                }
            }
        }

        return $addresses;
    }

    /**
     * Parse an address string into components
     *
     * GetAddress.io autocomplete returns addresses like:
     * "1 Amherst Place, Sevenoaks, Kent, TN13 3BT"
     * "Flat 1, 25 High Street, London, Greater London, SW1A 1AA"
     */
    private function parseAddressString(string $addressString, string $postcode): array
    {
        // Split by comma and trim each part
        $parts = array_map('trim', explode(',', $addressString));

        // Remove empty parts
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));

        $count = count($parts);

        // Default values
        $line1 = '';
        $line2 = '';
        $city = '';
        $county = '';

        // Parse based on number of parts
        // Typical formats:
        // 4 parts: "Street, Town, County, Postcode"
        // 5 parts: "Building, Street, Town, County, Postcode"
        // 3 parts: "Street, Town, Postcode"

        if ($count >= 4) {
            // Last part is postcode, second-to-last is county, third-to-last is city
            $line1 = $parts[0];
            $line2 = $count >= 5 ? $parts[1] : '';
            $city = $parts[$count - 3];
            $county = $parts[$count - 2];
        } elseif ($count === 3) {
            // Street, Town, Postcode (no county)
            $line1 = $parts[0];
            $city = $parts[1];
        } elseif ($count === 2) {
            // Street, Postcode only
            $line1 = $parts[0];
        } elseif ($count === 1) {
            $line1 = $parts[0];
        }

        // For addresses with more than 5 parts, combine early parts into line_1/line_2
        if ($count > 5) {
            $extraParts = $count - 4; // How many extra parts beyond the standard 4
            $line1 = implode(', ', array_slice($parts, 0, $extraParts + 1));
            $line2 = $parts[$extraParts + 1] ?? '';
            $city = $parts[$count - 3];
            $county = $parts[$count - 2];
        }

        return [
            'line_1' => $line1,
            'line_2' => $line2,
            'city' => $city,
            'county' => $county,
            'postcode' => $postcode,
            'display' => $addressString,
        ];
    }
}
