<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\ActuarialLifeTable;
use App\Models\Estate\IHTProfile;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Goals\LifeEventIntegrationService;
use App\Services\TaxConfigService;
use App\Services\UserProfile\ProfileCompletenessChecker;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Support\Collection;

/**
 * Generates a comprehensive estate plan combining:
 * - User profile and estate overview
 * - Gifting strategy (PETs and annual exemptions)
 * - Life policy strategy (whole of life vs self-insurance)
 * - Trust strategy (CLTs and various trust types)
 * - Optimized combined recommendation
 */
class ComprehensiveEstatePlanService
{
    use CalculatesOwnershipShare;

    public function __construct(
        private readonly PersonalizedGiftingStrategyService $giftingStrategy,
        private readonly PersonalizedTrustStrategyService $trustStrategy,
        private readonly NetWorthAnalyzer $netWorthAnalyzer,
        private readonly IHTCalculationService $ihtCalculationService,
        private readonly EstateAssetAggregatorService $assetAggregator,
        private readonly ProfileCompletenessChecker $completenessChecker,
        private readonly TaxConfigService $taxConfig,
        private readonly LifeEventIntegrationService $lifeEventIntegration
    ) {}

    /**
     * Generate comprehensive estate plan
     */
    public function generateComprehensiveEstatePlan(User $user): array
    {
        // Check profile completeness
        $profileCompleteness = $this->completenessChecker->checkCompleteness($user);
        $completenessScore = $profileCompleteness['completeness_score'];
        $isComplete = $profileCompleteness['is_complete'];

        // Get IHT profile
        $ihtProfile = IHTProfile::where('user_id', $user->id)->first();
        if (! $ihtProfile) {
            $ihtConfig = $this->taxConfig->getInheritanceTax();
            $ihtProfile = new IHTProfile([
                'user_id' => $user->id,
                'marital_status' => $user->marital_status ?? 'single',
                'available_nrb' => $ihtConfig['nil_rate_band'],
                'nrb_transferred_from_spouse' => 0,
                'charitable_giving_percent' => 0,
            ]);
        }

        // Calculate current IHT position using simplified service
        $spouse = ($user->marital_status === 'married' && $user->spouse_id) ? User::find($user->spouse_id) : null;
        $dataSharingEnabled = $spouse && $user->hasAcceptedSpousePermission();

        // Gather user assets
        $aggregatedAssets = $this->assetAggregator->gatherUserAssets($user);
        $assets = $this->convertToAssetModels($aggregatedAssets, $user);

        // Gather spouse assets if data sharing enabled
        $spouseAggregatedAssets = collect();
        $spouseAssets = collect();
        if ($dataSharingEnabled) {
            $spouseAggregatedAssets = $this->assetAggregator->gatherUserAssets($spouse);
            $spouseAssets = $this->convertToAssetModels($spouseAggregatedAssets, $spouse);
        }

        $ihtAnalysis = $this->ihtCalculationService->calculate($user, $spouse, $dataSharingEnabled);
        $currentIHTLiability = $ihtAnalysis['iht_liability'];

        // For married couples, the IHTCalculationService already calculated combined values
        // Extract projected IHT liability from the calculation result
        $projectedIHTLiability = $ihtAnalysis['projected_iht_liability'] ?? null;

        // For methods that still expect old structure, create a compatibility object
        $secondDeathAnalysis = [
            'current_iht_calculation' => [
                'iht_liability' => $currentIHTLiability,
                'taxable_estate' => $ihtAnalysis['taxable_estate'] ?? 0,
                'net_estate' => $ihtAnalysis['total_net_estate'] ?? 0,
            ],
        ];

        // Calculate years until death (life expectancy)
        $yearsUntilDeath = $this->calculateYearsUntilDeath($user);

        // Generate individual strategies
        $giftingPlan = $this->giftingStrategy->generatePersonalizedStrategy(
            $assets,
            $currentIHTLiability,
            $ihtProfile,
            $user,
            $yearsUntilDeath
        );

        $trustPlan = $this->trustStrategy->generatePersonalizedTrustStrategy(
            $assets,
            $currentIHTLiability,
            $ihtProfile,
            $user,
            $yearsUntilDeath
        );

        // Get life policy strategy data (if available)
        $lifePolicyPlan = $this->getLifePolicyStrategy($user, $currentIHTLiability);

        // Generate optimized combined strategy
        $optimizedStrategy = $this->generateOptimizedStrategy(
            $giftingPlan,
            $trustPlan,
            $lifePolicyPlan,
            $currentIHTLiability,
            $ihtProfile
        );

        // Build comprehensive plan
        return [
            'plan_metadata' => [
                'generated_date' => now()->format('d F Y'),
                'generated_time' => now()->format('H:i'),
                'plan_version' => 'v1.0',
                'user_name' => $user->name,
                'completeness_score' => $completenessScore,
                'is_complete' => $isComplete,
                'plan_type' => $isComplete ? 'Personalised' : 'Generic',
            ],
            'completeness_warning' => $this->generateCompletenessWarning($profileCompleteness),
            'executive_summary' => $this->generateExecutiveSummary(
                $user,
                $ihtAnalysis,
                $optimizedStrategy,
                $profileCompleteness,
                $currentIHTLiability,
                $projectedIHTLiability,
                $secondDeathAnalysis
            ),
            'user_profile' => $this->buildUserProfile($user, $spouse, $dataSharingEnabled),
            'balance_sheet' => $this->buildBalanceSheet($user, $assets, $ihtAnalysis, $spouse, $spouseAssets, $dataSharingEnabled),
            'estate_overview' => $this->buildEstateOverview($aggregatedAssets, $ihtAnalysis, $spouseAggregatedAssets, $dataSharingEnabled),
            'estate_breakdown' => $this->buildEstateBreakdown($user, $aggregatedAssets, $secondDeathAnalysis, $spouse, $spouseAggregatedAssets, $dataSharingEnabled),
            'current_iht_position' => $this->buildIHTPosition($ihtAnalysis, $ihtProfile, $secondDeathAnalysis),
            'gifting_strategy' => $giftingPlan,
            'trust_strategy' => $trustPlan,
            'life_policy_strategy' => $lifePolicyPlan,
            'life_events_impact' => $this->buildLifeEventsImpact($user, $currentIHTLiability, $ihtAnalysis),
            'optimized_recommendation' => $optimizedStrategy,
            'implementation_timeline' => $this->buildImplementationTimeline($optimizedStrategy),
            'next_steps' => $this->generateNextSteps($optimizedStrategy, $profileCompleteness),
        ];
    }

    /**
     * Convert aggregated assets to Asset models
     */
    private function convertToAssetModels(Collection $aggregatedAssets, User $user): Collection
    {
        return $aggregatedAssets->map(function ($asset) use ($user) {
            return new \App\Models\Estate\Asset([
                'user_id' => $user->id,
                'asset_type' => $asset->asset_type,
                'asset_name' => $asset->asset_name,
                'current_value' => $asset->current_value,
                'is_iht_exempt' => $asset->is_iht_exempt ?? false,
            ]);
        });
    }

    /**
     * Calculate years until death based on actuarial life tables
     */
    private function calculateYearsUntilDeath(User $user): int
    {
        if (! $user->date_of_birth) {
            return 20;
        }

        $age = $user->age ?? \Carbon\Carbon::parse($user->date_of_birth)->age;
        $gender = $user->gender ?? 'male';

        // Query actuarial life tables (same approach as IHTCalculationService)
        $lifeExpectancy = ActuarialLifeTable::where('gender', $gender)
            ->where('age', '<=', $age)
            ->where('table_year', '2020-2022')
            ->orderBy('age', 'desc')
            ->value('life_expectancy_years');

        if ($lifeExpectancy) {
            return max(1, (int) ceil((float) $lifeExpectancy));
        }

        // Fallback if no actuarial data
        return max(1, 85 - $age);
    }

    /**
     * Get life policy strategy (simplified - would call actual service)
     */
    private function getLifePolicyStrategy(User $user, float $ihtLiability): ?array
    {
        if ($ihtLiability <= 0) {
            return null;
        }

        // Simplified life policy recommendation
        return [
            'recommended_approach' => 'Whole of Life Policy',
            'sum_assured_required' => $ihtLiability,
            'estimated_monthly_premium' => $this->estimateLifePremium($user, $ihtLiability),
            'policy_type' => 'Whole of Life',
            'written_in_trust' => true,
            'benefits' => [
                'Guaranteed payout to cover IHT liability',
                'Written in trust - proceeds outside estate',
                'No investment risk',
                'Peace of mind for beneficiaries',
            ],
        ];
    }

    /**
     * Estimate monthly life insurance premium (simplified)
     */
    private function estimateLifePremium(User $user, float $sumAssured): float
    {
        $age = $user->age ?? 55;
        $monthlyRatePer1000 = 0.50 + ($age - 40) * 0.05; // Simplified

        return ($sumAssured / 1000) * $monthlyRatePer1000;
    }

    /**
     * Build user profile section
     */
    private function buildUserProfile(User $user, ?User $spouse = null, bool $dataSharingEnabled = false): array
    {
        // Calculate age from date of birth
        $age = 'Not provided';
        if ($user->date_of_birth) {
            $age = \Carbon\Carbon::parse($user->date_of_birth)->age;
        }

        // Get children and step-children from user's family members
        $children = FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'child')
            ->get();

        $stepChildren = FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'step_child')
            ->get();

        // If spouse exists and data sharing is enabled, also include spouse's children
        // (avoiding duplicates based on name and date_of_birth)
        if ($spouse && $dataSharingEnabled) {
            $spouseChildren = FamilyMember::where('user_id', $spouse->id)
                ->where('relationship', 'child')
                ->get();

            // Add spouse's children that aren't duplicates
            foreach ($spouseChildren as $spouseChild) {
                $isDuplicate = $children->contains(function ($child) use ($spouseChild) {
                    return $child->name === $spouseChild->name &&
                           $child->date_of_birth === $spouseChild->date_of_birth;
                });

                if (! $isDuplicate) {
                    $children->push($spouseChild);
                }
            }

            $spouseStepChildren = FamilyMember::where('user_id', $spouse->id)
                ->where('relationship', 'step_child')
                ->get();

            // Add spouse's step-children that aren't duplicates
            foreach ($spouseStepChildren as $spouseStepChild) {
                $isDuplicate = $stepChildren->contains(function ($child) use ($spouseStepChild) {
                    return $child->name === $spouseStepChild->name &&
                           $child->date_of_birth === $spouseStepChild->date_of_birth;
                });

                if (! $isDuplicate) {
                    $stepChildren->push($spouseStepChild);
                }
            }
        }

        // Convert to array format
        $childrenArray = $children->map(fn ($child) => ['name' => $child->name, 'relationship' => 'Child'])
            ->values()
            ->toArray();

        $stepChildrenArray = $stepChildren->map(fn ($child) => ['name' => $child->name, 'relationship' => 'Step-Child'])
            ->values()
            ->toArray();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'date_of_birth' => $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not provided',
            'age' => $age,
            'gender' => ucfirst($user->gender ?? 'Not specified'),
            'marital_status' => ucfirst(str_replace('_', ' ', $user->marital_status ?? 'single')),
            'spouse' => $spouse ? [
                'name' => $spouse->name,
                'relationship' => 'Spouse',
            ] : null,
            'children' => $childrenArray,
            'step_children' => $stepChildrenArray,
        ];
    }

    /**
     * Build estate overview with detailed asset breakdown
     * Shows user, spouse, and combined totals when data sharing enabled
     */
    private function buildEstateOverview(Collection $assets, array $ihtAnalysis, Collection $spouseAssets, bool $dataSharingEnabled): array
    {
        // User's assets
        $assetsByType = $assets->groupBy('asset_type');
        $breakdown = [];
        $detailedAssets = [];

        foreach ($assetsByType as $type => $typeAssets) {
            $breakdown[] = [
                'type' => ucfirst($type),
                'count' => $typeAssets->count(),
                'value' => $typeAssets->sum('current_value'),
            ];

            // Add detailed list of assets by type
            $detailedAssets[$type] = $typeAssets->map(function ($asset) {
                return [
                    'name' => $asset->asset_name,
                    'value' => $asset->current_value,
                    'is_iht_exempt' => $asset->is_iht_exempt ?? false,
                ];
            })->toArray();
        }

        $result = [
            'total_assets' => $assets->sum('current_value'),
            'total_liabilities' => $ihtAnalysis['total_liabilities'] ?? 0,
            'net_estate' => $ihtAnalysis['total_net_estate'] ?? 0,
            'asset_count' => $assets->count(),
            'breakdown' => $breakdown,
            'detailed_assets' => $detailedAssets,
        ];

        // Add spouse data if sharing enabled
        if ($dataSharingEnabled && $spouseAssets->isNotEmpty()) {
            $spouseAssetsByType = $spouseAssets->groupBy('asset_type');
            $spouseBreakdown = [];
            $spouseDetailedAssets = [];

            foreach ($spouseAssetsByType as $type => $typeAssets) {
                $spouseBreakdown[] = [
                    'type' => ucfirst($type),
                    'count' => $typeAssets->count(),
                    'value' => $typeAssets->sum('current_value'),
                ];

                $spouseDetailedAssets[$type] = $typeAssets->map(function ($asset) {
                    return [
                        'name' => $asset->asset_name,
                        'value' => $asset->current_value,
                        'is_iht_exempt' => $asset->is_iht_exempt ?? false,
                    ];
                })->toArray();
            }

            $result['spouse'] = [
                'total_assets' => $spouseAssets->sum('current_value'),
                'asset_count' => $spouseAssets->count(),
                'breakdown' => $spouseBreakdown,
                'detailed_assets' => $spouseDetailedAssets,
            ];

            // Combined totals
            $result['combined'] = [
                'total_assets' => $assets->sum('current_value') + $spouseAssets->sum('current_value'),
                'asset_count' => $assets->count() + $spouseAssets->count(),
            ];
        }

        return $result;
    }

    /**
     * Build estate breakdown with separate user, spouse, and joint sections
     * Uses data from secondDeathAnalysis if available (already calculated in Estate module)
     */
    private function buildEstateBreakdown(User $user, Collection $aggregatedAssets, ?array $secondDeathAnalysis, ?User $spouse, Collection $spouseAggregatedAssets, bool $dataSharingEnabled): array
    {
        $breakdown = [
            'user' => null,
            'spouse' => null,
            'combined' => null,
        ];

        // If we have second death analysis, use that data (already calculated)
        if ($secondDeathAnalysis && isset($secondDeathAnalysis['first_death']) && isset($secondDeathAnalysis['second_death'])) {
            // Combine all assets (user + spouse)
            $allAssets = $aggregatedAssets->concat($spouseAggregatedAssets);

            // User's estate
            $userAssets = $allAssets->filter(fn ($asset) => $asset->user_id === $user->id);
            $breakdown['user'] = [
                'name' => $secondDeathAnalysis['first_death']['name'],
                'total_assets' => $secondDeathAnalysis['first_death']['current_estate_value'],
                'total_liabilities' => $secondDeathAnalysis['liability_breakdown']['current']['user_liabilities'] ?? 0,
                'net_estate' => $secondDeathAnalysis['first_death']['current_estate_value'] - ($secondDeathAnalysis['liability_breakdown']['current']['user_liabilities'] ?? 0),
                'asset_count' => $userAssets->count(),
                'detailed_assets' => $this->groupAssetsByType($userAssets),
            ];

            // Spouse's estate
            if ($spouse) {
                $spouseAssets = $allAssets->filter(fn ($asset) => $asset->user_id === $spouse->id);
                $breakdown['spouse'] = [
                    'name' => $secondDeathAnalysis['second_death']['name'],
                    'total_assets' => $secondDeathAnalysis['second_death']['current_estate_value'],
                    'total_liabilities' => $secondDeathAnalysis['liability_breakdown']['current']['spouse_liabilities'] ?? 0,
                    'net_estate' => $secondDeathAnalysis['second_death']['current_estate_value'] - ($secondDeathAnalysis['liability_breakdown']['current']['spouse_liabilities'] ?? 0),
                    'asset_count' => $spouseAssets->count(),
                    'detailed_assets' => $this->groupAssetsByType($spouseAssets),
                ];
            }

            // Combined estate (from current_combined_totals)
            $breakdown['combined'] = [
                'total_assets' => $secondDeathAnalysis['current_combined_totals']['gross_assets'],
                'total_liabilities' => $secondDeathAnalysis['current_combined_totals']['total_liabilities'],
                'net_estate' => $secondDeathAnalysis['current_combined_totals']['net_estate'],
                'asset_count' => $allAssets->count(),
                'detailed_assets' => $this->groupAssetsByType($allAssets),
            ];
        } else {
            // User's estate - get detailed liabilities
            $userDetailedLiabilities = $this->getDetailedLiabilities($user->id);
            $userLiabilitiesTotal = collect($userDetailedLiabilities)->sum('balance');

            $breakdown['user'] = [
                'name' => $user->name,
                'total_assets' => $aggregatedAssets->sum('current_value'),
                'total_liabilities' => $userLiabilitiesTotal,
                'net_estate' => $aggregatedAssets->sum('current_value') - $userLiabilitiesTotal,
                'asset_count' => $aggregatedAssets->count(),
                'detailed_assets' => $this->groupAssetsByType($aggregatedAssets),
                'detailed_liabilities' => $userDetailedLiabilities,
            ];

            // Add spouse data if available and sharing enabled
            if ($dataSharingEnabled && $spouse && $spouseAggregatedAssets->isNotEmpty()) {
                $spouseDetailedLiabilities = $this->getDetailedLiabilities($spouse->id);
                $spouseLiabilitiesTotal = collect($spouseDetailedLiabilities)->sum('balance');

                $breakdown['spouse'] = [
                    'name' => $spouse->name,
                    'total_assets' => $spouseAggregatedAssets->sum('current_value'),
                    'total_liabilities' => $spouseLiabilitiesTotal,
                    'net_estate' => $spouseAggregatedAssets->sum('current_value') - $spouseLiabilitiesTotal,
                    'asset_count' => $spouseAggregatedAssets->count(),
                    'detailed_assets' => $this->groupAssetsByType($spouseAggregatedAssets),
                    'detailed_liabilities' => $spouseDetailedLiabilities,
                ];

                // Combined totals
                $allLiabilities = array_merge($userDetailedLiabilities, $spouseDetailedLiabilities);
                $breakdown['combined'] = [
                    'total_assets' => $aggregatedAssets->sum('current_value') + $spouseAggregatedAssets->sum('current_value'),
                    'total_liabilities' => $userLiabilitiesTotal + $spouseLiabilitiesTotal,
                    'net_estate' => ($aggregatedAssets->sum('current_value') + $spouseAggregatedAssets->sum('current_value')) - ($userLiabilitiesTotal + $spouseLiabilitiesTotal),
                    'asset_count' => $aggregatedAssets->count() + $spouseAggregatedAssets->count(),
                    'detailed_assets' => $this->groupAssetsByType($aggregatedAssets->concat($spouseAggregatedAssets)),
                    'detailed_liabilities' => $allLiabilities,
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Group assets by type for detailed breakdown
     */
    private function groupAssetsByType(Collection $assets): array
    {
        $assetsByType = $assets->groupBy('asset_type');
        $grouped = [];

        foreach ($assetsByType as $type => $typeAssets) {
            $grouped[$type] = $typeAssets->map(function ($asset) {
                return [
                    'name' => $asset->asset_name,
                    'value' => $asset->current_value,
                    'is_iht_exempt' => $asset->is_iht_exempt ?? false,
                ];
            })->toArray();
        }

        return $grouped;
    }

    /**
     * Get detailed liabilities for a user (mortgages and other liabilities)
     *
     * Single-record pattern: Apply ownership percentage to get user's share
     */
    private function getDetailedLiabilities(int $userId): array
    {
        $liabilities = [];

        // Get mortgages where user is owner OR joint_owner, with property addresses
        $mortgages = \App\Models\Mortgage::forUserOrJoint($userId)
            ->with('property:id,address_line_1')
            ->get();

        foreach ($mortgages as $mortgage) {
            // Apply ownership share calculation for user's portion of mortgage
            $userShare = $this->calculateUserMortgageShare($mortgage, $userId);

            $liabilities[] = [
                'type' => 'Mortgage',
                'name' => $mortgage->property?->address_line_1
                    ? "Mortgage - {$mortgage->property->address_line_1}"
                    : ($mortgage->lender_name ? "Mortgage - {$mortgage->lender_name}" : 'Mortgage'),
                'balance' => $userShare,
                'full_balance' => (float) $mortgage->outstanding_balance,
                'ownership_type' => $mortgage->ownership_type ?? 'individual',
                'ownership_percentage' => $mortgage->ownership_percentage ?? 100,
            ];
        }

        // Get other liabilities where user is owner OR joint_owner
        $otherLiabilities = \App\Models\Estate\Liability::forUserOrJoint($userId)
            ->get();

        foreach ($otherLiabilities as $liability) {
            // Liability model uses 'current_balance' not 'current_value'
            // Calculate user's share manually based on ownership type
            $fullBalance = (float) ($liability->current_balance ?? 0);
            $ownershipType = $liability->ownership_type ?? 'individual';

            if ($ownershipType === 'individual' || $ownershipType === 'trust') {
                // Individual ownership - 100% if owner, 0 if not
                $userShare = $liability->user_id === $userId ? $fullBalance : 0.0;
            } else {
                // Joint ownership - apply percentage
                $percentage = (float) ($liability->ownership_percentage ?? 50);
                if ($liability->user_id === $userId) {
                    $userShare = $fullBalance * ($percentage / 100);
                } elseif (($liability->joint_owner_id ?? null) === $userId) {
                    $userShare = $fullBalance * ((100 - $percentage) / 100);
                } else {
                    $userShare = 0.0;
                }
            }

            $liabilities[] = [
                'type' => ucfirst(str_replace('_', ' ', $liability->liability_type)),
                'name' => $liability->liability_name,
                'balance' => $userShare,
                'full_balance' => $fullBalance,
                'ownership_type' => $ownershipType,
                'ownership_percentage' => $liability->ownership_percentage ?? 100,
            ];
        }

        return $liabilities;
    }

    /**
     * Build balance sheet from user profile
     * Shows user, spouse, and combined balance sheets when data sharing enabled
     */
    private function buildBalanceSheet(User $user, Collection $assets, array $ihtAnalysis, ?User $spouse, Collection $spouseAssets, bool $dataSharingEnabled): array
    {
        // Group assets by type for balance sheet presentation
        $assetsByType = $assets->groupBy('asset_type');

        $balanceSheetAssets = [];

        // Property assets
        if ($assetsByType->has('property')) {
            $balanceSheetAssets['Property'] = [
                'items' => $assetsByType['property']->map(fn ($a) => [
                    'name' => $a->asset_name,
                    'value' => $a->current_value,
                ])->toArray(),
                'total' => $assetsByType['property']->sum('current_value'),
            ];
        }

        // Investment assets
        if ($assetsByType->has('investment')) {
            $balanceSheetAssets['Investments'] = [
                'items' => $assetsByType['investment']->map(fn ($a) => [
                    'name' => $a->asset_name,
                    'value' => $a->current_value,
                ])->toArray(),
                'total' => $assetsByType['investment']->sum('current_value'),
            ];
        }

        // Cash/Savings assets
        if ($assetsByType->has('cash')) {
            $balanceSheetAssets['Cash & Savings'] = [
                'items' => $assetsByType['cash']->map(fn ($a) => [
                    'name' => $a->asset_name,
                    'value' => $a->current_value,
                ])->toArray(),
                'total' => $assetsByType['cash']->sum('current_value'),
            ];
        }

        // Pension assets
        if ($assetsByType->has('pension')) {
            $balanceSheetAssets['Pensions'] = [
                'items' => $assetsByType['pension']->map(fn ($a) => [
                    'name' => $a->asset_name,
                    'value' => $a->current_value,
                ])->toArray(),
                'total' => $assetsByType['pension']->sum('current_value'),
            ];
        }

        // Business interests
        if ($assetsByType->has('business')) {
            $balanceSheetAssets['Business Interests'] = [
                'items' => $assetsByType['business']->map(fn ($a) => [
                    'name' => $a->asset_name,
                    'value' => $a->current_value,
                ])->toArray(),
                'total' => $assetsByType['business']->sum('current_value'),
            ];
        }

        // Other assets (chattels, etc.)
        $otherTypes = $assetsByType->keys()->diff(['property', 'investment', 'cash', 'pension', 'business']);
        if ($otherTypes->isNotEmpty()) {
            $otherAssets = $assets->whereIn('asset_type', $otherTypes->toArray());
            $balanceSheetAssets['Other Assets'] = [
                'items' => $otherAssets->map(fn ($a) => [
                    'name' => $a->asset_name,
                    'value' => $a->current_value,
                ])->toArray(),
                'total' => $otherAssets->sum('current_value'),
            ];
        }

        $totalAssets = $assets->sum('current_value');
        // Use user's liabilities from IHT analysis (correctly calculates ownership shares)
        $totalLiabilities = $ihtAnalysis['user_total_liabilities'] ?? 0;
        $netWorth = $totalAssets - $totalLiabilities;

        $result = [
            'as_at_date' => now()->format('d F Y'),
            'user' => [
                'name' => $user->name,
                'assets' => $balanceSheetAssets,
                'total_assets' => $totalAssets,
                'liabilities' => [
                    'total' => $totalLiabilities,
                    'breakdown' => [],
                ],
                'net_worth' => $netWorth,
                'monthly_income' => $user->net_monthly_income ?? 0,
                'monthly_expenditure' => $user->monthly_expenditure ?? 0,
                'annual_income' => $user->gross_annual_income ?? 0,
            ],
        ];

        // Add spouse balance sheet if data sharing enabled
        if ($dataSharingEnabled && $spouse && $spouseAssets->isNotEmpty()) {
            $spouseAssetsByType = $spouseAssets->groupBy('asset_type');
            $spouseBalanceSheetAssets = [];

            // Property
            if ($spouseAssetsByType->has('property')) {
                $spouseBalanceSheetAssets['Property'] = [
                    'items' => $spouseAssetsByType['property']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $spouseAssetsByType['property']->sum('current_value'),
                ];
            }

            // Investments
            if ($spouseAssetsByType->has('investment')) {
                $spouseBalanceSheetAssets['Investments'] = [
                    'items' => $spouseAssetsByType['investment']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $spouseAssetsByType['investment']->sum('current_value'),
                ];
            }

            // Cash & Savings
            if ($spouseAssetsByType->has('cash')) {
                $spouseBalanceSheetAssets['Cash & Savings'] = [
                    'items' => $spouseAssetsByType['cash']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $spouseAssetsByType['cash']->sum('current_value'),
                ];
            }

            // Pensions
            if ($spouseAssetsByType->has('pension')) {
                $spouseBalanceSheetAssets['Pensions'] = [
                    'items' => $spouseAssetsByType['pension']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $spouseAssetsByType['pension']->sum('current_value'),
                ];
            }

            // Business
            if ($spouseAssetsByType->has('business')) {
                $spouseBalanceSheetAssets['Business Interests'] = [
                    'items' => $spouseAssetsByType['business']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $spouseAssetsByType['business']->sum('current_value'),
                ];
            }

            // Other
            $spouseOtherTypes = $spouseAssetsByType->keys()->diff(['property', 'investment', 'cash', 'pension', 'business']);
            if ($spouseOtherTypes->isNotEmpty()) {
                $spouseOtherAssets = $spouseAssets->whereIn('asset_type', $spouseOtherTypes->toArray());
                $spouseBalanceSheetAssets['Other Assets'] = [
                    'items' => $spouseOtherAssets->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $spouseOtherAssets->sum('current_value'),
                ];
            }

            $spouseTotalAssets = $spouseAssets->sum('current_value');
            // Use aggregator service to correctly apply ownership share to liabilities
            $spouseLiabilities = $this->assetAggregator->calculateUserLiabilities($spouse);
            $spouseNetWorth = $spouseTotalAssets - $spouseLiabilities;

            $result['spouse'] = [
                'name' => $spouse->name,
                'assets' => $spouseBalanceSheetAssets,
                'total_assets' => $spouseTotalAssets,
                'liabilities' => [
                    'total' => $spouseLiabilities,
                    'breakdown' => [],
                ],
                'net_worth' => $spouseNetWorth,
                'monthly_income' => $spouse->net_monthly_income ?? 0,
                'monthly_expenditure' => $spouse->monthly_expenditure ?? 0,
                'annual_income' => $spouse->gross_annual_income ?? 0,
            ];

            // Combined balance sheet
            $combinedAssets = $assets->concat($spouseAssets);
            $combinedAssetsByType = $combinedAssets->groupBy('asset_type');
            $combinedBalanceSheetAssets = [];

            // Property
            if ($combinedAssetsByType->has('property')) {
                $combinedBalanceSheetAssets['Property'] = [
                    'items' => $combinedAssetsByType['property']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $combinedAssetsByType['property']->sum('current_value'),
                ];
            }

            // Investments
            if ($combinedAssetsByType->has('investment')) {
                $combinedBalanceSheetAssets['Investments'] = [
                    'items' => $combinedAssetsByType['investment']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $combinedAssetsByType['investment']->sum('current_value'),
                ];
            }

            // Cash & Savings
            if ($combinedAssetsByType->has('cash')) {
                $combinedBalanceSheetAssets['Cash & Savings'] = [
                    'items' => $combinedAssetsByType['cash']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $combinedAssetsByType['cash']->sum('current_value'),
                ];
            }

            // Pensions
            if ($combinedAssetsByType->has('pension')) {
                $combinedBalanceSheetAssets['Pensions'] = [
                    'items' => $combinedAssetsByType['pension']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $combinedAssetsByType['pension']->sum('current_value'),
                ];
            }

            // Business
            if ($combinedAssetsByType->has('business')) {
                $combinedBalanceSheetAssets['Business Interests'] = [
                    'items' => $combinedAssetsByType['business']->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $combinedAssetsByType['business']->sum('current_value'),
                ];
            }

            // Other
            $combinedOtherTypes = $combinedAssetsByType->keys()->diff(['property', 'investment', 'cash', 'pension', 'business']);
            if ($combinedOtherTypes->isNotEmpty()) {
                $combinedOtherAssets = $combinedAssets->whereIn('asset_type', $combinedOtherTypes->toArray());
                $combinedBalanceSheetAssets['Other Assets'] = [
                    'items' => $combinedOtherAssets->map(fn ($a) => [
                        'name' => $a->asset_name,
                        'value' => $a->current_value,
                    ])->toArray(),
                    'total' => $combinedOtherAssets->sum('current_value'),
                ];
            }

            $result['combined'] = [
                'assets' => $combinedBalanceSheetAssets,
                'total_assets' => $totalAssets + $spouseTotalAssets,
                'liabilities' => [
                    'total' => $totalLiabilities + $spouseLiabilities,
                    'breakdown' => [],
                ],
                'net_worth' => $netWorth + $spouseNetWorth,
                'monthly_income' => ($user->net_monthly_income ?? 0) + ($spouse->net_monthly_income ?? 0),
                'monthly_expenditure' => ($user->monthly_expenditure ?? 0) + ($spouse->monthly_expenditure ?? 0),
                'annual_income' => ($user->gross_annual_income ?? 0) + ($spouse->gross_annual_income ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Build IHT position
     * Uses second death analysis if available (married couples)
     */
    private function buildIHTPosition(array $ihtAnalysis, IHTProfile $profile, ?array $secondDeathAnalysis): array
    {
        // If we have second death analysis, show both NOW and PROJECTED scenarios
        if ($secondDeathAnalysis && isset($secondDeathAnalysis['current_iht_calculation']) && isset($secondDeathAnalysis['iht_calculation'])) {
            return [
                'has_projection' => true,

                // NOW scenario (if both die today)
                'now' => [
                    'gross_estate' => $secondDeathAnalysis['current_combined_totals']['gross_assets'],
                    'liabilities' => $secondDeathAnalysis['current_combined_totals']['total_liabilities'],
                    'net_estate' => $secondDeathAnalysis['current_combined_totals']['net_estate'],
                    'user_nrb' => $this->taxConfig->getInheritanceTax()['nil_rate_band'],
                    'spouse_nrb' => $this->taxConfig->getInheritanceTax()['nil_rate_band'],
                    'available_nrb' => $secondDeathAnalysis['current_iht_calculation']['available_nrb'] ?? 650000,
                    'user_rnrb' => $secondDeathAnalysis['current_iht_calculation']['rnrb'] ? ($secondDeathAnalysis['current_iht_calculation']['rnrb'] / 2) : 0,
                    'spouse_rnrb' => $secondDeathAnalysis['current_iht_calculation']['rnrb'] ? ($secondDeathAnalysis['current_iht_calculation']['rnrb'] / 2) : 0,
                    'rnrb' => $secondDeathAnalysis['current_iht_calculation']['rnrb'] ?? 0,
                    'total_allowances' => $secondDeathAnalysis['current_iht_calculation']['total_allowance'] ?? 650000,
                    'taxable_estate' => $secondDeathAnalysis['current_iht_calculation']['taxable_estate'] ?? 0,
                    'iht_liability' => $secondDeathAnalysis['current_iht_calculation']['iht_liability'] ?? 0,
                    'effective_rate' => $secondDeathAnalysis['current_combined_totals']['net_estate'] > 0
                        ? ($secondDeathAnalysis['current_iht_calculation']['iht_liability'] / $secondDeathAnalysis['current_combined_totals']['net_estate']) * 100
                        : 0,
                ],

                // PROJECTED scenario (at expected death age)
                'projected' => [
                    'age_at_death' => $secondDeathAnalysis['second_death']['estimated_age_at_death'],
                    'years_until_death' => $secondDeathAnalysis['second_death']['years_until_death'],
                    'gross_estate' => $secondDeathAnalysis['second_death']['projected_combined_estate_at_second_death'],
                    'liabilities' => $secondDeathAnalysis['liability_breakdown']['projected']['survivor_liabilities'] ?? 0,
                    'net_estate' => $secondDeathAnalysis['second_death']['projected_combined_estate_at_second_death'] - ($secondDeathAnalysis['liability_breakdown']['projected']['survivor_liabilities'] ?? 0),
                    'user_nrb' => $this->taxConfig->getInheritanceTax()['nil_rate_band'],
                    'spouse_nrb' => $this->taxConfig->getInheritanceTax()['nil_rate_band'],
                    'available_nrb' => $secondDeathAnalysis['iht_calculation']['available_nrb'] ?? 650000,
                    'user_rnrb' => $secondDeathAnalysis['iht_calculation']['rnrb'] ? ($secondDeathAnalysis['iht_calculation']['rnrb'] / 2) : 0,
                    'spouse_rnrb' => $secondDeathAnalysis['iht_calculation']['rnrb'] ? ($secondDeathAnalysis['iht_calculation']['rnrb'] / 2) : 0,
                    'rnrb' => $secondDeathAnalysis['iht_calculation']['rnrb'] ?? 0,
                    'total_allowances' => $secondDeathAnalysis['iht_calculation']['total_allowance'] ?? 650000,
                    'taxable_estate' => $secondDeathAnalysis['iht_calculation']['taxable_estate'] ?? 0,
                    'iht_liability' => $secondDeathAnalysis['iht_calculation']['iht_liability'] ?? 0,
                    'effective_rate' => ($secondDeathAnalysis['second_death']['projected_combined_estate_at_second_death'] > 0)
                        ? ($secondDeathAnalysis['iht_calculation']['iht_liability'] / $secondDeathAnalysis['second_death']['projected_combined_estate_at_second_death']) * 100
                        : 0,
                ],
            ];
        }

        // Single person - just show current position
        $ihtConfig = $this->taxConfig->getInheritanceTax();

        return [
            'has_projection' => false,
            'gross_estate' => $ihtAnalysis['total_net_estate'] ?? 0,
            'available_nrb' => $profile->available_nrb ?? $ihtConfig['nil_rate_band'],
            'rnrb' => $ihtAnalysis['rnrb_available'] ?? 0,
            'total_allowances' => $ihtAnalysis['total_allowances'] ?? $ihtConfig['nil_rate_band'],
            'taxable_estate' => $ihtAnalysis['taxable_estate'] ?? 0,
            'iht_liability' => $ihtAnalysis['iht_liability'] ?? 0,
            'effective_rate' => ($ihtAnalysis['total_net_estate'] ?? 0) > 0
                ? ($ihtAnalysis['iht_liability'] / $ihtAnalysis['total_net_estate']) * 100
                : 0,
        ];
    }

    /**
     * Generate optimized combined strategy
     */
    private function generateOptimizedStrategy(
        array $giftingPlan,
        array $trustPlan,
        ?array $lifePolicyPlan,
        float $currentIHTLiability,
        IHTProfile $profile
    ): array {
        $recommendations = [];
        $totalIHTSaving = 0;
        $totalCosts = 0;

        // Priority 1: Immediate actions (Annual exemption + Trust within NRB)
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $giftingConfig = $this->taxConfig->getGiftingExemptions();
        $annualExemption = (float) ($giftingConfig['annual_exemption'] ?? 3000);
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? 0.40);
        $annualExemptionIHTSaving = $annualExemption * $ihtRate;
        $availableNRB = $profile->available_nrb ?? $ihtConfig['nil_rate_band'];

        $recommendations[] = [
            'priority' => 1,
            'category' => 'Immediate Actions (Year 1)',
            'actions' => [
                [
                    'action' => 'Start using annual gifting exemption',
                    'details' => 'Gift £'.number_format($annualExemption, 0).' per year to beneficiaries using annual exemption',
                    'iht_saving' => $annualExemptionIHTSaving,
                    'cost' => 0,
                    'timeframe' => 'Annual',
                ],
                [
                    'action' => 'Establish discretionary trust within NRB',
                    'details' => 'Transfer £'.number_format($availableNRB, 0).' to discretionary trust',
                    'iht_saving' => $availableNRB * $ihtRate,
                    'cost' => 0,
                    'timeframe' => 'Once-off (Year 1)',
                ],
            ],
        ];

        $totalIHTSaving += $annualExemptionIHTSaving + ($availableNRB * $ihtRate);

        // Priority 2: Medium-term strategy (PET cycles)
        if ($giftingPlan['summary']['total_gifted'] > 0) {
            $recommendations[] = [
                'priority' => 2,
                'category' => 'Medium-term Gifting (Years 1-7)',
                'actions' => [
                    [
                        'action' => 'Implement PET gifting cycles',
                        'details' => 'Gift liquid assets totaling £'.number_format($giftingPlan['summary']['total_gifted'], 0).' over 7 years',
                        'iht_saving' => $giftingPlan['summary']['total_iht_saved'],
                        'cost' => 0,
                        'timeframe' => '7 years',
                    ],
                ],
            ];
            $totalIHTSaving += $giftingPlan['summary']['total_iht_saved'];
        }

        // Priority 3: Life insurance for remaining liability
        if ($lifePolicyPlan) {
            $remainingLiability = max(0, $currentIHTLiability - $totalIHTSaving);
            if ($remainingLiability > 10000) {
                $recommendations[] = [
                    'priority' => 3,
                    'category' => 'Life Insurance Protection',
                    'actions' => [
                        [
                            'action' => 'Establish Whole of Life policy in trust',
                            'details' => 'Sum assured: £'.number_format($remainingLiability, 0).' | Premium: £'.number_format($lifePolicyPlan['estimated_monthly_premium'], 2).'/month',
                            'iht_saving' => 0, // Doesn't reduce IHT, but covers the cost
                            'cost' => $lifePolicyPlan['estimated_monthly_premium'] * 12,
                            'timeframe' => 'Ongoing',
                        ],
                    ],
                ];
                $totalCosts += $lifePolicyPlan['estimated_monthly_premium'] * 12;
            }
        }

        // Priority 4: Property planning (if applicable)
        $propertyStrategy = collect($trustPlan['strategies'])->firstWhere('strategy_name', 'Property Trust Planning');
        if ($propertyStrategy && isset($propertyStrategy['applicable']) && $propertyStrategy['applicable']) {
            $recommendations[] = [
                'priority' => 4,
                'category' => 'Long-term Property Planning',
                'actions' => [
                    [
                        'action' => 'Plan for downsizing main residence',
                        'details' => 'When dependants leave home, consider downsizing to release equity for gifting',
                        'iht_saving' => 'Variable',
                        'cost' => 0,
                        'timeframe' => 'Future (when appropriate)',
                    ],
                ],
            ];
        }

        return [
            'strategy_name' => 'Optimized Combined Estate Plan',
            'recommendations' => $recommendations,
            'summary' => [
                'current_iht_liability' => $currentIHTLiability,
                'total_iht_saving' => $totalIHTSaving,
                'remaining_liability' => max(0, $currentIHTLiability - $totalIHTSaving),
                'annual_costs' => $totalCosts,
                'net_benefit' => $totalIHTSaving - $totalCosts,
                'effectiveness_percentage' => $currentIHTLiability > 0 ? ($totalIHTSaving / $currentIHTLiability) * 100 : 0,
            ],
        ];
    }

    /**
     * Generate completeness warning
     */
    private function generateCompletenessWarning(?array $profileCompleteness): ?array
    {
        if (! $profileCompleteness || $profileCompleteness['is_complete']) {
            return null;
        }

        $score = $profileCompleteness['completeness_score'];
        $missingFields = $profileCompleteness['missing_fields'] ?? [];

        // Determine severity
        $severity = match (true) {
            $score < 50 => 'critical',
            $score < 100 => 'warning',
            default => 'success',
        };

        // Build disclaimer text
        $disclaimer = match ($severity) {
            'critical' => 'This estate plan is highly generic due to incomplete profile information. Key data is missing, which significantly limits the accuracy of IHT calculations and personalization of recommendations. Please complete your profile to receive a comprehensive and tailored estate strategy.',
            'warning' => 'This estate plan is partially generic as some profile information is incomplete. Completing the missing fields will enable more accurate IHT calculations and personalized recommendations.',
            default => 'Your profile is complete. This estate plan is fully personalized based on your circumstances.',
        };

        // Extract top priority missing fields
        $topMissingFields = [];
        foreach ($missingFields as $key => $field) {
            if ($field['priority'] === 'high' && $field['required']) {
                $topMissingFields[] = [
                    'field' => $key,
                    'message' => $field['message'],
                    'link' => $field['link'],
                ];
            }
        }

        return [
            'score' => $score,
            'severity' => $severity,
            'disclaimer' => $disclaimer,
            'missing_fields' => $topMissingFields,
            'recommendations' => $profileCompleteness['recommendations'] ?? [],
        ];
    }

    /**
     * Generate executive summary
     */
    private function generateExecutiveSummary(
        User $user,
        array $ihtAnalysis,
        array $optimizedStrategy,
        ?array $profileCompleteness,
        float $currentIHTLiability,
        ?float $projectedIHTLiability,
        ?array $secondDeathAnalysis
    ): array {
        // Extract key actions as list instead of count
        $keyActions = [];
        foreach ($optimizedStrategy['recommendations'] as $rec) {
            if ($rec['priority'] <= 2) { // Only priority 1 and 2 actions
                foreach ($rec['actions'] as $action) {
                    $keyActions[] = $action['action'];
                }
            }
        }

        // Calculate potential IHT saving more accurately
        // The saving is from implementing the recommended strategies
        $potentialSaving = $optimizedStrategy['summary']['total_iht_saving'];

        // For married couples: use projected liability as the baseline for calculating savings
        // (since that's the future liability we're trying to reduce)
        if ($projectedIHTLiability && $projectedIHTLiability > $currentIHTLiability) {
            // Don't cap the savings - strategies could save more than current liability
            // The actual saving amount is already calculated in the optimized strategy
            // Just ensure we're not showing a negative number
            $potentialSaving = max(0, $optimizedStrategy['summary']['total_iht_saving']);
        }

        return [
            'title' => 'Estate Planning Report for '.$user->name,
            'current_position' => [
                'net_estate' => $ihtAnalysis['total_net_estate'] ?? 0,
                'iht_liability' => $currentIHTLiability, // Current IHT liability (NOW)
            ],
            'iht_liabilities' => [
                'current' => $currentIHTLiability, // If die now
                'projected' => $projectedIHTLiability, // If die at projected age (married couples only)
                'projected_age' => $secondDeathAnalysis['second_death']['estimated_age_at_death'] ?? null,
            ],
            'recommended_strategy' => $optimizedStrategy['strategy_name'],
            'potential_saving' => $potentialSaving,
            'annual_cost' => $optimizedStrategy['summary']['annual_costs'],
            'key_actions' => $keyActions, // Array of action strings, not count
        ];
    }

    /**
     * Build implementation timeline
     */
    private function buildImplementationTimeline(array $optimizedStrategy): array
    {
        $timeline = [];

        foreach ($optimizedStrategy['recommendations'] as $rec) {
            foreach ($rec['actions'] as $action) {
                $timeline[] = [
                    'priority' => $rec['priority'],
                    'category' => $rec['category'],
                    'action' => $action['action'],
                    'timeframe' => $action['timeframe'],
                    'iht_saving' => is_numeric($action['iht_saving']) ? $action['iht_saving'] : 0,
                ];
            }
        }

        return $timeline;
    }

    /**
     * Generate next steps
     */
    private function generateNextSteps(array $optimizedStrategy, ?array $profileCompleteness): array
    {
        $steps = [
            'Immediate (Within 1 month)' => [],
            'Short-term (1-6 months)' => [],
            'Medium-term (6-12 months)' => [],
            'Long-term (12+ months)' => [],
        ];

        // Add profile completeness steps if incomplete
        if ($profileCompleteness && ! $profileCompleteness['is_complete']) {
            $completenessScore = $profileCompleteness['completeness_score'];

            if ($completenessScore < 70) {
                $steps['Immediate (Within 1 month)'][] = '⚠️ PRIORITY: Complete your profile information for accurate estate planning';

                // Add specific missing fields
                $missingFields = $profileCompleteness['missing_fields'] ?? [];
                foreach ($missingFields as $key => $field) {
                    if ($field['priority'] === 'high' && $field['required']) {
                        $steps['Immediate (Within 1 month)'][] = '  → '.$field['message'];
                    }
                }
            }
        }

        // Categorize actions by timeframe
        foreach ($optimizedStrategy['recommendations'] as $rec) {
            if ($rec['priority'] === 1) {
                $steps['Immediate (Within 1 month)'][] = $rec['category'];
            } elseif ($rec['priority'] === 2) {
                $steps['Short-term (1-6 months)'][] = $rec['category'];
            } elseif ($rec['priority'] === 3) {
                $steps['Medium-term (6-12 months)'][] = $rec['category'];
            } else {
                $steps['Long-term (12+ months)'][] = $rec['category'];
            }
        }

        return $steps;
    }

    /**
     * Build life events impact section for the estate plan.
     *
     * Lists upcoming life events with estate implications, projected IHT impact,
     * and recommendations for estate plan review triggers.
     */
    private function buildLifeEventsImpact(User $user, float $currentIHTLiability, array $ihtAnalysis): array
    {
        $events = $this->lifeEventIntegration->getEventsForModule($user->id, 'estate');
        $impactSummary = $this->lifeEventIntegration->getModuleImpactSummary($user->id, 'estate');

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $giftingConfig = $this->taxConfig->getGiftingExemptions();
        $ihtRate = $ihtConfig['standard_rate'];
        $annualGiftExemption = (float) ($giftingConfig['annual_exemption'] ?? 3000);
        $totalAllowances = ($ihtAnalysis['total_allowances'] ?? 0);
        $currentNetEstate = ($ihtAnalysis['total_net_estate'] ?? 0);

        $eventImpacts = [];
        $reviewTriggers = [];

        foreach ($events as $event) {
            $amount = (float) $event['amount'];
            $isIncome = $event['impact_type'] === 'income';

            // Calculate projected IHT impact of this event
            $estateAfterEvent = $isIncome
                ? $currentNetEstate + $amount
                : $currentNetEstate - $amount;

            $taxableAfterEvent = max(0, $estateAfterEvent - $totalAllowances);
            $ihtAfterEvent = $taxableAfterEvent * $ihtRate;
            $ihtChange = $ihtAfterEvent - $currentIHTLiability;

            $eventImpact = [
                'event_name' => $event['event_name'],
                'event_type' => $event['event_type'],
                'amount' => $amount,
                'impact_type' => $event['impact_type'],
                'expected_date' => $event['expected_date'],
                'certainty' => $event['certainty'],
                'module_context' => $event['module_context'],
                'projected_iht_change' => round($ihtChange, 2),
                'projected_iht_after_event' => round($ihtAfterEvent, 2),
            ];

            $eventImpacts[] = $eventImpact;

            // Flag events that should trigger an estate plan review
            if ($isIncome && $amount >= 50000) {
                $reviewTriggers[] = [
                    'event_name' => $event['event_name'],
                    'reason' => 'Large incoming amount of £'.number_format($amount).' will increase your taxable estate',
                    'recommendation' => $ihtChange > 0
                        ? 'Consider a gifting strategy to mitigate the additional £'.number_format(abs($ihtChange)).' Inheritance Tax liability'
                        : 'Review your estate plan to ensure the additional funds are efficiently allocated',
                    'priority' => $ihtChange > 10000 ? 'high' : 'medium',
                ];
            } elseif (! $isIncome && $event['event_type'] === 'gift_given' && $amount >= $annualGiftExemption) {
                $reviewTriggers[] = [
                    'event_name' => $event['event_name'],
                    'reason' => 'Planned gift of £'.number_format($amount).' is a Potentially Exempt Transfer',
                    'recommendation' => 'Ensure this gift is recorded for Inheritance Tax purposes. It will become exempt after 7 years.',
                    'priority' => 'medium',
                ];
            }
        }

        return [
            'has_events' => count($eventImpacts) > 0,
            'event_count' => count($eventImpacts),
            'events' => $eventImpacts,
            'summary' => [
                'total_incoming' => $impactSummary['upcoming_income'],
                'total_outgoing' => $impactSummary['upcoming_expense'],
                'net_estate_impact' => $impactSummary['net_impact'],
            ],
            'review_triggers' => $reviewTriggers,
            'next_event' => $impactSummary['next_event'],
        ];
    }

    /**
     * Invalidate estate plan cache for a user.
     */
    public function invalidateCache(User $user): void
    {
        $this->ihtCalculationService->invalidateCache($user);
    }
}
