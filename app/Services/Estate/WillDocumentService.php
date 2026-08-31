<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;

class WillDocumentService
{
    /**
     * Blocking message when a will appoints its own testator as its executor.
     * Lives here as the one home so every path — the will builder, the mirror
     * generator and Fyn's create_will handler — refuses in the same words
     * (W-0024, Rule 20).
     */
    public const EXECUTOR_IS_TESTATOR_MESSAGE = 'A will cannot appoint its own testator as executor. Name the person who will carry out your wishes.';

    /**
     * Shown to the partner on a generated mirror will, against every gift
     * carried over from the will it was mirrored from.
     */
    public const COPIED_GIFTS_MESSAGE = "These gifts were copied from your partner's will — review them before completing, and change anything that should be different in yours.";

    /**
     * Raised while a mirror will has no counterpart. A mirror is a pair; half a
     * pair that looks finished is the failure W-0053 records.
     */
    public const MIRROR_NOT_GENERATED_MESSAGE = "Your partner's will has not been generated yet. A mirror will only works as a pair — use \"Generate Spouse's Will\" so they have their matching will to sign.";

    public function __construct(
        private readonly WillTypePolicy $willTypePolicy,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Gather pre-populated data from the user's existing profile.
     */
    public function prePopulateData(User $user): array
    {
        $addressParts = array_filter([
            $user->address_line_1,
            $user->address_line_2,
            $user->city,
            $user->county,
            $user->postcode,
        ]);

        $fullName = trim(implode(' ', array_filter([
            $user->first_name,
            $user->middle_name,
            $user->surname,
        ])));

        // Get spouse details
        $spouse = null;
        if ($user->spouse_id) {
            $spouseUser = User::find($user->spouse_id);
            if ($spouseUser) {
                $spouseAddressParts = array_filter([
                    $spouseUser->address_line_1,
                    $spouseUser->address_line_2,
                    $spouseUser->city,
                    $spouseUser->county,
                    $spouseUser->postcode,
                ]);

                $spouse = [
                    'full_name' => trim(implode(' ', array_filter([
                        $spouseUser->first_name,
                        $spouseUser->middle_name,
                        $spouseUser->surname,
                    ]))),
                    'address' => implode(', ', $spouseAddressParts),
                    'date_of_birth' => $spouseUser->date_of_birth?->format('Y-m-d'),
                    'occupation' => $spouseUser->occupation,
                ];
            }
        }

        $children = $this->householdChildren($user);

        // Get existing executor from will record
        $will = Will::where('user_id', $user->id)->first();
        $executorName = $will?->executor_name;

        return [
            'testator' => [
                'full_name' => $fullName,
                'address' => implode(', ', $addressParts),
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'occupation' => $user->occupation,
            ],
            'spouse' => $spouse,
            'children' => $children,
            'has_minor_children' => collect($children)->contains('is_minor', true),
            'existing_executor_name' => $executorName,
            'domicile_status' => $user->domicile_status,
            'marital_status' => $user->marital_status,
            // liveSpouseId(), not the raw spouse_id: the column deliberately
            // survives the partner deleting their account, and a mirror will
            // cannot be generated into an account that no longer exists.
            'has_spouse' => $user->liveSpouseId() !== null,
            'will_type_policy' => $this->willTypePolicy->payloadFor($user),
        ];
    }

    /**
     * Children of the household, not merely of this account.
     *
     * In a mirror pair the FamilyMember rows sit on whichever account did the
     * onboarding, so reading only the viewing user's rows hides the Guardians
     * step from the other parent — the will where the appointment matters just
     * as much, since a guardian only takes effect once both parents are gone
     * (W-0024). One query, one answer, read by both the wizard gate and
     * validateDocument().
     *
     * @return list<array{full_name: string, date_of_birth: string|null, is_dependent: bool, is_minor: bool}>
     */
    public function householdChildren(User $user): array
    {
        $householdIds = array_values(array_filter([$user->id, $user->liveSpouseId()]));

        return FamilyMember::whereIn('user_id', $householdIds)
            ->where('relationship', 'child')
            ->get()
            ->map(fn (FamilyMember $child) => [
                'full_name' => $child->full_name,
                'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
                'is_dependent' => (bool) $child->is_dependent,
                'is_minor' => $child->date_of_birth
                    ? $child->date_of_birth->age < 18
                    : false,
            ])
            ->unique(fn (array $child) => mb_strtolower($child['full_name']).'|'.($child['date_of_birth'] ?? ''))
            ->values()
            ->toArray();
    }

    /**
     * Create a new draft will document.
     */
    public function createDraft(User $user, array $data): WillDocument
    {
        $will = Will::where('user_id', $user->id)->first();

        return WillDocument::create([
            'user_id' => $user->id,
            'will_id' => $will?->id,
            'will_type' => $data['will_type'] ?? 'simple',
            'status' => 'draft',
            'testator_full_name' => $data['testator_full_name'] ?? '',
            'testator_address' => $data['testator_address'] ?? null,
            'testator_date_of_birth' => $data['testator_date_of_birth'] ?? null,
            'testator_occupation' => $data['testator_occupation'] ?? null,
            'domicile_confirmed' => $data['domicile_confirmed'] ?? null,
        ]);
    }

    /**
     * Update a specific wizard step.
     */
    public function updateStep(WillDocument $doc, string $step, array $data): WillDocument
    {
        $updateData = match ($step) {
            'personal' => [
                'testator_full_name' => $data['testator_full_name'] ?? $doc->testator_full_name,
                'testator_address' => $data['testator_address'] ?? $doc->testator_address,
                'testator_date_of_birth' => $data['testator_date_of_birth'] ?? $doc->testator_date_of_birth,
                'testator_occupation' => $data['testator_occupation'] ?? $doc->testator_occupation,
            ],
            'executors' => [
                'executors' => $data['executors'] ?? [],
            ],
            'guardians' => [
                'guardians' => $data['guardians'] ?? [],
            ],
            'gifts' => [
                // Saving this step IS the review the mirror asks for, so the
                // "copied from your partner" marker is cleared here rather than
                // needing its own dismissal (W-0024).
                'specific_gifts' => $this->clearCopiedMarkers($data['specific_gifts'] ?? []),
            ],
            'residuary' => [
                'residuary_estate' => $data['residuary_estate'] ?? [],
            ],
            'funeral' => [
                'funeral_preference' => $data['funeral_preference'] ?? null,
                'funeral_wishes_notes' => $data['funeral_wishes_notes'] ?? null,
            ],
            'digital' => [
                'digital_executor_name' => $data['digital_executor_name'] ?? null,
                'digital_assets_instructions' => $data['digital_assets_instructions'] ?? null,
            ],
            'intro' => [
                'will_type' => $data['will_type'] ?? $doc->will_type,
                'domicile_confirmed' => $data['domicile_confirmed'] ?? $doc->domicile_confirmed,
            ],
            default => [],
        };

        if (! empty($updateData)) {
            $updateData['last_edited_at'] = now();
            $doc->update($updateData);
        }

        return $doc->fresh();
    }

    /**
     * Validate the document and return any warnings.
     */
    public function validateDocument(WillDocument $doc): array
    {
        $warnings = [];

        // Must have at least one executor
        $executors = $doc->executors ?? [];
        if (empty($executors)) {
            $warnings[] = [
                'field' => 'executors',
                'message' => 'You must appoint at least one executor.',
                'severity' => 'error',
            ];
        }

        // Check executor has required fields
        foreach ($executors as $i => $executor) {
            if (empty($executor['name'])) {
                $warnings[] = [
                    'field' => 'executors',
                    'message' => 'Executor '.($i + 1).' is missing a name.',
                    'severity' => 'error',
                ];
            }
            if (empty($executor['address'])) {
                $warnings[] = [
                    'field' => 'executors',
                    'message' => 'Executor '.($i + 1).' is missing an address.',
                    'severity' => 'warning',
                ];
            }
            // W-0024: the mirror generator copied executors verbatim, so a
            // partner's will appointed her as her own executor. Blocking here
            // covers every path into a document, not just that generator.
            if (self::isSameParty((string) ($executor['name'] ?? ''), (string) $doc->testator_full_name)) {
                $warnings[] = [
                    'field' => 'executors',
                    'message' => self::EXECUTOR_IS_TESTATOR_MESSAGE,
                    'severity' => 'error',
                ];
            }
        }

        // Residuary estate must sum to 100%
        $residuary = $doc->residuary_estate ?? [];
        if (empty($residuary)) {
            $warnings[] = [
                'field' => 'residuary_estate',
                'message' => 'You must specify how to distribute your residuary estate.',
                'severity' => 'error',
            ];
        } else {
            $totalPercentage = array_sum(array_column($residuary, 'percentage'));
            if (abs($totalPercentage - 100) > 0.01) {
                $warnings[] = [
                    'field' => 'residuary_estate',
                    'message' => "Residuary estate percentages total {$totalPercentage}% — they must add up to 100%.",
                    'severity' => 'error',
                ];
            }
        }

        // A mirror will is a PAIR. Completing one half and never generating the
        // other leaves the household with a will that looks finished and a
        // partner who has nothing — worse than the one-sided will W-0019 exists
        // to prevent, because it does not announce itself (W-0053).
        if ($doc->will_type === WillTypePolicy::MIRROR && $doc->mirror_document_id === null) {
            $warnings[] = [
                'field' => 'mirror',
                'message' => self::MIRROR_NOT_GENERATED_MESSAGE,
                'severity' => 'warning',
            ];
        }

        // Gifts carried over from the will this one mirrors must be looked at
        // before completing — a charitable legacy must never silently name the
        // other partner's charity (W-0024).
        if ($this->hasCopiedGifts($doc->specific_gifts ?? [])) {
            $warnings[] = [
                'field' => 'specific_gifts',
                'message' => self::COPIED_GIFTS_MESSAGE,
                'severity' => 'warning',
            ];
        }

        // Check for minor children without guardians
        $user = $doc->user;
        $hasMinorChildren = collect($this->householdChildren($user))->contains('is_minor', true);

        if ($hasMinorChildren && empty($doc->guardians)) {
            $warnings[] = [
                'field' => 'guardians',
                'message' => 'You have children under 18 but have not appointed a guardian.',
                'severity' => 'warning',
            ];
        }

        // Testator must be 18+
        if ($doc->testator_date_of_birth) {
            $age = $doc->testator_date_of_birth->age;
            if ($age < 18) {
                $warnings[] = [
                    'field' => 'personal',
                    'message' => 'You must be 18 or older to create a valid will in England and Wales.',
                    'severity' => 'error',
                ];
            }
        }

        // Domicile check
        if ($doc->domicile_confirmed && ! in_array($doc->domicile_confirmed, ['england_wales'])) {
            $warnings[] = [
                'field' => 'domicile',
                'message' => 'This will builder is designed for England and Wales law only. Different rules apply in Scotland and Northern Ireland.',
                'severity' => 'warning',
            ];
        }

        // Recommend backup executor
        if (count($executors) < 2) {
            $warnings[] = [
                'field' => 'executors',
                'message' => 'Consider appointing a backup executor in case your primary executor is unable to act.',
                'severity' => 'info',
            ];
        }

        return $warnings;
    }

    /**
     * Generate a mirror will for the spouse.
     */
    public function generateMirrorWill(WillDocument $primary): WillDocument
    {
        // Generating is now reachable after completion as well as before
        // (W-0053), so it has to be safe to press twice. Return the pair that
        // already exists rather than creating a second one.
        if ($primary->mirror_document_id !== null) {
            $existing = WillDocument::find($primary->mirror_document_id);

            if ($existing !== null) {
                return $existing;
            }
        }

        $user = $primary->user;

        // W-0350 — a mirror will is a document CREATED INSIDE the other person's
        // account, carrying this caller's executors and guardians. `User::find($user
        // ->spouse_id)` authorised that on the strength of the caller having named
        // them. Reciprocal only.
        $spouse = $user->reciprocalLiveSpouse();

        if (! $spouse) {
            throw new \RuntimeException('Cannot generate mirror will: no reciprocally linked spouse.');
        }

        $spouseFullName = trim(implode(' ', array_filter([
            $spouse->first_name,
            $spouse->middle_name,
            $spouse->surname,
        ])));

        $spouseAddressParts = array_filter([
            $spouse->address_line_1,
            $spouse->address_line_2,
            $spouse->city,
            $spouse->county,
            $spouse->postcode,
        ]);

        // Every party named in the primary's will is swapped the same way: where
        // the primary named their partner, the mirror names the primary. Before
        // W-0024 only the residuary got this treatment, so the partner's will
        // appointed her as her own executor and described her as her own spouse.
        //
        // W-0396: matched on ONE spelling of each name, and the two sides were
        // built differently — the primary's from `testator_full_name` (what the
        // will says) and the partner's from first + middle + surname (what the
        // profile holds). A partner with a middle name recorded, named in the
        // will without it, matched neither, so nothing was swapped and W-0024's
        // exact symptom came back for that household. Both sides now carry every
        // spelling the person's own records give them; the first is the one
        // written.
        $primaryNames = self::nameVariants($user, (string) $primary->testator_full_name);
        $spouseNames = self::nameVariants($spouse);

        $swap = fn (array $rows, string $nameKey): array => $this->swapPartiesForMirror(
            $rows,
            $nameKey,
            $primaryNames,
            $spouseNames
        );

        $mirrorResiduary = $swap($primary->residuary_estate ?? [], 'beneficiary_name');

        $spouseWill = Will::where('user_id', $spouse->id)->first();

        $mirror = WillDocument::create([
            'user_id' => $spouse->id,
            'will_id' => $spouseWill?->id,
            'mirror_document_id' => $primary->id,
            'will_type' => 'mirror',
            'status' => 'draft',
            'testator_full_name' => $spouseFullName,
            'testator_address' => implode(', ', $spouseAddressParts),
            'testator_date_of_birth' => $spouse->date_of_birth,
            'testator_occupation' => $spouse->occupation,
            'executors' => $swap($primary->executors ?? [], 'name'),
            'guardians' => $swap($primary->guardians ?? [], 'name'),
            // Copied so the partner starts from something, but marked so the
            // review step tells her they came from his will. A mirror pair may
            // hold different gifts — each partner edits their own draft.
            'specific_gifts' => $this->markGiftsAsCopied(
                $swap($primary->specific_gifts ?? [], 'beneficiary_name')
            ),
            'residuary_estate' => $mirrorResiduary,
            'funeral_preference' => $primary->funeral_preference,
            'funeral_wishes_notes' => null,
            'digital_executor_name' => $primary->digital_executor_name,
            'digital_assets_instructions' => null,
            'survivorship_days' => $primary->survivorship_days,
            'domicile_confirmed' => $primary->domicile_confirmed,
        ]);

        // Link the primary to the mirror
        $primary->update(['mirror_document_id' => $mirror->id]);

        return $mirror;
    }

    /**
     * Put a completed will back into draft so it can be corrected.
     *
     * **W-0133.** Completion was a one-way door: nothing anywhere set `status`
     * back to `draft`, so a gift edited or a bequest deleted in the Estate module
     * after finalising could never be re-synced, and the will document and the
     * module diverged permanently — with the document still presented as the
     * user's will.
     *
     * Reopening does NOT rewrite anything. It clears `generated_at`, because a
     * document being edited has not been generated, and leaves every answer in
     * place so the wizard resumes where it was. Completing again runs the same
     * validation and the same `WillTypePolicy` refusal as the first time.
     *
     * Deliberately not automatic. A will that silently reverted to draft when an
     * unrelated gift changed would leave a user believing they had a completed
     * will when they did not — the failure this fix exists to remove, in the
     * opposite direction.
     */
    public function reopen(WillDocument $doc): WillDocument
    {
        if ($doc->status !== 'complete') {
            return $doc;
        }

        $doc->update([
            'status' => 'draft',
            'generated_at' => null,
            'last_edited_at' => now(),
        ]);

        return $doc->fresh();
    }

    /**
     * Mark a will document as complete.
     */
    public function markComplete(WillDocument $doc): WillDocument
    {
        // W-0019: a married user may only complete a mirror will. Documents
        // already completed under the old flow are left exactly as they are —
        // this refuses a new one-sided will, it never rewrites an old one.
        $refusal = $this->willTypePolicy->refusalFor($doc->user, (string) $doc->will_type);
        if ($refusal !== null) {
            throw new \RuntimeException(WillTypePolicy::asText($refusal));
        }

        $errors = collect($this->validateDocument($doc))
            ->where('severity', 'error');

        if ($errors->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot complete will: '.$errors->first()['message']
            );
        }

        $doc->update([
            'status' => 'complete',
            'generated_at' => now(),
            'last_edited_at' => now(),
        ]);

        // Sync with the wills table
        $will = Will::firstOrCreate(
            ['user_id' => $doc->user_id],
            ['has_will' => true]
        );

        $will->update([
            'has_will' => true,
            'will_last_updated' => now(),
            'last_reviewed_date' => now(),
            'will_document_id' => $doc->id,
            'executor_name' => self::executorNameFor($doc),
        ]);

        $doc->update(['will_id' => $will->id]);

        $this->syncBequests($doc, $will);

        $this->cacheInvalidation->invalidateForUser($doc->user_id);

        return $doc->fresh();
    }

    /**
     * Every spelling this person's own records give them, most authoritative first.
     *
     * W-0396. The mirror generator matched each partner on ONE spelling, and
     * built the two sides differently: the primary's from the will's
     * `testator_full_name`, the partner's from first + middle + surname off the
     * profile. A partner with a middle name recorded but named in the will
     * without it matched neither, so the swap found nothing to do and the
     * partner's mirror appointed her as her own executor — W-0024's exact
     * symptom, still reachable after W-0024 was fixed.
     *
     * This is NOT a loosening of isSameParty(), which stays deliberately
     * conservative because two people can share a name and guessing at nicknames
     * in a legal document would be worse than the bug. Every candidate here is a
     * name the SAME person's own record already holds — the will's own spelling,
     * the full profile name, and the same name without the middle. Nothing is
     * inferred, abbreviated or guessed.
     *
     * @return list<string>
     */
    public static function nameVariants(?User $person, ?string $asWritten = null): array
    {
        $full = $person === null ? '' : trim(implode(' ', array_filter([
            $person->first_name,
            $person->middle_name,
            $person->surname,
        ])));

        $withoutMiddle = $person === null ? '' : trim(implode(' ', array_filter([
            $person->first_name,
            $person->surname,
        ])));

        $candidates = array_filter(
            [$asWritten === null ? '' : trim($asWritten), $full, $withoutMiddle],
            static fn (string $name): bool => $name !== '',
        );

        return array_values(array_unique($candidates));
    }

    /**
     * The spelling of the partner's name that this will already uses.
     *
     * A repair rewords a legal instrument, so it should reword it as little as
     * possible: if Sarah's will already calls him "David Jones" in the residuary,
     * her executor clause should say "David Jones" too — not "David Michael
     * Jones" because that is what his profile happens to hold. The document's own
     * wording comes first, then the paired will's, then the profile as a last
     * resort.
     */
    private function partnerNameAsThisWillWritesIt(WillDocument $doc, User $spouse): string
    {
        $partnerNames = self::nameVariants($spouse);

        $alreadyWritten = [
            [$doc->executors ?? [], 'name'],
            [$doc->guardians ?? [], 'name'],
            [$doc->residuary_estate ?? [], 'beneficiary_name'],
        ];

        foreach ($alreadyWritten as [$rows, $key]) {
            foreach ($rows as $row) {
                $name = is_array($row) ? trim((string) ($row[$key] ?? '')) : '';

                if ($name !== '' && self::matchesAnyName($name, $partnerNames)) {
                    return $name;
                }
            }
        }

        $pairedName = $doc->mirror_document_id !== null
            ? WillDocument::whereKey($doc->mirror_document_id)->value('testator_full_name')
            : null;

        if (is_string($pairedName) && trim($pairedName) !== '' && self::matchesAnyName($pairedName, $partnerNames)) {
            return trim($pairedName);
        }

        return $partnerNames[0] ?? '';
    }

    /**
     * Does this document name its own testator as a party?
     *
     * The one home for the defect's definition, shared by the repair below and
     * by `estate:backfill-mirror-parties`. A document is broken exactly when it
     * names its testator — whatever produced it, whatever its type, whatever its
     * date. That catches pre-W-0024 mirrors and every other route to the same
     * state, and it cannot match a correct document.
     *
     * Deliberately NOT the same test `validateDocument()` applies before letting
     * a user complete a will. That one compares a single spelling, because it
     * BLOCKS: a false positive there stops someone finishing their will, and a
     * father and son can share a name where only one has a middle name recorded.
     * This one only selects candidates for a repair whose alternative is a
     * document that appoints its author as their own executor. The costs of
     * being wrong are not the same, so the tests are not the same.
     */
    public function namesItsOwnTestator(WillDocument $doc): bool
    {
        $testatorNames = self::nameVariants($doc->user, (string) $doc->testator_full_name);

        $lists = [
            [$doc->executors ?? [], 'name'],
            [$doc->guardians ?? [], 'name'],
            [$doc->residuary_estate ?? [], 'beneficiary_name'],
        ];

        foreach ($lists as [$rows, $key]) {
            foreach ($rows as $row) {
                if (is_array($row) && self::matchesAnyName((string) ($row[$key] ?? ''), $testatorNames)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $names
     */
    private static function matchesAnyName(string $name, array $names): bool
    {
        foreach ($names as $candidate) {
            if (self::isSameParty($name, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `wills.executor_name` string a document implies.
     *
     * The one home for it. `markComplete()` built this inline, so nothing else
     * could produce the same string and the repair below would have been a
     * second implementation of the same derivation (Rule 20).
     */
    public static function executorNameFor(WillDocument $doc): ?string
    {
        $names = collect($doc->executors ?? [])
            ->pluck('name')
            ->filter()
            ->implode(', ');

        return $names ?: null;
    }

    /**
     * Repair a will that names its own testator as a party.
     *
     * W-0395 — the backfill half of W-0024. That fix corrected the mirror
     * GENERATOR; it could not correct documents already generated. Every mirror
     * created before it landed carries the primary's party lists verbatim, so
     * the partner's own will appoints HER as her executor, describes her as her
     * own spouse, and — because `markComplete()` derives `wills.executor_name`
     * from those lists — persisted that into Fynla's record of the household's
     * intentions, where the estate model reads it.
     *
     * Deliberately one-directional, and NOT the generator's swap. Running
     * `swapPartiesForMirror()` over an already-correct document would exchange
     * the partners back and make the primary his own executor — turning a
     * repair into the very defect it repairs. This replaces the testator's own
     * name with their partner's, wherever it appears as a party, and touches
     * nothing else: a professional executor, a sibling and a charity all keep
     * their name and their recorded relationship.
     *
     * It shares `isSameParty()` and `nameVariants()` with the generator and the
     * validator, so all three agree on when two names are one person.
     *
     * Returns true when something changed. Idempotent: a repaired document no
     * longer names its testator, so a second run finds nothing to do.
     */
    public function repairSelfNamedParties(WillDocument $doc): bool
    {
        $user = $doc->user;
        $spouse = $user?->spouse_id ? User::find($user->spouse_id) : null;

        if ($spouse === null) {
            return false;
        }

        $partnerName = $this->partnerNameAsThisWillWritesIt($doc, $spouse);

        if ($partnerName === '') {
            return false;
        }

        $testatorNames = self::nameVariants($user, (string) $doc->testator_full_name);
        $changed = false;

        // Legacy rows, so nothing is assumed about the JSON's shape: a row that
        // is not an array is passed through untouched rather than crashing a
        // sweep over every completed will in the database.
        $replaceSelf = function (array $rows, string $nameKey) use ($testatorNames, $partnerName, &$changed): array {
            return array_values(array_map(function ($row) use ($nameKey, $testatorNames, $partnerName, &$changed) {
                if (! is_array($row)) {
                    return $row;
                }

                if (self::matchesAnyName((string) ($row[$nameKey] ?? ''), $testatorNames)) {
                    $row[$nameKey] = $partnerName;
                    $row['relationship'] = 'Spouse';
                    $changed = true;
                }

                return $row;
            }, $rows));
        };

        $executors = $replaceSelf($doc->executors ?? [], 'name');
        $guardians = $replaceSelf($doc->guardians ?? [], 'name');
        $residuary = $replaceSelf($doc->residuary_estate ?? [], 'beneficiary_name');

        if (! $changed) {
            return false;
        }

        $doc->update([
            'executors' => $executors,
            'guardians' => $guardians,
            'residuary_estate' => $residuary,
        ]);

        // The wrong names did not stay on the document — `markComplete()` wrote
        // them into `wills.executor_name`, which is what the will planning
        // screen reads. Repairing the document without this leaves the screen
        // showing the defect it just fixed.
        $will = $doc->will_id ? Will::find($doc->will_id) : Will::where('user_id', $doc->user_id)->first();

        if ($will !== null) {
            $will->update(['executor_name' => self::executorNameFor($doc->fresh())]);
        }

        $this->cacheInvalidation->invalidateForUser($doc->user_id);

        return true;
    }

    /**
     * Backfill entry point: sync one already-completed document's gifts.
     *
     * W-0046. syncBequests() runs on completion, so every will completed before
     * W-0023 landed holds its gifts in the document and has zero Bequest rows —
     * invisible to the Estate module and worth £0 to the charitable total, so a
     * legacy that should reach the reduced Inheritance Tax rate does not.
     *
     * This is a thin wrapper, not a second implementation: the backfill and a
     * later re-completion run the SAME sync, which is what makes them unable to
     * write the same gift twice (Rule 20). Returns the number of rows written.
     */
    public function syncBequestsForDocument(WillDocument $doc): int
    {
        $will = Will::firstOrCreate(
            ['user_id' => $doc->user_id],
            ['has_will' => true],
        );

        if ($doc->will_id !== $will->id) {
            $doc->update(['will_id' => $will->id]);
        }

        return $this->syncBequests($doc, $will);
    }

    /**
     * Turn the document's specific gifts into Bequest rows.
     *
     * Before W-0023 a gift entered in the will builder lived only as JSON on
     * the document: it rendered inside the generated will, so it looked
     * recorded, while the Estate module, WillAnalysisService and the /m
     * bequests screen all saw nothing — which is why a charitable legacy could
     * never reach the reduced Inheritance Tax rate.
     *
     * Rows this sync wrote before are replaced, so re-editing and re-completing
     * updates rather than duplicates. Rows created by hand through the Estate
     * bequest API carry no will_document_id and are never touched.
     *
     * Residuary beneficiaries deliberately stay document-only. The bequests
     * table has no way to say "a share of what is left after the gifts": a
     * residuary row would have to be stored as `percentage`, and
     * Will::getNonSpouseAllocationPercentage() sums exactly those rows — so a
     * mirror will leaving 100% to a partner would report a 100% NON-partner
     * allocation. Recording it there would corrupt an existing answer to buy a
     * duplicate of one the document already holds.
     */
    private function syncBequests(WillDocument $doc, Will $will): int
    {
        // Clear rows from ANY will document, not just this one. A user who
        // completes a second document has superseded the first, and scoping the
        // delete to $doc->id alone left the earlier document's gifts standing
        // beside the new ones — two live sets for one will, double-counted by
        // the charitable total (W-0046 acceptance 4).
        //
        // forceDelete, not delete: "replaced" has to mean replaced. Soft-
        // deleting would pile up a tombstone per completion, and these rows
        // hold no history the document does not already hold.
        Bequest::where('will_id', $will->id)
            ->whereNotNull('will_document_id')
            ->forceDelete();

        $existing = Bequest::where('will_id', $will->id)
            ->whereNull('will_document_id')
            ->get();

        $priority = 0;

        foreach ($doc->specific_gifts ?? [] as $gift) {
            if (! is_array($gift)) {
                continue;
            }

            $beneficiary = trim((string) ($gift['beneficiary_name'] ?? ''));
            if ($beneficiary === '') {
                continue;
            }

            $isCash = ($gift['type'] ?? 'cash') === 'cash';
            $amount = $gift['amount'] ?? null;
            $description = trim((string) ($gift['description'] ?? ''));

            $attributes = [
                'will_id' => $will->id,
                'will_document_id' => $doc->id,
                'user_id' => $doc->user_id,
                'beneficiary_name' => $beneficiary,
                // W-0394. Left unset, this took the column default `individual`,
                // so a charitable legacy entered in the will builder was stored
                // as a gift to a person — including both of the peak_earners
                // household's. Bequest::isCharitable() re-derived the truth from
                // the name on every read, which hid it for the charities its
                // name list happens to know and hid nothing for the ones it does
                // not. Same one home as WillController::classifyBeneficiary().
                'beneficiary_type' => Bequest::inferBeneficiaryType($beneficiary),
                'bequest_type' => $isCash ? 'specific_amount' : 'specific_asset',
                'specific_amount' => $isCash && $amount !== null ? (float) $amount : null,
                'specific_asset_description' => $isCash ? null : ($description !== '' ? $description : null),
                'priority_order' => ++$priority,
                'conditions' => trim((string) ($gift['conditions'] ?? '')) ?: null,
            ];

            // A user who recorded this gift through the Estate bequest API
            // before entering it in the will builder must not end up with two
            // live rows — the charitable total sums both, and a doubled total
            // can push someone onto the reduced Inheritance Tax rate they have
            // not earned. Adopt the hand-made row instead of adding to it.
            $duplicate = $existing->first(fn (Bequest $bequest) => self::isSameParty($bequest->beneficiary_name, $beneficiary)
                && $bequest->bequest_type === $attributes['bequest_type']);

            if ($duplicate !== null) {
                $duplicate->update($attributes);

                continue;
            }

            Bequest::create($attributes);
        }

        return $priority;
    }

    /**
     * Get the user's current draft or completed will document.
     */
    public function getForUser(User $user): ?WillDocument
    {
        return WillDocument::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Swap the two partners wherever either is named, for any list of parties
     * in a will document — executors, guardians or residuary beneficiaries.
     *
     * Where a swap happens the `relationship` becomes "Spouse", because after
     * the swap the named party IS the mirror testator's partner. Third parties
     * (a professional executor, a sibling) keep their name and their recorded
     * relationship: we cannot derive one partner's relationship to the other's
     * relatives, which is why every copied gift is flagged for review rather
     * than presented as settled.
     *
     * Each partner arrives as a list of the spellings their own records give
     * them (W-0396). The FIRST entry is the one written into the mirror; the
     * rest exist only so a party recorded under a different spelling of the same
     * person is still recognised as that person.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $primaryNames
     * @param  list<string>  $spouseNames
     * @return list<array<string, mixed>>
     */
    private function swapPartiesForMirror(array $rows, string $nameKey, array $primaryNames, array $spouseNames): array
    {
        return array_values(array_map(function (array $row) use ($nameKey, $primaryNames, $spouseNames) {
            $name = (string) ($row[$nameKey] ?? '');

            if (self::matchesAnyName($name, $spouseNames)) {
                $row[$nameKey] = $primaryNames[0] ?? $name;
                $row['relationship'] = 'Spouse';
            } elseif (self::matchesAnyName($name, $primaryNames)) {
                $row[$nameKey] = $spouseNames[0] ?? $name;
                $row['relationship'] = 'Spouse';
            }

            return $row;
        }, $rows));
    }

    /**
     * @param  list<array<string, mixed>>  $gifts
     * @return list<array<string, mixed>>
     */
    private function markGiftsAsCopied(array $gifts): array
    {
        return array_values(array_map(function (array $gift) {
            $gift['copied_from_partner'] = true;

            return $gift;
        }, $gifts));
    }

    /**
     * @param  array<int, mixed>  $gifts
     * @return list<array<string, mixed>>
     */
    private function clearCopiedMarkers(array $gifts): array
    {
        return array_values(array_map(function ($gift) {
            if (! is_array($gift)) {
                return $gift;
            }

            unset($gift['copied_from_partner']);

            return $gift;
        }, $gifts));
    }

    /**
     * @param  array<int, mixed>  $gifts
     */
    private function hasCopiedGifts(array $gifts): bool
    {
        foreach ($gifts as $gift) {
            if (is_array($gift) && ! empty($gift['copied_from_partner'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Do these two free-text names refer to the same person?
     *
     * The one home for that question — the mirror swap, the executor-is-testator
     * block and Fyn's create_will handler all ask it here so they cannot drift
     * apart (Rule 20). Deliberately conservative: case and surrounding
     * whitespace are ignored, nothing else. Two people can share a name, and
     * guessing at nicknames in a legal document would be worse than the bug.
     */
    public static function isSameParty(string $a, string $b): bool
    {
        $a = mb_strtolower(trim(preg_replace('/\s+/', ' ', $a) ?? ''));
        $b = mb_strtolower(trim(preg_replace('/\s+/', ' ', $b) ?? ''));

        return $a !== '' && $a === $b;
    }
}
