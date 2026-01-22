<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // Call GetAddress.io API
        try {
            $response = Http::timeout(10)
                ->get("https://api.getaddress.io/find/{$postcode}", [
                    'api-key' => $apiKey,
                    'expand' => 'true', // Get expanded address fields
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
     * Format addresses from GetAddress.io response
     */
    private function formatAddresses(array $data, string $postcode): array
    {
        $addresses = [];

        if (isset($data['addresses']) && is_array($data['addresses'])) {
            foreach ($data['addresses'] as $address) {
                // GetAddress.io with expand=true returns objects with named fields
                if (is_array($address)) {
                    $addresses[] = [
                        'line_1' => trim($address['line_1'] ?? ''),
                        'line_2' => trim($address['line_2'] ?? ''),
                        'city' => trim($address['town_or_city'] ?? ''),
                        'county' => trim($address['county'] ?? ''),
                        'postcode' => $postcode,
                        // Formatted display string for dropdown
                        'display' => $this->formatDisplayAddress($address, $postcode),
                    ];
                }
            }
        }

        return $addresses;
    }

    /**
     * Create a display string for the address dropdown
     */
    private function formatDisplayAddress(array $address, string $postcode): string
    {
        $parts = array_filter([
            trim($address['line_1'] ?? ''),
            trim($address['line_2'] ?? ''),
            trim($address['town_or_city'] ?? ''),
            trim($address['county'] ?? ''),
            $postcode,
        ]);

        return implode(', ', $parts);
    }
}
