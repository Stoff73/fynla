<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Agents\CoordinatingAgent;
use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\Liability;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use App\Services\TaxConfigService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiToolExecutor
{
    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly ProtectionAgent $protectionAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly InvestmentAgent $investmentAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly EstateAgent $estateAgent,
        private readonly GoalsAgent $goalsAgent,
        private readonly TaxConfigService $taxConfig,
        private readonly NetWorthService $netWorthService,
    ) {}

    /**
     * Execute a tool call and return the result.
     */
    public function execute(string $toolName, array $input, User $user): array
    {
        $isPreviewUser = $user->is_preview_user;

        try {
            return match ($toolName) {
                'navigate_to_page' => $this->navigateToPage($input),
                'get_module_analysis' => $this->getModuleAnalysis($input, $user),
                'run_what_if_scenario' => $this->runWhatIfScenario($input, $user),
                'get_recommendations' => $this->getRecommendations($user),
                'get_tax_information' => $this->getTaxInformation($input),
                'generate_financial_plan' => $this->generateFinancialPlan($user),
                'create_goal' => $this->createGoal($input, $user, $isPreviewUser),
                'create_life_event' => $this->createLifeEvent($input, $user, $isPreviewUser),
                'create_savings_account' => $this->createSavingsAccount($input, $user, $isPreviewUser),
                'create_investment_account' => $this->createInvestmentAccount($input, $user, $isPreviewUser),
                'create_pension' => $this->createPension($input, $user, $isPreviewUser),
                'create_property' => $this->createProperty($input, $user, $isPreviewUser),
                'create_mortgage' => $this->createMortgage($input, $user, $isPreviewUser),
                'create_protection_policy' => $this->createProtectionPolicy($input, $user, $isPreviewUser),
                'create_estate_asset' => $this->createEstateAsset($input, $user, $isPreviewUser),
                'create_estate_liability' => $this->createEstateLiability($input, $user, $isPreviewUser),
                'create_estate_gift' => $this->createEstateGift($input, $user, $isPreviewUser),
                default => ['error' => "Unknown tool: {$toolName}"],
            };
        } catch (\Exception $e) {
            Log::error('[AiToolExecutor] Tool execution failed', [
                'tool' => $toolName,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Tool execution failed. Please try again.'];
        }
    }

    private function navigateToPage(array $input): array
    {
        return [
            'action' => 'navigate',
            'route_path' => $input['route_path'],
            'description' => $input['description'] ?? '',
        ];
    }

    private function getModuleAnalysis(array $input, User $user): array
    {
        $module = $input['module'];

        $analysis = match ($module) {
            'protection' => $this->protectionAgent->analyze($user->id),
            'savings' => $this->savingsAgent->analyze($user->id),
            'investment' => $this->investmentAgent->analyze($user->id),
            'retirement' => $this->retirementAgent->analyze($user->id),
            'estate' => $this->estateAgent->analyze($user->id),
            'goals' => $this->goalsAgent->analyze($user->id),
            'holistic' => $this->coordinatingAgent->orchestrateAnalysis($user->id),
            default => ['error' => "Unknown module: {$module}"],
        };

        return $this->summariseAnalysis($module, $analysis);
    }

    private function runWhatIfScenario(array $input, User $user): array
    {
        $module = $input['module'];
        $parameters = $input['parameters'] ?? [];

        $agent = match ($module) {
            'protection' => $this->protectionAgent,
            'savings' => $this->savingsAgent,
            'investment' => $this->investmentAgent,
            'retirement' => $this->retirementAgent,
            default => null,
        };

        if (! $agent) {
            return ['error' => "Scenarios not available for module: {$module}"];
        }

        return $agent->buildScenarios($user->id, $parameters);
    }

    private function getRecommendations(User $user): array
    {
        $analysis = $this->coordinatingAgent->orchestrateAnalysis($user->id);

        return [
            'recommendations' => $analysis['ranked_recommendations'] ?? [],
            'total' => count($analysis['ranked_recommendations'] ?? []),
            'surplus' => $analysis['available_surplus'] ?? 0,
        ];
    }

    private function getTaxInformation(array $input): array
    {
        $topic = $input['topic'];

        return match ($topic) {
            'income_tax' => $this->taxConfig->getIncomeTax(),
            'capital_gains' => $this->taxConfig->getCapitalGainsTax(),
            'inheritance_tax' => $this->taxConfig->getInheritanceTax(),
            'isa_allowances' => $this->taxConfig->getISAAllowances(),
            'pension_allowances' => $this->taxConfig->getPensionAllowances(),
            default => ['error' => "Unknown tax topic: {$topic}"],
        };
    }

    private function createGoal(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return [
                'blocked' => true,
                'reason' => 'You are in preview mode. Goal creation is not available — please create a real account to save goals.',
            ];
        }

        $goal = Goal::create([
            'user_id' => $user->id,
            'goal_name' => $input['name'],
            'goal_type' => $input['goal_type'],
            'target_amount' => $input['target_amount'],
            'target_date' => $input['target_date'],
            'priority' => $input['priority'],
            'status' => 'active',
            'current_amount' => 0,
            'start_date' => now()->toDateString(),
        ]);

        return [
            'created' => true,
            'entity_type' => 'goal',
            'entity_id' => $goal->id,
            'name' => $goal->goal_name,
            'message' => "Goal \"{$goal->goal_name}\" created successfully.",
        ];
    }

    private function createLifeEvent(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return [
                'blocked' => true,
                'reason' => 'You are in preview mode. Life event creation is not available — please create a real account to save life events.',
            ];
        }

        $impactType = $this->resolveImpactType($input['event_type']);

        $lifeEvent = LifeEvent::create([
            'user_id' => $user->id,
            'event_name' => $input['description'],
            'event_type' => $input['event_type'],
            'description' => $input['description'],
            'amount' => $input['estimated_cost'] ?? 0,
            'impact_type' => $impactType,
            'expected_date' => $input['event_date'],
            'certainty' => 'likely',
            'status' => 'planned',
        ]);

        return [
            'created' => true,
            'entity_type' => 'life_event',
            'entity_id' => $lifeEvent->id,
            'name' => $lifeEvent->event_name,
            'message' => "Life event \"{$lifeEvent->event_name}\" created successfully.",
        ];
    }

    private function generateFinancialPlan(User $user): array
    {
        $plan = $this->coordinatingAgent->generateHolisticPlan($user->id);

        $summary = [];

        if (isset($plan['executive_summary'])) {
            $summary['executive_summary'] = $plan['executive_summary'];
        }

        if (isset($plan['overall_score'])) {
            $summary['overall_score'] = $plan['overall_score'];
        }

        $recommendations = $plan['ranked_recommendations'] ?? $plan['recommendations'] ?? [];
        $summary['top_recommendations'] = array_slice($recommendations, 0, 5);

        if (isset($plan['action_plan'])) {
            $summary['action_plan'] = array_slice($plan['action_plan'], 0, 5);
        }

        if (isset($plan['available_surplus'])) {
            $summary['monthly_surplus'] = $plan['available_surplus'];
        }

        if (isset($plan['cashflow_allocation'])) {
            $summary['suggested_allocation'] = $plan['cashflow_allocation'];
        }

        return $summary;
    }

    private function createSavingsAccount(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('savings account');
        }

        $isIsa = $input['is_isa'] ?? false;

        $account = SavingsAccount::create([
            'user_id' => $user->id,
            'account_name' => $input['account_name'],
            'account_type' => $input['account_type'] ?? 'easy_access',
            'institution' => $input['institution'] ?? null,
            'current_balance' => $input['current_balance'],
            'interest_rate' => $input['interest_rate'] ?? null,
            'access_type' => $this->resolveAccessType($input['account_type'] ?? 'easy_access'),
            'is_isa' => $isIsa,
            'isa_type' => $isIsa ? 'cash' : null,
            'is_emergency_fund' => $input['is_emergency_fund'] ?? false,
            'regular_contribution_amount' => $input['regular_contribution_amount'] ?? null,
            'contribution_frequency' => isset($input['regular_contribution_amount']) ? 'monthly' : null,
            'ownership_type' => $isIsa ? 'individual' : 'individual',
            'ownership_percentage' => 100,
            'country' => 'GB',
        ]);

        $this->invalidateModuleCache($user->id, 'savings');

        return [
            'created' => true,
            'entity_type' => 'savings_account',
            'entity_id' => $account->id,
            'name' => $account->account_name,
            'message' => "Savings account \"{$account->account_name}\" created with a balance of £" . number_format((float) $account->current_balance, 2) . '.',
        ];
    }

    private function createInvestmentAccount(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('investment account');
        }

        $accountType = $input['account_type'] ?? 'personal_investment_account';
        $isIsa = Str::contains($accountType, 'isa');

        $account = InvestmentAccount::create([
            'user_id' => $user->id,
            'account_name' => $input['account_name'],
            'account_type' => $accountType,
            'provider' => $input['provider'] ?? null,
            'current_value' => $input['current_value'],
            'monthly_contribution_amount' => $input['monthly_contribution_amount'] ?? 0,
            'contribution_frequency' => 'monthly',
            'platform_fee_percent' => $input['platform_fee_percent'] ?? null,
            'ownership_type' => $isIsa ? 'individual' : 'individual',
            'ownership_percentage' => 100,
            'country' => 'GB',
            'tax_year' => $this->taxConfig->getTaxYear(),
        ]);

        $this->invalidateModuleCache($user->id, 'investment');

        return [
            'created' => true,
            'entity_type' => 'investment_account',
            'entity_id' => $account->id,
            'name' => $account->account_name,
            'message' => "Investment account \"{$account->account_name}\" created with a value of £" . number_format((float) $account->current_value, 2) . '.',
        ];
    }

    private function createPension(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('pension');
        }

        $category = $input['pension_category'] ?? 'dc';

        if ($category === 'db') {
            return $this->createDBPension($input, $user);
        }

        return $this->createDCPension($input, $user);
    }

    private function createDCPension(array $input, User $user): array
    {
        $pension = DCPension::create([
            'user_id' => $user->id,
            'scheme_name' => $input['scheme_name'],
            'scheme_type' => $input['scheme_type'] ?? 'workplace',
            'provider' => $input['provider'] ?? null,
            'current_fund_value' => $input['current_fund_value'] ?? 0,
            'employee_contribution_percent' => $input['employee_contribution_percent'] ?? null,
            'employer_contribution_percent' => $input['employer_contribution_percent'] ?? null,
            'retirement_age' => $input['normal_retirement_age'] ?? 67,
        ]);

        $this->invalidateModuleCache($user->id, 'retirement');

        return [
            'created' => true,
            'entity_type' => 'dc_pension',
            'entity_id' => $pension->id,
            'name' => $pension->scheme_name,
            'message' => "Defined Contribution pension \"{$pension->scheme_name}\" created" .
                ($pension->current_fund_value > 0 ? " with a fund value of £" . number_format((float) $pension->current_fund_value, 2) : '') . '.',
        ];
    }

    private function createDBPension(array $input, User $user): array
    {
        $pension = DBPension::create([
            'user_id' => $user->id,
            'scheme_name' => $input['scheme_name'],
            'scheme_type' => $input['scheme_type'] ?? 'final_salary',
            'accrued_annual_pension' => $input['accrued_annual_pension'] ?? 0,
            'normal_retirement_age' => $input['normal_retirement_age'] ?? 67,
            'pensionable_service_years' => $input['pensionable_service_years'] ?? null,
        ]);

        $this->invalidateModuleCache($user->id, 'retirement');

        return [
            'created' => true,
            'entity_type' => 'db_pension',
            'entity_id' => $pension->id,
            'name' => $pension->scheme_name,
            'message' => "Defined Benefit pension \"{$pension->scheme_name}\" created" .
                ($pension->accrued_annual_pension > 0 ? " with an accrued pension of £" . number_format((float) $pension->accrued_annual_pension, 2) . ' per year' : '') . '.',
        ];
    }

    private function createProperty(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('property');
        }

        $property = Property::create([
            'user_id' => $user->id,
            'property_type' => $input['property_type'] ?? 'main_residence',
            'current_value' => $input['current_value'],
            'purchase_price' => $input['purchase_price'] ?? null,
            'purchase_date' => $input['purchase_date'] ?? null,
            'address_line_1' => $input['address_line_1'] ?? null,
            'postcode' => $input['postcode'] ?? null,
            'outstanding_mortgage' => $input['outstanding_mortgage'] ?? 0,
            'monthly_rental_income' => $input['monthly_rental_income'] ?? null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'country' => 'GB',
        ]);

        // Auto-create linked mortgage if outstanding mortgage provided
        $mortgageMessage = '';
        if (! empty($input['outstanding_mortgage']) && $input['outstanding_mortgage'] > 0) {
            Mortgage::create([
                'property_id' => $property->id,
                'user_id' => $user->id,
                'outstanding_balance' => $input['outstanding_mortgage'],
                'interest_rate' => $input['mortgage_rate'] ?? null,
                'lender_name' => $input['mortgage_lender'] ?? null,
                'mortgage_type' => 'repayment',
                'rate_type' => 'fixed',
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
                'country' => 'GB',
            ]);
            $mortgageMessage = ' A linked mortgage of £' . number_format((float) $input['outstanding_mortgage'], 2) . ' was also created.';
        }

        $this->invalidateModuleCache($user->id, 'property');

        return [
            'created' => true,
            'entity_type' => 'property',
            'entity_id' => $property->id,
            'name' => $input['address_line_1'] ?? ucfirst(str_replace('_', ' ', $input['property_type'] ?? 'main_residence')),
            'message' => "Property created with a value of £" . number_format((float) $property->current_value, 2) . '.' . $mortgageMessage,
        ];
    }

    private function createMortgage(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('mortgage');
        }

        // Try to match an existing property
        $propertyId = $this->resolvePropertyId($user, $input['property_address_hint'] ?? null);

        if (! $propertyId) {
            return ['error' => 'Could not find a matching property. Please create the property first, or provide a more specific address hint.'];
        }

        $mortgage = Mortgage::create([
            'property_id' => $propertyId,
            'user_id' => $user->id,
            'lender_name' => $input['lender_name'] ?? null,
            'outstanding_balance' => $input['outstanding_balance'],
            'interest_rate' => $input['interest_rate'] ?? null,
            'mortgage_type' => $input['mortgage_type'] ?? 'repayment',
            'rate_type' => $input['rate_type'] ?? 'fixed',
            'monthly_payment' => $input['monthly_payment'] ?? null,
            'remaining_term_months' => $input['remaining_term_months'] ?? null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'country' => 'GB',
        ]);

        $this->invalidateModuleCache($user->id, 'property');

        return [
            'created' => true,
            'entity_type' => 'mortgage',
            'entity_id' => $mortgage->id,
            'name' => ($input['lender_name'] ?? 'Mortgage') . ' mortgage',
            'message' => "Mortgage created with an outstanding balance of £" . number_format((float) $mortgage->outstanding_balance, 2) . '.',
        ];
    }

    private function createProtectionPolicy(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('protection policy');
        }

        $policyType = $input['policy_type'];

        if ($policyType === 'income_protection') {
            return $this->createIncomeProtection($input, $user);
        }

        if (in_array($policyType, ['standalone_ci', 'accelerated_ci'])) {
            return $this->createCriticalIllnessPolicy($input, $user);
        }

        return $this->createLifeInsurancePolicy($input, $user);
    }

    private function createLifeInsurancePolicy(array $input, User $user): array
    {
        $policy = LifeInsurancePolicy::create([
            'user_id' => $user->id,
            'policy_type' => $input['policy_type'],
            'provider' => $input['provider'] ?? null,
            'sum_assured' => $input['sum_assured'] ?? 0,
            'premium_amount' => $input['premium_amount'] ?? null,
            'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
            'policy_term_years' => $input['policy_term_years'] ?? null,
            'in_trust' => $input['in_trust'] ?? false,
        ]);

        $this->invalidateModuleCache($user->id, 'protection');

        $typeLabel = str_replace('_', ' ', $input['policy_type']);

        return [
            'created' => true,
            'entity_type' => 'life_insurance_policy',
            'entity_id' => $policy->id,
            'name' => ($input['provider'] ?? 'Life insurance') . ' - ' . $typeLabel,
            'message' => "Life insurance policy created" .
                ($policy->sum_assured > 0 ? " for £" . number_format((float) $policy->sum_assured, 2) : '') . '.',
        ];
    }

    private function createCriticalIllnessPolicy(array $input, User $user): array
    {
        $ciType = match ($input['policy_type']) {
            'standalone_ci' => 'standalone',
            'accelerated_ci' => 'accelerated',
            default => 'standalone',
        };

        $policy = CriticalIllnessPolicy::create([
            'user_id' => $user->id,
            'policy_type' => $ciType,
            'provider' => $input['provider'] ?? null,
            'sum_assured' => $input['sum_assured'] ?? 0,
            'premium_amount' => $input['premium_amount'] ?? null,
            'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
            'policy_term_years' => $input['policy_term_years'] ?? null,
        ]);

        $this->invalidateModuleCache($user->id, 'protection');

        return [
            'created' => true,
            'entity_type' => 'critical_illness_policy',
            'entity_id' => $policy->id,
            'name' => ($input['provider'] ?? 'Critical illness') . ' policy',
            'message' => "Critical illness policy created" .
                ($policy->sum_assured > 0 ? " for £" . number_format((float) $policy->sum_assured, 2) : '') . '.',
        ];
    }

    private function createIncomeProtection(array $input, User $user): array
    {
        $policy = IncomeProtectionPolicy::create([
            'user_id' => $user->id,
            'provider' => $input['provider'] ?? null,
            'benefit_amount' => $input['benefit_amount'] ?? 0,
            'benefit_frequency' => 'monthly',
            'premium_amount' => $input['premium_amount'] ?? null,
        ]);

        $this->invalidateModuleCache($user->id, 'protection');

        return [
            'created' => true,
            'entity_type' => 'income_protection_policy',
            'entity_id' => $policy->id,
            'name' => ($input['provider'] ?? 'Income protection') . ' policy',
            'message' => "Income protection policy created" .
                ($policy->benefit_amount > 0 ? " with a monthly benefit of £" . number_format((float) $policy->benefit_amount, 2) : '') . '.',
        ];
    }

    private function createEstateAsset(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('estate asset');
        }

        $asset = Asset::create([
            'user_id' => $user->id,
            'asset_name' => $input['asset_name'],
            'asset_type' => $input['asset_type'],
            'current_value' => $input['current_value'],
            'is_iht_exempt' => $input['is_iht_exempt'] ?? false,
            'exemption_reason' => $input['exemption_reason'] ?? null,
            'ownership_type' => 'individual',
            'valuation_date' => now()->toDateString(),
        ]);

        $this->invalidateModuleCache($user->id, 'estate');

        return [
            'created' => true,
            'entity_type' => 'estate_asset',
            'entity_id' => $asset->id,
            'name' => $asset->asset_name,
            'message' => "Estate asset \"{$asset->asset_name}\" created with a value of £" . number_format((float) $asset->current_value, 2) . '.',
        ];
    }

    private function createEstateLiability(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('estate liability');
        }

        $liability = Liability::create([
            'user_id' => $user->id,
            'liability_name' => $input['liability_name'],
            'liability_type' => $input['liability_type'],
            'current_balance' => $input['current_balance'],
            'monthly_payment' => $input['monthly_payment'] ?? null,
            'interest_rate' => $input['interest_rate'] ?? null,
            'ownership_type' => 'individual',
            'country' => 'GB',
        ]);

        $this->invalidateModuleCache($user->id, 'estate');

        return [
            'created' => true,
            'entity_type' => 'estate_liability',
            'entity_id' => $liability->id,
            'name' => $liability->liability_name,
            'message' => "Estate liability \"{$liability->liability_name}\" created with a balance of £" . number_format((float) $liability->current_balance, 2) . '.',
        ];
    }

    private function createEstateGift(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('estate gift');
        }

        $giftDate = substr($input['gift_date'], 0, 10);

        $gift = Gift::create([
            'user_id' => $user->id,
            'gift_date' => $giftDate,
            'recipient' => $input['recipient'],
            'gift_type' => $input['gift_type'] ?? 'pet',
            'gift_value' => $input['gift_value'],
            'status' => 'within_7_years',
            'notes' => $input['notes'] ?? null,
        ]);

        $this->invalidateModuleCache($user->id, 'estate');

        return [
            'created' => true,
            'entity_type' => 'estate_gift',
            'entity_id' => $gift->id,
            'name' => "Gift to {$gift->recipient}",
            'message' => "Gift of £" . number_format((float) $gift->gift_value, 2) . " to {$gift->recipient} recorded.",
        ];
    }

    /**
     * Return a standard preview-blocked response.
     */
    private function previewBlocked(string $entityType): array
    {
        return [
            'blocked' => true,
            'reason' => "You are in preview mode. Creating a {$entityType} is not available — please create a real account to save data.",
        ];
    }

    /**
     * Resolve savings account access type from account type.
     */
    private function resolveAccessType(string $accountType): string
    {
        return match ($accountType) {
            'notice' => 'notice',
            'fixed_term' => 'fixed',
            default => 'immediate',
        };
    }

    /**
     * Fuzzy-match a property by address hint, postcode, or type.
     */
    private function resolvePropertyId(User $user, ?string $hint): ?int
    {
        $properties = Property::where('user_id', $user->id)->get();

        if ($properties->isEmpty()) {
            return null;
        }

        // If only one property, use it
        if ($properties->count() === 1) {
            return $properties->first()->id;
        }

        if (! $hint) {
            // Default to main residence
            $main = $properties->firstWhere('property_type', 'main_residence');

            return $main?->id ?? $properties->first()->id;
        }

        $hintLower = Str::lower($hint);

        // Try matching by type keywords
        if (Str::contains($hintLower, ['main', 'home', 'primary', 'residence'])) {
            $match = $properties->firstWhere('property_type', 'main_residence');
            if ($match) {
                return $match->id;
            }
        }

        if (Str::contains($hintLower, ['buy to let', 'btl', 'rental', 'let'])) {
            $match = $properties->firstWhere('property_type', 'buy_to_let');
            if ($match) {
                return $match->id;
            }
        }

        if (Str::contains($hintLower, ['second', 'holiday'])) {
            $match = $properties->firstWhere('property_type', 'secondary_residence');
            if ($match) {
                return $match->id;
            }
        }

        // Try matching by address or postcode
        foreach ($properties as $property) {
            $address = Str::lower(($property->address_line_1 ?? '') . ' ' . ($property->postcode ?? ''));
            if (Str::contains($address, $hintLower) || Str::contains($hintLower, trim($address))) {
                return $property->id;
            }
        }

        // Fallback to first property
        return $properties->first()->id;
    }

    /**
     * Invalidate caches for the relevant module after data creation.
     */
    private function invalidateModuleCache(int $userId, string $module): void
    {
        $this->netWorthService->invalidateCache($userId);

        $cachePatterns = [
            'savings' => ["v1_savings_{$userId}_*"],
            'investment' => ["v1_investment_{$userId}_*"],
            'retirement' => ["v1_retirement_{$userId}_*"],
            'property' => ["v1_property_{$userId}_*"],
            'protection' => ["v1_protection_{$userId}_*"],
            'estate' => ["v1_estate_{$userId}_*"],
        ];

        foreach ($cachePatterns[$module] ?? [] as $pattern) {
            // Use the agent's cache key convention
            $key = str_replace('_*', '', $pattern);
            Cache::forget($key);
            Cache::forget("{$key}_analysis");
            Cache::forget("{$key}_recommendations");
        }

        // Also invalidate the coordinating agent cache
        Cache::forget("v1_coordinating_{$userId}_analysis");
    }

    /**
     * Resolve impact type based on event type.
     */
    private function resolveImpactType(string $eventType): string
    {
        if (in_array($eventType, LifeEvent::INCOME_EVENT_TYPES)) {
            return 'income';
        }

        if (in_array($eventType, LifeEvent::EXPENSE_EVENT_TYPES)) {
            return 'expense';
        }

        return 'expense';
    }

    /**
     * Summarise analysis data to fit within token budget.
     */
    private function summariseAnalysis(string $module, array $analysis): array
    {
        if (isset($analysis['error'])) {
            return $analysis;
        }

        // Extract key metrics based on module to keep response concise
        $summary = ['module' => $module];

        if (isset($analysis['data'])) {
            $data = $analysis['data'];
            $summary['metrics'] = $this->extractKeyMetrics($data);
            $summary['recommendations'] = array_slice($data['recommendations'] ?? [], 0, 5);
        } elseif (isset($analysis['summary'])) {
            $summary['metrics'] = $analysis['summary'];
            $summary['recommendations'] = array_slice($analysis['ranked_recommendations'] ?? [], 0, 5);
        } else {
            $summary['metrics'] = $analysis;
        }

        return $summary;
    }

    /**
     * Extract the most important metrics from analysis data.
     */
    private function extractKeyMetrics(array $data): array
    {
        $metrics = [];

        // Common fields across modules
        $keyFields = [
            'total_value', 'total_cover', 'coverage_gaps', 'net_worth',
            'monthly_surplus', 'emergency_fund_months', 'pension_projection',
            'iht_liability', 'total_savings', 'total_investments',
            'retirement_income', 'target_income', 'shortfall',
            'risk_score', 'asset_allocation', 'progress_percentage',
        ];

        foreach ($keyFields as $field) {
            if (isset($data[$field])) {
                $metrics[$field] = $data[$field];
            }
        }

        return $metrics;
    }
}
