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
            $config = $this->store->activeConfig()['config_data'] ?? [];

            $calculations = [
                'income_tax' => $this->incomeTaxCalculations($config['income_tax'] ?? []),
                'national_insurance' => $this->nationalInsuranceCalculations($config['national_insurance'] ?? []),
                'inheritance_tax' => $this->inheritanceTaxCalculations($config['inheritance_tax'] ?? []),
                'capital_gains_tax' => $this->capitalGainsTaxCalculations($config['capital_gains_tax'] ?? []),
                'pension_allowances' => $this->pensionAllowanceCalculations($config['pension'] ?? []),
                'isa_allowances' => $this->isaAllowanceCalculations($config['isa'] ?? []),
            ];

            return response()->json([
                'success' => true,
                'data' => $calculations,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch calculations', $e);
        }
    }

    /** Build the income-tax block from active config (W1 fix — was hardcoded). */
    private function incomeTaxCalculations(array $incomeTax): array
    {
        $pa = (int) ($incomeTax['personal_allowance'] ?? 0);
        $bands = $incomeTax['bands'] ?? [];
        $taperThreshold = (int) ($incomeTax['personal_allowance_taper_threshold'] ?? 100000);
        $taperRate = (float) ($incomeTax['personal_allowance_taper_rate'] ?? 0.5);
        $taperFraction = $taperRate > 0 ? (int) round(1 / $taperRate) : 2;

        $bandStrings = ['personal_allowance' => sprintf('£0 - £%s (0%%)', number_format($pa))];
        foreach ($bands as $band) {
            $name = strtolower(str_replace(' ', '_', (string) ($band['name'] ?? '')));
            $lower = (int) ($band['lower_limit'] ?? $band['threshold'] ?? 0);
            $upper = isset($band['upper_limit']) && $band['upper_limit'] !== null
                ? '£'.number_format((int) $band['upper_limit'])
                : 'no upper limit';
            $rate = (float) ($band['rate'] ?? 0) * 100;
            $bandStrings[$name.'_rate'] = sprintf(
                '£%s - %s (%d%%)',
                number_format($lower + 1),
                $upper,
                $rate
            );
        }

        return [
            'name' => 'Income Tax',
            'description' => 'UK Income Tax on earned income',
            'formula' => 'Taxable Income × Tax Rate (after Personal Allowance)',
            'bands' => $bandStrings,
            'notes' => sprintf(
                'Personal allowance reduces by £1 for every £%d earned over £%s',
                $taperFraction,
                number_format($taperThreshold)
            ),
        ];
    }

    /** Build the NI block from active config (W1 fix — was hardcoded). */
    private function nationalInsuranceCalculations(array $ni): array
    {
        $emp = $ni['class_1']['employee'] ?? [];
        $empr = $ni['class_1']['employer'] ?? [];
        $c4 = $ni['class_4'] ?? [];

        return [
            'name' => 'National Insurance',
            'description' => 'UK National Insurance contributions',
            'class_1_employee' => [
                'primary_threshold' => sprintf('£%s per year', number_format((int) ($emp['primary_threshold'] ?? 0))),
                'upper_earnings_limit' => sprintf('£%s per year', number_format((int) ($emp['upper_earnings_limit'] ?? 0))),
                'main_rate' => sprintf('%g%% (between thresholds)', ((float) ($emp['main_rate'] ?? 0)) * 100),
                'additional_rate' => sprintf('%g%% (above upper limit)', ((float) ($emp['additional_rate'] ?? 0)) * 100),
            ],
            'class_1_employer' => [
                'secondary_threshold' => sprintf('£%s per year', number_format((int) ($empr['secondary_threshold'] ?? 0))),
                'rate' => sprintf('%g%% (above threshold)', ((float) ($empr['rate'] ?? 0)) * 100),
            ],
            'class_4_self_employed' => [
                'lower_profits_limit' => sprintf('£%s per year', number_format((int) ($c4['lower_profits_limit'] ?? 0))),
                'upper_profits_limit' => sprintf('£%s per year', number_format((int) ($c4['upper_profits_limit'] ?? 0))),
                'main_rate' => sprintf('%g%% (between limits)', ((float) ($c4['main_rate'] ?? 0)) * 100),
                'additional_rate' => sprintf('%g%% (above upper limit)', ((float) ($c4['additional_rate'] ?? 0)) * 100),
            ],
        ];
    }

    /** Build the IHT block from active config (W1 fix — was hardcoded). */
    private function inheritanceTaxCalculations(array $iht): array
    {
        return [
            'name' => 'Inheritance Tax (IHT)',
            'description' => 'Tax on estate value above nil rate bands',
            'formula' => '(Estate Value - NRB - RNRB) × Standard Rate',
            'nil_rate_band' => sprintf('£%s (transferable between spouses)', number_format((int) ($iht['nil_rate_band'] ?? 0))),
            'residence_nil_rate_band' => sprintf('£%s (for main residence, transferable)', number_format((int) ($iht['residence_nil_rate_band'] ?? 0))),
            'standard_rate' => sprintf('%g%%', ((float) ($iht['standard_rate'] ?? 0)) * 100),
            'reduced_rate' => sprintf('%g%% (if 10%%+ to charity)', ((float) ($iht['reduced_rate'] ?? 0.36)) * 100),
            'pets' => 'Potentially Exempt Transfers — 7-year rule with taper relief',
            'taper_relief' => 'Years 3-7: tapered reduction in IHT (see active config for exact percentages)',
        ];
    }

    /** Build the CGT block from active config (W1 fix — was hardcoded). */
    private function capitalGainsTaxCalculations(array $cgt): array
    {
        return [
            'name' => 'Capital Gains Tax (CGT)',
            'description' => 'Tax on profits from selling assets',
            'formula' => '(Gain - Annual Exemption) × CGT Rate',
            'annual_exemption' => sprintf('£%s per tax year', number_format((int) ($cgt['annual_exempt_amount'] ?? 0))),
            'rates' => [
                'basic_rate_taxpayer' => sprintf(
                    '%g%% (%g%% for residential property)',
                    ((float) ($cgt['basic_rate'] ?? 0)) * 100,
                    ((float) ($cgt['residential_property_basic_rate'] ?? 0)) * 100,
                ),
                'higher_rate_taxpayer' => sprintf(
                    '%g%% (%g%% for residential property)',
                    ((float) ($cgt['higher_rate'] ?? 0)) * 100,
                    ((float) ($cgt['residential_property_higher_rate'] ?? 0)) * 100,
                ),
            ],
        ];
    }

    /** Build the pension block from active config (W1 fix — was hardcoded). */
    private function pensionAllowanceCalculations(array $pension): array
    {
        return [
            'name' => 'Pension Allowances',
            'annual_allowance' => sprintf('£%s per tax year', number_format((int) ($pension['annual_allowance'] ?? 0))),
            'tapered_allowance' => 'Reduces for high earners (see income_tax + pension config for thresholds)',
            'minimum_allowance' => sprintf('£%s', number_format((int) ($pension['minimum_allowance'] ?? 10000))),
            'money_purchase_annual_allowance' => sprintf(
                '£%s (after flexibly accessing pension)',
                number_format((int) ($pension['money_purchase_annual_allowance'] ?? $pension['mpaa'] ?? 10000))
            ),
            'carry_forward' => 'Can carry forward unused allowance from previous 3 years',
            'lifetime_allowance' => 'Abolished from April 2024',
        ];
    }

    /** Build the ISA block from active config (W1 fix — was hardcoded). */
    private function isaAllowanceCalculations(array $isa): array
    {
        return [
            'name' => 'ISA Allowances',
            'total_allowance' => sprintf('£%s per tax year', number_format((int) ($isa['annual_allowance'] ?? 0))),
            'cash_isa' => 'Part of total allowance',
            'stocks_shares_isa' => 'Part of total allowance',
            'lifetime_isa' => sprintf(
                '£%s (counts towards total allowance)',
                number_format((int) ($isa['lifetime_isa']['annual_allowance'] ?? 4000))
            ),
            'innovative_finance_isa' => 'Part of total allowance',
            'note' => sprintf('Can split £%s across different ISA types', number_format((int) ($isa['annual_allowance'] ?? 0))),
        ];
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
