<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Constants\TaxDefaults;
use App\Models\DCPension;
use App\Models\RetirementActionDefinition;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;

/**
 * Evaluates retirement action definitions against user data
 * to produce configurable, database-driven recommendations.
 */
class RetirementActionDefinitionService
{
    use FormatsCurrency;

    public function __construct(
        private readonly PensionContributionOptimizer $optimizer,
        private readonly TaxConfigService $taxConfig,
        private readonly SalarySacrificeAnalyzer $salarySacrificeAnalyzer,
        private readonly DecumulationPlanner $decumulationPlanner
    ) {}

    /**
     * Evaluate all enabled agent-sourced action definitions against analysis data.
     *
     * @return array Recommendations in the standard format consumed by structureActions()
     */
    public function evaluateAgentActions(array $analysisData): array
    {
        $definitions = RetirementActionDefinition::getEnabledBySource('agent');
        $recommendations = [];
        $priority = 1;

        if (empty($analysisData['profile'])) {
            return [];
        }

        $userId = $analysisData['profile']['user_id'];
        $profile = RetirementProfile::find($analysisData['profile']['id']);
        $dcPensions = DCPension::where('user_id', $userId)->with('holdings')->get();

        foreach ($definitions as $definition) {
            $results = $this->evaluateAgentTrigger($definition, $analysisData, $profile, $dcPensions, $priority);

            foreach ($results as $rec) {
                $recommendations[] = $rec;
                $priority++;
            }
        }

        $recommendations = $this->resolveContributionConflicts($recommendations, $dcPensions);

        return [
            'recommendations' => $recommendations,
            'total_count' => count($recommendations),
            'high_priority_count' => count(array_filter($recommendations, fn ($r) => ($r['priority'] ?? 999) <= 2)),
        ];
    }

    /**
     * Resolve conflicts between start_contributions and contribution_increase actions.
     *
     * These are mutually exclusive: if the user has any pension with active contributions,
     * show "increase contributions" only. If they have no contributing pensions at all,
     * show "start contributions" only.
     */
    private function resolveContributionConflicts(array $recommendations, $dcPensions): array
    {
        $hasStart = collect($recommendations)->contains(fn ($r) => ($r['category'] ?? '') === 'Start_contributions');
        $hasIncrease = collect($recommendations)->contains(fn ($r) => ($r['category'] ?? '') === 'Contribution_increase');

        if (! $hasStart || ! $hasIncrease) {
            return $recommendations;
        }

        $hasContributingPension = $dcPensions->contains(fn ($p) => $this->calculateAnnualContribution($p) > 0);

        $removeCategory = $hasContributingPension ? 'Start_contributions' : 'Contribution_increase';

        return array_values(array_filter(
            $recommendations,
            fn ($r) => ($r['category'] ?? '') !== $removeCategory
        ));
    }

    /**
     * Evaluate all enabled goal-sourced action definitions against linked goals.
     *
     * Accepts DC pensions so that pension contributions can be factored into
     * goal on-track evaluations (the goal system itself doesn't track pension
     * contributions, so without this the actions would incorrectly flag goals
     * as off-track when the user is making significant pension contributions).
     *
     * @param  \Illuminate\Support\Collection|null  $dcPensions  User's DC pensions
     * @return array Recommendations in the standard format consumed by structureActions()
     */
    public function evaluateGoalActions(array $linkedGoals, $dcPensions = null): array
    {
        $definitions = RetirementActionDefinition::getEnabledBySource('goal');
        $recommendations = [];

        $monthlyPensionContribution = $dcPensions
            ? $this->calculateTotalMonthlyPensionContributions($dcPensions)
            : 0.0;

        foreach ($linkedGoals as $goal) {
            $progress = $goal['progress_percentage'] ?? 0;
            $isComplete = $progress >= 100;

            if ($isComplete) {
                continue;
            }

            foreach ($definitions as $definition) {
                $rec = $this->evaluateGoalTrigger($definition, $goal, $monthlyPensionContribution);

                if ($rec !== null) {
                    $recommendations[] = $rec;
                }
            }
        }

        return $recommendations;
    }

    /**
     * Calculate total monthly pension contributions across all DC pensions.
     */
    private function calculateTotalMonthlyPensionContributions($dcPensions): float
    {
        $total = 0.0;

        foreach ($dcPensions as $pension) {
            $annual = $this->calculateAnnualContribution($pension);
            $total += $annual / 12;
        }

        return $total;
    }

    /**
     * Look up the what_if_impact_type for a given action category.
     */
    public function getWhatIfImpactType(string $category): string
    {
        $definition = RetirementActionDefinition::where('category', $category)->first();

        return $definition?->what_if_impact_type ?? 'default';
    }

    /**
     * Evaluate a single agent-sourced trigger against analysis data.
     *
     * @return array List of recommendations (may be empty or contain multiple for per-pension triggers)
     */
    private function evaluateAgentTrigger(
        RetirementActionDefinition $definition,
        array $analysisData,
        ?RetirementProfile $profile,
        $dcPensions,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            'employee_contribution_percent_below' => $this->evaluateEmployerMatch($definition, $dcPensions, $config, $priority),
            'zero_contribution_with_fund_value' => $this->evaluateZeroContribution($definition, $dcPensions, $priority),
            'income_gap_positive_and_additional_contribution_required' => $this->evaluateContributionIncrease($definition, $analysisData, $profile, $dcPensions, $priority),
            'higher_rate_taxpayer_below_allowance' => $this->evaluateTaxRelief($definition, $profile, $dcPensions, $config, $priority),
            'annual_allowance_has_excess' => $this->evaluateAnnualAllowance($definition, $analysisData, $priority),
            'ni_years_wont_reach_required_by_spa' => $this->evaluateNIGaps($definition, $analysisData, $profile, $priority),
            'income_gap_exceeds_percentage_of_target' => $this->evaluateRetirementAge($definition, $analysisData, $config, $priority),
            'workplace_pension_no_salary_sacrifice' => $this->evaluateSalarySacrificeAvailable($definition, $analysisData, $dcPensions, $priority),
            'salary_sacrifice_below_proxy_floor' => $this->evaluateSalarySacrificeFloor($definition, $analysisData, $dcPensions, $priority),
            'auto_enrolment_below_minimum_total' => $this->evaluateAutoEnrolmentMinimum($definition, $analysisData, $dcPensions, $priority),
            'smoker_or_health_condition_enhanced_annuity' => $this->evaluateEnhancedAnnuity($definition, $analysisData, $priority),
            'no_care_costs_entered_over_50' => $this->evaluateCareCostsNotModelled($definition, $analysisData, $profile, $config, $priority),
            'no_state_pension_forecast' => $this->evaluateStatePensionNoForecast($definition, $analysisData, $priority),
            'within_years_of_retirement' => $this->evaluateApproachingDecumulation($definition, $analysisData, $config, $priority),
            'multiple_dc_pensions' => $this->evaluatePensionConsolidation($definition, $dcPensions, $config, $priority),
            'pension_total_fee_percent_above' => $this->evaluateHighPensionTotalFees($definition, $dcPensions, $config, $priority),
            'pension_platform_fee_percent_above' => $this->evaluateHighPensionPlatformFees($definition, $dcPensions, $config, $priority),
            'pension_weighted_ocf_above' => $this->evaluateHighPensionFundFees($definition, $dcPensions, $config, $priority),
            default => [],
        };
    }

    /**
     * Employer match: triggers for each workplace pension where employee % < threshold.
     */
    private function evaluateEmployerMatch(
        RetirementActionDefinition $definition,
        $dcPensions,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 5.0);
        $results = [];

        // Resolve user for profile context
        $firstPension = $dcPensions->first();
        $user = $firstPension ? User::find($firstPension->user_id) : null;
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        foreach ($dcPensions as $pension) {
            $trace = [];

            // Step 1: User profile
            $trace[] = [
                'question' => 'What is the user\'s personal and employment profile?',
                'data_field' => 'User profile',
                'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
                'threshold' => 'Required for employer match analysis',
                'passed' => true,
                'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
            ];

            // Step 2: Pension details
            $pensionName = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            $salary = (float) ($pension->annual_salary ?? $grossIncome);
            $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
            $employerPct = (float) ($pension->employer_contribution_percent ?? 0);
            $employerMatchLimit = (float) ($pension->employer_matching_limit ?? 0);
            $monthlyEmployee = round($salary * $employeePct / 100 / 12, 2);
            $monthlyEmployer = round($salary * $employerPct / 100 / 12, 2);

            $trace[] = [
                'question' => 'What are the details of this pension?',
                'data_field' => 'Defined Contribution pension',
                'data_value' => $pensionName.': fund value £'.number_format($fundValue, 0).', scheme type '.$pension->scheme_type.', salary £'.number_format($salary, 0),
                'threshold' => 'Pension record details',
                'passed' => true,
                'explanation' => $pensionName.' has a fund value of £'.number_format($fundValue, 0).'. Pensionable salary is £'.number_format($salary, 0).'.',
            ];

            // Step 3: Is this a workplace pension?
            $isWorkplace = $pension->scheme_type === 'workplace';
            $trace[] = [
                'question' => 'Is this a workplace pension?',
                'data_field' => 'scheme_type',
                'data_value' => $pension->scheme_type ?? 'not set',
                'threshold' => 'workplace',
                'passed' => $isWorkplace,
                'explanation' => $isWorkplace
                    ? $pensionName.' is a workplace pension, so employer match rules apply.'
                    : $pensionName.' is a '.$pension->scheme_type.' pension — employer match does not apply.',
            ];

            if (! $isWorkplace) {
                continue;
            }

            // Step 4: Current contribution breakdown
            $trace[] = [
                'question' => 'What are the current employee and employer contribution rates?',
                'data_field' => 'Contribution rates',
                'data_value' => 'Employee: '.number_format($employeePct, 1).'% (£'.number_format($monthlyEmployee, 0).'/month), Employer: '.number_format($employerPct, 1).'% (£'.number_format($monthlyEmployer, 0).'/month)'.($employerMatchLimit > 0 ? ', employer match limit: '.number_format($employerMatchLimit, 1).'%' : ''),
                'threshold' => 'Employee contribution threshold: '.number_format($threshold, 1).'%',
                'passed' => true,
                'explanation' => $userName.' contributes '.number_format($employeePct, 1).'% of £'.number_format($salary, 0).' = £'.number_format($monthlyEmployee, 0).' per month. The employer contributes '.number_format($employerPct, 1).'% = £'.number_format($monthlyEmployer, 0).' per month.',
            ];

            // Step 5: Threshold check
            $belowThreshold = $employeePct < $threshold;
            $trace[] = [
                'question' => 'Is the employee contribution below the '.number_format($threshold, 1).'% threshold to maximise employer matching?',
                'data_field' => 'employee_contribution_percent',
                'data_value' => number_format($employeePct, 1).'%',
                'threshold' => number_format($threshold, 1).'%',
                'passed' => $belowThreshold,
                'explanation' => $belowThreshold
                    ? $userName.'\'s employee contribution of '.number_format($employeePct, 1).'% is below the '.number_format($threshold, 1).'% threshold — increasing to '.number_format($threshold, 1).'% could unlock additional employer matching.'
                    : $userName.'\'s employee contribution of '.number_format($employeePct, 1).'% meets or exceeds the '.number_format($threshold, 1).'% threshold.',
            ];

            if (! $belowThreshold) {
                continue;
            }

            // Step 6: Recommendation with cost calculation
            $additionalPercent = $threshold - $employeePct;
            $additionalMonthly = round($salary * $additionalPercent / 100 / 12, 2);
            $trace[] = [
                'question' => 'What is the recommended action?',
                'data_field' => 'Recommendation',
                'data_value' => 'Increase employee contribution by '.number_format($additionalPercent, 1).'% (£'.number_format($additionalMonthly, 0).' per month extra)',
                'threshold' => 'Maximise employer matching on '.$pensionName,
                'passed' => false,
                'explanation' => 'Increasing from '.number_format($employeePct, 1).'% to '.number_format($threshold, 1).'% on a salary of £'.number_format($salary, 0).' adds £'.number_format($additionalMonthly, 0).' per month (£'.number_format($salary * $additionalPercent / 100, 0).' per year) to unlock the full employer match.',
            ];

            $vars = [
                'additional_percent' => number_format($additionalPercent, 1),
                'scheme_name' => $pension->scheme_name ?: 'pension',
            ];

            $rec = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
                'impact' => ucfirst($definition->priority),
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
                'decision_trace' => $trace,
            ];

            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Zero contribution: triggers for pensions with fund value but no contributions.
     */
    private function evaluateZeroContribution(
        RetirementActionDefinition $definition,
        $dcPensions,
        int $priority
    ): array {
        $results = [];

        // Resolve user for profile context
        $firstPension = $dcPensions->first();
        $user = $firstPension ? User::find($firstPension->user_id) : null;
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        foreach ($dcPensions as $pension) {
            $trace = [];

            // Step 1: User profile
            $trace[] = [
                'question' => 'What is the user\'s personal and employment profile?',
                'data_field' => 'User profile',
                'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
                'threshold' => 'Required for contribution analysis',
                'passed' => true,
                'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
            ];

            // Step 2: Pension details
            $pensionName = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            $salary = (float) ($pension->annual_salary ?? $grossIncome);
            $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
            $employerPct = (float) ($pension->employer_contribution_percent ?? 0);
            $monthlyContribAmount = (float) ($pension->monthly_contribution_amount ?? 0);
            $platformFee = (float) ($pension->platform_fee_percent ?? 0);

            $trace[] = [
                'question' => 'What are the details of this pension?',
                'data_field' => 'Defined Contribution pension',
                'data_value' => $pensionName.': fund value £'.number_format($fundValue, 0).', scheme type '.$pension->scheme_type.', provider '.$pension->provider,
                'threshold' => 'Pension record details',
                'passed' => true,
                'explanation' => $pensionName.' has a fund value of £'.number_format($fundValue, 0).'.'.($platformFee > 0 ? ' Platform fee: '.number_format($platformFee, 2).'%.' : ''),
            ];

            // Step 3: Contribution calculation
            $annualContrib = $this->calculateAnnualContribution($pension);

            $hasNoContribution = $annualContrib <= 0;
            $trace[] = [
                'question' => 'Are there any active contributions to this pension?',
                'data_field' => 'Contribution rates',
                'data_value' => 'Employee: '.number_format($employeePct, 1).'%, Employer: '.number_format($employerPct, 1).'%, Monthly amount: £'.number_format($monthlyContribAmount, 0).', Calculated annual: £'.number_format($annualContrib, 0),
                'threshold' => 'Annual contribution greater than £0',
                'passed' => $hasNoContribution,
                'explanation' => $hasNoContribution
                    ? 'No contributions are being made to '.$pensionName.'. Employee contribution is '.number_format($employeePct, 1).'% and employer contribution is '.number_format($employerPct, 1).'% with no monthly amount set.'
                    : $pensionName.' has active contributions totalling £'.number_format($annualContrib, 0).' per year ('.number_format($employeePct, 1).'% employee + '.number_format($employerPct, 1).'% employer).',
            ];

            // Step 4: Fund value check
            $hasFundValue = $fundValue > 0;
            $trace[] = [
                'question' => 'Does this pension have an existing fund value that could benefit from further contributions?',
                'data_field' => 'current_fund_value',
                'data_value' => '£'.number_format($fundValue, 0),
                'threshold' => 'Greater than £0',
                'passed' => $hasFundValue,
                'explanation' => $hasFundValue
                    ? $pensionName.' has a fund value of £'.number_format($fundValue, 0).' sitting without new contributions. Restarting contributions would benefit from compound growth.'
                    : $pensionName.' has no existing fund value — this pension appears dormant.',
            ];

            if ($annualContrib > 0 || $fundValue <= 0) {
                continue;
            }

            // Step 5: Recommendation
            $trace[] = [
                'question' => 'What is the recommended action?',
                'data_field' => 'Recommendation',
                'data_value' => 'Restart contributions to '.$pensionName,
                'threshold' => '£'.number_format($fundValue, 0).' fund value with zero contributions',
                'passed' => false,
                'explanation' => $userName.' has £'.number_format($fundValue, 0).' in '.$pensionName.' but is making no contributions. Restarting even modest contributions would add to this existing pot and benefit from compound growth.',
            ];

            $vars = [
                'scheme_name' => $pension->scheme_name ?: 'pension',
            ];

            $rec = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
                'impact' => ucfirst($definition->priority),
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
                'decision_trace' => $trace,
            ];

            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Contribution increase: triggers when there's an income gap and the user
     * has available annual allowance headroom (including carry forward from
     * the previous three tax years).
     */
    private function evaluateContributionIncrease(
        RetirementActionDefinition $definition,
        array $analysisData,
        ?RetirementProfile $profile,
        $dcPensions,
        int $priority
    ): array {
        $trace = [];

        if (! $profile) {
            return [];
        }

        $userId = $analysisData['profile']['user_id'] ?? null;
        $user = $userId ? User::find($userId) : null;

        // Step 1: User profile data gathered
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for retirement analysis',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: Retirement target
        $targetRetirementAge = (int) ($profile->target_retirement_age ?? $user?->target_retirement_age ?? 67);
        $targetIncome = (float) ($profile->target_retirement_income ?? 0);
        $yearsToRetirement = $age !== null ? max(0, $targetRetirementAge - $age) : null;

        $trace[] = [
            'question' => 'What is the retirement target?',
            'data_field' => 'Retirement target',
            'data_value' => 'Retire at age '.$targetRetirementAge.($yearsToRetirement !== null ? ' ('.$yearsToRetirement.' years away)' : '').', target income £'.number_format($targetIncome, 0).' per year',
            'threshold' => 'User-defined retirement goals',
            'passed' => true,
            'explanation' => $userName.' aims to retire at '.$targetRetirementAge.' with an annual income of £'.number_format($targetIncome, 0).'.',
        ];

        // Step 3: Current pension positions
        $pensionSummaries = [];
        $totalCurrentValue = 0;
        $totalMonthlyContribution = 0;
        foreach ($dcPensions as $pension) {
            $name = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            $salary = (float) ($pension->annual_salary ?? $grossIncome);
            $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
            $employerPct = (float) ($pension->employer_contribution_percent ?? 0);
            $monthlyEmployee = round($salary * $employeePct / 100 / 12, 2);
            $monthlyEmployer = round($salary * $employerPct / 100 / 12, 2);
            $totalCurrentValue += $fundValue;
            $totalMonthlyContribution += $monthlyEmployee + $monthlyEmployer;
            $pensionSummaries[] = $name.': fund value £'.number_format($fundValue, 0).', you contribute '.number_format($employeePct, 1).'% (£'.number_format($monthlyEmployee, 0).'/month)'.($employerPct > 0 ? ', employer contributes '.number_format($employerPct, 1).'% (£'.number_format($monthlyEmployer, 0).'/month)' : ', no employer contributions');
        }

        $trace[] = [
            'question' => 'What are the current pension positions?',
            'data_field' => 'Defined Contribution pensions',
            'data_value' => count($dcPensions).' pensions, total fund value £'.number_format($totalCurrentValue, 0).', total monthly contributions £'.number_format($totalMonthlyContribution, 0),
            'threshold' => 'All Defined Contribution pensions for this user',
            'passed' => true,
            'explanation' => implode('. ', $pensionSummaries) ?: 'No Defined Contribution pensions found.',
        ];

        // Step 4: Tax position and relief rate
        $taxBands = $this->taxConfig->get('income_tax.bands') ?? [];
        $personalAllowance = (float) ($this->taxConfig->get('income_tax.personal_allowance') ?? TaxDefaults::PERSONAL_ALLOWANCE);
        $incomeTaxBands = $this->taxConfig->getIncomeTax();
        $additionalRateThreshold = (float) ($incomeTaxBands['additional_rate_threshold'] ?? TaxDefaults::ADDITIONAL_RATE_THRESHOLD);
        $higherRateThreshold = (float) ($incomeTaxBands['higher_rate_threshold'] ?? TaxDefaults::HIGHER_RATE_THRESHOLD);
        $taxBand = 'basic';
        if ($grossIncome > $additionalRateThreshold) {
            $taxBand = 'additional';
        } elseif ($grossIncome > $higherRateThreshold) {
            $taxBand = 'higher';
        }
        $reliefRate = match ($taxBand) {
            'additional' => 45,
            'higher' => 40,
            default => 20,
        };

        $trace[] = [
            'question' => 'What tax relief rate applies to pension contributions?',
            'data_field' => 'Tax position',
            'data_value' => ucfirst($taxBand).' rate taxpayer ('.$reliefRate.'% relief), Personal Allowance £'.number_format($personalAllowance, 0),
            'threshold' => 'Based on gross income of £'.number_format($grossIncome, 0),
            'passed' => true,
            'explanation' => 'As a '.$taxBand.' rate taxpayer, '.$userName.' receives '.$reliefRate.'% tax relief on pension contributions.',
        ];

        // Step 5: Annual Allowance position
        $annualAllowance = (float) ($analysisData['annual_allowance']['standard_allowance'] ?? $this->taxConfig->getPensionAllowances()['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE);
        $usedAllowance = (float) ($analysisData['annual_allowance']['allowance_used'] ?? $analysisData['annual_allowance']['total_contributions'] ?? 0);
        $remainingAllowance = (float) ($analysisData['annual_allowance']['remaining_allowance'] ?? 0);
        $carryForward = (float) ($analysisData['annual_allowance']['carry_forward_available'] ?? 0);
        $availableHeadroom = $remainingAllowance + $carryForward;

        $trace[] = [
            'question' => 'How much Pension Annual Allowance is available?',
            'data_field' => 'Pension Annual Allowance',
            'data_value' => 'Allowance £'.number_format($annualAllowance, 0).', used £'.number_format($usedAllowance, 0).', remaining £'.number_format($remainingAllowance, 0).($carryForward > 0 ? ', carry forward £'.number_format($carryForward, 0) : ''),
            'threshold' => '£'.number_format($annualAllowance, 0).' annual limit',
            'passed' => $availableHeadroom > 0,
            'explanation' => '£'.number_format($availableHeadroom, 0).' total headroom available for additional pension contributions'.($carryForward > 0 ? ' (including £'.number_format($carryForward, 0).' carry forward from previous years)' : '').'.',
        ];

        // Step 6: Income gap assessment
        $projectedIncome = (float) ($analysisData['summary']['projected_retirement_income'] ?? $analysisData['projected_income'] ?? 0);
        $incomeGap = (float) ($analysisData['summary']['income_gap'] ?? $analysisData['income_gap'] ?? 0);
        $hasIncomeGap = $incomeGap > 0;

        $trace[] = [
            'question' => 'Is there a shortfall between projected and target retirement income?',
            'data_field' => 'Retirement income projection',
            'data_value' => 'Projected income £'.number_format($projectedIncome, 0).' per year vs target £'.number_format($targetIncome, 0).' per year',
            'threshold' => 'Target income £'.number_format($targetIncome, 0).' per year',
            'passed' => ! $hasIncomeGap,
            'explanation' => $hasIncomeGap
                ? 'There is a projected income gap of £'.number_format($incomeGap, 0).' per year. Additional contributions of £'.number_format($availableHeadroom / 12, 0).' per month could help close this gap with '.$reliefRate.'% tax relief.'
                : 'Projected retirement income of £'.number_format($projectedIncome, 0).' meets the target of £'.number_format($targetIncome, 0).'.',
        ];

        if (! $hasIncomeGap) {
            return [];
        }

        // Step 7: Headroom check
        if ($availableHeadroom <= 0) {
            $trace[] = [
                'question' => 'Is there available headroom for additional contributions?',
                'data_field' => 'Available headroom',
                'data_value' => '£0',
                'threshold' => 'Greater than £0',
                'passed' => false,
                'explanation' => 'No annual allowance headroom available. The full £'.number_format($annualAllowance, 0).' allowance has been used.',
            ];

            return [];
        }

        // Step 8: Outcome
        $monthlyHeadroom = $availableHeadroom / 12;
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Increase contributions by up to £'.number_format($monthlyHeadroom, 0).' per month',
            'threshold' => 'Close the £'.number_format($incomeGap, 0).' income gap',
            'passed' => false,
            'explanation' => 'Increasing pension contributions by £'.number_format($monthlyHeadroom, 0).' per month would use the available £'.number_format($availableHeadroom, 0).' annual headroom. At '.$reliefRate.'% tax relief, the net cost is £'.number_format($monthlyHeadroom * (1 - $reliefRate / 100), 0).' per month.',
        ];

        $vars = [
            'monthly_amount' => '£'.number_format($monthlyHeadroom, 2),
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'available_annual_headroom' => round($availableHeadroom, 2),
            'available_monthly_headroom' => round($monthlyHeadroom, 2),
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Tax relief: triggers for higher-rate taxpayers with capacity below threshold.
     */
    private function evaluateTaxRelief(
        RetirementActionDefinition $definition,
        ?RetirementProfile $profile,
        $dcPensions,
        array $config,
        int $priority
    ): array {
        $trace = [];

        if (! $profile) {
            return [];
        }

        $userId = $profile->user_id;
        $user = User::find($userId);
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for tax relief analysis',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: Tax band determination
        $personalAllowance = (float) ($this->taxConfig->get('income_tax.personal_allowance') ?? TaxDefaults::PERSONAL_ALLOWANCE);
        $incomeTaxBands = $this->taxConfig->getIncomeTax();
        $additionalRateThreshold = (float) ($incomeTaxBands['additional_rate_threshold'] ?? TaxDefaults::ADDITIONAL_RATE_THRESHOLD);
        $higherRateThreshold = (float) ($incomeTaxBands['higher_rate_threshold'] ?? TaxDefaults::HIGHER_RATE_THRESHOLD);
        $taxBand = 'basic';
        if ($grossIncome > $additionalRateThreshold) {
            $taxBand = 'additional';
        } elseif ($grossIncome > $higherRateThreshold) {
            $taxBand = 'higher';
        }
        $reliefRate = match ($taxBand) {
            'additional' => 45,
            'higher' => 40,
            default => 20,
        };

        $isHigherRate = in_array($taxBand, ['higher', 'additional'], true);
        $trace[] = [
            'question' => 'Is the user a higher or additional rate taxpayer?',
            'data_field' => 'Tax position',
            'data_value' => ucfirst($taxBand).' rate taxpayer ('.$reliefRate.'% relief), Personal Allowance £'.number_format($personalAllowance, 0),
            'threshold' => 'Higher rate (income above £'.number_format($higherRateThreshold).') or additional rate (above £'.number_format($additionalRateThreshold).')',
            'passed' => $isHigherRate,
            'explanation' => $isHigherRate
                ? $userName.' is a '.$taxBand.' rate taxpayer with '.$reliefRate.'% tax relief available on pension contributions.'
                : $userName.' is a basic rate taxpayer — higher-rate tax relief optimisation does not apply.',
        ];

        // Step 3: Current pension positions
        $pensionSummaries = [];
        $totalCurrentContributions = 0;
        foreach ($dcPensions as $pension) {
            $name = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            $annualContrib = $this->calculateAnnualContribution($pension);
            $totalCurrentContributions += $annualContrib;
            $pensionSummaries[] = $name.': fund value £'.number_format($fundValue, 0).', annual contribution £'.number_format($annualContrib, 0);
        }

        $trace[] = [
            'question' => 'What are the current pension positions?',
            'data_field' => 'Defined Contribution pensions',
            'data_value' => count($dcPensions).' pensions, total annual contributions £'.number_format($totalCurrentContributions, 0),
            'threshold' => 'All Defined Contribution pensions for this user',
            'passed' => true,
            'explanation' => implode('. ', $pensionSummaries) ?: 'No Defined Contribution pensions found.',
        ];

        // Step 4: Contribution optimiser analysis
        $optimization = $this->optimizer->optimizeContributions($profile, $dcPensions);
        $taxRec = collect($optimization['recommendations'])->firstWhere('type', 'tax_relief');

        $hasTaxRec = $taxRec !== null;
        $potentialSaving = $taxRec['potential_saving'] ?? 0;

        // Step 5: Annual Allowance headroom
        $pensionConfig = $this->taxConfig->getPensionAllowances();
        $annualAllowance = (float) ($pensionConfig['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE);
        $remainingAllowance = max(0, $annualAllowance - $totalCurrentContributions);

        $trace[] = [
            'question' => 'How much Pension Annual Allowance headroom is available for additional contributions?',
            'data_field' => 'Pension Annual Allowance',
            'data_value' => 'Allowance £'.number_format($annualAllowance, 0).', current contributions £'.number_format($totalCurrentContributions, 0).', remaining £'.number_format($remainingAllowance, 0),
            'threshold' => 'Headroom available for higher-rate relief',
            'passed' => $remainingAllowance > 0,
            'explanation' => $remainingAllowance > 0
                ? '£'.number_format($remainingAllowance, 0).' of annual allowance headroom is available. Additional contributions up to this amount would attract '.$reliefRate.'% tax relief.'
                : 'No annual allowance headroom remaining — the full £'.number_format($annualAllowance, 0).' has been used.',
        ];

        // Step 6: Tax relief opportunity check
        $trace[] = [
            'question' => 'Is there a higher-rate tax relief opportunity on additional pension contributions?',
            'data_field' => 'Tax relief optimisation',
            'data_value' => $hasTaxRec ? '£'.number_format($potentialSaving, 0).' potential tax saving on £'.number_format($remainingAllowance, 0).' additional contributions' : 'No opportunity identified',
            'threshold' => 'Higher-rate taxpayer with unused annual allowance',
            'passed' => $hasTaxRec,
            'explanation' => $hasTaxRec
                ? $userName.' could save £'.number_format($potentialSaving, 0).' in tax by contributing an additional £'.number_format($remainingAllowance, 0).' to pensions at '.$reliefRate.'% relief.'
                : 'No higher-rate tax relief opportunity was identified — '.$userName.' may already be maximising contributions or is not a higher-rate taxpayer.',
        ];

        if (! $hasTaxRec) {
            return [];
        }

        // Step 7: Recommendation
        $additionalContribution = $potentialSaving / ($reliefRate / 100);
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Contribute additional £'.number_format($additionalContribution, 0).' to save £'.number_format($potentialSaving, 0).' in tax',
            'threshold' => 'Maximise '.$reliefRate.'% tax relief',
            'passed' => false,
            'explanation' => 'By contributing an additional £'.number_format($additionalContribution, 0).' per year (£'.number_format($additionalContribution / 12, 0).' per month), '.$userName.' would receive £'.number_format($potentialSaving, 0).' in '.$taxBand.' rate tax relief. The net cost after relief is £'.number_format($additionalContribution - $potentialSaving, 0).' per year.',
        ];

        $vars = [
            'tax_saving' => '£'.number_format($potentialSaving, 2),
            'additional_contribution' => '£'.number_format($additionalContribution, 2),
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'potential_saving' => $potentialSaving,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Annual allowance exceeded: triggers when has_excess is true.
     */
    private function evaluateAnnualAllowance(
        RetirementActionDefinition $definition,
        array $analysisData,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'] ?? null;
        $user = $userId ? User::find($userId) : null;
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for annual allowance analysis',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: Tax band (determines marginal rate for excess charge)
        $incomeTaxBands = $this->taxConfig->getIncomeTax();
        $additionalRateThreshold = (float) ($incomeTaxBands['additional_rate_threshold'] ?? TaxDefaults::ADDITIONAL_RATE_THRESHOLD);
        $higherRateThreshold = (float) ($incomeTaxBands['higher_rate_threshold'] ?? TaxDefaults::HIGHER_RATE_THRESHOLD);
        $taxBand = 'basic';
        if ($grossIncome > $additionalRateThreshold) {
            $taxBand = 'additional';
        } elseif ($grossIncome > $higherRateThreshold) {
            $taxBand = 'higher';
        }
        $marginalRate = match ($taxBand) {
            'additional' => 45,
            'higher' => 40,
            default => 20,
        };

        $trace[] = [
            'question' => 'What is the user\'s marginal tax rate?',
            'data_field' => 'Tax band',
            'data_value' => ucfirst($taxBand).' rate taxpayer ('.$marginalRate.'%)',
            'threshold' => 'Determines the annual allowance tax charge rate',
            'passed' => true,
            'explanation' => $userName.' is a '.$taxBand.' rate taxpayer. Any annual allowance excess is taxed at the marginal rate of '.$marginalRate.'%.',
        ];

        // Step 3: Annual Allowance position
        $annualAllowance = (float) ($analysisData['annual_allowance']['standard_allowance'] ?? $this->taxConfig->getPensionAllowances()['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE);
        $usedAllowance = (float) ($analysisData['annual_allowance']['allowance_used'] ?? $analysisData['annual_allowance']['total_contributions'] ?? 0);
        $isTapered = $analysisData['annual_allowance']['is_tapered'] ?? false;
        $carryForward = (float) ($analysisData['annual_allowance']['carry_forward_available'] ?? 0);

        $trace[] = [
            'question' => 'What is the Pension Annual Allowance position?',
            'data_field' => 'Pension Annual Allowance',
            'data_value' => 'Allowance £'.number_format($annualAllowance, 0).($isTapered ? ' (tapered)' : '').', total contributions £'.number_format($usedAllowance, 0).($carryForward > 0 ? ', carry forward £'.number_format($carryForward, 0) : ''),
            'threshold' => '£'.number_format($annualAllowance, 0).' annual limit',
            'passed' => true,
            'explanation' => $userName.'\'s annual allowance is £'.number_format($annualAllowance, 0).($isTapered ? ' (tapered due to adjusted income)' : '').'. Total pension contributions this tax year are £'.number_format($usedAllowance, 0).'.',
        ];

        // Step 4: Excess check
        $hasExcess = $analysisData['annual_allowance']['has_excess'] ?? false;
        $excess = (float) ($analysisData['annual_allowance']['excess_contributions'] ?? 0);
        $estimatedCharge = round($excess * $marginalRate / 100, 0);

        $trace[] = [
            'question' => 'Have pension contributions exceeded the annual allowance (including carry forward)?',
            'data_field' => 'Excess contributions',
            'data_value' => $hasExcess ? '£'.number_format($excess, 0).' over the allowance' : 'No excess',
            'threshold' => 'Contributions within annual allowance of £'.number_format($annualAllowance, 0),
            'passed' => $hasExcess,
            'explanation' => $hasExcess
                ? 'Contributions of £'.number_format($usedAllowance, 0).' exceed the £'.number_format($annualAllowance, 0).' annual allowance by £'.number_format($excess, 0).'.'
                : 'Contributions of £'.number_format($usedAllowance, 0).' are within the £'.number_format($annualAllowance, 0).' annual allowance — no tax charge applies.',
        ];

        if (! $hasExcess) {
            return [];
        }

        // Step 5: Tax charge calculation and recommendation
        $trace[] = [
            'question' => 'What is the estimated annual allowance tax charge?',
            'data_field' => 'Recommendation',
            'data_value' => '£'.number_format($excess, 0).' excess × '.$marginalRate.'% marginal rate = £'.number_format($estimatedCharge, 0).' estimated tax charge',
            'threshold' => 'Excess of £'.number_format($excess, 0).' must be reported to HMRC',
            'passed' => false,
            'explanation' => $userName.' faces an estimated annual allowance tax charge of £'.number_format($estimatedCharge, 0).' (£'.number_format($excess, 0).' × '.$marginalRate.'%). This must be reported via Self Assessment. Consider reducing contributions or using carry forward from previous years to avoid future charges.',
        ];

        $vars = [
            'excess_amount' => '£'.number_format($excess, 2),
        ];

        return [[
            'priority' => 1, // Always high priority for tax charges
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Consult with a financial adviser to minimise tax charges.',
            'impact' => 'High',
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * NI gaps: triggers when NI years won't reach requirement by state pension age.
     */
    private function evaluateNIGaps(
        RetirementActionDefinition $definition,
        array $analysisData,
        ?RetirementProfile $profile,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'];
        $user = User::find($userId);
        $statePension = StatePension::where('user_id', $userId)->first();

        if (! $statePension || ! $profile) {
            return [];
        }

        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for National Insurance analysis',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: State Pension details
        $spa = (int) ($statePension->state_pension_age ?? 67);
        $forecastAnnual = (float) ($statePension->state_pension_forecast_annual ?? 0);
        $alreadyReceiving = (bool) ($statePension->already_receiving ?? false);
        $gapFillCost = (float) ($statePension->gap_fill_cost ?? 0);
        $fullStatePension = (float) ($this->taxConfig->get('pension.state_pension.full_new_state_pension', 11502));

        $trace[] = [
            'question' => 'What is the State Pension position?',
            'data_field' => 'State Pension record',
            'data_value' => 'State Pension age: '.$spa.', forecast: £'.number_format($forecastAnnual, 0).'/year (full amount: £'.number_format($fullStatePension, 0).'/year)'.($alreadyReceiving ? ', already receiving' : ''),
            'threshold' => 'State Pension data for NI gap assessment',
            'passed' => true,
            'explanation' => $userName.'\'s State Pension age is '.$spa.'. '.($forecastAnnual > 0 ? 'Forecast annual State Pension is £'.number_format($forecastAnnual, 0).'.' : 'No State Pension forecast has been entered.'),
        ];

        // Step 3: NI record assessment
        $niCompleted = (int) $statePension->ni_years_completed;
        $niRequired = (int) $statePension->ni_years_required;
        $isShort = $niCompleted < $niRequired;

        $trace[] = [
            'question' => 'Are the completed National Insurance years below the required amount for a full State Pension?',
            'data_field' => 'National Insurance record',
            'data_value' => $niCompleted.' years completed of '.$niRequired.' required',
            'threshold' => $niRequired.' years for full State Pension',
            'passed' => $isShort,
            'explanation' => $isShort
                ? $userName.' has completed '.$niCompleted.' of the '.$niRequired.' National Insurance years required for a full State Pension — a shortfall of '.($niRequired - $niCompleted).' years.'
                : $userName.' has completed '.$niCompleted.' years, meeting the '.$niRequired.'-year requirement for a full State Pension.',
        ];

        if (! $isShort) {
            return [];
        }

        // Step 4: Time until State Pension age
        $yearsShort = $niRequired - $niCompleted;
        $currentAge = $profile->current_age ?? ($age ?? 0);
        $yearsUntilSPA = max(0, $spa - $currentAge);
        $willReachNaturally = ($niCompleted + $yearsUntilSPA) >= $niRequired;

        $trace[] = [
            'question' => 'Will the NI shortfall be filled naturally through continued employment before State Pension age?',
            'data_field' => 'Years until State Pension age',
            'data_value' => $yearsUntilSPA.' years until State Pension age '.$spa.', currently age '.$currentAge.', '.$yearsShort.' years short',
            'threshold' => $yearsShort.' additional NI years needed within '.$yearsUntilSPA.' remaining years',
            'passed' => ! $willReachNaturally,
            'explanation' => $willReachNaturally
                ? 'With '.$yearsUntilSPA.' working years remaining before State Pension age '.$spa.', '.$userName.' can accumulate the '.$yearsShort.' missing years naturally through continued employment.'
                : 'With only '.$yearsUntilSPA.' years until State Pension age '.$spa.', '.$userName.' cannot fill the '.$yearsShort.'-year gap naturally — voluntary National Insurance contributions may be needed.',
        ];

        if ($willReachNaturally) {
            return [];
        }

        // Step 5: Cost of filling gaps and recommendation
        $voluntaryYearsNeeded = $yearsShort - $yearsUntilSPA;
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => $voluntaryYearsNeeded.' voluntary NI years needed'.($gapFillCost > 0 ? ', estimated cost £'.number_format($gapFillCost, 0) : ''),
            'threshold' => 'Fill '.$yearsShort.'-year NI shortfall to secure full State Pension',
            'passed' => false,
            'explanation' => $userName.' needs '.$voluntaryYearsNeeded.' voluntary National Insurance years to secure a full State Pension of £'.number_format($fullStatePension, 0).' per year.'.($gapFillCost > 0 ? ' The estimated cost to fill these gaps is £'.number_format($gapFillCost, 0).'.' : ' Check gov.uk for the current cost of voluntary Class 3 contributions.').' Each qualifying year adds approximately £'.number_format($fullStatePension / $niRequired, 0).' per year to the State Pension.',
        ];

        $vars = [
            'years_short' => (string) $yearsShort,
            'years_until_spa' => (string) $yearsUntilSPA,
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Check your NI record and consider making voluntary contributions if cost-effective.',
            'impact' => 'High',
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Retirement age adjustment: triggers when income gap exceeds threshold % of target.
     */
    private function evaluateRetirementAge(
        RetirementActionDefinition $definition,
        array $analysisData,
        array $config,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'] ?? null;
        $user = $userId ? User::find($userId) : null;
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for retirement age analysis',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: Retirement target
        $targetIncome = (float) ($analysisData['summary']['target_retirement_income'] ?? 0);
        $retirementAge = (int) ($analysisData['summary']['target_retirement_age'] ?? 0);
        $yearsToRetirement = (int) ($analysisData['summary']['years_to_retirement'] ?? 0);
        $projectedIncome = (float) ($analysisData['summary']['projected_retirement_income'] ?? 0);
        $totalDCValue = (float) ($analysisData['summary']['total_dc_value'] ?? $analysisData['summary']['current_dc_value'] ?? 0);

        $hasTargetIncome = $targetIncome > 0;
        $trace[] = [
            'question' => 'What is the retirement target?',
            'data_field' => 'Retirement target',
            'data_value' => 'Retire at age '.$retirementAge.($yearsToRetirement > 0 ? ' ('.$yearsToRetirement.' years away)' : '').', target income £'.number_format($targetIncome, 0).' per year, current Defined Contribution value £'.number_format($totalDCValue, 0),
            'threshold' => 'Target income greater than £0',
            'passed' => $hasTargetIncome,
            'explanation' => $hasTargetIncome
                ? $userName.' aims to retire at '.$retirementAge.' with a target annual income of £'.number_format($targetIncome, 0).'. Current Defined Contribution pension value is £'.number_format($totalDCValue, 0).'.'
                : 'No target retirement income has been set — retirement age adjustment cannot be assessed.',
        ];

        // Step 3: Income gap assessment
        $incomeGap = (float) ($analysisData['summary']['income_gap'] ?? 0);
        $threshold = (float) ($config['threshold'] ?? 0.10);
        $gapThresholdAmount = $targetIncome * $threshold;
        $gapPercent = $targetIncome > 0 ? round($incomeGap / $targetIncome * 100, 1) : 0;

        $trace[] = [
            'question' => 'What is the projected retirement income versus the target?',
            'data_field' => 'Income gap',
            'data_value' => 'Projected £'.number_format($projectedIncome, 0).'/year vs target £'.number_format($targetIncome, 0).'/year — gap of £'.number_format($incomeGap, 0).' ('.$gapPercent.'% of target)',
            'threshold' => 'Income gap exceeds '.round($threshold * 100, 0).'% of target (£'.number_format($gapThresholdAmount, 0).')',
            'passed' => true,
            'explanation' => 'Projected retirement income is £'.number_format($projectedIncome, 0).' per year against a target of £'.number_format($targetIncome, 0).'. The income gap of £'.number_format($incomeGap, 0).' represents '.$gapPercent.'% of the target.',
        ];

        // Step 4: Threshold check
        $maxSuggestedAge = (int) ($config['max_suggested_age'] ?? 70);
        $ageIncrease = (int) ($config['age_increase'] ?? 3);
        $gapExceedsThreshold = $hasTargetIncome && $incomeGap > $gapThresholdAmount && $retirementAge > 0;

        $trace[] = [
            'question' => 'Does the income gap exceed the '.round($threshold * 100, 0).'% threshold, making a later retirement age worth considering?',
            'data_field' => 'Gap vs threshold',
            'data_value' => '£'.number_format($incomeGap, 0).' gap vs £'.number_format($gapThresholdAmount, 0).' threshold ('.round($threshold * 100, 0).'% of £'.number_format($targetIncome, 0).')',
            'threshold' => 'Gap must exceed £'.number_format($gapThresholdAmount, 0),
            'passed' => $gapExceedsThreshold,
            'explanation' => $gapExceedsThreshold
                ? 'The income gap of £'.number_format($incomeGap, 0).' exceeds the '.round($threshold * 100, 0).'% threshold of £'.number_format($gapThresholdAmount, 0).' — delaying retirement could help close this gap through additional years of contributions and investment growth.'
                : 'The income gap of £'.number_format($incomeGap, 0).' is within acceptable limits (below the '.round($threshold * 100, 0).'% threshold of £'.number_format($gapThresholdAmount, 0).').',
        ];

        if ($targetIncome <= 0 || $incomeGap <= $gapThresholdAmount || $retirementAge <= 0) {
            return [];
        }

        // Step 5: Recommendation
        $suggestedAge = min($retirementAge + $ageIncrease, $maxSuggestedAge);
        $additionalYears = $suggestedAge - $retirementAge;

        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Consider delaying retirement from age '.$retirementAge.' to '.$suggestedAge.' ('.$additionalYears.' additional years)',
            'threshold' => 'Close the £'.number_format($incomeGap, 0).' income gap',
            'passed' => false,
            'explanation' => 'Delaying retirement by '.$additionalYears.' years from age '.$retirementAge.' to '.$suggestedAge.' would give '.$userName.' additional years of pension contributions and investment growth. This could significantly reduce the £'.number_format($incomeGap, 0).' income gap. Use the what-if scenario tool to model the impact of retiring at '.$suggestedAge.'.',
        ];

        $vars = [
            'suggested_age' => (string) $suggestedAge,
            'current_age' => (string) $retirementAge,
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? sprintf('Review scenarios for retiring at %d.', $suggestedAge),
            'impact' => 'High',
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Salary sacrifice available: triggers for employed users with workplace pensions.
     */
    private function evaluateSalarySacrificeAvailable(
        RetirementActionDefinition $definition,
        array $analysisData,
        $dcPensions,
        int $priority
    ): array {
        $userId = $analysisData['profile']['user_id'];
        $user = User::find($userId);

        if (! $user || $user->employment_status === 'self_employed') {
            return [];
        }

        $userName = $user->first_name.' '.$user->surname;
        $dob = $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user->employment_status ?? 'Not set';
        $grossIncome = (float) ($user->annual_employment_income ?? 0);

        $results = [];

        foreach ($dcPensions as $pension) {
            $trace = [];

            // Step 1: User profile
            $trace[] = [
                'question' => 'What is the user\'s personal and employment profile?',
                'data_field' => 'User profile',
                'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
                'threshold' => 'Must be employed (not self-employed) for salary sacrifice',
                'passed' => true,
                'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'. Salary sacrifice is available to employed individuals.',
            ];

            // Step 2: Pension details
            $pensionName = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            $salary = (float) ($pension->annual_salary ?? $grossIncome);
            $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
            $employerPct = (float) ($pension->employer_contribution_percent ?? 0);
            $monthlyEmployee = round($salary * $employeePct / 100 / 12, 2);

            $trace[] = [
                'question' => 'What are the details of this pension?',
                'data_field' => 'Defined Contribution pension',
                'data_value' => $pensionName.': fund value £'.number_format($fundValue, 0).', employee '.number_format($employeePct, 1).'% (£'.number_format($monthlyEmployee, 0).'/month), employer '.number_format($employerPct, 1).'%',
                'threshold' => 'Pension record details',
                'passed' => true,
                'explanation' => $pensionName.' has a fund value of £'.number_format($fundValue, 0).'. '.$userName.' contributes '.number_format($employeePct, 1).'% (£'.number_format($monthlyEmployee, 0).'/month) and the employer contributes '.number_format($employerPct, 1).'%.',
            ];

            // Step 3: Workplace pension check
            $isWorkplace = $pension->scheme_type === 'workplace';
            $trace[] = [
                'question' => 'Is this a workplace pension eligible for salary sacrifice?',
                'data_field' => 'scheme_type',
                'data_value' => $pension->scheme_type ?? 'not set',
                'threshold' => 'workplace',
                'passed' => $isWorkplace,
                'explanation' => $isWorkplace
                    ? $pensionName.' is a workplace pension — salary sacrifice may be available.'
                    : $pensionName.' is a '.$pension->scheme_type.' pension — salary sacrifice is only available for workplace pensions.',
            ];

            if (! $isWorkplace) {
                continue;
            }

            // Step 4: Salary sacrifice analysis
            $analysis = $this->salarySacrificeAnalyzer->analyzeForPension($user, $pension);
            $employeeNISaving = (float) ($analysis['employee_ni_saving'] ?? 0);
            $employerNISaving = (float) ($analysis['employer_ni_saving'] ?? 0);
            $totalNISaving = $employeeNISaving + $employerNISaving;
            $annualContribution = (float) ($analysis['current_employee_contribution'] ?? 0);

            $isAvailable = $analysis['is_available'] && $employeeNISaving > 0;
            $trace[] = [
                'question' => 'Is salary sacrifice available with a meaningful National Insurance saving?',
                'data_field' => 'Salary sacrifice analysis',
                'data_value' => $analysis['is_available']
                    ? 'Available — employee NI saving: £'.number_format($employeeNISaving, 0).'/year, employer NI saving: £'.number_format($employerNISaving, 0).'/year, annual contribution: £'.number_format($annualContribution, 0)
                    : 'Not available',
                'threshold' => 'Available with employee NI saving greater than £0',
                'passed' => $isAvailable,
                'explanation' => $isAvailable
                    ? 'Salary sacrifice on '.$pensionName.' could save '.$userName.' £'.number_format($employeeNISaving, 0).' per year in employee National Insurance. The employer would also save £'.number_format($employerNISaving, 0).' per year — a combined saving of £'.number_format($totalNISaving, 0).'.'
                    : 'No salary sacrifice opportunity identified for '.$pensionName.'.',
            ];

            if (! $isAvailable) {
                continue;
            }

            // Step 5: Recommendation
            $monthlyNISaving = round($employeeNISaving / 12, 0);
            $trace[] = [
                'question' => 'What is the recommended action?',
                'data_field' => 'Recommendation',
                'data_value' => 'Switch to salary sacrifice on '.$pensionName.' to save £'.number_format($employeeNISaving, 0).'/year (£'.number_format($monthlyNISaving, 0).'/month)',
                'threshold' => 'National Insurance saving available',
                'passed' => false,
                'explanation' => $userName.' could save £'.number_format($employeeNISaving, 0).' per year (£'.number_format($monthlyNISaving, 0).' per month) in National Insurance contributions by switching to salary sacrifice. The pension contribution stays the same, but it comes from pre-NI salary. Speak to the employer to arrange this.',
            ];

            // Step 6: April 2029 NIC exemption cap
            $nicCap = (float) $this->taxConfig->get('pension.salary_sacrifice.nic_exemption_cap', 2000);
            $exceedsCap = $annualContribution > $nicCap;
            $post2029EmployeeSaving = (float) ($analysis['post_2029_employee_ni_saving'] ?? ($exceedsCap ? $nicCap * 0.08 : $employeeNISaving));
            $niReduction = $employeeNISaving - $post2029EmployeeSaving;

            $trace[] = [
                'question' => 'How will the April 2029 salary sacrifice changes affect this?',
                'data_field' => 'April 2029 National Insurance exemption cap',
                'data_value' => 'Annual sacrifice £'.number_format($annualContribution, 0).' vs £'.number_format($nicCap, 0).' cap. '.($exceedsCap ? 'Exceeds cap by £'.number_format($annualContribution - $nicCap, 0) : 'Within cap'),
                'threshold' => '£'.number_format($nicCap, 0).' annual NIC exemption limit from April 2029',
                'passed' => ! $exceedsCap,
                'explanation' => $exceedsCap
                    ? 'From April 2029, only the first £'.number_format($nicCap, 0).' of employee salary sacrifice will be exempt from National Insurance. '.$userName.'\'s sacrifice of £'.number_format($annualContribution, 0).' exceeds this cap. Current NI saving: £'.number_format($employeeNISaving, 0).'/year. Post-2029 NI saving: £'.number_format($post2029EmployeeSaving, 0).'/year (a reduction of £'.number_format($niReduction, 0).'). Employer contributions and Income Tax relief are unaffected.'
                    : $userName.'\'s sacrifice of £'.number_format($annualContribution, 0).' is within the £'.number_format($nicCap, 0).' cap — no change after April 2029.',
            ];

            $vars = [
                'scheme_name' => $pension->scheme_name ?: 'workplace pension',
                'employee_ni_saving' => '£'.number_format($analysis['employee_ni_saving'], 2),
                'employer_ni_saving' => '£'.number_format($analysis['employer_ni_saving'], 2),
            ];

            $results[] = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'Review salary sacrifice options with your employer.',
                'impact' => ucfirst($definition->priority),
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
                'decision_trace' => $trace,
            ];
        }

        return $results;
    }

    /**
     * Salary sacrifice floor warning: triggers when sacrifice would drop below proxy floor.
     */
    private function evaluateSalarySacrificeFloor(
        RetirementActionDefinition $definition,
        array $analysisData,
        $dcPensions,
        int $priority
    ): array {
        $userId = $analysisData['profile']['user_id'];
        $user = User::find($userId);

        if (! $user || $user->employment_status === 'self_employed') {
            return [];
        }

        $salary = (float) ($user->annual_employment_income ?? 0);
        if ($salary <= 0) {
            return [];
        }

        $userName = $user->first_name.' '.$user->surname;
        $dob = $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user->employment_status ?? 'Not set';

        $proxyFloor = (float) $this->taxConfig->get('pension.salary_sacrifice.conservative_proxy_floor', 10000);
        $results = [];

        foreach ($dcPensions as $pension) {
            $trace = [];

            // Step 1: User profile
            $trace[] = [
                'question' => 'What is the user\'s personal and employment profile?',
                'data_field' => 'User profile',
                'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($salary, 0),
                'threshold' => 'Must be employed for salary sacrifice floor check',
                'passed' => true,
                'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($salary, 0).'.',
            ];

            // Step 2: Pension details
            $pensionName = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
            $employerPct = (float) ($pension->employer_contribution_percent ?? 0);
            $pensionSalary = (float) ($pension->annual_salary ?? $salary);
            $annualContribution = round($pensionSalary * $employeePct / 100, 2);

            $trace[] = [
                'question' => 'What are the details of this pension?',
                'data_field' => 'Defined Contribution pension',
                'data_value' => $pensionName.': fund value £'.number_format($fundValue, 0).', employee '.number_format($employeePct, 1).'% (£'.number_format($annualContribution, 0).'/year), employer '.number_format($employerPct, 1).'%',
                'threshold' => 'Pension record details',
                'passed' => true,
                'explanation' => $pensionName.' has a fund value of £'.number_format($fundValue, 0).'. '.$userName.'\'s employee contribution is '.number_format($employeePct, 1).'% of £'.number_format($pensionSalary, 0).' = £'.number_format($annualContribution, 0).' per year.',
            ];

            // Step 3: Workplace check
            $isWorkplace = $pension->scheme_type === 'workplace';
            $trace[] = [
                'question' => 'Is this a workplace pension?',
                'data_field' => 'scheme_type',
                'data_value' => $pension->scheme_type ?? 'not set',
                'threshold' => 'workplace',
                'passed' => $isWorkplace,
                'explanation' => $isWorkplace
                    ? $pensionName.' is a workplace pension — salary sacrifice floor check applies.'
                    : $pensionName.' is a '.$pension->scheme_type.' pension — salary sacrifice floor check only applies to workplace pensions.',
            ];

            if (! $isWorkplace) {
                continue;
            }

            // Step 4: Salary sacrifice availability
            $analysis = $this->salarySacrificeAnalyzer->analyzeForPension($user, $pension);

            $isAvailable = $analysis['is_available'];
            $trace[] = [
                'question' => 'Is salary sacrifice available for this pension?',
                'data_field' => 'Salary sacrifice analysis',
                'data_value' => $isAvailable ? 'Available, annual contribution £'.number_format($analysis['current_employee_contribution'] ?? 0, 0) : 'Not available',
                'threshold' => 'Salary sacrifice must be available',
                'passed' => $isAvailable,
                'explanation' => $isAvailable
                    ? 'Salary sacrifice is available for '.$pensionName.' with an annual employee contribution of £'.number_format($analysis['current_employee_contribution'] ?? 0, 0).'.'
                    : 'Salary sacrifice is not available for '.$pensionName.'.',
            ];

            if (! $isAvailable) {
                continue;
            }

            // Step 5: Post-sacrifice salary vs safety floor
            $postSacrifice = (float) $analysis['post_sacrifice_salary'];
            $belowFloor = $postSacrifice < $proxyFloor;
            $shortfall = max(0, $proxyFloor - $postSacrifice);

            $trace[] = [
                'question' => 'Would the post-sacrifice salary fall below the £'.number_format($proxyFloor, 0).' safety floor?',
                'data_field' => 'Post-sacrifice salary calculation',
                'data_value' => '£'.number_format($salary, 0).' gross salary - £'.number_format($analysis['current_employee_contribution'] ?? 0, 0).' sacrifice = £'.number_format($postSacrifice, 0).' post-sacrifice salary',
                'threshold' => '£'.number_format($proxyFloor, 0).' safety floor',
                'passed' => $belowFloor,
                'explanation' => $belowFloor
                    ? 'Post-sacrifice salary of £'.number_format($postSacrifice, 0).' is £'.number_format($shortfall, 0).' below the £'.number_format($proxyFloor, 0).' safety floor. This could affect '.$userName.'\'s entitlement to state benefits including Statutory Sick Pay, maternity pay, and mortgage affordability assessments.'
                    : 'Post-sacrifice salary of £'.number_format($postSacrifice, 0).' remains above the £'.number_format($proxyFloor, 0).' safety floor.',
            ];

            if (! $belowFloor) {
                continue;
            }

            // Step 6: Recommendation
            $maxSafeContribution = max(0, $salary - $proxyFloor);
            $trace[] = [
                'question' => 'What is the recommended action?',
                'data_field' => 'Recommendation',
                'data_value' => 'Reduce salary sacrifice to keep post-sacrifice salary above £'.number_format($proxyFloor, 0),
                'threshold' => 'Protect state benefit entitlement',
                'passed' => false,
                'explanation' => $userName.' should consider limiting salary sacrifice contributions to a maximum of £'.number_format($maxSafeContribution, 0).' per year to keep the post-sacrifice salary above £'.number_format($proxyFloor, 0).'. Currently sacrificing £'.number_format($analysis['current_employee_contribution'] ?? 0, 0).' drops the effective salary to £'.number_format($postSacrifice, 0).'.',
            ];

            $vars = [
                'scheme_name' => $pension->scheme_name ?: 'workplace pension',
                'post_sacrifice_salary' => '£'.number_format($postSacrifice, 2),
                'proxy_floor' => '£'.number_format($proxyFloor, 0),
            ];

            $results[] = [
                'priority' => 1, // Always critical
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'Review your salary sacrifice amount.',
                'impact' => 'High',
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
                'decision_trace' => $trace,
            ];
        }

        return $results;
    }

    /**
     * Auto-enrolment below minimum: triggers when total contributions are below 8%.
     */
    private function evaluateAutoEnrolmentMinimum(
        RetirementActionDefinition $definition,
        array $analysisData,
        $dcPensions,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'];
        $user = User::find($userId);

        if (! $user) {
            return [];
        }

        $userName = $user->first_name.' '.$user->surname;
        $dob = $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user->employment_status ?? 'Not set';
        $grossIncome = (float) ($user->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for auto-enrolment compliance check',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: Workplace pension positions
        $pensionSummaries = [];
        foreach ($dcPensions as $pension) {
            if ($pension->scheme_type !== 'workplace') {
                continue;
            }
            $name = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
            $employerPct = (float) ($pension->employer_contribution_percent ?? 0);
            $totalPct = $employeePct + $employerPct;
            $pensionSummaries[] = $name.': employee '.number_format($employeePct, 1).'% + employer '.number_format($employerPct, 1).'% = '.number_format($totalPct, 1).'% total';
        }

        $trace[] = [
            'question' => 'What are the current workplace pension contribution rates?',
            'data_field' => 'Workplace pension contributions',
            'data_value' => count($pensionSummaries) > 0 ? implode('; ', $pensionSummaries) : 'No workplace pensions',
            'threshold' => 'Workplace pensions for auto-enrolment check',
            'passed' => true,
            'explanation' => count($pensionSummaries) > 0
                ? implode('. ', $pensionSummaries).'.'
                : 'No workplace pensions found for '.$userName.'.',
        ];

        // Step 3: Eligibility check
        $compliance = $this->optimizer->checkAutoEnrolmentCompliance($user, $dcPensions);

        $isEligible = $compliance['eligible'];
        $trace[] = [
            'question' => 'Is '.$userName.' eligible for auto-enrolment?',
            'data_field' => 'Auto-enrolment eligibility',
            'data_value' => $isEligible ? 'Eligible (income £'.number_format($grossIncome, 0).' exceeds earnings trigger)' : 'Not eligible',
            'threshold' => 'Income above earnings trigger and age 22-66',
            'passed' => $isEligible,
            'explanation' => $isEligible
                ? $userName.' meets the auto-enrolment eligibility criteria with earnings of £'.number_format($grossIncome, 0).'.'
                : $userName.' does not meet the auto-enrolment eligibility criteria.',
        ];

        if (! $isEligible) {
            return [];
        }

        // Step 4: Minimum contribution check
        $meetsMinimum = $compliance['meets_minimum_total'];
        $totalPercent = (float) ($compliance['total_contribution_percent'] ?? 0);
        $shortfallPercent = max(0, 8.0 - $totalPercent);
        $shortfallAnnual = (float) ($compliance['shortfall_annual'] ?? 0);

        $trace[] = [
            'question' => 'Do total contributions meet the auto-enrolment minimum of 8% (5% employee + 3% employer)?',
            'data_field' => 'Total contribution percent',
            'data_value' => number_format($totalPercent, 1).'% total'.($shortfallPercent > 0 ? ', '.number_format($shortfallPercent, 1).'% below minimum' : ''),
            'threshold' => '8% minimum total contribution',
            'passed' => ! $meetsMinimum,
            'explanation' => $meetsMinimum
                ? 'Total contributions of '.number_format($totalPercent, 1).'% meet the 8% auto-enrolment minimum.'
                : 'Total contributions of '.number_format($totalPercent, 1).'% are '.number_format($shortfallPercent, 1).'% below the 8% auto-enrolment minimum — a shortfall of £'.number_format($shortfallAnnual, 0).' per year.',
        ];

        if ($meetsMinimum) {
            return [];
        }

        // Step 5: Recommendation
        $monthlyShortfall = round($shortfallAnnual / 12, 0);
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Increase contributions by '.number_format($shortfallPercent, 1).'% (£'.number_format($shortfallAnnual, 0).'/year, £'.number_format($monthlyShortfall, 0).'/month)',
            'threshold' => 'Meet the 8% auto-enrolment minimum',
            'passed' => false,
            'explanation' => $userName.' needs to increase total pension contributions by '.number_format($shortfallPercent, 1).'% (£'.number_format($shortfallAnnual, 0).' per year, £'.number_format($monthlyShortfall, 0).' per month) to meet the statutory 8% auto-enrolment minimum. Check with the employer whether the employee or employer portion needs increasing.',
        ];

        $vars = [
            'total_percent' => number_format($compliance['total_contribution_percent'], 1),
            'shortfall_annual' => '£'.number_format($compliance['shortfall_annual'], 2),
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Review your pension contribution levels.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Enhanced annuity eligible: triggers when smoker or health condition qualifies.
     */
    private function evaluateEnhancedAnnuity(
        RetirementActionDefinition $definition,
        array $analysisData,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'];
        $user = User::with('protectionProfile')->find($userId);

        if (! $user) {
            return [];
        }

        $userName = $user->first_name.' '.$user->surname;
        $dob = $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user->employment_status ?? 'Not set';
        $grossIncome = (float) ($user->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for enhanced annuity assessment',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.($age !== null ? ', age '.$age : '').', with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: Pension positions (for annuity context)
        $dcPensions = \App\Models\DCPension::where('user_id', $userId)->get();
        $totalFundValue = 0;
        $pensionSummaries = [];
        foreach ($dcPensions as $pension) {
            $name = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            $totalFundValue += $fundValue;
            $pensionSummaries[] = $name.': £'.number_format($fundValue, 0);
        }

        $trace[] = [
            'question' => 'What are the Defined Contribution pension values available for annuity purchase?',
            'data_field' => 'Defined Contribution pensions',
            'data_value' => count($dcPensions).' pensions, total fund value £'.number_format($totalFundValue, 0),
            'threshold' => 'Pension funds available for annuity purchase',
            'passed' => true,
            'explanation' => count($pensionSummaries) > 0
                ? implode('. ', $pensionSummaries).'. Total fund value of £'.number_format($totalFundValue, 0).' is available for potential annuity purchase.'
                : 'No Defined Contribution pensions found.',
        ];

        // Step 3: Health and lifestyle factors
        $protectionProfile = $user->protectionProfile;
        $smokerStatus = $protectionProfile?->smoker_status ?? null;
        $healthStatus = $protectionProfile?->health_status ?? null;

        $trace[] = [
            'question' => 'What are the health and lifestyle factors relevant to enhanced annuity eligibility?',
            'data_field' => 'Protection profile',
            'data_value' => 'Smoker status: '.($smokerStatus ? 'Yes' : 'No/Not recorded').', Health status: '.($healthStatus ?? 'Not recorded'),
            'threshold' => 'Smoker or health condition (poor/fair) for enhanced rates',
            'passed' => true,
            'explanation' => $userName.'\'s protection profile records smoker status as '.($smokerStatus ? 'smoker' : 'non-smoker or not recorded').' and health status as '.($healthStatus ?? 'not recorded').'.',
        ];

        // Step 4: Enhanced annuity eligibility assessment
        $eligibility = $this->decumulationPlanner->assessEnhancedAnnuityEligibility($user);

        $isEligible = $eligibility['is_eligible'];
        $enhancementFactor = (float) ($eligibility['enhancement_factor'] ?? 1.0);
        $enhancementPercent = round(($enhancementFactor - 1.0) * 100, 1);

        $trace[] = [
            'question' => 'Does '.$userName.' qualify for an enhanced annuity due to health or lifestyle factors?',
            'data_field' => 'Enhanced annuity eligibility',
            'data_value' => $isEligible ? 'Eligible — '.($eligibility['reason'] ?? 'qualifying factor identified').', enhancement factor '.number_format($enhancementFactor, 2).' (+'.$enhancementPercent.'%)' : 'Not eligible',
            'threshold' => 'Smoker or health condition present',
            'passed' => $isEligible,
            'explanation' => $isEligible
                ? $userName.' qualifies for enhanced annuity rates due to: '.($eligibility['reason'] ?? 'qualifying health or lifestyle factors').'. This could provide up to '.$enhancementPercent.'% higher annuity income compared to standard rates.'
                : 'No qualifying factors for enhanced annuity rates were identified for '.$userName.'.',
        ];

        if (! $isEligible) {
            return [];
        }

        // Step 5: Recommendation with income impact
        $standardAnnuityRate = 0.05; // approximate
        $standardAnnualIncome = round($totalFundValue * $standardAnnuityRate, 0);
        $enhancedAnnualIncome = round($totalFundValue * $standardAnnuityRate * $enhancementFactor, 0);
        $additionalIncome = $enhancedAnnualIncome - $standardAnnualIncome;

        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Request enhanced annuity quotes — potential £'.number_format($additionalIncome, 0).'/year additional income on £'.number_format($totalFundValue, 0).' fund',
            'threshold' => 'Enhanced rates available for '.$userName,
            'passed' => false,
            'explanation' => 'Based on a total fund value of £'.number_format($totalFundValue, 0).' and an enhancement factor of '.number_format($enhancementFactor, 2).', '.$userName.' could receive approximately £'.number_format($additionalIncome, 0).' per year more than standard annuity rates. Always request enhanced annuity quotes from multiple providers when approaching retirement.',
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle(),
            'description' => $definition->renderDescription(),
            'action' => $definition->renderAction() ?? 'Request enhanced annuity quotes when approaching retirement.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'enhanced_annuity_reason' => $eligibility['reason'],
            'enhancement_factor' => $eligibility['enhancement_factor'],
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Care costs not modelled: triggers when user is over threshold age with no care costs.
     */
    private function evaluateCareCostsNotModelled(
        RetirementActionDefinition $definition,
        array $analysisData,
        ?RetirementProfile $profile,
        array $config,
        int $priority
    ): array {
        $trace = [];

        if (! $profile) {
            return [];
        }

        $userId = $analysisData['profile']['user_id'] ?? $profile->user_id;
        $user = User::find($userId);
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for care cost planning assessment',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.($age !== null ? ', age '.$age : '').', with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: Retirement profile details
        $targetRetirementAge = (int) ($profile->target_retirement_age ?? $user?->target_retirement_age ?? 67);
        $targetIncome = (float) ($profile->target_retirement_income ?? 0);
        $lifeExpectancy = (int) ($profile->life_expectancy ?? 85);
        $careStartAge = $profile->care_start_age ?? null;
        $currentAge = (int) ($profile->current_age ?? ($age ?? 0));

        $trace[] = [
            'question' => 'What is the retirement profile?',
            'data_field' => 'Retirement profile',
            'data_value' => 'Current age '.$currentAge.', target retirement age '.$targetRetirementAge.', target income £'.number_format($targetIncome, 0).'/year, life expectancy '.$lifeExpectancy,
            'threshold' => 'Retirement profile context for care cost assessment',
            'passed' => true,
            'explanation' => $userName.' is currently '.$currentAge.' with a target retirement age of '.$targetRetirementAge.' and life expectancy of '.$lifeExpectancy.'. Retirement years span '.($lifeExpectancy - $targetRetirementAge).' years during which care costs may arise.',
        ];

        // Step 3: Age threshold check
        $ageThreshold = (int) ($config['age_threshold'] ?? 50);

        $isOverThreshold = $currentAge >= $ageThreshold;
        $trace[] = [
            'question' => 'Is '.$userName.' aged '.$ageThreshold.' or over, where care cost planning becomes important?',
            'data_field' => 'current_age',
            'data_value' => $currentAge.' years old',
            'threshold' => $ageThreshold.' years',
            'passed' => $isOverThreshold,
            'explanation' => $isOverThreshold
                ? 'At age '.$currentAge.', '.$userName.' is above the '.$ageThreshold.'-year threshold where care costs should be factored into retirement planning.'
                : 'At age '.$currentAge.', '.$userName.' is below the '.$ageThreshold.'-year threshold — care cost planning is not yet a priority.',
        ];

        // Step 4: Care cost assumptions check
        $careCostAnnual = (float) ($profile->care_cost_annual ?? 0);

        $noCareCosts = $careCostAnnual <= 0;
        $trace[] = [
            'question' => 'Has '.$userName.' entered any care cost assumptions in the retirement plan?',
            'data_field' => 'care_cost_annual',
            'data_value' => '£'.number_format($careCostAnnual, 0).' per year'.($careStartAge ? ', from age '.$careStartAge : ''),
            'threshold' => 'Greater than £0 per year',
            'passed' => $noCareCosts,
            'explanation' => $noCareCosts
                ? 'No care cost assumptions have been entered. With a life expectancy of '.$lifeExpectancy.' and '.($lifeExpectancy - $targetRetirementAge).' years in retirement, this could lead to a significant underestimate of funding needs. Average UK residential care costs are approximately £35,000-£50,000 per year.'
                : 'Care costs of £'.number_format($careCostAnnual, 0).' per year have been included in the retirement plan'.($careStartAge ? ' from age '.$careStartAge : '').'.',
        ];

        if (! $isOverThreshold || ! $noCareCosts) {
            return [];
        }

        // Step 5: Recommendation
        $retirementYears = max(0, $lifeExpectancy - $targetRetirementAge);
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Add care cost assumptions for '.$retirementYears.' retirement years',
            'threshold' => 'Care costs not modelled for user aged '.$currentAge,
            'passed' => false,
            'explanation' => $userName.' should add care cost assumptions to the retirement plan. With '.$retirementYears.' years in retirement (age '.$targetRetirementAge.' to '.$lifeExpectancy.'), even a few years of care at £35,000-£50,000 per year could require £100,000-£200,000 of additional funding. Adding these assumptions will provide a more realistic view of the retirement income needed.',
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle(),
            'description' => $definition->renderDescription(),
            'action' => $definition->renderAction() ?? 'Add care cost assumptions to your retirement profile.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * State Pension no forecast: triggers when no State Pension forecast entered.
     */
    private function evaluateStatePensionNoForecast(
        RetirementActionDefinition $definition,
        array $analysisData,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'];
        $user = User::find($userId);
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for State Pension forecast assessment',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: State Pension record
        $statePension = StatePension::where('user_id', $userId)->first();
        $forecastAmount = $statePension ? (float) ($statePension->state_pension_forecast_annual ?? 0) : 0;
        $spa = $statePension ? (int) ($statePension->state_pension_age ?? 67) : 67;
        $niCompleted = $statePension ? (int) ($statePension->ni_years_completed ?? 0) : 0;
        $niRequired = $statePension ? (int) ($statePension->ni_years_required ?? 35) : 35;
        $alreadyReceiving = $statePension ? (bool) ($statePension->already_receiving ?? false) : false;

        $trace[] = [
            'question' => 'What State Pension data has been recorded?',
            'data_field' => 'State Pension record',
            'data_value' => $statePension
                ? 'State Pension age: '.$spa.', NI years: '.$niCompleted.'/'.$niRequired.($alreadyReceiving ? ', already receiving' : '')
                : 'No State Pension record found',
            'threshold' => 'State Pension record exists',
            'passed' => true,
            'explanation' => $statePension
                ? $userName.'\'s State Pension age is '.$spa.' with '.$niCompleted.' of '.$niRequired.' National Insurance years completed.'.($alreadyReceiving ? ' Already receiving State Pension.' : '')
                : 'No State Pension record has been created for '.$userName.'.',
        ];

        // Step 3: Forecast check
        $hasForecast = $statePension && $forecastAmount > 0;
        $fullStatePension = (float) ($this->taxConfig->get('pension.state_pension.full_new_state_pension', 11502));

        $trace[] = [
            'question' => 'Has '.$userName.' entered a State Pension forecast?',
            'data_field' => 'state_pension_forecast_annual',
            'data_value' => $hasForecast ? '£'.number_format($forecastAmount, 0).' per year' : 'Not entered',
            'threshold' => 'Forecast amount greater than £0',
            'passed' => ! $hasForecast,
            'explanation' => $hasForecast
                ? $userName.' has entered a State Pension forecast of £'.number_format($forecastAmount, 0).' per year (full new State Pension is £'.number_format($fullStatePension, 0).'/year).'
                : 'No State Pension forecast has been entered. Without this, retirement projections assume either the full State Pension of £'.number_format($fullStatePension, 0).'/year or no State Pension — both could be inaccurate.',
        ];

        if ($hasForecast) {
            return [];
        }

        // Step 4: Impact on retirement projections
        $targetIncome = (float) ($analysisData['summary']['target_retirement_income'] ?? 0);
        $spPercent = $targetIncome > 0 ? round($fullStatePension / $targetIncome * 100, 1) : 0;

        $trace[] = [
            'question' => 'How significant is the State Pension to the overall retirement plan?',
            'data_field' => 'State Pension impact',
            'data_value' => 'Full State Pension £'.number_format($fullStatePension, 0).'/year'.($targetIncome > 0 ? ' = '.$spPercent.'% of target income £'.number_format($targetIncome, 0) : ''),
            'threshold' => 'Material component of retirement income',
            'passed' => true,
            'explanation' => 'The full new State Pension of £'.number_format($fullStatePension, 0).' per year'.($targetIncome > 0 ? ' represents '.$spPercent.'% of '.$userName.'\'s target retirement income of £'.number_format($targetIncome, 0) : ' is a significant component of retirement income').'. An accurate forecast is essential for reliable retirement projections.',
        ];

        // Step 5: Recommendation
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Obtain State Pension forecast from gov.uk and enter into the plan',
            'threshold' => 'No forecast entered for '.$userName,
            'passed' => false,
            'explanation' => $userName.' should request a State Pension forecast from gov.uk/check-state-pension to get an accurate projection. With '.$niCompleted.' of '.$niRequired.' NI years completed, the actual forecast may differ from the full amount of £'.number_format($fullStatePension, 0).'/year. Entering the forecast will significantly improve the accuracy of retirement income projections.',
        ];

        $vars = [
            'full_state_pension' => '£'.number_format($fullStatePension, 0),
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Request your State Pension forecast from gov.uk.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Approaching decumulation: triggers when within configurable years of retirement.
     */
    private function evaluateApproachingDecumulation(
        RetirementActionDefinition $definition,
        array $analysisData,
        array $config,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'] ?? null;
        $user = $userId ? User::find($userId) : null;
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for decumulation planning assessment',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.($age !== null ? ', age '.$age : '').', with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: Retirement target and pension position
        $targetRetirementAge = (int) ($analysisData['summary']['target_retirement_age'] ?? 67);
        $yearsToRetirement = (int) ($analysisData['summary']['years_to_retirement'] ?? 999);
        $totalDCValue = (float) ($analysisData['summary']['total_dc_value'] ?? $analysisData['summary']['current_dc_value'] ?? 0);
        $targetIncome = (float) ($analysisData['summary']['target_retirement_income'] ?? 0);
        $projectedIncome = (float) ($analysisData['summary']['projected_retirement_income'] ?? 0);

        $trace[] = [
            'question' => 'What is the retirement target and current position?',
            'data_field' => 'Retirement position',
            'data_value' => 'Target retirement age '.$targetRetirementAge.' ('.$yearsToRetirement.' years away), Defined Contribution value £'.number_format($totalDCValue, 0).', target income £'.number_format($targetIncome, 0).'/year',
            'threshold' => 'Retirement position for decumulation assessment',
            'passed' => true,
            'explanation' => $userName.' aims to retire at '.$targetRetirementAge.' ('.$yearsToRetirement.' years away) with a target income of £'.number_format($targetIncome, 0).' per year. Current Defined Contribution pension value is £'.number_format($totalDCValue, 0).'.',
        ];

        // Step 3: Pension details for decumulation context
        $dcPensions = \App\Models\DCPension::where('user_id', $userId)->get();
        $pensionSummaries = [];
        foreach ($dcPensions as $pension) {
            $name = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            $pensionSummaries[] = $name.': £'.number_format($fundValue, 0);
        }

        $trace[] = [
            'question' => 'What Defined Contribution pensions will need a decumulation strategy?',
            'data_field' => 'Defined Contribution pensions',
            'data_value' => count($dcPensions).' pensions, total value £'.number_format($totalDCValue, 0),
            'threshold' => 'Pensions approaching decumulation phase',
            'passed' => true,
            'explanation' => count($pensionSummaries) > 0
                ? implode('. ', $pensionSummaries).'. These pensions will need a drawdown or annuity strategy when '.$userName.' retires.'
                : 'No Defined Contribution pensions found.',
        ];

        // Step 4: Years to retirement threshold check
        $yearsThreshold = (int) ($config['years_threshold'] ?? 10);

        $withinThreshold = $yearsToRetirement <= $yearsThreshold;
        $trace[] = [
            'question' => 'Is '.$userName.' within '.$yearsThreshold.' years of their target retirement age?',
            'data_field' => 'years_to_retirement',
            'data_value' => $yearsToRetirement.' years until retirement at age '.$targetRetirementAge,
            'threshold' => $yearsThreshold.' years or fewer',
            'passed' => $withinThreshold,
            'explanation' => $withinThreshold
                ? 'With '.$yearsToRetirement.' years until retirement, '.$userName.' should be actively planning a decumulation strategy for £'.number_format($totalDCValue, 0).' in pension funds.'
                : 'Retirement is '.$yearsToRetirement.' years away — decumulation planning is not yet urgent for '.$userName.'.',
        ];

        // Step 5: Pre-retirement check
        $isPositive = $yearsToRetirement > 0;
        $trace[] = [
            'question' => 'Is '.$userName.' still pre-retirement?',
            'data_field' => 'years_to_retirement',
            'data_value' => $yearsToRetirement.' years',
            'threshold' => 'Greater than 0',
            'passed' => $isPositive,
            'explanation' => $isPositive
                ? $userName.' has not yet reached the target retirement age of '.$targetRetirementAge.' — there is time to plan a decumulation strategy.'
                : $userName.' has already reached or passed the target retirement age of '.$targetRetirementAge.'.',
        ];

        if ($yearsToRetirement > $yearsThreshold || $yearsToRetirement <= 0) {
            return [];
        }

        // Step 6: Recommendation
        $taxFreeLump = round($totalDCValue * 0.25, 0);
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Plan decumulation strategy for £'.number_format($totalDCValue, 0).' pension funds within '.$yearsToRetirement.' years',
            'threshold' => 'Approaching retirement — decumulation planning needed',
            'passed' => false,
            'explanation' => 'With '.$yearsToRetirement.' years until retirement, '.$userName.' should consider: the 25% tax-free lump sum (up to £'.number_format($taxFreeLump, 0).'), whether to use drawdown, annuity, or a combination, and the investment strategy shift towards lower risk as retirement approaches. Projected income is £'.number_format($projectedIncome, 0).'/year against a target of £'.number_format($targetIncome, 0).'/year.',
        ];

        $vars = [
            'years_to_retirement' => (string) $yearsToRetirement,
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Review your decumulation strategy.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Pension consolidation opportunity: triggers when user has 3+ DC pensions.
     */
    private function evaluatePensionConsolidation(
        RetirementActionDefinition $definition,
        $dcPensions,
        array $config,
        int $priority
    ): array {
        $trace = [];

        // Resolve user for profile context
        $firstPension = $dcPensions->first();
        $user = $firstPension ? User::find($firstPension->user_id) : null;
        $userName = $user ? ($user->first_name.' '.$user->surname) : 'Unknown';
        $dob = $user?->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not set';
        $age = $user?->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $employmentStatus = $user?->employment_status ?? 'Not set';
        $grossIncome = (float) ($user?->annual_employment_income ?? 0);

        // Step 1: User profile
        $trace[] = [
            'question' => 'What is the user\'s personal and employment profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', born '.$dob.($age !== null ? ' (age '.$age.')' : '').', '.$employmentStatus.', gross income £'.number_format($grossIncome, 0),
            'threshold' => 'Required for pension consolidation assessment',
            'passed' => true,
            'explanation' => $userName.' is '.$employmentStatus.' with a gross annual income of £'.number_format($grossIncome, 0).'.',
        ];

        // Step 2: Individual pension details
        $pensionCount = $dcPensions->count();
        $totalFundValue = 0;
        $totalAnnualFees = 0;
        $pensionSummaries = [];

        foreach ($dcPensions as $pension) {
            $name = $pension->provider.' '.($pension->scheme_name ?? $pension->pension_type ?? 'Pension');
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            $platformFee = (float) ($pension->platform_fee_percent ?? 0);
            $annualFee = round($fundValue * $platformFee / 100, 0);
            $totalFundValue += $fundValue;
            $totalAnnualFees += $annualFee;
            $pensionSummaries[] = $name.': £'.number_format($fundValue, 0).', platform fee '.number_format($platformFee, 2).'% (£'.number_format($annualFee, 0).'/year)';
        }

        $trace[] = [
            'question' => 'What Defined Contribution pensions does '.$userName.' hold?',
            'data_field' => 'Defined Contribution pensions',
            'data_value' => $pensionCount.' pensions, total value £'.number_format($totalFundValue, 0).', total annual fees £'.number_format($totalAnnualFees, 0),
            'threshold' => 'All pensions listed for consolidation assessment',
            'passed' => true,
            'explanation' => implode('. ', $pensionSummaries) ?: 'No Defined Contribution pensions found.',
        ];

        // Step 3: Pension count threshold check
        $minPensionCount = (int) ($config['min_pension_count'] ?? 3);

        $meetsThreshold = $pensionCount >= $minPensionCount;
        $trace[] = [
            'question' => 'Does '.$userName.' have '.$minPensionCount.' or more Defined Contribution pensions where consolidation could be beneficial?',
            'data_field' => 'dc_pension_count',
            'data_value' => $pensionCount.' pensions',
            'threshold' => $minPensionCount.' or more',
            'passed' => $meetsThreshold,
            'explanation' => $meetsThreshold
                ? $userName.' has '.$pensionCount.' Defined Contribution pensions with a combined value of £'.number_format($totalFundValue, 0).'. Consolidation could reduce fees (currently £'.number_format($totalAnnualFees, 0).'/year across all pensions) and simplify management.'
                : $userName.' has '.$pensionCount.' Defined Contribution pensions, below the '.$minPensionCount.'-pension threshold for consolidation review.',
        ];

        if (! $meetsThreshold) {
            return [];
        }

        // Step 4: Fee comparison context
        $averageFee = $pensionCount > 0 ? $totalAnnualFees / $pensionCount : 0;
        $highestFee = 0;
        $highestFeePension = '';
        $lowestFee = PHP_FLOAT_MAX;
        $lowestFeePension = '';

        foreach ($dcPensions as $pension) {
            $fee = (float) ($pension->platform_fee_percent ?? 0);
            $name = $pension->provider.' '.($pension->scheme_name ?? 'Pension');
            if ($fee > $highestFee) {
                $highestFee = $fee;
                $highestFeePension = $name;
            }
            if ($fee < $lowestFee) {
                $lowestFee = $fee;
                $lowestFeePension = $name;
            }
        }

        $trace[] = [
            'question' => 'What is the fee comparison across pensions?',
            'data_field' => 'Fee analysis',
            'data_value' => 'Total fees £'.number_format($totalAnnualFees, 0).'/year, highest: '.number_format($highestFee, 2).'% ('.$highestFeePension.'), lowest: '.number_format($lowestFee, 2).'% ('.$lowestFeePension.')',
            'threshold' => 'Fee comparison for consolidation decision',
            'passed' => true,
            'explanation' => 'Across '.$pensionCount.' pensions, '.$userName.' pays £'.number_format($totalAnnualFees, 0).' per year in platform fees. The highest fee is '.number_format($highestFee, 2).'% ('.$highestFeePension.') and the lowest is '.number_format($lowestFee, 2).'% ('.$lowestFeePension.'). Consolidating into a lower-fee provider could save money over time.',
        ];

        // Step 5: Recommendation
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Review consolidation of '.$pensionCount.' pensions (£'.number_format($totalFundValue, 0).' total) to reduce fees and simplify management',
            'threshold' => $pensionCount.' pensions exceed the '.$minPensionCount.'-pension consolidation threshold',
            'passed' => false,
            'explanation' => $userName.' should consider consolidating '.$pensionCount.' Defined Contribution pensions into fewer accounts. Compare fees, investment options, and any exit charges or guaranteed benefits before transferring. Consolidating £'.number_format($totalFundValue, 0).' into a single low-cost provider could reduce annual fees and make retirement planning simpler.',
        ];

        $vars = [
            'pension_count' => (string) $pensionCount,
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Compare fees and features before consolidating.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * High total fees: triggers per DC pension when platform + advisor + weighted OCF exceeds threshold.
     */
    private function evaluateHighPensionTotalFees(
        RetirementActionDefinition $definition,
        $dcPensions,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 1.0);
        $results = [];

        foreach ($dcPensions as $pension) {
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            if ($fundValue <= 0) {
                continue;
            }

            $platformFeePercent = $this->calculateAnnualisedPlatformFeePercent($pension);
            $advisorFeePercent = (float) ($pension->advisor_fee_percent ?? 0);
            $weightedOCF = $this->calculateWeightedOCF($pension);
            $totalFeePercent = $platformFeePercent + $advisorFeePercent + $weightedOCF;

            if ($totalFeePercent <= $threshold) {
                continue;
            }

            $annualFees = round($fundValue * $totalFeePercent / 100, 0);
            $pensionName = ($pension->provider ?? '').' '.($pension->scheme_name ?? 'Pension');

            $vars = [
                'pension_name' => trim($pensionName),
                'total_fee_percent' => number_format($totalFeePercent, 2),
                'annual_fees' => '£'.number_format($annualFees, 0),
            ];

            $results[] = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars),
                'impact' => ucfirst($definition->priority),
                'scope' => $definition->scope,
                'estimated_impact' => round($annualFees * 0.4, 2),
                'decision_trace' => [[
                    'question' => 'Are total fees on this pension above '.number_format($threshold, 1).'%?',
                    'data_field' => 'total_fee_percent',
                    'data_value' => number_format($totalFeePercent, 2).'% (platform '.number_format($platformFeePercent, 2).'% + advisor '.number_format($advisorFeePercent, 2).'% + fund OCF '.number_format($weightedOCF, 2).'%)',
                    'threshold' => number_format($threshold, 1).'%',
                    'passed' => false,
                    'explanation' => trim($pensionName).' has total annual fees of '.number_format($totalFeePercent, 2).'% (£'.number_format($annualFees, 0).'/year), exceeding the '.number_format($threshold, 1).'% threshold.',
                ]],
            ];
            $priority++;
        }

        return $results;
    }

    /**
     * High platform fees: triggers per DC pension when platform fee alone exceeds threshold.
     */
    private function evaluateHighPensionPlatformFees(
        RetirementActionDefinition $definition,
        $dcPensions,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 0.8);
        $results = [];

        foreach ($dcPensions as $pension) {
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            if ($fundValue <= 0) {
                continue;
            }

            $platformFeePercent = $this->calculateAnnualisedPlatformFeePercent($pension);

            if ($platformFeePercent <= $threshold) {
                continue;
            }

            $pensionName = ($pension->provider ?? '').' '.($pension->scheme_name ?? 'Pension');

            $vars = [
                'pension_name' => trim($pensionName),
                'platform_fee_percent' => number_format($platformFeePercent, 2),
            ];

            $results[] = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars),
                'impact' => ucfirst($definition->priority),
                'scope' => $definition->scope,
                'decision_trace' => [[
                    'question' => 'Is the platform fee above '.number_format($threshold, 1).'%?',
                    'data_field' => 'platform_fee_percent',
                    'data_value' => number_format($platformFeePercent, 2).'%',
                    'threshold' => number_format($threshold, 1).'%',
                    'passed' => false,
                    'explanation' => trim($pensionName).' has a platform fee of '.number_format($platformFeePercent, 2).'%, above the '.number_format($threshold, 1).'% threshold.',
                ]],
            ];
            $priority++;
        }

        return $results;
    }

    /**
     * High fund fees: triggers per DC pension when weighted average OCF exceeds threshold.
     */
    private function evaluateHighPensionFundFees(
        RetirementActionDefinition $definition,
        $dcPensions,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 0.5);
        $results = [];

        foreach ($dcPensions as $pension) {
            $fundValue = (float) ($pension->current_fund_value ?? 0);
            if ($fundValue <= 0 || ! $pension->relationLoaded('holdings') || $pension->holdings->isEmpty()) {
                continue;
            }

            $weightedOCF = $this->calculateWeightedOCF($pension);

            if ($weightedOCF <= $threshold) {
                continue;
            }

            $potentialSaving = round($fundValue * ($weightedOCF - 0.25) / 100, 0);
            $pensionName = ($pension->provider ?? '').' '.($pension->scheme_name ?? 'Pension');

            $vars = [
                'pension_name' => trim($pensionName),
                'weighted_ocf' => number_format($weightedOCF, 2),
                'potential_saving' => '£'.number_format(max(0, $potentialSaving), 0),
            ];

            $results[] = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars),
                'impact' => ucfirst($definition->priority),
                'scope' => $definition->scope,
                'decision_trace' => [[
                    'question' => 'Is the weighted fund charge above '.number_format($threshold, 1).'%?',
                    'data_field' => 'weighted_ocf',
                    'data_value' => number_format($weightedOCF, 2).'%',
                    'threshold' => number_format($threshold, 1).'%',
                    'passed' => false,
                    'explanation' => trim($pensionName).' has a weighted average fund charge of '.number_format($weightedOCF, 2).'%, above the '.number_format($threshold, 1).'% threshold. Switching to index funds could save approximately £'.number_format(max(0, $potentialSaving), 0).'/year.',
                ]],
            ];
            $priority++;
        }

        return $results;
    }

    /**
     * Calculate annualised platform fee as a percentage for a DC pension.
     * Handles both percentage and fixed fee types.
     */
    private function calculateAnnualisedPlatformFeePercent(DCPension $pension): float
    {
        $fundValue = (float) ($pension->current_fund_value ?? 0);

        if (($pension->platform_fee_type ?? 'percentage') === 'fixed' && $fundValue > 0) {
            $amount = (float) ($pension->platform_fee_amount ?? 0);
            $frequency = $pension->platform_fee_frequency ?? 'annually';
            $annualAmount = match ($frequency) {
                'monthly' => $amount * 12,
                'quarterly' => $amount * 4,
                default => $amount,
            };

            return ($annualAmount / $fundValue) * 100;
        }

        return (float) ($pension->platform_fee_percent ?? 0);
    }

    /**
     * Calculate weighted average OCF across a pension's holdings.
     */
    private function calculateWeightedOCF(DCPension $pension): float
    {
        $fundValue = (float) ($pension->current_fund_value ?? 0);
        if ($fundValue <= 0 || ! $pension->relationLoaded('holdings') || $pension->holdings->isEmpty()) {
            return 0.0;
        }

        $totalWeightedOCF = $pension->holdings->sum(function ($holding) use ($fundValue) {
            $holdingValue = $fundValue * ((float) ($holding->allocation_percent ?? 0)) / 100;

            return $holdingValue * ((float) ($holding->ocf_percent ?? 0));
        });

        return $totalWeightedOCF / $fundValue;
    }

    /**
     * Evaluate a single goal-sourced trigger against a goal.
     */
    private function evaluateGoalTrigger(RetirementActionDefinition $definition, array $goal, float $monthlyPensionContribution = 0): ?array
    {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            'linked_goal_no_monthly_contribution' => $this->evaluateGoalNoContribution($definition, $goal, $monthlyPensionContribution),
            'linked_goal_off_track' => $this->evaluateGoalOffTrack($definition, $goal, $monthlyPensionContribution),
            'goal_months_remaining_below_and_progress_below' => $this->evaluateGoalDeadline($definition, $goal, $config),
            default => null,
        };
    }

    /**
     * Goal no contribution: triggers when monthly contribution is zero but required > 0.
     *
     * Pension contributions count as effective contributions for retirement goals,
     * so if the user has pension contributions, this won't fire even if the goal's
     * own monthly_contribution field is zero.
     */
    private function evaluateGoalNoContribution(RetirementActionDefinition $definition, array $goal, float $monthlyPensionContribution = 0): ?array
    {
        $trace = [];

        // Step 1: Goal details
        $goalName = $goal['name'] ?? 'Unnamed goal';
        $targetAmount = (float) ($goal['target_amount'] ?? 0);
        $currentAmount = (float) ($goal['current_amount'] ?? 0);
        $progress = (float) ($goal['progress_percentage'] ?? 0);
        $monthsRemaining = (int) ($goal['months_remaining'] ?? 0);

        $trace[] = [
            'question' => 'What are the details of this retirement goal?',
            'data_field' => 'Goal details',
            'data_value' => $goalName.': target £'.number_format($targetAmount, 0).', current £'.number_format($currentAmount, 0).' ('.number_format($progress, 1).'% complete)'.($monthsRemaining > 0 ? ', '.$monthsRemaining.' months remaining' : ''),
            'threshold' => 'Goal record details',
            'passed' => true,
            'explanation' => 'The "'.$goalName.'" goal has a target of £'.number_format($targetAmount, 0).' with £'.number_format($currentAmount, 0).' accumulated so far ('.number_format($progress, 1).'% progress).'.($monthsRemaining > 0 ? ' '.$monthsRemaining.' months remain until the target date.' : ''),
        ];

        // Step 2: Contribution assessment (goal + pension)
        $monthlyContribution = (float) ($goal['monthly_contribution'] ?? 0);
        $effectiveContribution = $monthlyContribution + $monthlyPensionContribution;

        $hasNoContribution = $effectiveContribution <= 0;
        $trace[] = [
            'question' => 'Are there any contributions towards this goal (including pension contributions)?',
            'data_field' => 'Contribution breakdown',
            'data_value' => 'Goal contribution: £'.number_format($monthlyContribution, 0).'/month, pension contribution: £'.number_format($monthlyPensionContribution, 0).'/month, effective total: £'.number_format($effectiveContribution, 0).'/month',
            'threshold' => 'Effective contribution greater than £0',
            'passed' => $hasNoContribution,
            'explanation' => $hasNoContribution
                ? 'No contributions are being made towards "'.$goalName.'". Monthly goal contribution is £'.number_format($monthlyContribution, 0).' and pension contributions are £'.number_format($monthlyPensionContribution, 0).'.'
                : 'Effective contributions of £'.number_format($effectiveContribution, 0).' per month are being made (£'.number_format($monthlyContribution, 0).' goal + £'.number_format($monthlyPensionContribution, 0).' pension).',
        ];

        // Step 3: Required contribution check
        $required = (float) ($goal['required_monthly_contribution'] ?? 0);
        $shortfall = max(0, $required - $effectiveContribution);

        $hasRequirement = $required > 0;
        $trace[] = [
            'question' => 'Is a monthly contribution required to meet this goal on time?',
            'data_field' => 'required_monthly_contribution',
            'data_value' => '£'.number_format($required, 0).' per month required'.($shortfall > 0 ? ', shortfall of £'.number_format($shortfall, 0).'/month' : ''),
            'threshold' => 'Greater than £0 per month',
            'passed' => $hasRequirement,
            'explanation' => $hasRequirement
                ? '£'.number_format($required, 0).' per month is needed to stay on track for "'.$goalName.'". With zero effective contributions, the full £'.number_format($required, 0).' is a shortfall.'
                : 'No monthly contribution is required for "'.$goalName.'".',
        ];

        if ($effectiveContribution > 0 || $required <= 0) {
            return null;
        }

        // Step 4: Recommendation
        $annualRequired = $required * 12;
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Start contributing £'.number_format($required, 0).'/month (£'.number_format($annualRequired, 0).'/year) towards "'.$goalName.'"',
            'threshold' => 'No contributions being made towards a goal requiring £'.number_format($required, 0).'/month',
            'passed' => false,
            'explanation' => 'To reach the £'.number_format($targetAmount, 0).' target for "'.$goalName.'", contributions of £'.number_format($required, 0).' per month (£'.number_format($annualRequired, 0).' per year) need to begin. Currently at '.number_format($progress, 1).'% with £'.number_format($currentAmount, 0).' accumulated and no ongoing contributions.',
        ];

        $vars = [
            'goal_name' => $goalName,
            'required_monthly' => $this->formatCurrency($required),
            'target_amount' => $this->formatCurrency($targetAmount),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal off track: triggers when goal is_on_track is false.
     *
     * Pension contributions are factored in: if the effective monthly contribution
     * (goal contribution + pension contributions) meets or exceeds the required
     * amount, the goal is treated as on-track even if the goal record itself
     * says otherwise (because the goal system doesn't track pension contributions).
     */
    private function evaluateGoalOffTrack(RetirementActionDefinition $definition, array $goal, float $monthlyPensionContribution = 0): ?array
    {
        $trace = [];

        // Step 1: Goal details
        $goalName = $goal['name'] ?? 'Unnamed goal';
        $targetAmount = (float) ($goal['target_amount'] ?? 0);
        $currentAmount = (float) ($goal['current_amount'] ?? 0);
        $progress = (float) ($goal['progress_percentage'] ?? 0);
        $monthsRemaining = (int) ($goal['months_remaining'] ?? 0);

        $trace[] = [
            'question' => 'What are the details of this retirement goal?',
            'data_field' => 'Goal details',
            'data_value' => $goalName.': target £'.number_format($targetAmount, 0).', current £'.number_format($currentAmount, 0).' ('.number_format($progress, 1).'% complete)'.($monthsRemaining > 0 ? ', '.$monthsRemaining.' months remaining' : ''),
            'threshold' => 'Goal record details',
            'passed' => true,
            'explanation' => 'The "'.$goalName.'" goal has a target of £'.number_format($targetAmount, 0).' with £'.number_format($currentAmount, 0).' accumulated so far ('.number_format($progress, 1).'% progress).',
        ];

        // Step 2: Contribution breakdown
        $monthlyContribution = (float) ($goal['monthly_contribution'] ?? 0);
        $effectiveContribution = $monthlyContribution + $monthlyPensionContribution;

        $hasContribution = $effectiveContribution > 0;
        $trace[] = [
            'question' => 'Are there any effective contributions towards this goal?',
            'data_field' => 'Contribution breakdown',
            'data_value' => 'Goal contribution: £'.number_format($monthlyContribution, 0).'/month, pension contribution: £'.number_format($monthlyPensionContribution, 0).'/month, effective total: £'.number_format($effectiveContribution, 0).'/month',
            'threshold' => 'Effective contribution greater than £0',
            'passed' => $hasContribution,
            'explanation' => $hasContribution
                ? 'Effective contributions of £'.number_format($effectiveContribution, 0).' per month are being made towards "'.$goalName.'" (£'.number_format($monthlyContribution, 0).' goal + £'.number_format($monthlyPensionContribution, 0).' pension).'
                : 'No effective contributions — this case is handled by the no-contribution check.',
        ];

        // Skip if no effective contribution (caught by no-contribution check)
        if (! $hasContribution) {
            return null;
        }

        // Step 3: Required contribution vs effective
        $required = (float) ($goal['required_monthly_contribution'] ?? 0);
        $shortfall = max(0, $required - $effectiveContribution);

        $meetsRequired = $required > 0 && $effectiveContribution >= $required;
        $trace[] = [
            'question' => 'Do effective contributions meet the required monthly amount for this goal?',
            'data_field' => 'Contribution vs required',
            'data_value' => 'Effective: £'.number_format($effectiveContribution, 0).'/month vs required: £'.number_format($required, 0).'/month'.($shortfall > 0 ? ', shortfall: £'.number_format($shortfall, 0).'/month' : ''),
            'threshold' => '£'.number_format($required, 0).' per month required',
            'passed' => ! $meetsRequired,
            'explanation' => $meetsRequired
                ? 'Effective contributions of £'.number_format($effectiveContribution, 0).' meet the required £'.number_format($required, 0).' — "'.$goalName.'" is effectively on track when pension contributions are included.'
                : 'Effective contributions of £'.number_format($effectiveContribution, 0).' fall £'.number_format($shortfall, 0).' short of the required £'.number_format($required, 0).' per month.',
        ];

        // If pension contributions bring the effective contribution up to the required
        // amount, treat the goal as on-track regardless of the goal record's is_on_track
        if ($meetsRequired) {
            return null;
        }

        // Step 4: On-track status check
        $isOffTrack = ! ($goal['is_on_track'] ?? true);
        $trace[] = [
            'question' => 'Is "'.$goalName.'" reported as off track?',
            'data_field' => 'is_on_track',
            'data_value' => ($goal['is_on_track'] ?? true) ? 'On track' : 'Off track',
            'threshold' => 'Goal must be off track',
            'passed' => $isOffTrack,
            'explanation' => $isOffTrack
                ? '"'.$goalName.'" is off track at '.number_format($progress, 1).'% progress with a shortfall of £'.number_format($shortfall, 0).'/month against the required £'.number_format($required, 0).'/month.'
                : '"'.$goalName.'" is reported as on track despite the contribution shortfall.',
        ];

        // Also skip if the goal itself reports on-track
        if (! $isOffTrack) {
            return null;
        }

        // Step 5: Recommendation
        $annualShortfall = $shortfall * 12;
        $remainingToTarget = max(0, $targetAmount - $currentAmount);
        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => 'Increase contributions by £'.number_format($shortfall, 0).'/month to get "'.$goalName.'" back on track',
            'threshold' => '£'.number_format($remainingToTarget, 0).' still needed to reach target',
            'passed' => false,
            'explanation' => '"'.$goalName.'" needs an additional £'.number_format($shortfall, 0).' per month (£'.number_format($annualShortfall, 0).' per year) to get back on track. Currently at '.number_format($progress, 1).'% with £'.number_format($remainingToTarget, 0).' remaining to reach the £'.number_format($targetAmount, 0).' target.',
        ];

        $vars = [
            'goal_name' => $goalName,
            'progress' => number_format($progress, 0),
            'shortfall' => $this->formatCurrency($shortfall),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal deadline approaching: triggers when months remaining and progress are below thresholds.
     */
    private function evaluateGoalDeadline(RetirementActionDefinition $definition, array $goal, array $config): ?array
    {
        $trace = [];

        // Step 1: Goal details
        $goalName = $goal['name'] ?? 'Unnamed goal';
        $targetAmount = (float) ($goal['target_amount'] ?? 0);
        $currentAmount = (float) ($goal['current_amount'] ?? 0);
        $progress = (float) ($goal['progress_percentage'] ?? 0);
        $monthsRemaining = (int) ($goal['months_remaining'] ?? 0);
        $monthlyContribution = (float) ($goal['monthly_contribution'] ?? 0);

        $trace[] = [
            'question' => 'What are the details of this retirement goal?',
            'data_field' => 'Goal details',
            'data_value' => $goalName.': target £'.number_format($targetAmount, 0).', current £'.number_format($currentAmount, 0).' ('.number_format($progress, 1).'%), '.$monthsRemaining.' months remaining, contributing £'.number_format($monthlyContribution, 0).'/month',
            'threshold' => 'Goal record details',
            'passed' => true,
            'explanation' => 'The "'.$goalName.'" goal has a target of £'.number_format($targetAmount, 0).' with £'.number_format($currentAmount, 0).' accumulated ('.number_format($progress, 1).'%). Monthly contribution is £'.number_format($monthlyContribution, 0).' with '.$monthsRemaining.' months remaining.',
        ];

        // Step 2: On-track status (this trigger only fires for on-track goals)
        $isOnTrack = $goal['is_on_track'] ?? true;
        $trace[] = [
            'question' => 'Is "'.$goalName.'" currently reported as on track?',
            'data_field' => 'is_on_track',
            'data_value' => $isOnTrack ? 'On track' : 'Off track',
            'threshold' => 'Must be on track (off-track goals are handled separately)',
            'passed' => $isOnTrack,
            'explanation' => $isOnTrack
                ? '"'.$goalName.'" is reported as on track — checking whether the deadline is approaching with low progress.'
                : '"'.$goalName.'" is already off track — this is handled by the off-track check instead.',
        ];

        // Only triggers for goals that are otherwise on-track (not caught by off-track check)
        if (! $isOnTrack) {
            return null;
        }

        // Step 3: Deadline proximity check
        $monthsThreshold = (int) ($config['months_threshold'] ?? 6);

        $deadlineApproaching = $monthsRemaining <= $monthsThreshold;
        $trace[] = [
            'question' => 'Is the goal deadline approaching (within '.$monthsThreshold.' months)?',
            'data_field' => 'months_remaining',
            'data_value' => $monthsRemaining.' months remaining',
            'threshold' => $monthsThreshold.' months or fewer',
            'passed' => $deadlineApproaching,
            'explanation' => $deadlineApproaching
                ? 'Only '.$monthsRemaining.' months remain until the "'.$goalName.'" deadline — urgent attention needed.'
                : 'The "'.$goalName.'" deadline is '.$monthsRemaining.' months away — not yet urgent.',
        ];

        // Step 4: Progress vs threshold check
        $progressThreshold = (float) ($config['progress_threshold'] ?? 75);
        $remainingToTarget = max(0, $targetAmount - $currentAmount);

        $progressBelowThreshold = $progress < $progressThreshold;
        $trace[] = [
            'question' => 'Is progress below '.round($progressThreshold, 0).'% with the deadline approaching?',
            'data_field' => 'Progress assessment',
            'data_value' => number_format($progress, 1).'% progress (£'.number_format($currentAmount, 0).' of £'.number_format($targetAmount, 0).'), £'.number_format($remainingToTarget, 0).' remaining',
            'threshold' => round($progressThreshold, 0).'% progress expected by this point',
            'passed' => $progressBelowThreshold,
            'explanation' => $progressBelowThreshold
                ? 'Progress of '.number_format($progress, 1).'% is below the '.round($progressThreshold, 0).'% threshold with only '.$monthsRemaining.' months remaining. £'.number_format($remainingToTarget, 0).' is still needed to reach the £'.number_format($targetAmount, 0).' target.'
                : 'Progress of '.number_format($progress, 1).'% is adequate relative to the deadline.',
        ];

        if ($monthsRemaining > $monthsThreshold || $progress >= $progressThreshold) {
            return null;
        }

        // Step 5: Recommendation with required monthly increase
        $requiredMonthly = $monthsRemaining > 0 ? round($remainingToTarget / $monthsRemaining, 0) : $remainingToTarget;
        $additionalNeeded = max(0, $requiredMonthly - $monthlyContribution);

        $trace[] = [
            'question' => 'What is the recommended action?',
            'data_field' => 'Recommendation',
            'data_value' => '£'.number_format($remainingToTarget, 0).' needed in '.$monthsRemaining.' months = £'.number_format($requiredMonthly, 0).'/month'.($additionalNeeded > 0 ? ' (£'.number_format($additionalNeeded, 0).'/month more than current)' : ''),
            'threshold' => 'Deadline approaching with low progress on "'.$goalName.'"',
            'passed' => false,
            'explanation' => 'To reach the £'.number_format($targetAmount, 0).' target in '.$monthsRemaining.' months, contributions need to be £'.number_format($requiredMonthly, 0).' per month'.($additionalNeeded > 0 ? ' — £'.number_format($additionalNeeded, 0).' more than the current £'.number_format($monthlyContribution, 0).'/month' : '').'. Consider increasing contributions or adjusting the target date.',
        ];

        $vars = [
            'goal_name' => $goalName,
            'progress' => number_format($progress, 0),
            'months_remaining' => (string) $monthsRemaining,
            'target_amount' => $this->formatCurrency($targetAmount),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Calculate annual contribution for a single pension.
     */
    private function calculateAnnualContribution(DCPension $pension): float
    {
        $monthly = (float) ($pension->monthly_contribution_amount ?? 0);

        if ($monthly > 0) {
            return $monthly * 12;
        }

        $salary = (float) ($pension->annual_salary ?? 0);
        $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
        $employerPct = (float) ($pension->employer_contribution_percent ?? 0);

        if ($salary > 0 && ($employeePct + $employerPct) > 0) {
            return $salary * ($employeePct + $employerPct) / 100;
        }

        return 0;
    }
}
