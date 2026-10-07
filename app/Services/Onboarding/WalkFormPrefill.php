<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AiConversation;
use App\Models\Investment\InvestmentAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Auth\FunnelAnswersMapper;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Retirement\PensionContributionRule;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;

/**
 * What a walk capture form already knows before the user types anything
 * (production 2026-09-29: an invited spouse was shown a blank income form
 * although their partner had entered their salary, then asked for the
 * partner's own income although the link already held it).
 *
 * The one home for filling a walk form in — the director attaches what this
 * returns to the form event and to the row's metadata, so a resumed
 * conversation opens the same values on every surface.
 *
 * - The work form, when the user has exactly one job on file and no work
 *   form has been saved in this conversation yet: that job, opened as an
 *   edit of its row (RecordEditForms), so Save changes it instead of adding
 *   a second one beside it. After a work form is saved, "add another" opens
 *   a blank form as before.
 * - The savings, ISA, investment and pension forms, on the same rule: the
 *   one record of that kind (CSJ 2026-09-29, ruling 50 — what a partner gave
 *   is transferred and stored, so the spouse's own walk confirms it rather
 *   than adding it again). The form is narrowed to that record's kind, as the
 *   Edit form is, because an edit saves one kind only
 *   (RecordEditForms::recordFields); it keeps the walk's own name so the save
 *   answers the step.
 * - The working-spouse form, when a financially-shared linked spouse's own
 *   record holds earnings: their income and earnings, filled in for the user
 *   to confirm (HouseholdFinancialContext::linkedSpouseEarnings). A figure
 *   the user already gave for their spouse is left alone.
 */
final class WalkFormPrefill
{
    public function __construct(
        private readonly RecordEditForms $editForms,
        private readonly HouseholdFinancialContext $household,
        private readonly SavingsStore $savings,
        private readonly PensionStore $pensions,
        private readonly SpouseHoldingTransfer $transfer,
    ) {}

    /**
     * @return array{values: array<string, array<string, mixed>>, record: array{type: string, id: int}|null, schema?: array<string, mixed>}|null
     */
    public function for(User $user, AiConversation $conversation, string $formName): ?array
    {
        return match ($formName) {
            CaptureForms::WORK => $this->existingJob($user, $conversation),
            CaptureForms::SPOUSE_HOUSEHOLD => $this->linkedSpouseIncome($user),
            CaptureForms::SAVINGS, CaptureForms::ISA, CaptureForms::INVESTMENT,
            CaptureForms::PENSION => $this->existingRecord($user, $conversation, $formName),
            CaptureForms::PENSION_PERSONAL => $this->withPensionIncomeLeft($user, $conversation, $this->existingRecord($user, $conversation, $formName)),
            default => null,
        };
    }

    /**
     * A retired partner's personal pension form opens with what they draw
     * worked out from the figure their partner gave, less the State Pension
     * and final salary pensions they have said are paid to them (item 10,
     * SpouseHoldingTransfer::pensionIncomeLeftToPlace). A figure already on
     * the record stands.
     *
     * @param  array<string, mixed>|null  $prefill
     * @return array<string, mixed>|null
     */
    private function withPensionIncomeLeft(User $user, AiConversation $conversation, ?array $prefill): ?array
    {
        if (self::formSaved($conversation, CaptureForms::PENSION_PERSONAL)) {
            return $prefill;
        }
        $left = $this->transfer->pensionIncomeLeftToPlace($user);
        if ($left === null) {
            return $prefill;
        }
        if ($prefill === null) {
            return ['values' => ['personal' => ['annual_drawdown_income' => $left]], 'record' => null];
        }
        if ((float) ($prefill['values']['personal']['annual_drawdown_income'] ?? 0) <= 0) {
            $prefill['values']['personal']['annual_drawdown_income'] = $left;
        }

        return $prefill;
    }

    /** @return array{values: array<string, array<string, mixed>>, record: array{type: string, id: int}}|null */
    private function existingJob(User $user, AiConversation $conversation): ?array
    {
        $jobs = $user->employments()->limit(2)->pluck('id');
        if ($jobs->count() !== 1 || self::formSaved($conversation, CaptureForms::WORK)) {
            return null;
        }

        $form = $this->editForms->formFor($user, 'employment', (int) $jobs->first());
        if ($form === null) {
            return null;
        }

        return ['values' => $form['answers'], 'record' => $form['record']];
    }

    /** @return array{values: array<string, array<string, mixed>>, record: array{type: string, id: int}, schema: array<string, mixed>}|null */
    private function existingRecord(User $user, AiConversation $conversation, string $formName): ?array
    {
        if (self::formSaved($conversation, $formName)) {
            return null;
        }

        $records = $this->recordsFor($user, $formName);
        if (count($records) !== 1) {
            return null;
        }

        [$type, $id] = $records[0];
        $form = $this->editForms->formFor($user, $type, $id);
        if ($form === null) {
            return null;
        }

        $schema = RecordEditForms::editSchema($formName, (string) array_key_first($form['answers']), $form['record']);
        if ($schema === null) {
            return null;
        }

        return ['values' => $form['answers'], 'record' => $form['record'], 'schema' => $schema];
    }

    /**
     * The user's own records a walk form captures, as [type, id], at most two
     * (only "exactly one" matters). Joint records the partner owns are theirs
     * to confirm.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function recordsFor(User $user, string $formName): array
    {
        $ids = static fn (string $type, $query): array => $query->limit(2)->pluck('id')
            ->map(static fn ($id): array => [$type, (int) $id])->all();
        // Savings and pensions are read through their stores (StoreBoundary).
        $idsOf = static fn (string $type, $records): array => $records->take(2)
            ->map(static fn ($record): array => [$type, (int) $record->id])->values()->all();
        $savings = $this->savings->forUser($user)->where('user_id', $user->id);
        $pensions = $this->pensions->dcPensionsFor($user);
        $isaInvestment = static fn ($q) => $q->whereNotNull('isa_type')->orWhere('account_type', 'isa');

        return match ($formName) {
            CaptureForms::SAVINGS => $idsOf('savings_account', $savings->where('account_type', '!=', 'cash_isa')),
            CaptureForms::ISA => array_slice([
                ...$idsOf('savings_account', $savings->where('account_type', 'cash_isa')),
                ...$ids('investment_account', InvestmentAccount::where('user_id', $user->id)->where($isaInvestment)),
            ], 0, 2),
            CaptureForms::INVESTMENT => $ids('investment_account', InvestmentAccount::where('user_id', $user->id)->whereNot($isaInvestment)),
            CaptureForms::PENSION => $idsOf('dc_pension', $pensions),
            // The personal-pension-only form cannot hold a workplace pension.
            CaptureForms::PENSION_PERSONAL => array_slice($pensions
                ->reject(static fn ($pension): bool => PensionContributionRule::isWorkplace($pension))
                ->map(static fn ($pension): array => ['dc_pension', (int) $pension->id])
                ->values()->all(), 0, 2),
            default => [],
        };
    }

    /**
     * The working-spouse form: what they do, when already known (an earlier
     * answer, the funnel, or their own linked account), and a linked
     * partner's own income when none was given for them.
     *
     * @return array{values: array<string, array<string, mixed>>, record: null}|null
     */
    private function linkedSpouseIncome(User $user): ?array
    {
        $holding = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
        $lead = [];

        $known = array_column(CaptureForms::SPOUSE_STATUS_OPTIONS, 'value');
        foreach ([$holding?->spouse_employment_status, FunnelAnswersMapper::spouseEmploymentStatus($user), $user->liveSpouse()?->employment_status] as $status) {
            if (in_array($status, $known, true)) {
                $lead['spouse_employment_status'] = $status;
                break;
            }
        }

        $linked = $holding?->spouse_annual_income !== null ? null : $this->household->linkedSpouseEarnings($user);
        if ($linked !== null) {
            $lead['spouse_annual_income'] = $linked['total_income'];
            $lead['spouse_annual_earnings'] = $linked['earnings'];
        }

        return $lead === [] ? null : ['values' => [CaptureForms::LEAD => $lead], 'record' => null];
    }

    /** A form of this name already posted in this conversation — the next one is another record. */
    private static function formSaved(AiConversation $conversation, string $formName): bool
    {
        return $conversation->messages()
            ->where('role', 'user')
            ->get(['metadata'])
            ->contains(static fn ($message): bool => ($message->metadata['form']['name'] ?? null) === $formName);
    }
}
