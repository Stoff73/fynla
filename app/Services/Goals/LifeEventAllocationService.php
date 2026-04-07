<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Constants\TaxDefaults;
use App\Models\DCPension;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeEventAllocation;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Savings\EmergencyFundCalculator;
use App\Services\Savings\ISATracker;
use App\Services\TaxConfigService;
use App\Traits\ResolvesExpenditure;
use App\Traits\StructuredLogging;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LifeEventAllocationService
{
    use ResolvesExpenditure;
    use StructuredLogging;

    private const SHORT_TERM_YEARS = 3;

    public function __construct(
        private readonly ISATracker $isaTracker,
        private readonly AnnualAllowanceChecker $aaChecker,
        private readonly EmergencyFundCalculator $efCalculator,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get existing allocations or generate them on first access.
     */
    public function getAllocations(LifeEvent $event, User $user): Collection
    {
        // Always use event's primary owner as canonical allocation owner
        $canonicalUserId = $event->user_id;

        $existing = LifeEventAllocation::where('life_event_id', $event->id)
            ->where('user_id', $canonicalUserId)
            ->orderBy('display_order')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        // Generate using the event's primary owner
        $canonicalUser = $user->id === $canonicalUserId
            ? $user
            : User::findOrFail($canonicalUserId);

        return $this->generateAllocations($event, $canonicalUser);
    }

    /**
     * Generate (or regenerate) allocation suggestions for a life event.
     */
    public function generateAllocations(LifeEvent $event, User $user): Collection
    {
        // Always use event's primary owner as canonical allocation owner
        $canonicalUserId = $event->user_id;

        // Clear any existing allocations
        LifeEventAllocation::where('life_event_id', $event->id)
            ->where('user_id', $canonicalUserId)
            ->delete();

        $allocationType = $event->isPositive() ? 'income' : 'expense';

        try {
            $rows = $allocationType === 'income'
                ? $this->buildIncomeWaterfall($event, $user)
                : $this->buildExpenseFundFrom($event, $user);
        } catch (\Throwable $e) {
            $this->logWarning('Life event allocation generation failed: '.$e->getMessage(), [
                'event_id' => $event->id,
                'user_id' => $user->id,
            ]);
            $rows = [];
        }

        // Persist all rows
        $allocations = collect();
        foreach ($rows as $index => $row) {
            $allocations->push(LifeEventAllocation::create([
                'life_event_id' => $event->id,
                'user_id' => $canonicalUserId,
                'allocation_type' => $allocationType,
                'allocation_step' => $row['step'],
                'account_type' => $row['account_type'] ?? null,
                'account_id' => $row['account_id'] ?? null,
                'account_label' => $row['account_label'] ?? null,
                'suggested_amount' => $row['amount'],
                'amount' => $row['amount'],
                'enabled' => true,
                'rationale' => $row['rationale'] ?? null,
                'display_order' => $index,
            ]));
        }

        return $allocations;
    }

    /**
     * Update a single allocation row.
     */
    public function updateAllocation(LifeEventAllocation $allocation, float $amount, bool $enabled): LifeEventAllocation
    {
        $allocation->update([
            'amount' => $amount,
            'enabled' => $enabled,
        ]);

        return $allocation->fresh();
    }

    /**
     * Clear all allocations for an event.
     */
    public function clearAllocations(LifeEvent $event): void
    {
        LifeEventAllocation::where('life_event_id', $event->id)
            ->where('user_id', $event->user_id)
            ->delete();
    }

    /**
     * Build income allocation waterfall.
     *
     * Order: Goals → ISA → Pension → Bond → Cash
     */
    private function buildIncomeWaterfall(LifeEvent $event, User $user): array
    {
        $remaining = (float) $event->getAmountForUser($user->id);
        $rows = [];

        if ($remaining <= 0) {
            return $rows;
        }

        // Step 1: Goals
        $goalRows = $this->determineGoalAllocations($user, $remaining);
        $rows = array_merge($rows, $goalRows);

        // Step 2: ISA
        if ($remaining > 0) {
            $isaRow = $this->determineISAAllocation($user->id, $remaining);
            if ($isaRow) {
                $rows[] = $isaRow;
            }
        }

        // Step 3: Pension
        if ($remaining > 0) {
            $pensionRow = $this->determinePensionAllocation($user->id, $remaining);
            if ($pensionRow) {
                $rows[] = $pensionRow;
            }
        }

        // Step 4: Cash (emergency fund top-up if needed — before bond)
        if ($remaining > 0) {
            $cashRow = $this->determineCashAllocation($user, $remaining);
            if ($cashRow) {
                $rows[] = $cashRow;
            }
        }

        // Step 5: Bond (consumes all remaining)
        if ($remaining > 0) {
            $bondRow = $this->determineBondAllocation($user->id, $remaining);
            if ($bondRow) {
                $rows[] = $bondRow;
            }
        }

        return $rows;
    }

    /**
     * Build expense fund-from suggestions.
     *
     * Reverse tax efficiency: cash/GIA first → ISA → bond.
     * Never pension unless post-retirement.
     */
    private function buildExpenseFundFrom(LifeEvent $event, User $user): array
    {
        $remaining = (float) $event->getAmountForUser($user->id);
        $rows = [];

        if ($remaining <= 0) {
            return $rows;
        }

        // 1. Cash savings first
        $cashAccounts = SavingsAccount::where('user_id', $user->id)
            ->where('is_isa', false)
            ->where('current_balance', '>', 0)
            ->orderByDesc('current_balance')
            ->get();

        foreach ($cashAccounts as $account) {
            if ($remaining <= 0) {
                break;
            }
            $available = (float) $account->current_balance;
            $drawAmount = min($available, $remaining);
            $remaining -= $drawAmount;

            $rows[] = [
                'step' => 'cash',
                'account_type' => 'savings_account',
                'account_id' => $account->id,
                'account_label' => $account->account_name ?: 'Cash Savings',
                'amount' => round($drawAmount, 2),
                'rationale' => 'Draw from cash savings first to preserve tax-advantaged accounts.',
            ];
        }

        // 2. General Investment Accounts (non-ISA)
        if ($remaining > 0) {
            $giaAccounts = InvestmentAccount::where('user_id', $user->id)
                ->whereNotIn('account_type', ['isa'])
                ->where('current_value', '>', 0)
                ->orderByDesc('current_value')
                ->get();

            foreach ($giaAccounts as $account) {
                if ($remaining <= 0) {
                    break;
                }
                $available = (float) $account->current_value;
                $drawAmount = min($available, $remaining);
                $remaining -= $drawAmount;

                $rows[] = [
                    'step' => 'cash',
                    'account_type' => 'investment_account',
                    'account_id' => $account->id,
                    'account_label' => $account->account_name ?: 'General Investment Account',
                    'amount' => round($drawAmount, 2),
                    'rationale' => 'Draw from general investment accounts before tax-wrapped accounts.',
                ];
            }
        }

        // 3. ISA (tax-free withdrawal)
        if ($remaining > 0) {
            $isaAccounts = SavingsAccount::where('user_id', $user->id)
                ->where('is_isa', true)
                ->where('current_balance', '>', 0)
                ->orderByDesc('current_balance')
                ->get();

            foreach ($isaAccounts as $account) {
                if ($remaining <= 0) {
                    break;
                }
                $available = (float) $account->current_balance;
                $drawAmount = min($available, $remaining);
                $remaining -= $drawAmount;

                $rows[] = [
                    'step' => 'isa',
                    'account_type' => 'savings_account',
                    'account_id' => $account->id,
                    'account_label' => $account->account_name ?: 'ISA',
                    'amount' => round($drawAmount, 2),
                    'rationale' => 'ISA withdrawals are tax-free but reduce your tax-sheltered allowance.',
                ];
            }

            $isaInvestments = InvestmentAccount::where('user_id', $user->id)
                ->where('account_type', 'isa')
                ->where('current_value', '>', 0)
                ->orderByDesc('current_value')
                ->get();

            foreach ($isaInvestments as $account) {
                if ($remaining <= 0) {
                    break;
                }
                $available = (float) $account->current_value;
                $drawAmount = min($available, $remaining);
                $remaining -= $drawAmount;

                $rows[] = [
                    'step' => 'isa',
                    'account_type' => 'investment_account',
                    'account_id' => $account->id,
                    'account_label' => $account->account_name ?: 'Stocks & Shares ISA',
                    'amount' => round($drawAmount, 2),
                    'rationale' => 'Stocks & Shares ISA withdrawals are tax-free.',
                ];
            }
        }

        // 4. Bond accounts
        if ($remaining > 0) {
            $bondAccounts = InvestmentAccount::where('user_id', $user->id)
                ->whereIn('account_type', ['onshore_bond', 'offshore_bond'])
                ->where('current_value', '>', 0)
                ->orderByDesc('current_value')
                ->get();

            foreach ($bondAccounts as $account) {
                if ($remaining <= 0) {
                    break;
                }
                $available = (float) $account->current_value;
                $drawAmount = min($available, $remaining);
                $remaining -= $drawAmount;

                $rows[] = [
                    'step' => 'bond',
                    'account_type' => 'investment_account',
                    'account_id' => $account->id,
                    'account_label' => $account->account_name ?: 'Investment Bond',
                    'amount' => round($drawAmount, 2),
                    'rationale' => 'Bond surrender may trigger a chargeable event gain.',
                ];
            }
        }

        // If still remaining, add generic cash suggestion
        if ($remaining > 0) {
            $rows[] = [
                'step' => 'cash',
                'account_type' => null,
                'account_id' => null,
                'account_label' => 'Additional Funding Required',
                'amount' => round($remaining, 2),
                'rationale' => 'Insufficient account balances to fully fund this expense. Consider reviewing your savings plan.',
            ];
        }

        return $rows;
    }

    /**
     * Step 1: Allocate to goals that need cash.
     */
    private function determineGoalAllocations(User $user, float &$remaining): array
    {
        $rows = [];

        $goals = Goal::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereRaw('current_amount < target_amount')
            ->orderBy('priority')
            ->orderBy('target_date')
            ->get();

        foreach ($goals as $goal) {
            if ($remaining <= 0) {
                break;
            }

            $shortfall = (float) $goal->target_amount - (float) $goal->current_amount;
            if ($shortfall <= 0) {
                continue;
            }

            $allocateAmount = min($shortfall, $remaining);
            $remaining -= $allocateAmount;

            $yearsToTarget = $goal->target_date
                ? max(0, now()->diffInYears(Carbon::parse($goal->target_date), false))
                : 0;

            // Determine account type based on goal timeframe
            if ($yearsToTarget <= self::SHORT_TERM_YEARS) {
                $accountType = 'cash';
                $accountLabel = 'Cash (Short-term Goal)';
                $rationale = sprintf(
                    'Goal "%s" is within %d years — cash allocation recommended for capital preservation.',
                    $goal->goal_name,
                    self::SHORT_TERM_YEARS
                );
                $accountId = $this->findCashAccount($user->id);
            } else {
                // Long-term: use tax-wrapped account depending on goal type
                $goalAccountInfo = $this->getGoalAccountType($goal, $user->id);
                $accountType = $goalAccountInfo['account_type'];
                $accountLabel = $goalAccountInfo['label'];
                $accountId = $goalAccountInfo['account_id'];
                $rationale = sprintf(
                    'Goal "%s" has a %d-year horizon — %s for tax-efficient growth.',
                    $goal->goal_name,
                    (int) ceil($yearsToTarget),
                    $accountLabel
                );
            }

            $rows[] = [
                'step' => 'goals',
                'account_type' => $accountType,
                'account_id' => $accountId,
                'account_label' => sprintf('%s — %s', $goal->goal_name, $accountLabel),
                'amount' => round($allocateAmount, 2),
                'rationale' => $rationale,
            ];
        }

        return $rows;
    }

    /**
     * Step 2: Allocate to ISA up to remaining allowance.
     */
    private function determineISAAllocation(int $userId, float &$remaining): ?array
    {
        try {
            $taxYear = $this->isaTracker->getCurrentTaxYear();
            $isaStatus = $this->isaTracker->getISAAllowanceStatus($userId, $taxYear);
            $isaRemaining = (float) $isaStatus['remaining'];
        } catch (\Throwable $e) {
            $this->logWarning('ISA allowance check failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($isaRemaining <= 0) {
            return null;
        }

        $allocateAmount = min($isaRemaining, $remaining);
        $remaining -= $allocateAmount;

        // Find user's ISA account
        $isaAccount = SavingsAccount::where('user_id', $userId)
            ->where('is_isa', true)
            ->first();

        if (! $isaAccount) {
            $isaAccount = InvestmentAccount::where('user_id', $userId)
                ->where('account_type', 'isa')
                ->first();
        }

        $totalAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'];

        return [
            'step' => 'isa',
            'account_type' => $isaAccount ? ($isaAccount instanceof SavingsAccount ? 'savings_account' : 'investment_account') : null,
            'account_id' => $isaAccount?->id,
            'account_label' => $isaAccount
                ? ($isaAccount->account_name ?: 'ISA')
                : 'ISA (no account — consider opening one)',
            'amount' => round($allocateAmount, 2),
            'rationale' => sprintf(
                'Maximise your Individual Savings Account allowance. You have %s of your %s annual allowance remaining.',
                number_format($isaRemaining, 0),
                number_format($totalAllowance, 0)
            ),
        ];
    }

    /**
     * Step 3: Allocate to pension up to remaining Annual Allowance.
     */
    private function determinePensionAllocation(int $userId, float &$remaining): ?array
    {
        try {
            $taxYear = $this->isaTracker->getCurrentTaxYear();
            $aaStatus = $this->aaChecker->checkAnnualAllowance($userId, $taxYear);
            $pensionRemaining = (float) $aaStatus['remaining_allowance'];
            $carryForward = (float) $aaStatus['carry_forward_available'];
            $totalAvailable = $pensionRemaining + $carryForward;
        } catch (\Throwable $e) {
            $this->logWarning('Pension allowance check failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($totalAvailable <= 0) {
            return null;
        }

        $allocateAmount = min($totalAvailable, $remaining);
        $remaining -= $allocateAmount;

        // Find user's DC pension
        $pension = DCPension::where('user_id', $userId)->first();

        $standardAA = $this->taxConfig->getPensionAllowances()['annual_allowance'];
        $carryNote = $carryForward > 0
            ? sprintf(' (including %s carry forward from previous years)', number_format($carryForward, 0))
            : '';

        return [
            'step' => 'pension',
            'account_type' => $pension ? 'dc_pension' : null,
            'account_id' => $pension?->id,
            'account_label' => $pension
                ? ($pension->provider_name ?: 'Pension')
                : 'Pension (no scheme found — consider setting one up)',
            'amount' => round($allocateAmount, 2),
            'rationale' => sprintf(
                'Utilise your remaining pension Annual Allowance for tax relief on contributions. %s available%s.',
                number_format($totalAvailable, 0),
                $carryNote
            ),
        ];
    }

    /**
     * Step 4: Allocate to bond (onshore for basic rate, offshore for higher/additional).
     */
    private function determineBondAllocation(int $userId, float &$remaining): ?array
    {
        $taxBand = $this->getTaxBand($userId);
        $isBasicRate = $taxBand === 'basic';

        $bondType = $isBasicRate ? 'onshore_bond' : 'offshore_bond';
        $bondLabel = $isBasicRate ? 'Onshore Investment Bond' : 'Offshore Investment Bond';

        // Check for existing bond account
        $bondAccount = InvestmentAccount::where('user_id', $userId)
            ->where('account_type', $bondType)
            ->first();

        $allocateAmount = $remaining;
        $remaining = 0;

        $rationale = $isBasicRate
            ? 'Based on your income, an onshore investment bond is tax-efficient as gains are taxed at the basic rate when encashed. Allows 5% annual tax-deferred withdrawals.'
            : 'Based on your income, an offshore investment bond offers tax deferral as the fund rolls up gross. Ideal for higher and additional rate taxpayers planning to encash in a lower tax year.';

        return [
            'step' => 'bond',
            'account_type' => 'investment_account',
            'account_id' => $bondAccount?->id,
            'account_label' => $bondAccount
                ? ($bondAccount->account_name ?: $bondLabel)
                : sprintf('%s (no account — consider opening one)', $bondLabel),
            'amount' => round($allocateAmount, 2),
            'rationale' => $rationale,
        ];
    }

    /**
     * Step 5: Allocate to cash only if emergency fund is short.
     */
    private function determineCashAllocation(User $user, float &$remaining): ?array
    {
        try {
            $totalSavings = (float) SavingsAccount::where('user_id', $user->id)
                ->sum('current_balance');

            $monthlyExpenditure = $this->resolveMonthlyExpenditure($user)['amount'];
            $runway = $this->efCalculator->calculateRunway($totalSavings, $monthlyExpenditure);
            $adequacy = $this->efCalculator->calculateAdequacy($runway);
        } catch (\Throwable $e) {
            $this->logWarning('Emergency fund check failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($adequacy['shortfall'] <= 0) {
            return null;
        }

        $shortfallAmount = $adequacy['shortfall'] * $monthlyExpenditure;
        $allocateAmount = min($shortfallAmount, $remaining);
        $remaining -= $allocateAmount;

        $cashAccount = $this->findCashAccountModel($user->id);

        return [
            'step' => 'cash',
            'account_type' => $cashAccount ? 'savings_account' : null,
            'account_id' => $cashAccount?->id,
            'account_label' => $cashAccount
                ? ($cashAccount->account_name ?: 'Emergency Fund')
                : 'Emergency Cash Fund',
            'amount' => round($allocateAmount, 2),
            'rationale' => sprintf(
                'Your emergency fund covers %.1f months of expenditure (target: 6 months). Topping up by %s would improve your financial resilience.',
                $runway,
                number_format($allocateAmount, 0)
            ),
        ];
    }

    /**
     * Determine tax band from user income.
     */
    private function getTaxBand(int $userId): string
    {
        $income = (float) (RetirementProfile::where('user_id', $userId)->value('current_annual_salary') ?? 0);

        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) $incomeTax['personal_allowance'];
        $bands = $incomeTax['bands'] ?? [];

        // Basic rate upper limit
        $basicRateMax = (float) ($incomeTax['bands'][0]['upper_limit'] ?? TaxDefaults::HIGHER_RATE_THRESHOLD);

        if ($income <= $basicRateMax) {
            return 'basic';
        }

        // Check for additional rate
        $additionalRateThreshold = (float) ($incomeTax['bands'][1]['upper_limit'] ?? TaxDefaults::ADDITIONAL_RATE_THRESHOLD);
        $additionalThreshold = $additionalRateThreshold;
        if ($additionalThreshold === 0.0) {
            foreach ($bands as $band) {
                if (isset($band['rate']) && $band['rate'] >= 0.45) {
                    $additionalThreshold = $personalAllowance + (float) ($band['min']);
                    break;
                }
            }
        }

        if ($additionalThreshold > 0 && $income > $additionalThreshold) {
            return 'additional';
        }

        return 'higher';
    }

    /**
     * Get account type for a long-term goal based on its type.
     */
    private function getGoalAccountType(Goal $goal, int $userId): array
    {
        $goalType = $goal->goal_type ?? 'custom';
        $assignedModule = $goal->assigned_module ?? 'savings';

        // Retirement goals → pension
        if ($goalType === 'retirement' || $assignedModule === 'retirement') {
            $pension = DCPension::where('user_id', $userId)->first();

            return [
                'account_type' => 'dc_pension',
                'account_id' => $pension?->id,
                'label' => 'Pension',
            ];
        }

        // Investment goals → ISA if allowance available, otherwise GIA
        if ($assignedModule === 'investment' || $goalType === 'wealth_accumulation') {
            $isaAccount = InvestmentAccount::where('user_id', $userId)
                ->where('account_type', 'isa')
                ->first();

            if ($isaAccount) {
                return [
                    'account_type' => 'investment_account',
                    'account_id' => $isaAccount->id,
                    'label' => 'Stocks & Shares ISA',
                ];
            }

            $giaAccount = InvestmentAccount::where('user_id', $userId)
                ->whereNotIn('account_type', ['isa'])
                ->first();

            return [
                'account_type' => 'investment_account',
                'account_id' => $giaAccount?->id,
                'label' => 'Investment Account',
            ];
        }

        // Default: savings ISA or regular savings
        $isaAccount = SavingsAccount::where('user_id', $userId)
            ->where('is_isa', true)
            ->first();

        if ($isaAccount) {
            return [
                'account_type' => 'savings_account',
                'account_id' => $isaAccount->id,
                'label' => 'Cash ISA',
            ];
        }

        return [
            'account_type' => 'savings_account',
            'account_id' => $this->findCashAccount($userId),
            'label' => 'Savings Account',
        ];
    }

    /**
     * Find the user's primary cash savings account ID.
     */
    private function findCashAccount(int $userId): ?int
    {
        return $this->findCashAccountModel($userId)?->id;
    }

    /**
     * Find the user's primary cash savings account model.
     */
    private function findCashAccountModel(int $userId): ?SavingsAccount
    {
        return SavingsAccount::where('user_id', $userId)
            ->where('is_emergency_fund', true)
            ->first()
            ?? SavingsAccount::where('user_id', $userId)
                ->where('is_isa', false)
                ->orderByDesc('current_balance')
                ->first();
    }
}
