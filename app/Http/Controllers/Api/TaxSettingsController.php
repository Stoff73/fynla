<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxConfigurationRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\TaxConfiguration;
use App\Models\TaxConfigurationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaxSettingsController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * Log an audit record for a tax configuration change
     */
    private function logAudit(
        TaxConfiguration $config,
        string $changeType,
        ?array $beforeState = null,
        ?string $rationale = null
    ): void {
        TaxConfigurationAudit::log(
            $config,
            $changeType,
            $beforeState,
            auth()->id(),
            $rationale,
            request()->ip()
        );
    }

    /**
     * Get current active tax configuration
     */
    public function getCurrent(): JsonResponse
    {
        try {
            $config = TaxConfiguration::where('is_active', true)->first();

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

            // Merge config_data fields into response
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
            $configs = TaxConfiguration::orderBy('effective_from', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $configs,
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
        $config = TaxConfiguration::find($id);

        if (! $config) {
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
            return DB::transaction(function () use ($config, $request) {
                // Capture before state for audit
                $beforeState = $config->config_data;

                if ($request->has('tax_year')) {
                    $config->tax_year = $request->tax_year;
                }
                if ($request->has('effective_from')) {
                    $config->effective_from = $request->effective_from;
                }
                if ($request->has('effective_to')) {
                    $config->effective_to = $request->effective_to;
                }
                if ($request->has('config_data')) {
                    $config->config_data = $request->config_data;
                }
                if ($request->has('is_active') && $request->is_active) {
                    // Deactivate all others first
                    TaxConfiguration::where('is_active', true)->update(['is_active' => false]);
                    $config->is_active = true;
                }

                $config->save();

                // Log audit
                $this->logAudit(
                    $config,
                    'updated',
                    $beforeState,
                    $request->input('rationale')
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Tax configuration updated successfully',
                    'data' => $config,
                ]);
            });
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
            return DB::transaction(function () use ($request) {
                // If setting as active, deactivate all others
                if ($request->is_active) {
                    TaxConfiguration::where('is_active', true)->update(['is_active' => false]);
                }

                $config = TaxConfiguration::create([
                    'tax_year' => $request->tax_year,
                    'effective_from' => $request->effective_from,
                    'effective_to' => $request->effective_to,
                    'config_data' => $request->config_data,
                    'is_active' => $request->is_active ?? false,
                ]);

                // Log audit
                $this->logAudit(
                    $config,
                    'created',
                    null,
                    $request->input('rationale')
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Tax configuration created successfully',
                    'data' => $config,
                ], 201);
            });
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to create tax configuration', $e);
        }
    }

    /**
     * Set a tax configuration as active
     */
    public function setActive(Request $request, int $id): JsonResponse
    {
        $config = TaxConfiguration::find($id);

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'Tax configuration not found',
            ], 404);
        }

        try {
            $result = DB::transaction(function () use ($config, $request) {
                // Log deactivation of current active config
                $currentActive = TaxConfiguration::where('is_active', true)->first();
                if ($currentActive && $currentActive->id !== $config->id) {
                    $currentActive->is_active = false;
                    $currentActive->save();
                    $this->logAudit($currentActive, 'deactivated');
                }

                // Deactivate all others
                TaxConfiguration::where('is_active', true)
                    ->where('id', '!=', $config->id)
                    ->update(['is_active' => false]);

                // Activate this one
                $config->is_active = true;
                $config->save();

                // Log activation
                $this->logAudit(
                    $config,
                    'activated',
                    null,
                    $request->input('rationale')
                );

                return $config;
            });

            // Flush cached analyses so every user sees the new tax year immediately.
            // Agents cache per-user analysis results keyed as v1_{agent}_{userId}_{suffix};
            // those results embed tax rates (dividend, BADR, APR/BPR, etc.) that change
            // when the active year changes. This is an admin-only, rare operation.
            Cache::flush();

            Log::info('Tax configuration activated — caches flushed', [
                'tax_year' => $result->tax_year,
                'config_id' => $result->id,
                'admin_user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tax configuration activated successfully',
                'data' => $result,
            ]);
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
        $source = TaxConfiguration::find($id);

        if (! $source) {
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
            return DB::transaction(function () use ($source, $request) {
                // Create duplicate with new dates and tax year
                $duplicate = TaxConfiguration::create([
                    'tax_year' => $request->new_tax_year,
                    'effective_from' => $request->effective_from,
                    'effective_to' => $request->effective_to,
                    'config_data' => $source->config_data, // Copy all tax values
                    'is_active' => false, // New config starts as inactive
                ]);

                // Log audit
                $this->logAudit(
                    $duplicate,
                    'duplicated',
                    null,
                    "Duplicated from tax year {$source->tax_year}"
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Tax configuration duplicated successfully',
                    'data' => $duplicate,
                ], 201);
            });
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to duplicate tax configuration', $e);
        }
    }

    /**
     * Delete a tax configuration
     */
    public function delete(int $id): JsonResponse
    {
        $config = TaxConfiguration::find($id);

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'Tax configuration not found',
            ], 404);
        }

        // Prevent deletion of active tax year
        if ($config->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete active tax configuration. Please activate another tax year first.',
            ], 403);
        }

        try {
            $config->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tax configuration deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to delete tax configuration', $e);
        }
    }
}
