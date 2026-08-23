<?php

declare(strict_types=1);

namespace App\Services\UserProfile;

use App\Models\Investment\InvestmentAccount;
use App\Models\LetterToSpouse;
use App\Models\User;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;
use App\Support\SharedOwnership;
use App\Traits\CalculatesOwnershipShare;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LetterToSpouseService
{
    use CalculatesOwnershipShare;

    /**
     * Sections Fynla fills in from the user's records. Everything else on the
     * letter is theirs from the start.
     *
     * @var list<string>
     */
    private const AUTO_POPULATED_FIELDS = [
        'immediate_actions',
        'employer_hr_contact',
        'immediate_funds_access',
        'bank_accounts_info',
        'investment_accounts_info',
        'insurance_policies_info',
        'real_estate_info',
        'liabilities_info',
        'beneficiary_info',
        'children_education_plans',
        'financial_guidance',
    ];

    /**
     * Text older rows may still hold from a generator that asserted an absence
     * instead of leaving the section empty. Adopting these on read is safe —
     * no user typed them (W-0022).
     *
     * @var list<string>
     */
    private const LEGACY_GENERATOR_SENTINELS = [
        'No outstanding liabilities recorded.',
        'Note: Review which accounts are joint accounts that can be accessed immediately.',
    ];

    public function __construct(
        private readonly PropertyStore $propertyStore,
        private readonly MortgageStore $mortgageStore,
        private readonly SavingsStore $savingsStore,
        private readonly CrossModuleAssetAggregator $assetAggregator,
        private readonly UserProfileService $profileService,
    ) {}

    /**
     * Get or create the letter, with Fynla-owned sections recomputed from live
     * data.
     *
     * The letter used to be written once, at row creation, and never revisited
     * — so a row created before the user added a mortgage told their partner
     * "No outstanding liabilities recorded." for good (W-0022). Sections the
     * user has edited are never touched.
     */
    public function getOrCreateLetter(User $user): LetterToSpouse
    {
        $letter = $user->letterToSpouse;

        if (! $letter) {
            return $this->createWithDefaults($user);
        }

        return $this->refreshAutoPopulatedSections($user, $letter);
    }

    /**
     * Create letter with auto-populated defaults from user data
     */
    private function createWithDefaults(User $user): LetterToSpouse
    {
        $data = $this->generateDefaultData($user);

        $letter = LetterToSpouse::create(array_merge(
            ['user_id' => $user->id, 'auto_populated_fields' => self::AUTO_POPULATED_FIELDS],
            $data,
        ));

        // user_id is unique on letters_to_spouse, and $user->letterToSpouse has
        // already cached its null. Without this a second call on the same User
        // instance tries to insert a second row.
        $user->setRelation('letterToSpouse', $letter);

        return $letter;
    }

    /**
     * Recompute every section Fynla still owns, and persist any that changed.
     */
    private function refreshAutoPopulatedSections(User $user, LetterToSpouse $letter): LetterToSpouse
    {
        $owned = $letter->auto_populated_fields;

        if (! is_array($owned)) {
            $owned = $this->adoptableLegacySections($letter);
        }

        $owned = array_values(array_intersect($owned, self::AUTO_POPULATED_FIELDS));

        $generated = $this->generateDefaultData($user);
        $changes = [];

        foreach ($owned as $field) {
            if (array_key_exists($field, $generated) && $generated[$field] !== $letter->$field) {
                $changes[$field] = $generated[$field];
            }
        }

        if ($changes !== [] || $letter->auto_populated_fields !== $owned) {
            $letter->update($changes + ['auto_populated_fields' => $owned]);
        }

        return $letter;
    }

    /**
     * Which sections of a row predating auto_populated_fields may Fynla adopt?
     *
     * Only those holding nothing, or one of the sentences the old generator
     * emitted when it found no data. Anything else might be the user's own
     * words, and a letter to a grieving partner is not the place to guess.
     *
     * @return list<string>
     */
    private function adoptableLegacySections(LetterToSpouse $letter): array
    {
        return array_values(array_filter(self::AUTO_POPULATED_FIELDS, function (string $field) use ($letter) {
            $value = $letter->$field;

            if ($value === null || (is_string($value) && trim($value) === '')) {
                return true;
            }

            return is_string($value) && in_array(trim($value), self::LEGACY_GENERATOR_SENTINELS, true);
        }));
    }

    /**
     * What this household owes — the one answer, read by the letter's
     * liabilities section and by the letter consistency checker.
     *
     * Before W-0022 each computed its own, so the page could tell the user
     * there were no liabilities in one panel and one liability in another
     * (Rule 20).
     *
     * @return array{liabilities: Collection, mortgages: Collection}
     */
    public function outstandingLiabilities(User $user): array
    {
        return [
            'liabilities' => $user->liabilities,
            // Primary-only, matching the pre-PR-5a $user->mortgages() HasMany
            // semantics the letter has always used.
            'mortgages' => $this->mortgageStore->forUserPrimaryOnly($user),
        ];
    }

    public function outstandingLiabilityCount(User $user): int
    {
        $records = $this->outstandingLiabilities($user);

        return $records['liabilities']->count() + $records['mortgages']->count();
    }

    /**
     * The one financial position this letter states.
     *
     * The cards, the printed document and the generated prose all read this and
     * nothing else. Before it the letter answered the question three separate
     * times and agreed with nobody: six `reduce()` calls in
     * `LetterToSpouse.vue` summing `current_balance` / `current_value` at 100%,
     * per-item values taken straight off the same records, and the generators
     * below listing raw record values in prose. None of the three applied an
     * ownership share.
     *
     * What that cost, on one live household: the letter told the bereaved spouse
     * the estate held £1,570,000 of property where this user's share is
     * £755,500, and £365,000 of debt where his share is £170,500. £177,000 of
     * that property and £72,000 of that debt belong to a co-owner who has no
     * account here. A third party's money, handed to the estate, in a document
     * addressed to the survivor and exportable as a PDF — and a wrong figure in
     * a printed document outlives every later fix.
     *
     * Nothing here re-derives a share. Reach and fraction come from
     * `CrossModuleAssetAggregator`; the debt side comes from
     * `UserProfileService::calculateLiabilitiesSummary`, the same itemisation the
     * profile and `/protection` read, whose mortgage share follows the property
     * securing it (W-0228) rather than the mortgage's own columns.
     *
     * **Pensions and protection are deliberately not here.** A defined-
     * contribution pension is individual, so no share applies to it; and which
     * policies reach a given user is the protection module's question, answered
     * by its own reader after W-0186/W-0384. Answering either again here would be
     * exactly the parallel mechanism this method exists to remove, so the letter
     * goes on reading both from their own modules.
     *
     * @return array{
     *     savings: array{total: float, items: list<array<string, mixed>>},
     *     investments: array{total: float, items: list<array<string, mixed>>},
     *     properties: array{total: float, items: list<array<string, mixed>>},
     *     liabilities: array{total: float, items: list<array<string, mixed>>}
     * }
     */
    public function financialPosition(User $user): array
    {
        $userId = (int) $user->id;

        $savingsRecords = $this->savingsStore->forUser($user)->keyBy('id');
        $investmentRecords = InvestmentAccount::forUserOrJoint($userId)->get()->keyBy('id');
        $propertyRecords = $this->propertyStore->forUserWithJointOwner($user)->keyBy('id');

        $debts = $this->profileService->calculateLiabilitiesSummary($user);
        $mortgageItems = collect($debts['mortgages']['items']);

        // What this user owes against each of their properties — their share of
        // it, not the whole of the borrowing. `properties.outstanding_mortgage`
        // is the denormalised full balance and was what the prose printed.
        $mortgageByProperty = $mortgageItems
            ->filter(fn (array $item): bool => $item['property_id'] !== null)
            ->groupBy('property_id')
            ->map(fn (Collection $items): float => round((float) $items->sum('outstanding_balance'), 2));

        $savings = $this->assetAggregator->getSavingsAssets($userId)
            ->map(function (object $asset) use ($savingsRecords): array {
                $record = $savingsRecords->get($asset->source_id);

                return $this->positionItem($asset, [
                    'name' => $record?->account_name ?: ($record?->institution ?: $asset->asset_name),
                    'subtext' => (string) ($record?->institution ?? ''),
                    'account_type' => $asset->account_type,
                    'is_isa' => (bool) ($record?->is_isa ?? false),
                ]);
            })
            ->values()
            ->all();

        $investments = $this->assetAggregator->getInvestmentAssets($userId)
            ->map(function (object $asset) use ($investmentRecords): array {
                $record = $investmentRecords->get($asset->source_id);

                return $this->positionItem($asset, [
                    'name' => $record?->account_name ?: ($record?->provider ?: $asset->asset_name),
                    'subtext' => (string) ($record?->provider ?? ''),
                    'account_type' => $asset->account_type,
                    // `investment_accounts.account_type` holds `isa`, never
                    // `stocks_and_shares_isa` — that is an `isa_type` value, and
                    // `TaxProductInfoService:52` maps the two together. The card's
                    // ISA badge was tested against the wrong one, so it has never
                    // fired on an investment account. Both accepted rather than
                    // swapped, since the pair is treated as one elsewhere.
                    'is_isa' => in_array($asset->account_type, ['isa', 'stocks_and_shares_isa'], true),
                ]);
            })
            ->values()
            ->all();

        $properties = $this->assetAggregator->getPropertyAssets($userId)
            ->map(function (object $asset) use ($propertyRecords, $mortgageByProperty): array {
                $record = $propertyRecords->get($asset->source_id);

                // `property_name` is not a column on `properties` and never was,
                // so the card's `property_name || address_line_1` fallback only
                // ever reached the address. Named directly rather than left as a
                // read that looks like it does something.
                return $this->positionItem($asset, [
                    'name' => (string) ($record?->address_line_1 ?? $asset->asset_name),
                    'subtext' => (string) ($record?->property_type ?? ''),
                    'property_type' => (string) ($record?->property_type ?? ''),
                    'city' => (string) ($record?->city ?? ''),
                    'postcode' => (string) ($record?->postcode ?? ''),
                    'mortgage_balance' => (float) $mortgageByProperty->get($asset->source_id, 0.0),
                ]);
            })
            ->values()
            ->all();

        $liabilities = $mortgageItems
            ->map(function (array $item) use ($propertyRecords, $userId): array {
                $securing = $item['property_id'] === null
                    ? null
                    : $propertyRecords->get($item['property_id']);

                return [
                    'id' => 'mortgage-'.$item['id'],
                    'name' => $item['lender'] ?: 'Mortgage — lender not recorded',
                    'subtext' => 'Mortgage',
                    'liability_type' => 'mortgage',
                    'value' => round((float) $item['outstanding_balance'], 2),
                    'monthly_payment' => $item['monthly_payment'],
                    // A mortgage is shared as the property securing it is shared
                    // (W-0228), so the badge names the property's ownership, not
                    // the mortgage row's — the two disagree on this household.
                    'ownership_type' => (string) ($securing->ownership_type ?? 'individual'),
                    'ownership_percentage' => $this->securingSharePercentage($securing, $userId),
                ];
            })
            ->concat($this->otherLiabilityItems($debts, $userId))
            ->values()
            ->all();

        return [
            'savings' => [
                'total' => round($this->assetAggregator->calculateCashTotal($userId), 2),
                'items' => $savings,
            ],
            'investments' => [
                'total' => round($this->assetAggregator->calculateInvestmentTotal($userId), 2),
                'items' => $investments,
            ],
            'properties' => [
                'total' => round($this->assetAggregator->calculatePropertyTotal($userId), 2),
                'items' => $properties,
            ],
            'liabilities' => [
                'total' => round((float) $debts['total'], 2),
                'items' => $liabilities,
            ],
        ];
    }

    /**
     * One item shape for every section, so the cards, the printed document and
     * the prose cannot drift apart the way they did.
     *
     * `value` is always THIS user's share; `full_value` is the whole record. They
     * are equal on anything individually held, so a reader can tell a shared
     * record from a wholly-owned one without being told the rules.
     *
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>
     */
    private function positionItem(object $asset, array $presentation): array
    {
        // Nothing in this persona populates `account_name`, so name and subtext
        // both resolve to the institution and the card reads "HSBC" over "HSBC".
        // Dropped here rather than in each surface, or two of them would drift.
        if (($presentation['subtext'] ?? null) === ($presentation['name'] ?? null)) {
            $presentation['subtext'] = '';
        }

        return array_merge([
            'id' => $asset->source_id,
            'value' => round((float) $asset->current_value, 2),
            'full_value' => round((float) $asset->full_value, 2),
            'ownership_type' => (string) $asset->ownership_type,
            'ownership_percentage' => (float) $asset->ownership_percentage,
            'is_shared' => (bool) $asset->is_shared,
        ], $presentation);
    }

    /**
     * This user's percentage of the property a mortgage is secured on — for the
     * ownership badge beside the debt, nothing else. The share itself is already
     * applied upstream; this only says which side of the split the reader is on.
     */
    private function securingSharePercentage(?object $securing, int $userId): float
    {
        if ($securing === null) {
            return 100.0;
        }

        return $this->isPrimaryOwner($securing, $userId)
            ? (float) ($securing->ownership_percentage ?? 100)
            : SharedOwnership::jointOwnerPercentage((float) ($securing->ownership_percentage ?? 50));
    }

    /**
     * Non-mortgage debts, already at this user's share.
     *
     * @param  array<string, mixed>  $debts
     * @return Collection<int, array<string, mixed>>
     */
    private function otherLiabilityItems(array $debts, int $userId): Collection
    {
        return collect($debts['other']['items'])
            ->map(fn (array $item): array => [
                'id' => 'liability-'.$item['id'],
                'name' => $item['liability_name'] ?: 'Not recorded',
                'subtext' => ucfirst(str_replace('_', ' ', (string) $item['liability_type'])),
                'liability_type' => (string) $item['liability_type'],
                'value' => round((float) $item['amount'], 2),
                'monthly_payment' => $item['monthly_payment'],
                'ownership_type' => 'individual',
                'ownership_percentage' => 100.0,
            ]);
    }

    /**
     * Generate default letter content from user's existing data
     */
    private function generateDefaultData(User $user): array
    {
        // Read once and hand to every generator below. The prose states the same
        // figures the cards and the exported document state, because it is the
        // same answer and not a second reading of the records (W-0421).
        $position = $this->financialPosition($user);

        return [
            // Part 1: Immediate actions - populate what we know
            'immediate_actions' => $this->generateImmediateActions($user),
            'employer_hr_contact' => $user->employer ? "Contact {$user->employer} HR Department" : null,
            'immediate_funds_access' => $this->generateImmediateFundsInfo($position),

            // Part 2: Accounts - populate from existing data
            'bank_accounts_info' => $this->generateBankAccountsInfo($position),
            'investment_accounts_info' => $this->generateInvestmentAccountsInfo($position),
            'insurance_policies_info' => $this->generateInsurancePoliciesInfo($user),
            'real_estate_info' => $this->generateRealEstateInfo($position),
            'liabilities_info' => $this->generateLiabilitiesInfo($position),

            // Part 3: Long-term plans
            'beneficiary_info' => $this->generateBeneficiaryInfo($user),
            'children_education_plans' => $this->generateEducationPlansInfo($user),
            'financial_guidance' => $this->generateFinancialGuidanceInfo($user),

            // Part 4: Funeral wishes - leave empty for user to fill
            'funeral_preference' => 'not_specified',
        ];
    }

    /**
     * Generate immediate actions text
     */
    private function generateImmediateActions(User $user): string
    {
        $actions = [];

        $actions[] = '1. Contact our executor immediately (details below)';
        $actions[] = "2. Notify my employer's HR department";
        $actions[] = '3. Access joint bank accounts for immediate expenses';
        $actions[] = '4. Contact our financial advisor for guidance';

        if ($user->protectionProfile) {
            $actions[] = '5. Contact life insurance companies to file claims (policy details below)';
        }

        $actions[] = '6. Keep my mobile phone active for account verification';
        $actions[] = '7. Register the death with the local registrar';
        $actions[] = '8. Obtain multiple death certificates (at least 10 copies)';

        return implode("\n", $actions);
    }

    /**
     * Which accounts the survivor can reach on day one.
     *
     * **The one figure in this letter deliberately NOT at the user's share.**
     * Everywhere else, stating the whole of a shared record hands a co-owner's
     * money to the estate. Here the question is different — what can be drawn
     * on immediately — and a surviving joint holder reaches the whole balance by
     * survivorship, not half of it. Halving it would understate what is
     * available to someone who has funeral costs to meet this week. The line
     * says which figure it is so it cannot be mistaken for a share.
     *
     * @param  array<string, mixed>  $position
     */
    private function generateImmediateFundsInfo(array $position): ?string
    {
        $joint = collect($position['savings']['items'])
            ->filter(fn (array $item): bool => ($item['ownership_type'] ?? 'individual') === 'joint');

        if ($joint->isEmpty()) {
            return 'Note: Review which accounts are joint accounts that can be accessed immediately.';
        }

        $info = "Joint Accounts (Accessible Immediately):\n\n";

        foreach ($joint as $item) {
            $info .= '• '.($item['subtext'] ?: $item['name']).' - £'
                .number_format((float) $item['full_value'], 2)." (full account balance)\n";
        }

        $info .= "\nThese joint accounts remain accessible in full to the surviving account holder. "
            .'Individual accounts may be frozen until probate.';

        return $info;
    }

    /**
     * @param  array<string, mixed>  $position
     */
    private function generateBankAccountsInfo(array $position): ?string
    {
        $items = $position['savings']['items'];

        if ($items === []) {
            return null;
        }

        $info = "Bank/Savings Accounts:\n\n";

        foreach ($items as $item) {
            $info .= '• '.($item['subtext'] ?: $item['name'])."\n";
            $info .= '  Account Type: '.$this->accountTypeLabel((string) ($item['account_type'] ?? 'savings'))."\n";
            $info .= '  Ownership: '.$this->humanise((string) $item['ownership_type'])."\n";
            $info .= $this->valueLine($item, 'Current Balance');
            $info .= "  Sort Code/Account Number: [Please add]\n\n";
        }

        $info .= 'Note: Add login credentials to password manager.';

        return $info;
    }

    /**
     * @param  array<string, mixed>  $position
     */
    private function generateInvestmentAccountsInfo(array $position): ?string
    {
        $items = $position['investments']['items'];

        if ($items === []) {
            return null;
        }

        $info = "Investment Accounts:\n\n";

        foreach ($items as $item) {
            $info .= '• '.($item['subtext'] ?: $item['name'])."\n";
            $info .= '  Account Type: '.$this->accountTypeLabel((string) ($item['account_type'] ?? ''))."\n";
            $info .= '  Ownership: '.$this->humanise((string) $item['ownership_type'])."\n";
            $info .= $this->valueLine($item, 'Current Value');
            $info .= "  Account Number: [Please add]\n\n";
        }

        $info .= 'Note: Add login credentials to password manager.';

        return $info;
    }

    /**
     * Generate insurance policies information
     */
    private function generateInsurancePoliciesInfo(User $user): ?string
    {
        $policies = [];

        // Life insurance
        $lifePolicies = $user->lifeInsurancePolicies;
        foreach ($lifePolicies as $policy) {
            $policies[] = [
                'type' => 'Life Insurance',
                'provider' => $policy->provider,
                'sum_assured' => $policy->sum_assured,
                'policy_number' => $policy->policy_number,
            ];
        }

        // Critical illness
        $ciPolicies = $user->criticalIllnessPolicies;
        foreach ($ciPolicies as $policy) {
            $policies[] = [
                'type' => 'Critical Illness',
                'provider' => $policy->provider,
                'sum_assured' => $policy->sum_assured,
                'policy_number' => $policy->policy_number,
            ];
        }

        // Income protection
        $ipPolicies = $user->incomeProtectionPolicies;
        foreach ($ipPolicies as $policy) {
            $policies[] = [
                'type' => 'Income Protection',
                'provider' => $policy->provider,
                'sum_assured' => $policy->monthly_benefit * 12, // Approximate annual
                'policy_number' => $policy->policy_number,
            ];
        }

        if (empty($policies)) {
            return null;
        }

        $info = "Insurance Policies:\n\n";

        foreach ($policies as $policy) {
            $info .= "• {$policy['type']} - {$policy['provider']}\n";
            $info .= "  Policy Number: {$policy['policy_number']}\n";
            $info .= '  Sum Assured: £'.number_format((float) $policy['sum_assured'], 2)."\n";
            $info .= "  Contact: [Add claims phone number]\n\n";
        }

        $info .= "Home Insurance: [Please add details]\n";
        $info .= 'Auto Insurance: [Please add details]';

        return $info;
    }

    /**
     * Property, at this user's share and with the same reach as the cards.
     *
     * Two changes beyond the figures. The reach was primary-owner-only, so a
     * spouse recorded as `joint_owner_id` — Sarah on both of this household's
     * jointly-held homes — got an EMPTY property section in her own letter while
     * her cards listed two properties. And the `Use:` line read
     * `$property->property_use`, which is not a column on `properties`, so it
     * printed "Primary_residence" for every property including the buy-to-let.
     * Removed rather than corrected: `property_type` already carries that fact
     * one line above.
     *
     * @param  array<string, mixed>  $position
     */
    private function generateRealEstateInfo(array $position): ?string
    {
        $items = $position['properties']['items'];

        if ($items === []) {
            return null;
        }

        $info = "Property Ownership:\n\n";

        foreach ($items as $item) {
            $address = array_filter([$item['name'], $item['city'] ?? '', $item['postcode'] ?? '']);
            $info .= '• '.implode(', ', $address)."\n";
            $info .= '  Type: '.$this->humanise((string) ($item['subtext'] ?: 'residential'))."\n";
            $info .= '  Ownership: '.$this->humanise((string) $item['ownership_type'])."\n";
            $info .= $this->valueLine($item, 'Current Value');

            if (($item['mortgage_balance'] ?? 0.0) > 0) {
                $info .= '  Outstanding Mortgage (your share): £'
                    .number_format((float) $item['mortgage_balance'], 2)."\n";
            }

            $info .= "  Title Deeds Location: [Please add]\n\n";
        }

        return $info;
    }

    /**
     * What this user owes, at their share, from the same itemisation the cards
     * and the profile read.
     *
     * The balances here were the whole of every debt the user was primary
     * borrower on — £365,000 against a household share of £170,500, including
     * £72,000 owed by an off-platform co-owner of one property. The mortgage
     * reach also widened with the reader: a mortgage secured on a property the
     * user co-owns counts even where the borrower record names someone else.
     *
     * The empty case is still deliberately empty and not "No outstanding
     * liabilities recorded." — a section with nothing in it reads as nothing
     * recorded yet, a sentence asserting the absence reads as a checked fact,
     * and the reader of this letter cannot ask us which it was (W-0022).
     *
     * @param  array<string, mixed>  $position
     */
    private function generateLiabilitiesInfo(array $position): ?string
    {
        $items = $position['liabilities']['items'];

        if ($items === []) {
            return null;
        }

        $info = "Outstanding Liabilities:\n\n";

        foreach ($items as $item) {
            $info .= '• '.$item['subtext'].' - '.$item['name']."\n";
            $info .= '  '.($this->isSharedType((string) $item['ownership_type']) ? 'Your Share' : 'Outstanding')
                .': £'.number_format((float) $item['value'], 2)."\n";

            if (($item['monthly_payment'] ?? null) !== null && (float) $item['monthly_payment'] > 0) {
                $info .= '  Monthly Payment'.($this->isSharedType((string) $item['ownership_type']) ? ' (your share)' : '')
                    .': £'.number_format((float) $item['monthly_payment'], 2)."\n";
            }

            $info .= "  Account Number: [Please add]\n\n";
        }

        return $info;
    }

    /**
     * A record's value line: one figure when the user owns the whole of it, and
     * both figures when they do not — so the reader can see their share AND what
     * the account or property is worth, and can check the section total by hand.
     *
     * @param  array<string, mixed>  $item
     */
    private function valueLine(array $item, string $label): string
    {
        $value = (float) $item['value'];
        $full = (float) $item['full_value'];

        if (abs($full - $value) < 0.005) {
            return '  '.$label.': £'.number_format($value, 2)."\n";
        }

        return '  Your Share: £'.number_format($value, 2).' of £'.number_format($full, 2)."\n";
    }

    private function isSharedType(string $ownershipType): bool
    {
        return in_array($ownershipType, ['joint', 'tenants_in_common'], true);
    }

    private function humanise(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    /**
     * What to call an account type in a letter someone else will read.
     *
     * Generic humanising is wrong for these four: it produced "Gia", "Vct" and
     * "Isa" — two meaningless acronyms and one that is no longer the acronym.
     * The code this replaced used `strtoupper`, which at least gave "ISA" but
     * also "GIA" and "VCT". Rule 9 spells acronyms out in user-facing text and
     * makes ISA the single exception, which is exactly the shape of this list.
     *
     * Everything else humanises — "Current account", "Premium bonds" — as it
     * always did.
     */
    private function accountTypeLabel(string $type): string
    {
        return match ($type) {
            'isa', 'stocks_and_shares_isa' => 'ISA',
            'cash_isa' => 'Cash ISA',
            'junior_isa' => 'Junior ISA',
            'lisa', 'lifetime_isa' => 'Lifetime ISA',
            'gia' => 'General Investment Account',
            'vct' => 'Venture Capital Trust',
            default => $this->humanise($type),
        };
    }

    /**
     * Generate beneficiary information
     */
    private function generateBeneficiaryInfo(User $user): ?string
    {
        $familyMembers = $user->familyMembers()->where('is_dependent', true)->get();

        if ($familyMembers->isEmpty()) {
            return null;
        }

        $info = "Beneficiaries:\n\n";

        foreach ($familyMembers as $member) {
            $info .= "• {$member->name}\n";
            $info .= '  Relationship: '.ucfirst($member->relationship ?? 'dependent')."\n";
            if ($member->date_of_birth) {
                $age = Carbon::parse($member->date_of_birth)->age;
                $info .= "  Age: {$age}\n";
            }
            $info .= "\n";
        }

        $info .= 'Review life insurance beneficiary designations and pension death benefits.';

        return $info;
    }

    /**
     * Generate education plans information
     */
    private function generateEducationPlansInfo(User $user): ?string
    {
        $children = $user->familyMembers()->where('relationship', 'child')->get();

        if ($children->isEmpty()) {
            return null;
        }

        $info = "Children's Education Plans:\n\n";

        foreach ($children as $child) {
            $info .= "• {$child->name}\n";
            if ($child->date_of_birth) {
                $age = Carbon::parse($child->date_of_birth)->age;
                $info .= "  Current Age: {$age}\n";
            }
            $info .= "  Education Plans: [Please add details about university plans, savings accounts, etc.]\n\n";
        }

        return $info;
    }

    /**
     * Generate financial guidance information
     */
    private function generateFinancialGuidanceInfo(User $user): string
    {
        $info = "Financial Guidance:\n\n";

        if ($user->annual_employment_income > 0 || $user->annual_self_employment_income > 0) {
            $totalIncome = ($user->annual_employment_income ?? 0) + ($user->annual_self_employment_income ?? 0);
            $info .= 'Current Household Income: £'.number_format((float) $totalIncome, 2)." per year\n\n";
        }

        $info .= "Please contact our financial advisor for guidance on:\n";
        $info .= "• State Pension entitlement and timing\n";
        $info .= "• Survivor benefits from my workplace pension\n";
        $info .= "• Tax-efficient withdrawal strategies\n";
        $info .= "• Investment portfolio rebalancing\n";
        $info .= "• Inheritance tax planning\n\n";

        $info .= 'Consider waiting at least 6 months before making major financial decisions.';

        return $info;
    }

    /**
     * Update letter with user data
     */
    public function updateLetter(User $user, array $data): LetterToSpouse
    {
        $letter = $this->getOrCreateLetter($user);

        // Editing a section hands it to the user for good — Fynla stops
        // regenerating it, so nothing they wrote is ever overwritten (W-0022).
        $owned = array_values(array_diff(
            is_array($letter->auto_populated_fields) ? $letter->auto_populated_fields : [],
            array_keys(array_filter(
                $data,
                fn ($value, string $field) => in_array($field, self::AUTO_POPULATED_FIELDS, true)
                    && $value !== $letter->$field,
                ARRAY_FILTER_USE_BOTH,
            )),
        ));

        $letter->update($data + ['auto_populated_fields' => $owned]);

        return $letter->fresh();
    }
}
