<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Agents\CoordinatingAgent;
use App\Models\FamilyMember;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The spouse facts a user gives during onboarding are held on the user's own
 * account — the household input row (income, ISA, pension, savings,
 * investments) and the spouse's family member card (date of birth, income).
 * The moment the spouse's account is linked, this copies them across ONCE
 * (CSJ 2026-09-16): profile facts onto the spouse's profile, balances as
 * records through the same write tools Fyn uses, so every guard applies.
 * The ISA is assumed to be a Stocks and Shares ISA (CSJ 21:56).
 */
final class SpouseHoldingTransfer
{
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
        if ($income > 0 && (float) ($spouse->annual_employment_income ?? 0) <= 0 && (float) ($spouse->annual_self_employment_income ?? 0) <= 0) {
            $this->run('capture_work_details', ['annual_income' => $income], $spouse, $copied, 'income');
        }

        // ── Records ────────────────────────────────────────────────────────
        $savings = (float) ($holding->spouse_existing_savings_balance ?? 0);
        if ($savings > 0) {
            $this->run('create_savings_account', [
                'account_name' => 'Savings', 'account_type' => 'easy_access', 'current_balance' => $savings, 'ownership_type' => 'individual',
            ], $spouse, $copied, 'savings');
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
        $ok = ($result['success'] ?? false) === true || ($result['onboarding_capture'] ?? false) === true;
        if ($ok) {
            $copied[] = $label;
            $spouse->refresh();
        } else {
            Log::warning('[SpouseHoldingTransfer] Copy refused', ['tool' => $tool, 'spouse_id' => $spouse->id, 'result' => $result['message'] ?? ($result['error_type'] ?? 'unknown')]);
        }
    }
}
