<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Constants\QuerySchemas;
use App\Constants\TaxDefaults;
use App\Models\User;
use App\Services\AI\Prompts\ComplianceRules;
use App\Services\AI\Prompts\CoreIdentity;
use App\Services\AI\Prompts\FcaProcessInstructions;
use App\Services\AI\Prompts\QueryKnowledge;
use App\Services\PrerequisiteGateService;
use App\Services\TaxConfigService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Assembles the AI system prompt from 10 composable layers.
 *
 * Layers:
 *  1. Core Identity (STATIC)          — identity, security, scope, personality, response format
 *  2. Compliance & Rules (STATIC)     — FCA compliance, hedging, acronyms, no-icons, joint ownership
 *  3. FCA Process Instructions (STATIC) — 6-step process, tool usage, data creation guidance
 *  4. User Profile (DYNAMIC/user)     — name, age, income, employment, family
 *  5. Financial Position (DYNAMIC/user) — net worth, module metrics, recommendations
 *  6. Existing Records (DYNAMIC/query) — record summaries for duplicate detection and referencing
 *  7. Data Completeness (DYNAMIC/user) — prerequisite gate status, navigation rules
 *  8. Query Knowledge (DYNAMIC/query) — per-domain knowledge retrieval (Phase 3)
 *  9. KYC Check Result (DYNAMIC/query) — KYC gate status (Phase 2)
 * 10. Context & Tools (DYNAMIC/msg)   — current page context
 */
class SystemPromptBuilder
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly PrerequisiteGateService $prerequisiteGate,
    ) {}

    /**
     * Build the complete system prompt.
     *
     * @param  User  $user  The authenticated user
     * @param  array|null  $classification  Query classification result (Phase 2)
     * @param  array|null  $kycResult  KYC gate check result (Phase 2)
     * @param  string|null  $currentRoute  Current page route
     * @param  bool  $isPreview  Whether user is in preview mode
     * @param  callable|null  $orchestrateAnalysis  Function to call orchestrateAnalysis (injected from CoordinatingAgent)
     */
    public function build(
        User $user,
        ?array $classification = null,
        ?array $kycResult = null,
        ?string $currentRoute = null,
        bool $isPreview = false,
        ?callable $orchestrateAnalysis = null,
    ): string {
        $nameParts = explode(' ', $user->name);
        $firstName = $nameParts[0] ?? 'there';

        $layers = [];

        // Layer 1: Core Identity (STATIC)
        $layers[] = CoreIdentity::get($firstName);

        // Layer 2: Compliance & Rules (STATIC, tax year dynamic)
        $taxYear = $this->taxConfig->getTaxYear() ?? '2026/27';
        $layers[] = ComplianceRules::get($taxYear);

        // Layer 3: FCA Process Instructions (STATIC)
        $layers[] = FcaProcessInstructions::get($isPreview);

        // Layer 4: User Profile (DYNAMIC/user)
        $profile = $this->buildUserProfile($user);
        $layers[] = "<user_profile>\n{$profile}\n</user_profile>";

        // Layer 5: Financial Position (DYNAMIC/user) — recommendations filtered by classification
        $financialContext = $this->buildFinancialContext($user, $orchestrateAnalysis, $classification);
        $layers[] = "<financial_context>\n{$financialContext}\n</financial_context>";

        // Layer 6: Existing Records (DYNAMIC/query) — filtered by classification
        $existingRecords = $this->buildExistingRecordsSummary($user, $classification);
        $layers[] = "<existing_records>\n{$existingRecords}\n</existing_records>";

        // Layer 7: Data Completeness (DYNAMIC/user)
        $prerequisiteState = $this->buildPrerequisiteStateContext($user);
        $layers[] = $this->buildDataCompletenessBlock($prerequisiteState);

        // Layer 7b: Review Due (DYNAMIC/user)
        $reviewBlock = $this->buildReviewDueBlock($user);
        if ($reviewBlock !== '') {
            $layers[] = $reviewBlock;
        }

        // Layer 8: Query Knowledge (DYNAMIC/query)
        $knowledgeBlock = $this->buildKnowledgeBlock($classification);
        if ($knowledgeBlock !== '') {
            $layers[] = $knowledgeBlock;
        }

        // Layer 8b: Required Tools + Relevant Triggers (DYNAMIC/query)
        $toolsAndTriggers = $this->buildToolsAndTriggersBlock($classification);
        if ($toolsAndTriggers !== '') {
            $layers[] = $toolsAndTriggers;
        }

        // Layer 9: KYC Check Result (DYNAMIC/query)
        if ($kycResult !== null && isset($kycResult['prompt_text']) && $kycResult['prompt_text'] !== '') {
            $layers[] = $kycResult['prompt_text'];
        }

        // Layer 10: Context & Tools (DYNAMIC/msg)
        $moduleContext = $this->getModuleContext($currentRoute);
        if ($moduleContext) {
            $layers[] = "<current_context>\n{$moduleContext}\n</current_context>";
        }

        return implode("\n\n", $layers);
    }

    // ─── Layer 4: User Profile ───────────────────────────────────────

    public function buildUserProfile(User $user): string
    {
        $lines = [];
        $firstName = $user->first_name ?? explode(' ', $user->name)[0] ?? 'User';
        $lines[] = "- Name: {$firstName}";

        if ($user->date_of_birth) {
            $lines[] = "- Age: {$user->date_of_birth->age}";
        }

        if ($user->employment_status) {
            $lines[] = "- Employment: {$user->employment_status}";
        }

        if ($user->marital_status) {
            $lines[] = "- Marital status: {$user->marital_status}";
        }

        $totalIncome = $this->calculateTotalUserIncome($user);
        if ($totalIncome > 0) {
            $formatted = number_format($totalIncome, 2);
            $lines[] = "- Total annual income: £{$formatted}";

            $taxBand = $this->estimateTaxBand($totalIncome);
            $lines[] = "- Estimated income tax band: {$taxBand}";

            $incomeTypes = [
                'Employment (PAYE)' => (float) ($user->annual_employment_income ?? 0),
                'Self-employment' => (float) ($user->annual_self_employment_income ?? 0),
                'Rental (property)' => (float) ($user->annual_rental_income ?? 0),
                'Dividend' => (float) ($user->annual_dividend_income ?? 0),
                'Savings interest' => (float) ($user->annual_interest_income ?? 0),
                'Trust' => (float) ($user->annual_trust_income ?? 0),
            ];
            $nonZero = array_filter($incomeTypes, fn ($v) => $v > 0);
            if (count($nonZero) > 1 || (count($nonZero) === 1 && ! isset($nonZero['Employment (PAYE)']))) {
                $lines[] = '- Income breakdown:';
                foreach ($nonZero as $type => $amount) {
                    $label = in_array($type, ['Employment (PAYE)', 'Self-employment'])
                        ? "{$type} [relevant UK earnings]"
                        : "{$type} [not relevant UK earnings]";
                    $lines[] = "  - {$label}: £".number_format($amount, 2);
                }
            }
        }

        $totalExpenditure = $this->calculateTotalExpenditure($user);
        if ($totalExpenditure > 0) {
            $formatted = number_format($totalExpenditure, 2);
            $lines[] = "- Monthly expenditure: £{$formatted}";
        }

        $spouse = $user->spouse;
        if ($spouse) {
            $spouseExpenditure = $this->calculateTotalExpenditure($spouse);
            if ($spouseExpenditure > 0) {
                $lines[] = '- Spouse monthly expenditure: £'.number_format($spouseExpenditure, 2);
            }
            if ($totalExpenditure > 0 && $spouseExpenditure > 0) {
                $lines[] = '- Combined household expenditure: £'.number_format($totalExpenditure + $spouseExpenditure, 2);
            }
        }

        if ($user->retirement_date) {
            $lines[] = "- Target retirement date: {$user->retirement_date->format('j F Y')}";
        } elseif ($user->target_retirement_age) {
            $lines[] = "- Target retirement age: {$user->target_retirement_age}";
        } elseif ($user->retirementProfile && $user->retirementProfile->target_retirement_age) {
            $lines[] = "- Target retirement age: {$user->retirementProfile->target_retirement_age}";
        }

        // Family members — names and ages so Fyn can reference them naturally
        $familyLines = [];

        $spouse = $user->spouse;
        if ($spouse) {
            $spouseName = $spouse->first_name ?? explode(' ', $spouse->name)[0] ?? 'Spouse';
            $spouseAge = $spouse->date_of_birth ? $spouse->date_of_birth->age : null;
            $familyLines[] = $spouseAge
                ? "  - Spouse: {$spouseName} (age {$spouseAge})"
                : "  - Spouse: {$spouseName}";
        }

        $familyMembers = $user->familyMembers()->orderBy('date_of_birth')->get();
        foreach ($familyMembers as $member) {
            $memberName = $member->first_name ?? 'Unknown';
            $memberAge = $member->date_of_birth ? now()->diffInYears($member->date_of_birth) : null;
            $relationship = ucfirst($member->relationship ?? 'family member');
            $familyLines[] = $memberAge
                ? "  - {$relationship}: {$memberName} (age {$memberAge})"
                : "  - {$relationship}: {$memberName}";
        }

        if (! empty($familyLines)) {
            $lines[] = '- Family:';
            $lines = array_merge($lines, $familyLines);
        }

        return implode("\n", $lines);
    }

    // ─── Layer 5: Financial Context ──────────────────────────────────

    public function buildFinancialContext(User $user, ?callable $orchestrateAnalysis = null, ?array $classification = null): string
    {
        return Cache::remember("ai_financial_context_{$user->id}", 120, function () use ($user, $orchestrateAnalysis, $classification) {
            if (! $orchestrateAnalysis) {
                return 'Financial context unavailable — analysis service not provided.';
            }

            try {
                $analysis = $orchestrateAnalysis($user->id);
            } catch (\Exception $e) {
                Log::warning('[SystemPromptBuilder] Failed to build financial context', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return 'Financial context unavailable — analysis could not be completed.';
            }

            $lines = [];
            $modules = $analysis['module_analysis'] ?? [];

            // Net worth from dedicated service
            try {
                $netWorthService = app(\App\Services\NetWorth\NetWorthService::class);
                $netWorthData = $netWorthService->calculateNetWorth($user);
                $lines[] = '- Total net worth: £'.number_format($netWorthData['net_worth'], 0);
                $lines[] = '- Total assets: £'.number_format($netWorthData['total_assets'], 0);
                $lines[] = '- Total liabilities: £'.number_format($netWorthData['total_liabilities'], 0);
            } catch (\Exception $e) {
                // Fall through — individual modules below will provide partial data
            }

            // Available surplus
            $surplus = $analysis['available_surplus'] ?? 0;
            if ($surplus !== 0) {
                $formatted = number_format(abs($surplus), 2);
                $label = $surplus >= 0 ? 'Monthly surplus' : 'Monthly shortfall';
                $lines[] = "- {$label}: £{$formatted}";
            }

            // Savings
            if (isset($modules['savings'])) {
                $s = $modules['savings'];
                if (($s['total_savings'] ?? 0) > 0) {
                    $lines[] = '- Total savings: £'.number_format($s['total_savings'], 2);
                }
                if (isset($s['emergency_fund_months'])) {
                    $lines[] = "- Emergency fund: {$s['emergency_fund_months']} months of cover";
                }
            }

            // Investments
            if (isset($modules['investment'])) {
                $inv = $modules['investment'];
                if (($inv['total_portfolio_value'] ?? 0) > 0) {
                    $lines[] = '- Investment portfolio: £'.number_format($inv['total_portfolio_value'], 0);
                }
            }

            // Retirement
            if (isset($modules['retirement'])) {
                $ret = $modules['retirement'];
                if (($ret['total_pension_value'] ?? 0) > 0) {
                    $lines[] = '- Total pension value: £'.number_format($ret['total_pension_value'], 0);
                }
                if (($ret['projected_annual_income'] ?? 0) > 0) {
                    $lines[] = '- Projected retirement income: £'.number_format($ret['projected_annual_income'], 0).' per year';
                }
                if (($ret['income_gap'] ?? 0) > 0) {
                    $lines[] = '- Retirement income gap: £'.number_format($ret['income_gap'], 0).' per year';
                }
            }

            // Protection
            if (isset($modules['protection'])) {
                $prot = $modules['protection'];
                if (($prot['full_analysis']['total_cover'] ?? 0) > 0) {
                    $lines[] = '- Total life cover: £'.number_format($prot['full_analysis']['total_cover'], 0);
                }
                if (($prot['coverage_gap'] ?? 0) > 0) {
                    $lines[] = '- Coverage gap: £'.number_format($prot['coverage_gap'], 0);
                }
            }

            // Property
            $ownsProperty = \App\Models\Property::forUserOrJoint($user->id)->exists();
            $lines[] = '- Property owner: '.($ownsProperty ? 'Yes' : 'No');

            // Estate (IHT-specific)
            if (isset($modules['estate'])) {
                $est = $modules['estate'];
                if (($est['iht_liability'] ?? 0) > 0) {
                    $lines[] = '- Estimated Inheritance Tax liability: £'.number_format($est['iht_liability'], 0);
                }
            }

            // Goals
            $activeGoals = \App\Models\Goal::forUserOrJoint($user->id)
                ->where('status', 'active')
                ->orderBy('priority')
                ->get();

            if ($activeGoals->isNotEmpty()) {
                $onTrack = $activeGoals->filter(fn ($g) => $g->is_on_track)->count();
                $lines[] = '';
                $lines[] = "Goals: {$activeGoals->count()} active ({$onTrack} on track)";
                foreach ($activeGoals as $goal) {
                    $remaining = max(0, (float) $goal->target_amount - (float) $goal->current_amount);
                    $status = $goal->is_on_track ? 'on track' : 'behind';
                    $contribution = $goal->monthly_contribution ? ' — £'.number_format((float) $goal->monthly_contribution, 0).'/month' : '';
                    $lines[] = "  [ID:{$goal->id}] {$goal->goal_name}: £".number_format((float) $goal->current_amount, 0)
                        .' of £'.number_format((float) $goal->target_amount, 0)
                        ." ({$status}){$contribution}"
                        .($goal->target_date ? ' — target: '.$goal->target_date->format('M Y') : '');
                }
            } else {
                $lines[] = '- Goals: None set';
            }

            // Life Events
            $activeEvents = \App\Models\LifeEvent::forUserOrJoint($user->id)
                ->active()
                ->orderBy('expected_date')
                ->get();

            if ($activeEvents->isNotEmpty()) {
                $lines[] = '';
                $lines[] = "Life Events: {$activeEvents->count()} upcoming";
                foreach ($activeEvents->take(10) as $event) {
                    $monthsUntil = max(0, (int) now()->diffInMonths($event->expected_date));
                    $sign = $event->impact_type === 'income' ? '+' : '-';
                    $lines[] = "  [ID:{$event->id}] {$event->event_name}: {$sign}£".number_format((float) $event->amount, 0)
                        ." — in {$monthsUntil} months ({$event->certainty})";
                }
            }

            // Ranked recommendations with decision traces — filtered by classification
            $recommendations = $analysis['ranked_recommendations'] ?? [];
            if ($classification !== null && ! empty($recommendations)) {
                $relevantModules = QuerySchemas::getModulesForClassification($classification);
                if (! empty($relevantModules)) {
                    $recommendations = array_filter($recommendations, function ($rec) use ($relevantModules) {
                        $recModule = $rec['module'] ?? '';

                        return $recModule === '' || in_array($recModule, $relevantModules, true);
                    });
                    $recommendations = array_values($recommendations);
                }
            }
            if (! empty($recommendations)) {
                $top = array_slice($recommendations, 0, 8);
                $lines[] = '';
                $lines[] = 'Top ranked recommendations (from decision engine):';
                foreach ($top as $i => $rec) {
                    $title = $rec['title'] ?? $rec['recommendation'] ?? 'Recommendation';
                    $urgency = isset($rec['urgency_score']) ? " (urgency: {$rec['urgency_score']}/100)" : '';
                    $module = isset($rec['module']) ? " [{$rec['module']}]" : '';
                    $num = $i + 1;
                    $lines[] = "{$num}. {$title}{$module}{$urgency}";

                    // Include description for actionable context
                    if (isset($rec['description']) && $rec['description']) {
                        $desc = mb_substr($rec['description'], 0, 200);
                        $lines[] = "   {$desc}";
                    }

                    // Include estimated saving if available
                    if (isset($rec['estimated_saving']) && $rec['estimated_saving'] > 0) {
                        $lines[] = '   Estimated saving: £'.number_format((float) $rec['estimated_saving'], 0);
                    }

                    // Include action step
                    if (isset($rec['action']) && $rec['action']) {
                        $action = mb_substr($rec['action'], 0, 150);
                        $lines[] = "   Action: {$action}";
                    }

                    if (isset($rec['decision_trace'])) {
                        $trace = $rec['decision_trace'];
                        $trigger = $trace['trigger'] ?? $trace['definition_key'] ?? null;
                        if ($trigger) {
                            $lines[] = "   Triggered by: {$trigger}";
                        }
                    }
                }
            }

            // Cashflow allocation
            $cashflow = $analysis['cashflow_allocation'] ?? [];
            if (! empty($cashflow) && isset($cashflow['total_demand'])) {
                $lines[] = '';
                $totalDemand = number_format($cashflow['total_demand'], 2);
                $lines[] = "Cashflow: Total monthly demand £{$totalDemand} vs surplus £".number_format(abs($surplus), 2);
            }

            // Shortfall analysis
            $shortfall = $analysis['shortfall_analysis'] ?? [];
            if ($shortfall['has_shortfall'] ?? false) {
                $lines[] = 'Cashflow shortfall detected — not all recommendations can be fully funded';
            }

            // Conflicts
            $conflicts = $analysis['conflicts'] ?? [];
            if (! empty($conflicts)) {
                $lines[] = '';
                $lines[] = 'Active conflicts:';
                foreach (array_slice($conflicts, 0, 3) as $conflict) {
                    $type = $conflict['type'] ?? 'Unknown';
                    $lines[] = "- {$type}";
                }
            }

            // Cross-module strategies
            $strategies = $analysis['cross_module_strategies'] ?? [];
            if (! empty($strategies)) {
                $lines[] = '';
                $lines[] = 'Cross-module strategies:';
                foreach (array_slice($strategies, 0, 3) as $strategy) {
                    $title = $strategy['title'] ?? $strategy['strategy'] ?? '';
                    if ($title) {
                        $lines[] = "- {$title}";
                    }
                }
            }

            // Life event impact summaries
            try {
                $integrationService = app(\App\Services\Goals\LifeEventIntegrationService::class);
                $impactModules = ['savings', 'investment', 'retirement', 'protection', 'estate'];
                $lifeEventImpacts = [];

                foreach ($impactModules as $module) {
                    $impact = $integrationService->getModuleImpactSummary($user->id, $module);
                    if ($impact['event_count'] > 0) {
                        $lifeEventImpacts[$module] = $impact;
                    }
                }

                if (! empty($lifeEventImpacts)) {
                    $lines[] = '';
                    $lines[] = 'LIFE EVENT IMPACTS BY MODULE:';
                    foreach ($lifeEventImpacts as $module => $impact) {
                        $sign = $impact['net_impact'] >= 0 ? '+' : '-';
                        $amount = number_format(abs($impact['net_impact']), 0);
                        $line = "- {$module}: {$impact['event_count']} upcoming events, net impact {$sign}£{$amount}";
                        if (isset($impact['next_event'])) {
                            $line .= " (next: {$impact['next_event']['event_name']} in {$impact['next_event']['months_until']} months)";
                        }
                        $lines[] = $line;
                    }
                }
            } catch (\Exception $e) {
                // Don't fail AI context building if life event enrichment fails
            }

            return ! empty($lines) ? implode("\n", $lines) : 'No financial data recorded yet.';
        });
    }

    // ─── Layer 6: Existing Records ───────────────────────────────────

    public function buildExistingRecordsSummary(User $user, ?array $classification = null): string
    {
        return Cache::remember("ai_existing_records_{$user->id}", 60, function () use ($user, $classification) {
            $lines = [];
            $userId = $user->id;

            // Determine which record types to include based on classification
            $relevantTypes = $this->getRelevantRecordTypes($classification);
            $include = fn (string $type) => $relevantTypes === null || in_array($type, $relevantTypes, true);

            // Helper to format ownership label from the record's own fields
            $ownershipLabel = function ($record) use ($userId) {
                $type = $record->ownership_type ?? 'individual';
                if ($type === 'individual') {
                    return '';
                }

                $pct = (int) ($record->user_id === $userId
                    ? ($record->ownership_percentage ?? 100)
                    : (100 - ($record->ownership_percentage ?? 100)));
                $otherPct = 100 - $pct;

                // Use the name stored on the record (joint_owner_name), or fall back to linked user
                $coOwnerName = $record->joint_owner_name
                    ?? ($record->jointOwner?->first_name)
                    ?? null;

                return $coOwnerName
                    ? " {$type} with {$coOwnerName}({$pct}%/{$otherPct}%)"
                    : " {$type}({$pct}%/{$otherPct}%)";
            };

            // Helper to format value with user's share for joint assets
            $valueWithShare = function ($record, float $totalValue) use ($userId) {
                $type = $record->ownership_type ?? 'individual';
                if ($type === 'individual') {
                    return '£'.number_format($totalValue, 0);
                }
                $userPct = (int) ($record->user_id === $userId
                    ? ($record->ownership_percentage ?? 100)
                    : (100 - ($record->ownership_percentage ?? 100)));
                $userValue = $totalValue * ($userPct / 100);

                return 'total:£'.number_format($totalValue, 0).' your-share:£'.number_format($userValue, 0);
            };

            // Savings
            if ($include('savings_account')) {
                $savings = \App\Models\SavingsAccount::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
                if ($savings->isNotEmpty()) {
                    $items = $savings->map(fn ($a) => "[ID:{$a->id} \"{$a->account_name}\" at {$a->institution}".($a->is_isa ? ' ISA(tax-free)' : '').$ownershipLabel($a).' '.$valueWithShare($a, (float) $a->current_balance).']')->implode(' ');
                    $lines[] = "SAVINGS: {$items}";
                }
            }

            // Investments
            if ($include('investment_account')) {
                $investments = \App\Models\Investment\InvestmentAccount::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
                if ($investments->isNotEmpty()) {
                    $items = $investments->map(fn ($a) => "[ID:{$a->id} \"{$a->provider}\" ".$this->formatInvestmentAccountType($a->account_type).$ownershipLabel($a).' '.$valueWithShare($a, (float) $a->current_value).']')->implode(' ');
                    $lines[] = "INVESTMENTS: {$items}";
                }
            }

            // DC Pensions
            if ($include('dc_pension')) {
                $dcPensions = \App\Models\DCPension::where('user_id', $userId)->get();
                if ($dcPensions->isNotEmpty()) {
                    $items = $dcPensions->map(fn ($p) => "[ID:{$p->id} \"{$p->scheme_name}\" {$p->pension_type} £".number_format((float) $p->current_fund_value, 0).']')->implode(' ');
                    $lines[] = "DC PENSIONS: {$items}";
                }
            }

            // DB Pensions
            if ($include('db_pension')) {
                $dbPensions = \App\Models\DBPension::where('user_id', $userId)->get();
                if ($dbPensions->isNotEmpty()) {
                    $items = $dbPensions->map(fn ($p) => "[ID:{$p->id} \"{$p->scheme_name}\" £".number_format((float) ($p->accrued_annual_pension ?? 0), 0).'/yr]')->implode(' ');
                    $lines[] = "DB PENSIONS: {$items}";
                }
            }

            // Properties — show total value, user's share, mortgage, and ownership with co-owner name
            if ($include('property') || $include('mortgage')) {
                $properties = \App\Models\Property::with('mortgages')->where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
                if ($properties->isNotEmpty()) {
                    $items = $properties->map(function ($p) use ($userId, $ownershipLabel) {
                        $totalValue = (float) $p->current_value;
                        $userPct = ($p->ownership_type !== 'individual')
                            ? (int) ($p->user_id === $userId ? ($p->ownership_percentage ?? 100) : (100 - ($p->ownership_percentage ?? 100)))
                            : 100;
                        $userValue = $totalValue * ($userPct / 100);

                        $mortgageTotal = (float) $p->mortgages->sum('outstanding_balance');
                        $userMortgage = $mortgageTotal * ($userPct / 100);

                        $valueLabel = $userPct < 100
                            ? ' total:£'.number_format($totalValue, 0).' your-share:£'.number_format($userValue, 0)
                            : ' £'.number_format($totalValue, 0);
                        $mortgageLabel = $mortgageTotal > 0
                            ? ($userPct < 100
                                ? ' mortgage-total:£'.number_format($mortgageTotal, 0).' your-mortgage:£'.number_format($userMortgage, 0)
                                : ' mortgage:£'.number_format($mortgageTotal, 0))
                            : '';
                        $rentalTotal = (float) ($p->monthly_rental_income ?? 0);
                        $rentalLabel = $p->property_type === 'buy_to_let' && $rentalTotal > 0
                            ? ($userPct < 100
                                ? ' rent-total:£'.number_format($rentalTotal, 0).'/mo your-rent:£'.number_format($rentalTotal * ($userPct / 100), 0).'/mo'
                                : ' rent:£'.number_format($rentalTotal, 0).'/mo')
                            : '';

                        return "[ID:{$p->id} \"{$p->address_line_1}\" {$p->property_type}".$ownershipLabel($p)."{$mortgageLabel}{$rentalLabel}{$valueLabel}]";
                    })->implode(' ');
                    $lines[] = "PROPERTIES: {$items}";
                }
            }

            // Life Insurance
            if ($include('life_insurance')) {
                $lifePolicies = \App\Models\LifeInsurancePolicy::where('user_id', $userId)->get();
                if ($lifePolicies->isNotEmpty()) {
                    $items = $lifePolicies->map(fn ($p) => "[ID:{$p->id} \"{$p->provider}\" {$p->policy_type} £".number_format((float) $p->sum_assured, 0).']')->implode(' ');
                    $lines[] = "LIFE INSURANCE: {$items}";
                }
            }

            // Critical Illness
            if ($include('critical_illness')) {
                $ciPolicies = \App\Models\CriticalIllnessPolicy::where('user_id', $userId)->get();
                if ($ciPolicies->isNotEmpty()) {
                    $items = $ciPolicies->map(fn ($p) => "[ID:{$p->id} \"{$p->provider}\" £".number_format((float) $p->sum_assured, 0).']')->implode(' ');
                    $lines[] = "CRITICAL ILLNESS: {$items}";
                }
            }

            // Income Protection
            if ($include('income_protection')) {
                $ipPolicies = \App\Models\IncomeProtectionPolicy::where('user_id', $userId)->get();
                if ($ipPolicies->isNotEmpty()) {
                    $items = $ipPolicies->map(fn ($p) => "[ID:{$p->id} \"{$p->provider}\" £".number_format((float) $p->benefit_amount, 0).'/mo]')->implode(' ');
                    $lines[] = "INCOME PROTECTION: {$items}";
                }
            }

            // Trusts
            if ($include('trust')) {
                $trusts = \App\Models\Estate\Trust::where('user_id', $userId)->get();
                if ($trusts->isNotEmpty()) {
                    $items = $trusts->map(fn ($t) => "[ID:{$t->id} \"{$t->trust_name}\" {$t->trust_type} £".number_format((float) $t->current_value, 0).']')->implode(' ');
                    $lines[] = "TRUSTS: {$items}";
                }
            }

            // Business Interests
            if ($include('business')) {
                $businesses = \App\Models\BusinessInterest::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
                if ($businesses->isNotEmpty()) {
                    $items = $businesses->map(fn ($b) => "[ID:{$b->id} \"{$b->business_name}\" {$b->business_type} £".number_format((float) $b->current_valuation, 0).']')->implode(' ');
                    $lines[] = "BUSINESS: {$items}";
                }
            }

            // Chattels
            if ($include('chattel')) {
                $chattels = \App\Models\Chattel::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
                if ($chattels->isNotEmpty()) {
                    $items = $chattels->map(fn ($c) => "[ID:{$c->id} \"{$c->description}\" {$c->chattel_type} £".number_format((float) $c->current_value, 0).']')->implode(' ');
                    $lines[] = "CHATTELS: {$items}";
                }
            }

            // Liabilities
            if ($include('liability')) {
                $liabilities = \App\Models\Estate\Liability::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
                if ($liabilities->isNotEmpty()) {
                    $items = $liabilities->map(function ($l) {
                        $parts = "[ID:{$l->id} \"{$l->liability_name}\" {$l->liability_type} £".number_format((float) $l->current_balance, 0);
                        if ($l->interest_rate) {
                            $parts .= " rate:{$l->interest_rate}%";
                        }
                        if ($l->monthly_payment) {
                            $parts .= ' £'.number_format((float) $l->monthly_payment, 0).'/mo';
                        }
                        if ($l->is_priority_debt) {
                            $parts .= ' PRIORITY';
                        }

                        return $parts.']';
                    })->implode(' ');
                    $lines[] = "LIABILITIES: {$items}";
                }
            }

            // Gifts
            if ($include('gift')) {
                $gifts = \App\Models\Estate\Gift::where('user_id', $userId)->get();
                if ($gifts->isNotEmpty()) {
                    $items = $gifts->map(fn ($g) => "[ID:{$g->id} \"{$g->recipient}\" {$g->gift_type} £".number_format((float) $g->gift_value, 0).' '.($g->gift_date ? $g->gift_date->format('M Y') : '').']')->implode(' ');
                    $lines[] = "GIFTS: {$items}";
                }
            }

            // Family Members
            if ($include('family_member')) {
                $family = \App\Models\FamilyMember::where('user_id', $userId)->get();
                $spouse = $user->spouse;
                $familyParts = [];
                if ($spouse) {
                    $familyParts[] = "[Spouse: {$spouse->first_name} {$spouse->surname}]";
                }
                foreach ($family as $m) {
                    $age = $m->date_of_birth ? now()->diffInYears($m->date_of_birth) : '?';
                    $familyParts[] = "[ID:{$m->id} \"{$m->first_name} {$m->last_name}\" {$m->relationship} age {$age}]";
                }
                if (! empty($familyParts)) {
                    $lines[] = 'FAMILY: '.implode(' ', $familyParts);
                }
            }

            return ! empty($lines) ? implode("\n", $lines) : 'No records yet.';
        });
    }

    // ─── Layer 7: Data Completeness ──────────────────────────────────

    public function buildPrerequisiteStateContext(User $user): string
    {
        return $this->prerequisiteGate->buildCompletenessContext($user);
    }

    private function buildDataCompletenessBlock(string $prerequisiteState): string
    {
        return <<<PROMPT
<data_completeness>
The following shows which modules have sufficient data for analysis:
{$prerequisiteState}

NAVIGATION RULES:
1. When the user asks to GO TO a page (e.g. "show me my estate planning"), ALWAYS navigate them there first using navigate_to_page. Never refuse to navigate — the user wants to see the page.
2. After navigating, if the module is BLOCKED or has no data, proactively offer to help: "This section doesn't have any data yet. Would you like me to help you add [specific items]?"
3. If the user can add data directly through you (e.g. savings accounts, pensions, properties, protection policies), offer to do it conversationally: "I can add that for you now — just tell me the details."

RULES FOR BLOCKED MODULES:
1. When a user asks about a BLOCKED module (analysis, advice, recommendations), explain what specific data is missing and why it is needed.
2. Do NOT attempt to give advice, estimates, or general guidance on blocked modules. You do not have the data to do so accurately.
3. List each missing item as a bullet point so the user can see exactly what to add.
4. ALWAYS use navigate_to_page to take the user to the correct page. This is mandatory — never just tell the user to go somewhere without navigating them.
5. End with an encouraging note and offer to help add the data.

MODULE DEPENDENCY GUIDANCE:
When navigating to modules that depend on data from other parts of the site, explain this to the user:
- Estate Planning gets its data from: Properties (property values), Pensions (pension death benefits), Savings & Investments (liquid assets), Family Members (beneficiaries), Protection (life insurance in trust). If any of these are missing, tell the user which specific areas need data and offer to navigate them there.
- Holistic Financial Plan requires data across all modules. Tell the user which modules are ready and which need data.
- Protection analysis needs: Family Members (to calculate dependant needs), Income (to calculate income replacement), Liabilities (mortgage/debt cover).
- Retirement projections need: Pensions, Income, Target retirement age.
- Investment analysis needs: Investment accounts, Risk profile.

If a tool call returns a "blocked" result, follow the instruction field in that result — explain the missing data to the user and navigate them to the right page.
</data_completeness>
PROMPT;
    }

    // ─── Layer 7b: Review Due ──────────────────────────────────────────

    private function buildReviewDueBlock(User $user): string
    {
        try {
            $reviewService = app(AdviceReviewService::class);
            $result = $reviewService->checkForChanges($user);
        } catch (\Exception $e) {
            return '';
        }

        $parts = [];

        // Data changes since last advice
        if (! empty($result['changes'])) {
            $changeLines = [];
            foreach ($result['changes'] as $change) {
                $field = str_replace('_', ' ', $change['field']);
                $changeLines[] = "- {$field} changed since advice on {$change['advice_date']}";
            }
            $parts[] = "DATA CHANGES SINCE LAST ADVICE:\n".implode("\n", $changeLines)
                ."\nPrevious advice may need updating based on these changes.";
        }

        // Modules overdue for review
        if (! empty($result['reviews_due'])) {
            $reviewLines = [];
            foreach ($result['reviews_due'] as $review) {
                $reviewLines[] = "- {$review['module']}: last reviewed {$review['months_ago']} months ago ({$review['last_reviewed']})";
            }
            $parts[] = "MODULES DUE FOR REVIEW (over 12 months since last advice):\n".implode("\n", $reviewLines)
                ."\nOffer to review these areas when relevant to the conversation.";
        }

        if (empty($parts)) {
            return '';
        }

        return "<review_due>\n".implode("\n\n", $parts)."\n</review_due>";
    }

    // ─── Layer 8: Query Knowledge (per-domain retrieval) ──────────────

    private function buildKnowledgeBlock(?array $classification): string
    {
        $knowledge = QueryKnowledge::getForClassification($classification);

        if ($knowledge === '') {
            return '';
        }

        return "<financial_knowledge>\n{$knowledge}\n</financial_knowledge>";
    }

    // ─── Layer 8b: Required Tools + Triggers ───────────────────────────

    private function buildToolsAndTriggersBlock(?array $classification): string
    {
        if ($classification === null) {
            return '';
        }

        $primary = $classification['primary'];

        // Skip for bypass types and general queries
        if (QuerySchemas::isBypassType($primary) || $primary === QuerySchemas::GENERAL) {
            return '';
        }

        $parts = [];

        // Required tools — only from the PRIMARY type to keep tool calls manageable
        // Related type tools are available but not mandatory
        $tools = QuerySchemas::REQUIRED_TOOLS[$primary] ?? [];
        if (! empty($tools)) {
            $toolList = implode("\n", array_map(fn ($t) => "- {$t}", $tools));
            $parts[] = <<<PROMPT
<required_tools>
Before responding to this query, you MUST call the following tools to retrieve current data. Do not answer from memory — use these tools first:

{$toolList}

Call these tools BEFORE writing your response. If a tool fails, note it and continue with the others.
IMPORTANT: Only call the tools listed above plus any that are strictly necessary for the specific question asked. Do not call extra tools speculatively — the user's data is already summarised in your context. Be efficient: most questions need 2-3 tool calls, not more.
</required_tools>
PROMPT;
        }

        // Relevant triggers
        $triggers = QuerySchemas::getRelevantTriggersForClassification($classification);
        if (! empty($triggers)) {
            $triggerList = implode("\n", array_map(fn ($t) => "- {$t}", $triggers));
            $parts[] = <<<PROMPT
<relevant_triggers>
The following decision tree triggers are relevant to this query. Check the recommendations in <financial_context> for these triggers and reference their results in your advice:

{$triggerList}

If a trigger has fired (appears in the ranked recommendations), explain what it means for this user with specific amounts. Do not mention triggers that have not fired.
</relevant_triggers>
PROMPT;
        }

        return implode("\n\n", $parts);
    }

    // ─── Layer 10: Module Context ────────────────────────────────────

    public function getModuleContext(?string $currentRoute): ?string
    {
        if (! $currentRoute) {
            return null;
        }

        $contexts = [
            '/dashboard' => 'The user is on their Dashboard — the main overview of their financial position.',
            '/profile' => 'The user is viewing their User Profile — personal details, date of birth, marital status, retirement date, employment status.',
            '/net-worth/wealth-summary' => 'The user is viewing their Net Worth summary across all asset categories.',
            '/net-worth/property' => 'The user is viewing their property portfolio, including property values, equity positions, and mortgage balances.',
            '/net-worth/investments' => 'The user is viewing their investment accounts — including Stocks and Shares ISAs and general investment accounts.',
            '/net-worth/retirement' => 'The user is viewing their pension holdings — Defined Contribution, Defined Benefit, and State Pension.',
            '/net-worth/cash' => 'The user is viewing their cash and savings accounts.',
            '/net-worth/chattels' => 'The user is viewing their valuable possessions (chattels).',
            '/net-worth/business' => 'The user is viewing their business interests.',
            '/net-worth/liabilities' => 'The user is viewing their liabilities and debts.',
            '/valuable-info?section=income' => 'The user is viewing their Income section — employment income, self-employment, rental, dividends, interest, and other income sources.',
            '/valuable-info?section=expenditure' => 'The user is viewing their Expenditure section — monthly and annual spending breakdown.',
            '/valuable-info?section=letter' => 'The user is viewing their Expression of Wishes — a letter to their spouse or family.',
            '/protection' => 'The user is on the Protection module — covering life insurance, income protection, and critical illness cover.',
            '/estate' => 'The user is on the Estate Planning module — covering Inheritance Tax, wills, trusts, gifting strategies, and Lasting Powers of Attorney.',
            '/estate/will-builder' => 'The user is viewing the Will Builder — creating or editing their will.',
            '/estate/power-of-attorney' => 'The user is viewing Lasting Powers of Attorney.',
            '/goals' => 'The user is on the Goals and Life Events module — tracking financial goals and planned life events.',
            '/holistic-plan' => 'The user is viewing their Holistic Financial Plan — a comprehensive cross-module summary.',
            '/trusts' => 'The user is viewing their Trusts within the Estate Planning module.',
            '/risk-profile' => 'The user is viewing their Risk Profile — their assessed attitude to investment risk.',
            '/plans' => 'The user is viewing their Financial Plans dashboard.',
            '/actions' => 'The user is viewing their Actions dashboard — recommended next steps.',
            '/planning/what-if' => 'The user is viewing What-If Scenarios — exploring how changes affect their financial position.',
        ];

        return $contexts[$currentRoute] ?? null;
    }

    // ─── Helper Methods ──────────────────────────────────────────────

    public function calculateTotalUserIncome(User $user): float
    {
        return (float) $user->annual_employment_income
            + (float) $user->annual_self_employment_income
            + (float) $user->annual_rental_income
            + (float) $user->annual_dividend_income
            + (float) $user->annual_interest_income
            + (float) $user->annual_other_income
            + (float) $user->annual_trust_income;
    }

    public function calculateTotalExpenditure(User $user): float
    {
        if ($user->monthly_expenditure && $user->monthly_expenditure > 0) {
            return (float) $user->monthly_expenditure;
        }

        if ($user->annual_expenditure && $user->annual_expenditure > 0) {
            return (float) $user->annual_expenditure / 12;
        }

        return 0;
    }

    public function estimateTaxBand(float $totalIncome): string
    {
        try {
            $incomeTax = $this->taxConfig->getIncomeTax();
            $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? TaxDefaults::PERSONAL_ALLOWANCE);
            $basicRateLimit = $personalAllowance + (float) ($incomeTax['bands'][0]['max'] ?? TaxDefaults::BASIC_RATE_BAND);
            $additionalRateLimit = (float) ($incomeTax['additional_rate_threshold'] ?? TaxDefaults::ADDITIONAL_RATE_THRESHOLD);
        } catch (\Exception) {
            $personalAllowance = (float) TaxDefaults::PERSONAL_ALLOWANCE;
            $basicRateLimit = (float) TaxDefaults::HIGHER_RATE_THRESHOLD;
            $additionalRateLimit = (float) TaxDefaults::ADDITIONAL_RATE_THRESHOLD;
        }

        if ($totalIncome <= $personalAllowance) {
            return 'No tax (below Personal Allowance)';
        }

        if ($totalIncome <= $basicRateLimit) {
            return 'Basic rate (20%)';
        }

        if ($totalIncome <= $additionalRateLimit) {
            return 'Higher rate (40%)';
        }

        return 'Additional rate (45%)';
    }

    public function formatInvestmentAccountType(string $type): string
    {
        return match ($type) {
            'isa' => 'ISA(tax-free)',
            'gia' => 'GIA(taxable)',
            'onshore_bond' => 'Onshore Bond(tax-deferred)',
            'offshore_bond' => 'Offshore Bond(gross roll-up)',
            'vct' => 'VCT(tax-advantaged)',
            'eis' => 'EIS(tax-advantaged)',
            'seis' => 'SEIS(tax-advantaged)',
            'nsi' => 'NS&I',
            default => $type,
        };
    }

    /**
     * Get relevant record types for a classification.
     * Returns null if ALL records should be included.
     */
    private function getRelevantRecordTypes(?array $classification): ?array
    {
        if ($classification === null) {
            return null;
        }

        $primary = $classification['primary'];
        $types = QuerySchemas::RECORD_TYPES[$primary] ?? [];

        // Empty array means include ALL (holistic, general, data_entry)
        if (empty($types)) {
            return null;
        }

        // Merge record types from related classifications
        foreach ($classification['related'] ?? [] as $related) {
            $types = array_merge($types, QuerySchemas::RECORD_TYPES[$related] ?? []);
        }

        return array_values(array_unique($types));
    }
}
