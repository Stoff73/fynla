<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\CriticalIllnessPolicy;
use App\Models\Estate\Liability;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Onboarding\AssetCaptureEntityExtractor;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;

/**
 * Builds a deterministic, factual acknowledgement when a user reasserts
 * records that are already on file.
 *
 * Used by AdviceFyn::handle on the routing-gate suppress path
 * (RecordDuplicateChecker::alreadyExists === true). We do NOT involve the
 * LLM in this turn — its phrasing is non-deterministic and on previous
 * walks it has produced responses that read as "I've added these"
 * (gaslighting) instead of "these are already on record" (honest).
 *
 * The acknowledgement is built by re-running the deterministic
 * AssetCaptureEntityExtractor over the user's message to identify the
 * intended entities, then looking up the matching DB rows for this user
 * to compose a list of human-readable descriptors. Output is plain text
 * with markdown bold for amounts, ready to render in the chat panel.
 *
 * Coverage matches WriteIntentClassifier::ENTITY_KEYWORDS — every
 * entity_type that classifier emits has a descriptor branch here.
 */
final class DuplicateAcknowledgement
{
    public function __construct(
        private readonly AssetCaptureEntityExtractor $extractor,
    ) {}

    /**
     * Build the acknowledgement text for a full-duplicate reassertion.
     *
     * @param  array{entity_type: string, matched_verb: string, matched_entity_keyword: string, fields_needed: list<string>, reason: string}  $intent
     * @return array{text: string, descriptors: list<string>}
     */
    public function build(User $user, array $intent, string $message): array
    {
        $focus = $this->focusForEntityType($intent['entity_type']);
        $descriptors = $focus !== null
            ? $this->descriptorsForFocus($focus, $user, $message)
            : [];

        $text = $this->composeText($descriptors);

        return ['text' => $text, 'descriptors' => $descriptors];
    }

    private function focusForEntityType(string $entityType): ?string
    {
        return match ($entityType) {
            'protection_policy' => 'protection',
            'savings_account' => 'savings',
            'investment_account' => 'investment',
            'pension' => 'retirement',
            'property' => 'property',
            'goal' => 'goal',
            'mortgage' => 'mortgage',
            'liability' => 'liability',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function descriptorsForFocus(string $focus, User $user, string $message): array
    {
        $extracted = $this->extractor->extractForFocus($focus, $message);
        if ($extracted === []) {
            return [];
        }

        return match ($focus) {
            'protection' => $this->protectionDescriptors($user, $extracted),
            'savings', 'budgeting' => $this->savingsDescriptors($user, $extracted),
            'investment' => $this->investmentDescriptors($user, $extracted),
            'retirement' => $this->retirementDescriptors($user, $extracted),
            'property' => $this->propertyDescriptors($user, $extracted),
            'goal' => $this->goalDescriptors($user, $extracted),
            'mortgage' => $this->mortgageDescriptors($user, $extracted),
            'liability' => $this->liabilityDescriptors($user, $extracted),
            default => [],
        };
    }

    /**
     * @param  list<string>  $descriptors
     */
    private function composeText(array $descriptors): string
    {
        if ($descriptors === []) {
            // Fall-back when the extractor yielded entities but the DB
            // lookup didn't surface a matching row (extremely rare —
            // alreadyExists already confirmed the rows are there). Stay
            // honest rather than fabricate a list.
            return "These records are already on file. Anything else you'd like to add or check?";
        }

        if (count($descriptors) === 1) {
            return "{$descriptors[0]} is already on record. Anything else you'd like to add?";
        }

        $bullets = array_map(fn (string $d): string => '- '.$d, $descriptors);

        return "These are already on record:\n\n".implode("\n", $bullets)."\n\nAnything else you'd like to add?";
    }

    // ─── Protection ─────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $extracted
     * @return list<string>
     */
    private function protectionDescriptors(User $user, array $extracted): array
    {
        $cutoff = now()->subHours(24);
        $descriptors = [];
        $seen = [];

        foreach ($extracted as $entity) {
            $type = (string) ($entity['policy_type'] ?? '');
            $provider = (string) ($entity['provider'] ?? '');

            $row = match (true) {
                in_array($type, ['standalone_ci', 'accelerated_ci'], true) => CriticalIllnessPolicy::query()
                    ->where('user_id', $user->id)
                    ->where('provider', $provider)
                    ->where('created_at', '>', $cutoff)
                    ->latest('id')
                    ->first(),
                $type === 'income_protection' => IncomeProtectionPolicy::query()
                    ->where('user_id', $user->id)
                    ->where('provider', $provider)
                    ->where('created_at', '>', $cutoff)
                    ->latest('id')
                    ->first(),
                default => LifeInsurancePolicy::query()
                    ->where('user_id', $user->id)
                    ->where('provider', $provider)
                    ->where('created_at', '>', $cutoff)
                    ->latest('id')
                    ->first(),
            };

            if ($row === null) {
                continue;
            }

            $key = get_class($row).'|'.$row->id;
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $descriptors[] = $this->describeProtectionRow($row);
        }

        return $descriptors;
    }

    private function describeProtectionRow(object $row): string
    {
        if ($row instanceof LifeInsurancePolicy) {
            $typeLabel = match ($row->policy_type) {
                'level_term' => 'level term life insurance',
                'decreasing_term' => 'decreasing term life insurance',
                'whole_of_life' => 'whole of life cover',
                'family_income_benefit' => 'family income benefit',
                default => 'life insurance',
            };

            return sprintf('%s %s for **£%s**', $row->provider, $typeLabel, number_format((float) $row->sum_assured));
        }

        if ($row instanceof CriticalIllnessPolicy) {
            $typeLabel = $row->policy_type === 'accelerated' ? 'accelerated critical illness' : 'standalone critical illness';

            return sprintf('%s %s cover for **£%s**', $row->provider, $typeLabel, number_format((float) $row->sum_assured));
        }

        if ($row instanceof IncomeProtectionPolicy) {
            return sprintf('%s income protection (**£%s** annual benefit)', $row->provider, number_format((float) $row->benefit_amount));
        }

        return 'protection policy';
    }

    // ─── Savings ────────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $extracted
     * @return list<string>
     */
    private function savingsDescriptors(User $user, array $extracted): array
    {
        $cutoff = now()->subHours(24);
        $descriptors = [];
        $seen = [];

        foreach ($extracted as $entity) {
            $institution = (string) ($entity['institution'] ?? '');
            $isIsa = (bool) ($entity['is_isa'] ?? false);

            $row = app(SavingsStore::class)->forUser($user)
                ->where('user_id', $user->id)
                ->where('institution', $institution)
                ->where('is_isa', $isIsa)
                ->filter(fn ($a) => $a->created_at > $cutoff)
                ->sortByDesc('id')
                ->first();
            if ($row === null) {
                continue;
            }

            $key = $row->id;
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $balance = number_format((float) $row->current_balance);
            $label = $row->is_isa ? 'cash ISA' : ($row->account_type ? str_replace('_', ' ', (string) $row->account_type).' account' : 'savings account');
            $descriptors[] = sprintf('%s %s with a balance of **£%s**', $row->institution, $label, $balance);
        }

        return $descriptors;
    }

    // ─── Investment ────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $extracted
     * @return list<string>
     */
    private function investmentDescriptors(User $user, array $extracted): array
    {
        $cutoff = now()->subHours(24);
        $descriptors = [];
        $seen = [];

        foreach ($extracted as $entity) {
            $provider = (string) ($entity['provider'] ?? '');
            $accountType = (string) ($entity['account_type'] ?? '');

            $row = InvestmentAccount::query()
                ->where('user_id', $user->id)
                ->where('provider', $provider)
                ->where('account_type', $accountType)
                ->where('created_at', '>', $cutoff)
                ->latest('id')
                ->first();

            if ($row === null) {
                continue;
            }

            $key = $row->id;
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $value = number_format((float) ($row->current_value ?? 0));
            $label = match ($row->account_type) {
                'stocks_shares_isa' => 'Stocks & Shares ISA',
                'lifetime_isa' => 'Lifetime ISA',
                'personal_investment_account' => 'General Investment Account',
                'onshore_bond' => 'onshore bond',
                'offshore_bond' => 'offshore bond',
                'vct' => 'Venture Capital Trust',
                'eis' => 'Enterprise Investment Scheme',
                default => 'investment account',
            };

            $descriptors[] = sprintf('%s %s worth **£%s**', $row->provider, $label, $value);
        }

        return $descriptors;
    }

    // ─── Retirement ────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $extracted
     * @return list<string>
     */
    private function retirementDescriptors(User $user, array $extracted): array
    {
        $cutoff = now()->subHours(24);
        $descriptors = [];
        $seen = [];

        foreach ($extracted as $entity) {
            $category = (string) ($entity['pension_category'] ?? 'dc');
            $provider = (string) ($entity['provider'] ?? '');

            if ($category === 'db') {
                $row = app(PensionStore::class)->forUserByType($user, 'db')
                    ->filter(fn ($p) => $p->created_at > $cutoff)
                    ->sortByDesc('id')
                    ->first();
                if ($row === null) {
                    continue;
                }
                $key = 'db|'.$row->id;
                if (in_array($key, $seen, true)) {
                    continue;
                }
                $seen[] = $key;
                $descriptors[] = sprintf('your %s defined benefit pension', $row->scheme_name);

                continue;
            }

            $row = app(PensionStore::class)->forUserByType($user, 'dc')
                ->where('provider', $provider)
                ->filter(fn ($p) => $p->created_at > $cutoff)
                ->sortByDesc('id')
                ->first();
            if ($row === null) {
                continue;
            }
            $key = 'dc|'.$row->id;
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $value = number_format((float) ($row->current_fund_value ?? 0));
            $descriptors[] = sprintf('%s %s worth **£%s**', $row->provider, $row->scheme_name, $value);
        }

        return $descriptors;
    }

    // ─── Property ───────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $extracted
     * @return list<string>
     */
    private function propertyDescriptors(User $user, array $extracted): array
    {
        $cutoff = now()->subHours(24);
        $descriptors = [];
        $seen = [];

        foreach ($extracted as $entity) {
            $type = (string) ($entity['property_type'] ?? '');
            $postcode = (string) ($entity['postcode'] ?? '');

            $query = Property::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id);
                })
                ->where('property_type', $type)
                ->where('created_at', '>', $cutoff);

            if ($postcode !== '') {
                // Postcode lookups must ignore spaces — extractor strips them
                // ("M1 1AA" → "M11AA") but DB rows often store them with the
                // space ("M1 1AA"). Compare on the normalised form.
                $needle = strtoupper((string) preg_replace('/\s+/u', '', $postcode));
                $query->whereRaw("UPPER(REPLACE(postcode, ' ', '')) = ?", [$needle]);
            }

            $row = $query->latest('id')->first();
            if ($row === null) {
                continue;
            }
            $key = $row->id;
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $label = match ($row->property_type) {
                'main_residence' => 'main residence',
                'buy_to_let' => 'buy-to-let property',
                'secondary_residence' => 'secondary residence',
                default => 'property',
            };
            $value = number_format((float) ($row->current_value ?? 0));
            $location = $row->postcode ?? $row->city ?? null;
            $location = $location !== null && $location !== '' ? sprintf(' at %s', $location) : '';
            $descriptors[] = sprintf('your %s%s worth **£%s**', $label, $location, $value);
        }

        return $descriptors;
    }

    // ─── Goal ───────────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $extracted
     * @return list<string>
     */
    private function goalDescriptors(User $user, array $extracted): array
    {
        $cutoff = now()->subHours(24);
        $descriptors = [];
        $seen = [];

        foreach ($extracted as $entity) {
            $name = (string) ($entity['goal_name'] ?? '');
            $rows = Goal::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id);
                })
                ->where('created_at', '>', $cutoff)
                ->get(['id', 'goal_name', 'target_amount', 'target_date']);

            $needle = preg_replace('/[^a-z0-9]/u', '', mb_strtolower($name)) ?? '';
            $match = null;
            foreach ($rows as $row) {
                $rowKey = preg_replace('/[^a-z0-9]/u', '', mb_strtolower((string) $row->goal_name)) ?? '';
                if ($rowKey === $needle) {
                    $match = $row;
                    break;
                }
            }

            if ($match === null) {
                continue;
            }
            $key = $match->id;
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $target = $match->target_amount > 0 ? sprintf(' (target **£%s**)', number_format((float) $match->target_amount)) : '';
            $descriptors[] = sprintf('your "%s" goal%s', $match->goal_name, $target);
        }

        return $descriptors;
    }

    // ─── Mortgage ───────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $extracted
     * @return list<string>
     */
    private function mortgageDescriptors(User $user, array $extracted): array
    {
        $cutoff = now()->subHours(24);
        $descriptors = [];
        $seen = [];

        foreach ($extracted as $entity) {
            $lender = (string) ($entity['lender_name'] ?? $entity['provider'] ?? '');
            $row = Mortgage::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id);
                })
                ->where('lender_name', $lender)
                ->where('created_at', '>', $cutoff)
                ->latest('id')
                ->first();
            if ($row === null) {
                continue;
            }
            $key = $row->id;
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $balance = number_format((float) ($row->outstanding_balance ?? 0));
            $descriptors[] = sprintf('your %s mortgage (outstanding balance **£%s**)', $row->lender_name, $balance);
        }

        return $descriptors;
    }

    // ─── Liability ──────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $extracted
     * @return list<string>
     */
    private function liabilityDescriptors(User $user, array $extracted): array
    {
        $cutoff = now()->subHours(24);
        $descriptors = [];
        $seen = [];

        foreach ($extracted as $entity) {
            $type = (string) ($entity['liability_type'] ?? '');
            $name = (string) ($entity['liability_name'] ?? '');

            $query = Liability::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id);
                })
                ->where('liability_type', $type)
                ->where('created_at', '>', $cutoff);
            if ($name !== '') {
                $query->where('liability_name', $name);
            }
            $row = $query->latest('id')->first();
            if ($row === null) {
                continue;
            }
            $key = $row->id;
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $balance = number_format((float) ($row->current_balance ?? 0));
            $label = $row->liability_name !== null && $row->liability_name !== ''
                ? $row->liability_name
                : str_replace('_', ' ', (string) $row->liability_type);
            $descriptors[] = sprintf('your %s (outstanding balance **£%s**)', $label, $balance);
        }

        return $descriptors;
    }
}
