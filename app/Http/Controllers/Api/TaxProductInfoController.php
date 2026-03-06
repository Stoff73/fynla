<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Tax\TaxProductInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tax Product Information Controller
 *
 * Provides API endpoints for retrieving tax treatment information
 * for investment and savings products.
 */
class TaxProductInfoController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private TaxProductInfoService $taxProductInfoService
    ) {}

    /**
     * Get tax information for an investment account type.
     *
     * @param  string  $accountType  Account type (isa, gia, onshore_bond, etc.)
     *
     * @route GET /api/tax-info/investment/{accountType}
     */
    public function getInvestmentTaxInfo(string $accountType): JsonResponse
    {
        $taxInfo = $this->taxProductInfoService->getInvestmentTaxInfo($accountType);

        return response()->json([
            'success' => true,
            'data' => $taxInfo,
        ]);
    }

    /**
     * Get tax information for a savings account type.
     *
     * @param  string  $accountType  Account type (easy_access, notice, etc.)
     *
     * @route GET /api/tax-info/savings/{accountType}
     */
    public function getSavingsTaxInfo(Request $request, string $accountType): JsonResponse
    {
        $isIsa = $request->boolean('is_isa', false);
        $taxInfo = $this->taxProductInfoService->getSavingsTaxInfo($accountType, $isIsa);

        return response()->json([
            'success' => true,
            'data' => $taxInfo,
        ]);
    }

    /**
     * Get tax summary for quick display (badges, tooltips).
     *
     *
     * @route GET /api/tax-info/summary
     */
    public function getTaxSummary(Request $request): JsonResponse
    {
        $category = $request->input('category', 'investment');
        $productType = $request->input('product_type', 'isa');

        $summary = $this->taxProductInfoService->getTaxSummary($category, $productType);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
