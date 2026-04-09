<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaxConfigService;
use Illuminate\Http\JsonResponse;

class TaxYearController extends Controller
{
    public function __construct(
        private TaxConfigService $taxConfig,
    ) {}

    /**
     * Return the active tax year label and effective dates.
     *
     * Lightweight endpoint — any authenticated user can read this.
     * No sensitive admin config is exposed here.
     */
    public function current(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'tax_year' => $this->taxConfig->getTaxYear(),
                'effective_from' => $this->taxConfig->getEffectiveFrom(),
                'effective_to' => $this->taxConfig->getEffectiveTo(),
            ],
        ]);
    }
}
