<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AiConversation;
use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Retirement\PensionContributionRule;

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
            CaptureForms::PENSION, CaptureForms::PENSION_PERSONAL => $this->existingRecord($user, $conversation, $formName),
            default => null,
        };
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
        $isaInvestment = static fn ($q) => $q->whereNotNull('isa_type')->orWhere('account_type', 'isa');

        return match ($formName) {
            CaptureForms::SAVINGS => $ids('savings_account', SavingsAccount::where('user_id', $user->id)->where('account_type', '!=', 'cash_isa')),
            CaptureForms::ISA => array_slice([
                ...$ids('savings_account', SavingsAccount::where('user_id', $user->id)->where('account_type', 'cash_isa')),
                ...$ids('investment_account', InvestmentAccount::where('user_id', $user->id)->where($isaInvestment)),
            ], 0, 2),
            CaptureForms::INVESTMENT => $ids('investment_account', InvestmentAccount::where('user_id', $user->id)->whereNot($isaInvestment)),
            CaptureForms::PENSION => $ids('dc_pension', DCPension::where('user_id', $user->id)),
            // The personal-pension-only form cannot hold a workplace pension.
            CaptureForms::PENSION_PERSONAL => array_slice(DCPension::where('user_id', $user->id)->get()
                ->reject(static fn (DCPension $pension): bool => PensionContributionRule::isWorkplace($pension))
                ->map(static fn (DCPension $pension): array => ['dc_pension', (int) $pension->id])
                ->values()->all(), 0, 2),
            default => [],
        };
    }

    /** @return array{values: array<string, array<string, mixed>>, record: null}|null */
    private function linkedSpouseIncome(User $user): ?array
    {
        $given = TaxStrategyHouseholdInput::where('user_id', $user->id)->whereNotNull('spouse_annual_income')->exists();
        $linked = $given ? null : $this->household->linkedSpouseEarnings($user);
        if ($linked === null) {
            return null;
        }

        return ['values' => [CaptureForms::LEAD => [
            'spouse_annual_income' => $linked['total_income'],
            'spouse_annual_earnings' => $linked['earnings'],
        ]], 'record' => null];
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
