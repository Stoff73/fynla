<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\WhatIfScenario;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The one place that decides what happens when Fyn captures a record the user
 * already has.
 *
 * SPEC: August/August17Updates/SPEC-crud-handler-contract.md §5, contract rules
 * C2–C4. Extracted from CoordinatingAgent::mergePensionRecapture, which was the
 * only correct handler of twenty-four. The rest each failed differently: sixteen
 * had no existence check and duplicated silently, savings and investments warned
 * and discarded the user's answer, protection skipped the call outright, and the
 * will handler `updateOrCreate`d — a silent overwrite (all measured 2026-08-17).
 *
 * Rule 20: every create handler calls THIS. There is no second implementation
 * and no per-handler copy — copying is what produced the mess this replaces.
 *
 * CSJ 2026-08-17: "an edit, amendment or change must be explicit. If there is
 * any ambiguity Fyn must ask 'are we editing {plan}' before making any changes."
 * So a match splits three ways:
 *
 *   fill      the record has no value for that field yet — unambiguous, apply it
 *   conflict  the record holds a DIFFERENT value — write nothing, ask
 *   identical everything already matches — write nothing, ask (do NOT assume a
 *             duplicate)
 */
final class RecaptureGuard
{
    /**
     * Generic product nouns carry no identity, so "Aviva Pension" and "Aviva
     * Personal Pension" are the same record (CSJ §7.1). Over-eager matching is
     * safe by construction: both ambiguous branches write nothing and ask, so
     * the worst case is one extra question, never an overwrite.
     */
    private const NAME_NOISE = [
        'a', 'an', 'the', 'my', 'our',
        'pension', 'plan', 'scheme', 'account', 'policy', 'fund',
        'personal', 'workplace', 'private',
    ];

    /**
     * Canonical pension column => the `create_pension` input that must be present
     * and non-null for a re-capture to apply it. The pension normaliser renames
     * on the way in, so without this map C4 could not tell a user-supplied value
     * from a derived default.
     */
    private const PENSION_FIELDS = [
        'pension_type' => 'scheme_type',
        'scheme_type' => 'scheme_type',
        'provider' => 'provider',
        'current_fund_value' => 'current_fund_value',
        'annual_salary' => 'annual_salary',
        'employee_contribution_percent' => 'employee_contribution_percent',
        'employer_contribution_percent' => 'employer_contribution_percent',
        'monthly_contribution_amount' => 'monthly_contribution_amount',
        'retirement_age' => 'retirement_age',
        'accrued_annual_pension' => 'accrued_annual_pension',
        'pensionable_service_years' => 'pensionable_service_years',
        'pensionable_salary' => 'final_salary',
        'normal_retirement_age' => 'normal_retirement_age',
    ];

    /**
     * Per entity type — identity is normalised name + provider + owner (§7.1).
     *
     *   model     the Eloquent model the handler creates
     *   name      the column holding its name; a list of columns for a name split
     *             across several; null when the user has at most one (a will),
     *             where existence alone is identity
     *   noun      what Fyn calls it out loud. Rule 9: spelled out, no acronyms
     *   provider  optional column distinguishing two same-named records
     *   match     columns that must be exactly equal for it to be the same record
     *   owner     the ownership column, or null when `match` scopes it instead
     *   fields    model column => tool-input key, for handlers whose canonical
     *             payload is renamed on the way in. Omitted means keys match
     *
     * @var array<string, array{model: class-string<Model>, name: string|list<string>|null, noun: string, provider?: string, match?: list<string>, owner?: string|null, fields?: array<string, string>}>
     */
    private const ENTITIES = [
        'dc_pension' => [
            'model' => DCPension::class,
            'name' => 'scheme_name',
            'noun' => 'pension',
            'provider' => 'provider',
            'fields' => self::PENSION_FIELDS,
        ],
        'db_pension' => [
            'model' => DBPension::class,
            'name' => 'scheme_name',
            'noun' => 'pension',
            'provider' => 'provider',
            'fields' => self::PENSION_FIELDS,
        ],
        'savings_account' => [
            'model' => SavingsAccount::class,
            'name' => 'account_name',
            'noun' => 'savings account',
            'provider' => 'institution',
        ],
        'investment_account' => [
            'model' => InvestmentAccount::class,
            'name' => 'account_name',
            'noun' => 'investment account',
            'provider' => 'provider',
        ],
        // A holding belongs to its account, not directly to the user, so the
        // account is what scopes it.
        'investment_holding' => [
            'model' => Holding::class,
            'name' => 'security_name',
            'noun' => 'holding',
            'match' => ['holdable_id', 'holdable_type'],
            'owner' => null,
        ],
        'property' => [
            'model' => Property::class,
            'name' => 'address_line_1',
            'noun' => 'property',
        ],
        'mortgage' => [
            'model' => Mortgage::class,
            'name' => 'lender_name',
            'noun' => 'mortgage',
            'match' => ['property_id'],
        ],
        'life_insurance_policy' => [
            'model' => LifeInsurancePolicy::class,
            'name' => 'provider',
            'noun' => 'life insurance policy',
            'match' => ['policy_type'],
        ],
        'critical_illness_policy' => [
            'model' => CriticalIllnessPolicy::class,
            'name' => 'provider',
            'noun' => 'critical illness policy',
            'match' => ['policy_type'],
        ],
        'income_protection_policy' => [
            'model' => IncomeProtectionPolicy::class,
            'name' => 'provider',
            'noun' => 'income protection policy',
        ],
        'goal' => [
            'model' => Goal::class,
            'name' => 'goal_name',
            'noun' => 'goal',
        ],
        'life_event' => [
            'model' => LifeEvent::class,
            'name' => 'event_name',
            'noun' => 'life event',
        ],
        'estate_asset' => [
            'model' => Asset::class,
            'name' => 'asset_name',
            'noun' => 'asset',
        ],
        'estate_liability' => [
            'model' => Liability::class,
            'name' => 'liability_name',
            'noun' => 'liability',
        ],
        // Two gifts to the same person on different dates are two gifts.
        'estate_gift' => [
            'model' => Gift::class,
            'name' => 'recipient',
            'noun' => 'gift',
            'match' => ['gift_date'],
        ],
        'will' => [
            'model' => Will::class,
            'name' => null,
            'noun' => 'will',
        ],
        'lasting_power_of_attorney' => [
            'model' => LastingPowerOfAttorney::class,
            'name' => null,
            'noun' => 'power of attorney',
            'match' => ['lpa_type'],
        ],
        'family_member' => [
            'model' => FamilyMember::class,
            'name' => ['first_name', 'last_name'],
            'noun' => 'family member',
        ],
        'trust' => [
            'model' => Trust::class,
            'name' => 'trust_name',
            'noun' => 'trust',
        ],
        'business_interest' => [
            'model' => BusinessInterest::class,
            'name' => 'business_name',
            'noun' => 'business interest',
        ],
        'chattel' => [
            'model' => Chattel::class,
            'name' => 'name',
            'noun' => 'item',
        ],
        'what_if_scenario' => [
            'model' => WhatIfScenario::class,
            'name' => 'name',
            'noun' => 'scenario',
        ],
    ];

    /**
     * @param  array<string, mixed>  $payload  canonical column => value, as the handler would write it
     * @param  array<string, mixed>  $input  the raw tool input — a field only counts if the USER supplied it (C4)
     * @param  callable(string, ?int): bool  $isExplicitEdit  is the user answering Fyn's own question about THIS record? (C3)
     * @return array<string, mixed>|null null when there is no match and the handler should carry on and create
     */
    public function inspect(
        string $entityType,
        array $payload,
        array $input,
        User $user,
        callable $isExplicitEdit,
    ): ?array {
        $entity = self::ENTITIES[$entityType] ?? null;
        if ($entity === null) {
            return null;
        }

        // Without a name there is nothing to identify the record by, so let the
        // handler create and validate as it always did. Singletons (name => null)
        // are identified by existence alone and skip this.
        if ($entity['name'] !== null && self::nameFrom($payload, $entity['name']) === '') {
            return null;
        }

        $existing = $this->findExisting($entity, $payload, $input, $user);
        if ($existing === null) {
            return null;
        }

        return $this->decide($existing, $entityType, $entity, $payload, $input, $isExplicitEdit);
    }

    /**
     * Normalise a record name to its identifying core: lower-cased, stripped of
     * punctuation, with generic product nouns dropped. Falls back to the plain
     * alphanumeric form when a name is nothing BUT noise ("My Pension"), so such
     * a name matches only other all-noise names rather than everything.
     */
    public static function normaliseName(string $name): string
    {
        $plain = trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($name)) ?? '');
        $tokens = array_values(array_diff(preg_split('/\s+/', $plain) ?: [], self::NAME_NOISE));

        return $tokens === [] ? $plain : implode(' ', $tokens);
    }

    /**
     * Is the stored value genuinely different from the one coming in?
     *
     * A false conflict is expensive — it asks the user a question about a value
     * that already matches. The two that bite are a date column, cast to Carbon
     * on the model but arriving as "2018-05-12", and a decimal cast, stored as
     * "45000.00" against an incoming 45000.
     */
    private static function differs(mixed $current, mixed $incoming): bool
    {
        if ($current instanceof DateTimeInterface || $incoming instanceof DateTimeInterface) {
            return self::asDate($current) !== self::asDate($incoming);
        }

        if (is_numeric($current) && is_numeric($incoming)) {
            return (float) $current !== (float) $incoming;
        }

        return $current != $incoming;
    }

    private static function asDate(mixed $value): string
    {
        return $value instanceof DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string) $value, 0, 10);
    }

    /**
     * @param  array<string, mixed>|Model  $source
     * @param  string|list<string>  $columns
     */
    private static function nameFrom(array|Model $source, string|array $columns): string
    {
        $parts = [];
        foreach ((array) $columns as $column) {
            $value = is_array($source) ? ($source[$column] ?? null) : $source->{$column};
            if (is_scalar($value) && trim((string) $value) !== '') {
                $parts[] = trim((string) $value);
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array{model: class-string<Model>, name: string|list<string>|null, noun: string, provider?: string, match?: list<string>, owner?: string|null, fields?: array<string, string>}  $entity
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $input
     */
    private function findExisting(array $entity, array $payload, array $input, User $user): ?Model
    {
        $query = $entity['model']::query();

        $owner = array_key_exists('owner', $entity) ? $entity['owner'] : 'user_id';
        if ($owner !== null) {
            $query->where($owner, $user->id);
        }

        foreach ($entity['match'] ?? [] as $column) {
            $query->where($column, $payload[$column] ?? null);
        }

        if ($entity['name'] === null) {
            return $query->first();
        }

        $target = self::normaliseName(self::nameFrom($payload, $entity['name']));

        // The provider only distinguishes two records when the USER named it.
        // Normalisers fall back to the record's own name (savings `institution`,
        // pension `provider`), and a derived value compared against a real one
        // says nothing — it would rule out a match that plainly is one.
        $providerKey = isset($entity['provider'])
            ? ($entity['fields'][$entity['provider']] ?? $entity['provider'])
            : null;
        $incomingProvider = $providerKey === null
            ? ''
            : self::normaliseName((string) ($input[$providerKey] ?? ''));

        foreach ($query->get() as $candidate) {
            if (self::normaliseName(self::nameFrom($candidate, $entity['name'])) !== $target) {
                continue;
            }

            // Two records may legitimately share a name at different providers.
            // Only a provider present on BOTH sides can rule a match out — an
            // absent one is missing information, not a distinction.
            if (isset($entity['provider'])) {
                $theirs = self::normaliseName((string) ($candidate->{$entity['provider']} ?? ''));
                if ($incomingProvider !== '' && $theirs !== '' && $incomingProvider !== $theirs) {
                    continue;
                }
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @param  array{model: class-string<Model>, name: string|list<string>|null, noun: string, provider?: string, match?: list<string>, owner?: string|null, fields?: array<string, string>}  $entity
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function decide(
        Model $existing,
        string $entityType,
        array $entity,
        array $payload,
        array $input,
        callable $isExplicitEdit,
    ): array {
        $identityColumns = array_merge((array) ($entity['name'] ?? []), $entity['match'] ?? []);
        $fills = [];
        $conflicts = [];

        foreach ($payload as $column => $incoming) {
            if (in_array($column, $identityColumns, true)) {
                continue;
            }

            // C4 — only a value the USER supplied may be merged. A normaliser
            // default (PensionNormaliser falls back provider = scheme_name)
            // would otherwise overwrite a real value on a later turn.
            $suppliedKey = $entity['fields'][$column] ?? $column;
            $supplied = $input[$suppliedKey] ?? null;
            if ($supplied === null || $supplied === '') {
                continue;
            }

            $current = $existing->{$column};

            if ($current === null || $current === '' || (is_numeric($current) && (float) $current === 0.0)) {
                $fills[$column] = $incoming;
            } elseif (self::differs($current, $incoming)) {
                $conflicts[$column] = ['current' => $current, 'proposed' => $incoming];
            }
        }

        // C3 — answering Fyn's own outstanding question IS explicit, so a
        // conflicting value applies rather than raising a second question.
        if ($conflicts !== [] && $isExplicitEdit($entityType, (int) $existing->id)) {
            foreach ($conflicts as $column => $pair) {
                $fills[$column] = $pair['proposed'];
            }
            $conflicts = [];
        }

        $noun = $entity['noun'];
        $name = $entity['name'] === null ? '' : self::nameFrom($existing, $entity['name']);
        // A singleton (a will) has no name to quote, so both forms drop it.
        $label = $name === '' ? "your {$noun}" : "your \"{$name}\" {$noun}";
        $indefinite = $name === '' ? "a {$noun}" : "a {$noun} recorded as \"{$name}\"";

        if ($conflicts !== []) {
            return [
                'error' => true,
                'error_type' => 'confirm_edit_required',
                'entity_type' => $entityType,
                'entity_id' => $existing->id,
                'name' => $name,
                'conflicts' => $conflicts,
                // C6 — the failure path prints `message` verbatim in chat, so it
                // must read as Fyn speaking. Model instructions go in
                // `model_directive` (a leak was shipped and caught live).
                'message' => "You already have {$indefinite}, with different details. "
                    ."Is this the same {$noun} you'd like me to update, or a separate one?",
                'model_directive' => 'Do not write anything until the user confirms whether this is an edit to '
                    ."{$label} or a separate {$noun}. If separate, record it under a name that distinguishes it.",
            ];
        }

        if ($fills !== []) {
            $existing->fill($fills)->save();

            return [
                'success' => true,
                'updated' => true,
                'entity_type' => $entityType,
                'entity_id' => $existing->id,
                'name' => $name,
                'updated_fields' => array_keys($fills),
                'message' => "I've completed {$label}.",
            ];
        }

        return [
            'error' => true,
            'error_type' => 'confirm_duplicate_required',
            'entity_type' => $entityType,
            'entity_id' => $existing->id,
            'name' => $name,
            'message' => "You already have {$indefinite}, with exactly these details. "
                ."Is this a separate {$noun} you also hold, or the same one?",
            'model_directive' => 'Do not add a second record on the assumption it is a duplicate. If the user '
                ."confirms it is a separate {$noun}, record it under a name that distinguishes it from the "
                .'existing one.',
        ];
    }
}
