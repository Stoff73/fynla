<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Agents\CoordinatingAgent;
use App\Models\FamilyMember;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Income\EmploymentIncomeService;
use App\Traits\ResolvesIncome;
use Illuminate\Support\Facades\Log;

/**
 * The spouse facts a user gives during onboarding are held on the user's own
 * account — the household input row (income, ISA, pension, savings,
 * investments) and the spouse's family member card (date of birth, income).
 * The moment the spouse's account is linked, this copies them across ONCE
 * (CSJ 2026-09-16): profile facts onto the spouse's profile, balances as
 * records through the same write tools Fyn uses, so every guard applies.
 * Income is the exception: it is held as an estimate the spouse's own job
 * replaces (EmploymentIncomeService::recordEstimate).
 * The ISA is assumed to be a Stocks and Shares ISA (CSJ 21:56).
 */
final class SpouseHoldingTransfer
{
    use ResolvesIncome;

    /** Employment statuses whose income is earnings from work (tax_strategy_household_inputs / users values). */
    private const WORKING_STATUSES = ['employed', 'full_time', 'part_time', 'self_employed'];

    /** Employment statuses whose income is not earnings from work. */
    private const NON_WORKING_STATUSES = ['retired', 'unemployed'];

    public function __construct(private readonly CoordinatingAgent $agent) {}

    /**
     * @return list<string> what was copied, for the log
     */
    public function transfer(User $requester, User $spouse): array
    {
        $holding = TaxStrategyHouseholdInput::firstOrCreate(['user_id' => $requester->id]);
        if ($holding->spouse_holding_transferred_at !== null) {
            return [];
        }

        $card = FamilyMember::where('user_id', $requester->id)->where('relationship', 'spouse')->latest('id')->first();
        $copied = [];

        // ── Profile ────────────────────────────────────────────────────────
        if (empty($spouse->date_of_birth) && $card?->date_of_birth !== null) {
            $this->run('capture_personal_details', ['date_of_birth' => $card->date_of_birth->format('Y-m-d')], $spouse, $copied, 'date of birth');
        }
        if (empty($spouse->employment_status) && $holding->spouse_employment_status !== null) {
            $spouse->employment_status = $holding->spouse_employment_status;
            $spouse->save();
            $copied[] = 'employment status';
        }
        $income = $holding->spouse_annual_income !== null ? (float) $holding->spouse_annual_income : (float) ($card?->annual_income ?? 0);
        $hasIncome = (float) ($spouse->annual_employment_income ?? 0) > 0
            || (float) ($spouse->annual_self_employment_income ?? 0) > 0
            || (float) ($spouse->annual_other_income ?? 0) > 0;
        if ($income > 0 && ! $hasIncome) {
            ['pay' => $pay, 'other' => $other] = $this->splitIncome($income, $holding, $spouse);
            if ($pay > 0) {
                // Held as an estimate, not through capture_work_details: it is the
                // requester's figure, and the spouse's own job must replace it
                // rather than be added to it (production 2026-09-29 summed the
                // two into £64,000). recordEstimate applies the same income cap
                // as capture_work_details. This runs after the link has
                // committed, so a failure is logged and the other copies go on,
                // as run() does, instead of failing the registration.
                try {
                    app(EmploymentIncomeService::class)->recordEstimate($spouse, $pay);
                    $spouse->refresh();
                    $copied[] = 'income';
                } catch (\Throwable $e) {
                    Log::warning('[SpouseHoldingTransfer] Income copy failed', ['spouse_id' => $spouse->id, 'error' => $e->getMessage()]);
                }
            }
            if ($other > 0) {
                $this->run('update_profile', ['section' => 'income_occupation', 'fields' => ['annual_other_income' => $other]], $spouse, $copied, 'other income');
            }
        }

        // ── Records ────────────────────────────────────────────────────────
        $savings = (float) ($holding->spouse_existing_savings_balance ?? 0);
        if ($savings > 0) {
            // The interest given for them travels as the account's rate, so
            // their savings position is not lost on linking (2026-10-01).
            // A rate the savings form would refuse (SavingsStore: max 20%) is
            // left off rather than losing the account.
            $interest = $holding->spouse_annual_savings_interest;
            $rate = $interest !== null ? round((float) $interest / $savings * 100, 2) : null;
            $this->run('create_savings_account', array_filter([
                'account_name' => 'Savings', 'account_type' => 'easy_access', 'current_balance' => $savings, 'ownership_type' => 'individual',
                'interest_rate' => $rate !== null && $rate <= 20 ? $rate : null,
            ], static fn ($v): bool => $v !== null), $spouse, $copied, 'savings');
        }

        $isa = (float) ($holding->spouse_isa_balance ?? $holding->spouse_existing_isa_balance ?? 0);
        if ($isa > 0) {
            $provider = trim((string) ($holding->spouse_isa_provider ?? ''));
            $this->run('create_investment_account', array_filter([
                'account_name' => trim($provider.' Stocks and Shares ISA'), 'account_type' => 'stocks_shares_isa', 'isa_type' => 'stocks_and_shares',
                'provider' => $provider !== '' ? $provider : null, 'current_value' => $isa, 'ownership_type' => 'individual',
            ], static fn ($v): bool => $v !== null), $spouse, $copied, 'ISA');
        }

        $investments = (float) ($holding->spouse_existing_investment_balance ?? 0);
        if ($investments > 0) {
            $this->run('create_investment_account', [
                'account_name' => 'Investments', 'account_type' => 'personal_investment_account', 'current_value' => $investments, 'ownership_type' => 'individual',
            ], $spouse, $copied, 'investments');
        }

        $pot = (float) ($holding->spouse_existing_pension_balance ?? 0);
        $contribution = (float) ($holding->spouse_pension_input_annual ?? 0);
        // A retired partner's income is their pension, but one figure cannot
        // say how much is State Pension, a final salary pension or drawn from
        // a pot (item 10, CSJ 2026-10-07: "Partner's setup asks"). Nothing is
        // recorded as drawn here: their walk asks the State Pension and final
        // salary forms, then opens the personal pension form with what is
        // left (pensionIncomeLeftToPlace). Until then the household reads the
        // figure given (TaxStrategyMath::linkedSpouseWithIncome).
        if ($pot > 0 || $contribution > 0) {
            $provider = trim((string) ($holding->spouse_pension_provider ?? ''));
            $input = ['pension_category' => 'dc', 'scheme_type' => 'personal', 'scheme_name' => trim($provider.' personal pension')];
            if ($provider !== '') {
                $input['provider'] = $provider;
            }
            if ($pot > 0) {
                $input['current_fund_value'] = $pot;
            }
            if ($contribution > 0) {
                $input['monthly_contribution_amount'] = round($contribution / 12, 2);
            }
            $this->run('create_pension', $input, $spouse, $copied, 'pension');
        }

        $holding->spouse_holding_transferred_at = now();
        $holding->save();

        Log::info('[SpouseHoldingTransfer] Copied onboarding spouse facts onto the linked account', [
            'requester_id' => $requester->id, 'spouse_id' => $spouse->id, 'copied' => $copied,
        ]);

        return $copied;
    }

    /**
     * The partner said they do not work after the link, so the inviter's figure
     * that arrived as an estimate of pay (status unknown at the link) is not
     * pay. Without this a retired partner kept it as pay beside the pension
     * they then confirmed: £30,000 counted twice, taxed at the higher rate with
     * National Insurance (fynla.org, 2026-09-30).
     *
     * Retired: the estimate goes and nothing takes its place here; their walk
     * asks what makes up their pension income (item 10). Anyone else not
     * working: it is other income, since it could be rent as much as anything.
     */
    public function restateEstimateForStatus(User $spouse): void
    {
        $status = $spouse->employment_status;
        if (! in_array($status, self::NON_WORKING_STATUSES, true)) {
            return;
        }
        $amount = app(EmploymentIncomeService::class)->dropEstimates($spouse);
        if ($amount <= 0) {
            return;
        }
        $spouse->refresh();
        $copied = ['estimate dropped'];

        if ($status !== 'retired') {
            $this->run('update_profile', ['section' => 'income_occupation', 'fields' => ['annual_other_income' => (float) ($spouse->annual_other_income ?? 0) + $amount]], $spouse, $copied, 'other income');
        }

        Log::info('[SpouseHoldingTransfer] Restated the inviter\'s income estimate for the partner\'s status', [
            'spouse_id' => $spouse->id, 'status' => $status, 'amount' => $amount, 'copied' => $copied,
        ]);
    }

    /**
     * The pension income the inviter gave for this partner, when the partner
     * is retired: their income less what was given as earnings from work.
     * Null when they are not retired, or arrived by no transfer.
     */
    public function inviterPensionIncome(User $spouse): ?float
    {
        if ($spouse->employment_status !== 'retired' || ($inviter = $spouse->liveSpouse()) === null) {
            return null;
        }
        $holding = TaxStrategyHouseholdInput::where('user_id', $inviter->id)->whereNotNull('spouse_holding_transferred_at')->first();
        if ($holding === null || $holding->spouse_annual_income === null) {
            return null;
        }
        $income = (float) $holding->spouse_annual_income;
        $earnings = min($income, max(0.0, (float) ($holding->spouse_annual_earnings ?? 0)));

        return $income - $earnings > 0 ? $income - $earnings : null;
    }

    /**
     * What a retired partner draws from a pension pot, worked out from the
     * figure their partner gave: that figure less the State Pension being paid
     * to them and the final salary pensions being paid to them, as they have
     * recorded them (CSJ 2026-10-07, item 10). Their personal pension form
     * opens with it. Null when nothing is left, or there is no such figure.
     */
    public function pensionIncomeLeftToPlace(User $spouse): ?float
    {
        $given = $this->inviterPensionIncome($spouse);
        if ($given === null) {
            return null;
        }
        // The one rule for pension income being paid (ResolvesIncome), less
        // what it counts as drawn from a pot: that is the part being placed.
        $spouse->loadMissing('dcPensions');
        $drawn = (float) $spouse->dcPensions->sum(fn ($pension): float => (float) ($pension->annual_drawdown_income ?? 0));
        $left = round($given - ($this->resolvePensionIncomeInPayment($spouse) - $drawn), 2);

        return $left > 0 ? $left : null;
    }

    /**
     * The partner's income as earnings from work, pension income and the rest.
     * Pension tax relief is capped at relevant UK earnings (FA 2004 s189-190,
     * https://www.legislation.gov.uk/ukpga/2004/12/section/190), and the plan
     * reads employment and self-employment income as those earnings, so income
     * that is a pension or rent must not arrive as pay.
     *
     * Earnings given: they are the pay. Earnings not given: the partner's
     * employment status decides: working means all pay, retired or unemployed
     * means none. Neither known: the figure is copied as pay, as it always was
     * (ruling 50, CSJ 2026-09-16; restated 2026-09-29: the details the inviter
     * gave are transferred and stored). It is an estimate, so the spouse's own
     * job replaces it rather than adding to it.
     *
     * What is not pay is a retired partner's pension, which their walk asks
     * them to split (item 10), and otherwise other income, since for anyone
     * else it could be rent as much as a pension.
     *
     * @return array{pay: float, other: float}
     */
    private function splitIncome(float $income, TaxStrategyHouseholdInput $holding, User $spouse): array
    {
        $status = $holding->spouse_employment_status ?? $spouse->employment_status;

        if ($holding->spouse_annual_earnings !== null) {
            $pay = min($income, max(0.0, (float) $holding->spouse_annual_earnings));
        } else {
            $pay = match (true) {
                in_array($status, self::WORKING_STATUSES, true) => $income,
                in_array($status, self::NON_WORKING_STATUSES, true) => 0.0,
                default => $income,
            };
        }

        $rest = $income - $pay;

        return ['pay' => $pay, 'other' => $status === 'retired' ? 0.0 : $rest];
    }

    /** @param  array<string, mixed>  $input */
    private function run(string $tool, array $input, User $spouse, array &$copied, string $label): void
    {
        // Ownership is a stated fact here (the spouse's own name), so the
        // accuracy gate has nothing to ask.
        $facts = isset($input['ownership_type']) ? ['ownership_type' => $input['ownership_type']] : [];
        if (isset($input['isa_type'])) {
            $facts['isa_subtype'] = $input['isa_type'];
        }
        $facts = $facts === [] ? null : $facts;
        try {
            $result = $this->agent->executeTool($tool, $input, $spouse, confirmedFacts: $facts);
        } catch (\Throwable $e) {
            Log::warning('[SpouseHoldingTransfer] Copy failed', ['tool' => $tool, 'spouse_id' => $spouse->id, 'error' => $e->getMessage()]);

            return;
        }
        // update_profile answers with 'updated' rather than 'success'.
        $ok = empty($result['error']) && (($result['success'] ?? false) === true
            || ($result['onboarding_capture'] ?? false) === true
            || ($result['updated'] ?? false) === true);
        if ($ok) {
            $copied[] = $label;
            $spouse->refresh();
        } else {
            Log::warning('[SpouseHoldingTransfer] Copy refused', ['tool' => $tool, 'spouse_id' => $spouse->id, 'result' => $result['message'] ?? ($result['error_type'] ?? 'unknown')]);
        }
    }
}
