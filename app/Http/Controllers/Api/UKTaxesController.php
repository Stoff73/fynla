<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;

class UKTaxesController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * Get UK tax configuration and rates.
     * This endpoint is admin-only for viewing current UK tax rules.
     */
    public function index(): JsonResponse
    {
        // For now, return a simple success response
        // In the future, this could load from config/uk_tax_config.php or database
        return response()->json([
            'success' => true,
            'message' => 'UK Taxes configuration access granted',
            'data' => [
                'tax_year' => '2025/26',
                'note' => 'UK tax configuration is currently managed in the frontend. This endpoint confirms admin access.',
            ],
        ]);
    }
}
