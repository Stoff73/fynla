<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxConfigurationRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TaxConfigStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TaxSettingsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly TaxConfigStore $store,
    ) {}

    /**
     * Get current active tax configuration
     */
    public function getCurrent(): JsonResponse
    {
        try {
            $config = $this->store->all()->firstWhere('is_active', true);

            if (! $config) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active tax configuration found',
                ], 404);
            }

            // Flatten config_data into top-level response
            $response = [
                'id' => $config->id,
                'tax_year' => $config->tax_year,
                'effective_from' => $config->effective_from,
                'effective_to' => $config->effective_to,
                'is_active' => $config->is_active,
            ];

            if ($config->config_data && is_array($config->config_data)) {
                $response = array_merge($response, $config->config_data);
            }

            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch tax configuration', $e);
        }
    }

    /**
     * Get all tax configurations (including historical)
     */
    public function getAll(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->store->all(),
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch tax configurations', $e);
        }
    }

    /**
     * Update tax configuration
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (! $this->store->findEloquent($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Tax configuration not found',
            ], 404);
        }

        $request->validate([
            'tax_year' => 'sometimes|string',
            'effective_from' => 'sometimes|date',
            'effective_to' => 'sometimes|date',
            'config_data' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $payload = $request->only(['tax_year', 'effective_from', 'effective_to', 'config_data']);
            $activateAfter = $request->boolean('is_active');

            if ($payload) {
                $this->store->update(
                    $id,
                    $payload,
                    IngestSource::ADMIN,
                    auth()->id(),
                    $request->input('rationale'),
                    $request->ip(),
                );
            }

            if ($activateAfter) {
                $this->store->setActive(
                    $id,
                    IngestSource::ADMIN,
                    auth()->id(),
                    $request->input('rationale'),
                    $request->ip(),
                );
                $this->flushAgentCaches();
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax configuration updated successfully',
                'data' => $this->store->findEloquent($id),
            ]);
        } catch (StoreValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors,
            ], 422);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to update tax configuration', $e);
        }
    }

    /**
     * Create new tax configuration
     */
    public function create(StoreTaxConfigurationRequest $request): JsonResponse
    {
        try {
            $id = $this->store->create(
                $request->validated(),
                IngestSource::ADMIN,
                auth()->id(),
                $request->input('rationale'),
                $request->ip(),
            );

            if ($request->boolean('is_active')) {
                $this->store->setActive(
                    $id,
                    IngestSource::ADMIN,
                    auth()->id(),
                    $request->input('rationale'),
                    $request->ip(),
                );
                $this->flushAgentCaches();
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax configuration created successfully',
                'data' => $this->store->findEloquent($id),
            ], 201);
        } catch (StoreValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors,
            ], 422);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to create tax configuration', $e);
        }
    }

    /**
     * Set a tax configuration as active
     */
    public function setActive(Request $request, int $id): JsonResponse
    {
        if (! $this->store->findEloquent($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Tax configuration not found',
            ], 404);
        }

        try {
            $this->store->setActive(
                $id,
                IngestSource::ADMIN,
                auth()->id(),
                $request->input('rationale'),
                $request->ip(),
            );

            $this->flushAgentCaches();

            $config = $this->store->findEloquent($id);
            Log::info('Tax configuration activated — caches flushed', [
                'tax_year' => $config?->tax_year,
                'config_id' => $id,
                'admin_user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tax configuration activated successfully',
                'data' => $config,
            ]);
        } catch (StoreValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors,
            ], 422);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to activate tax configuration', $e);
        }
    }

    /**
     * Get tax calculation formulas and explanations
     */
    public function getCalculations(): JsonResponse
    {
        try {
            $calculations = [
                'income_tax' => [
                    'name' => 'Income Tax',
                    'description' => 'UK Income Tax on earned income',
                    'formula' => 'Taxable Income × Tax Rate (after Personal Allowance)',
                    'bands' => [
                        'personal_allowance' => '£0 - £12,570 (0%)',
                        'basic_rate' => '£12,571 - £50,270 (20%)',
                        'higher_rate' => '£50,271 - £125,140 (40%)',
                        'additional_rate' => 'Over £125,140 (45%)',
                    ],
                    'notes' => 'Personal allowance reduces by £1 for every £2 earned over £100,000',
                ],
                'national_insurance' => [
                    'name' => 'National Insurance',
                    'description' => 'UK National Insurance contributions',
                    'class_1_employee' => [
                        'primary_threshold' => '£12,570 per year',
                        'upper_earnings_limit' => '£50,270 per year',
                        'main_rate' => '12% (between thresholds)',
                        'additional_rate' => '2% (above upper limit)',
                    ],
                    'class_1_employer' => [
                        'secondary_threshold' => '£9,100 per year',
                        'rate' => '13.8% (above threshold)',
                    ],
                    'class_4_self_employed' => [
                        'lower_profits_limit' => '£12,570 per year',
                        'upper_profits_limit' => '£50,270 per year',
                        'main_rate' => '9% (between limits)',
                        'additional_rate' => '2% (above upper limit)',
                    ],
                ],
                'inheritance_tax' => [
                    'name' => 'Inheritance Tax (IHT)',
                    'description' => 'Tax on estate value above nil rate bands',
                    'formula' => '(Estate Value - NRB - RNRB) × 40%',
                    'nil_rate_band' => '£325,000 (transferable between spouses)',
                    'residence_nil_rate_band' => '£175,000 (for main residence, transferable)',
                    'standard_rate' => '40%',
                    'reduced_rate' => '36% (if 10%+ to charity)',
                    'pets' => 'Potentially Exempt Transfers - 7 year rule with taper relief',
                    'taper_relief' => 'Years 3-7: 20% per year reduction in IHT',
                ],
                'capital_gains_tax' => [
                    'name' => 'Capital Gains Tax (CGT)',
                    'description' => 'Tax on profits from selling assets',
                    'formula' => '(Gain - Annual Exemption) × CGT Rate',
                    'annual_exemption' => '£3,000 per tax year',
                    'rates' => [
                        'basic_rate_taxpayer' => '10% (18% for property)',
                        'higher_rate_taxpayer' => '20% (28% for property)',
                    ],
                ],
                'pension_allowances' => [
                    'name' => 'Pension Allowances',
                    'annual_allowance' => '£60,000 per tax year',
                    'tapered_allowance' => 'Reduces for high earners (threshold income >£200k, adjusted income >£260k)',
                    'minimum_allowance' => '£10,000',
                    'money_purchase_annual_allowance' => '£10,000 (after flexibly accessing pension)',
                    'carry_forward' => 'Can carry forward unused allowance from previous 3 years',
                    'lifetime_allowance' => 'Abolished from April 2024',
                ],
                'isa_allowances' => [
                    'name' => 'ISA Allowances',
                    'total_allowance' => '£20,000 per tax year',
                    'cash_isa' => 'Part of total allowance',
                    'stocks_shares_isa' => 'Part of total allowance',
                    'lifetime_isa' => '£4,000 (counts towards total allowance)',
                    'innovative_finance_isa' => 'Part of total allowance',
                    'note' => 'Can split £20,000 across different ISA types',
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $calculations,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch calculations', $e);
        }
    }

    /**
     * Duplicate an existing tax configuration
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        if (! $this->store->findEloquent($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Source tax configuration not found',
            ], 404);
        }

        $request->validate([
            'new_tax_year' => 'required|string|regex:/^\d{4}\/\d{2}$/|unique:tax_configurations,tax_year',
            'effective_from' => 'required|date',
            'effective_to' => 'required|date|after:effective_from',
        ]);

        try {
            $newId = $this->store->duplicate(
                $id,
                $request->input('new_tax_year'),
                $request->input('effective_from'),
                $request->input('effective_to'),
                IngestSource::ADMIN,
                auth()->id(),
                $request->ip(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Tax configuration duplicated successfully',
                'data' => $this->store->findEloquent($newId),
            ], 201);
        } catch (StoreValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors,
            ], 422);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to duplicate tax configuration', $e);
        }
    }

    /**
     * Delete a tax configuration
     */
    public function delete(int $id): JsonResponse
    {
        $config = $this->store->findEloquent($id);

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'Tax configuration not found',
            ], 404);
        }

        if ($config->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete active tax configuration. Please activate another tax year first.',
            ], 403);
        }

        try {
            $this->store->delete(
                $id,
                IngestSource::ADMIN,
                auth()->id(),
                request()->input('rationale'),
                request()->ip(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Tax configuration deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to delete tax configuration', $e);
        }
    }

    /**
     * Flush agent-cached analyses. Tax-config changes alter dividend / BADR /
     * APR / BPR / IHT rates which are embedded in cached per-user analyses
     * keyed v1_{agent}_{userId}_{suffix}. This is an admin-only, rare operation.
     */
    private function flushAgentCaches(): void
    {
        Cache::flush();
    }
}
