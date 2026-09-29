<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AiConversation;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Coordination\HouseholdFinancialContext;

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
     * @return array{values: array<string, array<string, mixed>>, record: array{type: string, id: int}|null}|null
     */
    public function for(User $user, AiConversation $conversation, string $formName): ?array
    {
        return match ($formName) {
            CaptureForms::WORK => $this->existingJob($user, $conversation),
            CaptureForms::SPOUSE_HOUSEHOLD => $this->linkedSpouseIncome($user),
            default => null,
        };
    }

    /** @return array{values: array<string, array<string, mixed>>, record: array{type: string, id: int}}|null */
    private function existingJob(User $user, AiConversation $conversation): ?array
    {
        $jobs = $user->employments()->limit(2)->pluck('id');
        if ($jobs->count() !== 1 || self::workFormSaved($conversation)) {
            return null;
        }

        $form = $this->editForms->formFor($user, 'employment', (int) $jobs->first());
        if ($form === null) {
            return null;
        }

        return ['values' => $form['answers'], 'record' => $form['record']];
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

    /** A work form already posted in this conversation — the next one is another job. */
    private static function workFormSaved(AiConversation $conversation): bool
    {
        return $conversation->messages()
            ->where('role', 'user')
            ->get(['metadata'])
            ->contains(static fn ($message): bool => ($message->metadata['form']['name'] ?? null) === CaptureForms::WORK);
    }
}
