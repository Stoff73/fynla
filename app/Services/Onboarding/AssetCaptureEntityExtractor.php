<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\User;

/**
 * Deterministic entity extractor for onboarding asset_capture turns.
 *
 * The xAI/Anthropic models still drop a tool call on multi-entity messages
 * ("I have Aviva life insurance £300k and Vitality critical illness £100k"
 * emits only one create_protection_policy despite prompt instructions to
 * emit both). This extractor runs *after* the LLM stream finishes, parses
 * the original user message into a list of entity-shaped inputs, and the
 * director emits synthetic tool_use / fill_form events for any entities
 * the LLM missed.
 *
 * The fix is deliberately server-side and deterministic — no second LLM
 * round-trip, no prompt gymnastics. If the regex fails (ambiguous phrasing,
 * unusual provider names), we emit nothing and the LLM's single tool call
 * is the fallback. Under-filling is the acceptable degradation; duplicating
 * an already-persisted record is not.
 */
final class AssetCaptureEntityExtractor
{
    /**
     * Providers recognised by the protection/retirement/savings/investment
     * parsers. Order matters — longer, more-specific names first so
     * "Legal & General" wins over a generic "Legal".
     *
     * @var list<string>
     */
    private const KNOWN_PROVIDERS = [
        'Legal & General', 'Legal and General', 'L&G',
        'Scottish Widows', 'Royal London', 'Canada Life', 'Liverpool Victoria',
        'Hargreaves Lansdown', 'Interactive Investor', 'AJ Bell', 'Fidelity',
        'British Seniors', 'Beagle Street',
        'Aviva', 'Vitality', 'Prudential', 'Zurich', 'AIG', 'Aegon',
        'HSBC', 'Lloyds', 'Barclays', 'Halifax', 'NatWest', 'Santander',
        'Nationwide', 'Monzo', 'Starling', 'Revolut', 'Chase',
        'Vanguard', 'BlackRock', 'Octopus', 'Nutmeg', 'Wealthify', 'Moneyfarm',
        'SunLife', 'Unum', 'Cavendish', 'Drewberry', 'LifeSearch',
        'LV=', 'LV',
        'NEST', 'Now Pensions', 'The People\'s Pension', 'Smart Pension',
        'NHS', 'Teachers\' Pension', 'Civil Service Pension', 'LGPS',
    ];

    /**
     * Return the create_* tool name for a given onboarding focus.
     * Returns null for focuses we don't gap-fill (yet).
     */
    public function toolNameForFocus(string $focus): ?string
    {
        return match ($focus) {
            'protection' => 'create_protection_policy',
            'savings', 'budgeting' => 'create_savings_account',
            'retirement' => 'create_pension',
            'investment' => 'create_investment_account',
            default => null,
        };
    }

    /**
     * Extract entities from a user message for a given focus. Each returned
     * array is a ready-to-submit input to the matching create_* tool
     * handler (i.e. it passes the same validator as the LLM-emitted input).
     *
     * @return list<array<string, mixed>>
     */
    public function extractForFocus(string $focus, string $message): array
    {
        return match ($focus) {
            'protection' => $this->extractProtectionPolicies($message),
            'savings', 'budgeting' => $this->extractSavingsAccounts($message),
            'retirement' => $this->extractPensions($message),
            'investment' => $this->extractInvestmentAccounts($message),
            default => [],
        };
    }

    /**
     * Decide which extracted entities were NOT already persisted by the
     * LLM's own tool calls. We compare on a focus-specific identity key
     * (e.g. for protection: policy-type-group + provider) so an LLM call
     * with slightly different casing or punctuation still suppresses a
     * duplicate gap-fill.
     *
     * When $user is provided, also dedup against rows persisted in the
     * target module within the last 24h (S0.11.5 / INV-2.9.5). Without
     * this, a user retrying the same multi-entity message would re-emit
     * gap-fill tool calls that re-persist duplicate rows — the
     * in-message dedup only protects within a single turn.
     *
     * @param  list<array<string, mixed>>  $extracted   output of extractForFocus()
     * @param  list<array<string, mixed>>  $llmEmitted  fields[] from each fill_form event the LLM emitted
     * @return list<array<string, mixed>>
     */
    public function findMissing(string $focus, array $extracted, array $llmEmitted, ?User $user = null): array
    {
        if ($extracted === []) {
            return [];
        }

        $emittedKeys = array_map(
            fn (array $fields): string => $this->identityKey($focus, $fields, true),
            $llmEmitted
        );

        $persistedKeys = $user !== null
            ? $this->recentlyPersistedIdentityKeys($focus, $user)
            : [];

        $missing = [];
        $seen = array_merge($emittedKeys, $persistedKeys); // also dedupe within the extracted set
        foreach ($extracted as $entity) {
            $key = $this->identityKey($focus, $entity, false);
            if ($key === '' || in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;
            $missing[] = $entity;
        }

        return $missing;
    }

    /**
     * Build the set of identity keys for rows persisted in the last 24h
     * for this user in the focus's target module(s). Used by findMissing
     * to suppress gap-fill emissions that would re-create existing rows
     * (S0.11.5 / INV-2.9.5).
     *
     * The 24h window is the spec-fixed dedup horizon — it is wide enough
     * to catch retries from network glitches and same-day re-engagements
     * without permanently blocking a user from adding a genuinely new
     * second account at the same provider.
     *
     * @return list<string>
     */
    private function recentlyPersistedIdentityKeys(string $focus, User $user): array
    {
        $cutoff = now()->subHours(24);

        return match ($focus) {
            'protection' => $this->protectionPersistedKeys($user, $cutoff),
            'savings', 'budgeting' => $this->savingsPersistedKeys($user, $cutoff),
            'retirement' => $this->retirementPersistedKeys($user, $cutoff),
            'investment' => $this->investmentPersistedKeys($user, $cutoff),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function protectionPersistedKeys(User $user, \Illuminate\Support\Carbon $cutoff): array
    {
        $keys = [];

        foreach (LifeInsurancePolicy::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', $cutoff)
            ->get(['policy_type', 'provider']) as $row) {
            $keys[] = $this->protectionIdentityKey([
                'policy_type' => $row->policy_type,
                'provider' => $row->provider,
            ], false);
        }

        // CI table's policy_type column is 'standalone' / 'accelerated' (no
        // `_ci` suffix that the identity-key matcher expects). The table
        // identity is enough — every row in this table keys to group 'ci'
        // — so we synthesise 'standalone_ci' to land in the right bucket.
        foreach (CriticalIllnessPolicy::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', $cutoff)
            ->get(['provider']) as $row) {
            $keys[] = $this->protectionIdentityKey([
                'policy_type' => 'standalone_ci',
                'provider' => $row->provider,
            ], false);
        }

        foreach (IncomeProtectionPolicy::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', $cutoff)
            ->get(['provider']) as $row) {
            $keys[] = $this->protectionIdentityKey([
                'policy_type' => 'income_protection',
                'provider' => $row->provider,
            ], false);
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function savingsPersistedKeys(User $user, \Illuminate\Support\Carbon $cutoff): array
    {
        $keys = [];

        foreach (SavingsAccount::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', $cutoff)
            ->get(['institution', 'account_name', 'is_isa']) as $row) {
            $keys[] = $this->savingsIdentityKey([
                'institution' => $row->institution,
                'account_name' => $row->account_name,
                'is_isa' => (bool) $row->is_isa,
            ], false);
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function retirementPersistedKeys(User $user, \Illuminate\Support\Carbon $cutoff): array
    {
        $keys = [];

        foreach (DCPension::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', $cutoff)
            ->get(['provider', 'scheme_name']) as $row) {
            $keys[] = $this->pensionIdentityKey([
                'provider' => $row->provider,
                'scheme_name' => $row->scheme_name,
                'pension_category' => 'dc',
            ], false);
        }

        foreach (DBPension::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', $cutoff)
            ->get(['scheme_name']) as $row) {
            $keys[] = $this->pensionIdentityKey([
                'provider' => null,
                'scheme_name' => $row->scheme_name,
                'pension_category' => 'db',
            ], false);
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function investmentPersistedKeys(User $user, \Illuminate\Support\Carbon $cutoff): array
    {
        $keys = [];

        foreach (InvestmentAccount::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', $cutoff)
            ->get(['provider', 'account_name', 'account_type']) as $row) {
            $keys[] = $this->investmentIdentityKey([
                'provider' => $row->provider,
                'account_name' => $row->account_name,
                'account_type' => $row->account_type,
            ], false);
        }

        return $keys;
    }

    // ─── Protection ─────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function extractProtectionPolicies(string $message): array
    {
        $chunks = $this->splitOnConnectors($message);
        $policies = [];

        foreach ($chunks as $chunk) {
            $policy = $this->extractOneProtectionPolicy($chunk);
            if ($policy !== null) {
                $policies[] = $policy;
            }
        }

        return $policies;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractOneProtectionPolicy(string $chunk): ?array
    {
        $type = $this->detectProtectionPolicyType($chunk);
        if ($type === null) {
            return null;
        }

        $input = ['policy_type' => $type];

        $provider = $this->extractKnownProvider($chunk);
        if ($provider !== null) {
            $input['provider'] = $provider;
        }

        $amount = $this->extractAmount($chunk);
        if ($amount !== null) {
            if ($type === 'income_protection' || $type === 'family_income_benefit') {
                $input['benefit_amount'] = $amount;
            } else {
                $input['sum_assured'] = $amount;
            }
        }

        $premium = $this->extractPremium($chunk);
        if ($premium !== null) {
            $input['premium_amount'] = $premium;
            $input['premium_frequency'] = 'monthly';
        }

        return $input;
    }

    private function detectProtectionPolicyType(string $chunk): ?string
    {
        $lower = mb_strtolower($chunk);

        // Order matters — check most specific phrases first so
        // "critical illness cover" doesn't match the generic "cover".
        if (preg_match('/\b(critical[\s-]illness|\bci\b|critical[\s-]cover)/u', $lower) === 1) {
            return 'standalone_ci';
        }
        if (preg_match('/\b(income[\s-]protection|\bip\b(?!\s*address))/u', $lower) === 1) {
            return 'income_protection';
        }
        if (preg_match('/\bfamily[\s-]income[\s-]benefit\b/u', $lower) === 1) {
            return 'family_income_benefit';
        }
        if (preg_match('/\b(decreasing[\s-]term|mortgage[\s-]protection)\b/u', $lower) === 1) {
            return 'decreasing_term';
        }
        if (preg_match('/\bwhole[\s-]of[\s-]life\b|\bwol\b/u', $lower) === 1) {
            return 'whole_of_life';
        }
        if (preg_match('/\blevel[\s-]term\b/u', $lower) === 1) {
            return 'level_term';
        }
        if (preg_match('/\blife[\s-]insurance\b|\blife[\s-]cover\b|\blife[\s-]policy\b|\bterm[\s-]life\b|\blife[\s-]assurance\b/u', $lower) === 1) {
            return 'term';
        }

        // Fallback: bare "life" combined with a currency amount is still
        // a protection signal (e.g. "Aviva life £300k"). The amount check
        // guards against false positives on unrelated uses like "life events".
        if (preg_match('/\blife\b/u', $lower) === 1
            && preg_match('/£|\bk\b|\bm\b|thousand|million/u', $lower) === 1) {
            return 'term';
        }

        return null;
    }

    // ─── Savings ────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function extractSavingsAccounts(string $message): array
    {
        $chunks = $this->splitOnConnectors($message);
        $accounts = [];

        foreach ($chunks as $chunk) {
            $account = $this->extractOneSavingsAccount($chunk);
            if ($account !== null) {
                $accounts[] = $account;
            }
        }

        return $accounts;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractOneSavingsAccount(string $chunk): ?array
    {
        $lower = mb_strtolower($chunk);

        // Savings-specific signal: an ISA, saver, easy access, fixed term,
        // notice, cash deposit, or a provider + balance pattern.
        $hasSavingsSignal = preg_match(
            '/\b(isa|individual[\s-]savings[\s-]account|saver|easy[\s-]access|fixed[\s-]term|notice[\s-]account|bond|premium[\s-]bonds?|cash[\s-]deposit|deposit[\s-]account|savings?[\s-]account)\b/u',
            $lower
        ) === 1;

        $amount = $this->extractAmount($chunk);
        $provider = $this->extractKnownProvider($chunk);

        // A chunk is a savings entity only if we have BOTH the savings
        // signal (or provider) AND an amount. Amount-only ("£10k") could
        // be anything — skip rather than misclassify.
        if ($amount === null || (! $hasSavingsSignal && $provider === null)) {
            return null;
        }

        $isIsa = preg_match('/\bisa\b|individual[\s-]savings[\s-]account/u', $lower) === 1;

        $accountType = match (true) {
            preg_match('/\bfixed[\s-]term\b|\bfixed[\s-]rate[\s-]bond\b|\b\d+[\s-]year\s+bond\b/u', $lower) === 1 => 'fixed_term',
            preg_match('/\bnotice[\s-]account\b|\b\d+[\s-]day\s+notice\b/u', $lower) === 1 => 'notice',
            preg_match('/\bregular[\s-]saver\b|\bmonthly[\s-]saver\b/u', $lower) === 1 => 'regular_saver',
            default => 'easy_access',
        };

        $accountName = $this->composeSavingsName($provider, $lower, $isIsa);

        $input = [
            'account_name' => $accountName,
            'current_balance' => $amount,
            'account_type' => $accountType,
        ];
        if ($provider !== null) {
            $input['institution'] = $provider;
        }
        if ($isIsa) {
            $input['is_isa'] = true;
        }

        return $input;
    }

    private function composeSavingsName(?string $provider, string $lowerChunk, bool $isIsa): string
    {
        $suffix = match (true) {
            $isIsa => 'Cash Individual Savings Account',
            preg_match('/\beasy[\s-]access\b/u', $lowerChunk) === 1 => 'Easy Access Saver',
            preg_match('/\bfixed[\s-]term\b|\bfixed[\s-]rate[\s-]bond\b/u', $lowerChunk) === 1 => 'Fixed Term Bond',
            preg_match('/\bregular[\s-]saver\b/u', $lowerChunk) === 1 => 'Regular Saver',
            preg_match('/\bnotice[\s-]account\b/u', $lowerChunk) === 1 => 'Notice Account',
            default => 'Savings Account',
        };

        return trim(($provider ?? 'Savings').' '.$suffix);
    }

    // ─── Pensions ───────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function extractPensions(string $message): array
    {
        $chunks = $this->splitOnConnectors($message);
        $pensions = [];

        foreach ($chunks as $chunk) {
            $pension = $this->extractOnePension($chunk);
            if ($pension !== null) {
                $pensions[] = $pension;
            }
        }

        return $pensions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractOnePension(string $chunk): ?array
    {
        $lower = mb_strtolower($chunk);

        $hasPensionSignal = preg_match(
            '/\b(pension|sipp|self[\s-]invested|workplace|defined[\s-]contribution|defined[\s-]benefit|\bdc\b|\bdb\b|final[\s-]salary|career[\s-]average|nhs|teachers?\'?s?|civil[\s-]service|lgps)\b/u',
            $lower
        ) === 1;

        if (! $hasPensionSignal) {
            return null;
        }

        $category = match (true) {
            preg_match('/\bdefined[\s-]benefit\b|\bdb[\s-]pension\b|\bdb\b|final[\s-]salary|career[\s-]average|nhs|teachers?\'?s?[\s-]pension|lgps|civil[\s-]service/u', $lower) === 1 => 'db',
            default => 'dc',
        };

        $schemeType = match (true) {
            $category === 'db' && preg_match('/final[\s-]salary/u', $lower) === 1 => 'final_salary',
            $category === 'db' && preg_match('/career[\s-]average/u', $lower) === 1 => 'career_average',
            $category === 'db' => 'public_sector',
            preg_match('/\bsipp\b|self[\s-]invested/u', $lower) === 1 => 'sipp',
            preg_match('/\bworkplace\b/u', $lower) === 1 => 'workplace',
            default => 'personal_pension',
        };

        $provider = $this->extractKnownProvider($chunk);
        $value = $this->extractAmount($chunk);

        // Compose scheme_name from the strongest signals we found.
        $schemeNameParts = [];
        if ($provider !== null) {
            $schemeNameParts[] = $provider;
        }
        $schemeNameParts[] = match ($schemeType) {
            'sipp' => 'Self-Invested Personal Pension',
            'workplace' => 'Workplace Pension',
            'final_salary' => 'Final Salary Pension',
            'career_average' => 'Career Average Pension',
            'public_sector' => 'Public Sector Pension',
            default => 'Personal Pension',
        };

        $input = [
            'pension_category' => $category,
            'scheme_name' => trim(implode(' ', $schemeNameParts)),
            'scheme_type' => $schemeType,
        ];

        if ($provider !== null && $category === 'dc') {
            $input['provider'] = $provider;
        }
        if ($value !== null && $category === 'dc') {
            $input['current_fund_value'] = $value;
        }
        if ($value !== null && $category === 'db') {
            $input['accrued_annual_pension'] = $value;
        }

        return $input;
    }

    // ─── Investment accounts ────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function extractInvestmentAccounts(string $message): array
    {
        $chunks = $this->splitOnConnectors($message);
        $accounts = [];

        foreach ($chunks as $chunk) {
            $account = $this->extractOneInvestmentAccount($chunk);
            if ($account !== null) {
                $accounts[] = $account;
            }
        }

        return $accounts;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractOneInvestmentAccount(string $chunk): ?array
    {
        $lower = mb_strtolower($chunk);

        // Pension-shaped chunks are NOT investments — let extractPensions
        // handle those in their own focus.
        if (preg_match('/\b(pension|sipp|self[\s-]invested)\b/u', $lower) === 1) {
            return null;
        }

        $accountType = match (true) {
            preg_match('/\bstocks?[\s&and-]+shares?[\s-]isa\b|\bs&s[\s-]isa\b/u', $lower) === 1 => 'stocks_shares_isa',
            preg_match('/\blifetime[\s-]isa\b|\blisa\b/u', $lower) === 1 => 'lifetime_isa',
            preg_match('/\bgia\b|general[\s-]investment[\s-]account/u', $lower) === 1 => 'personal_investment_account',
            preg_match('/\boffshore[\s-]bond\b/u', $lower) === 1 => 'offshore_bond',
            preg_match('/\bonshore[\s-]bond\b|\binvestment[\s-]bond\b/u', $lower) === 1 => 'onshore_bond',
            preg_match('/\bvct\b|venture[\s-]capital[\s-]trust/u', $lower) === 1 => 'vct',
            preg_match('/\beis\b(?![\s-]relief)/u', $lower) === 1 => 'eis',
            default => null,
        };

        if ($accountType === null) {
            return null;
        }

        $provider = $this->extractKnownProvider($chunk);
        $value = $this->extractAmount($chunk);

        $suffix = match ($accountType) {
            'stocks_shares_isa' => 'Stocks & Shares Individual Savings Account',
            'lifetime_isa' => 'Lifetime Individual Savings Account',
            'personal_investment_account' => 'General Investment Account',
            'onshore_bond' => 'Onshore Bond',
            'offshore_bond' => 'Offshore Bond',
            'vct' => 'Venture Capital Trust',
            'eis' => 'Enterprise Investment Scheme',
            default => 'Investment Account',
        };

        $accountName = trim(($provider ?? 'Investment').' '.$suffix);

        $input = [
            'account_name' => $accountName,
            'account_type' => $accountType,
        ];
        if ($provider !== null) {
            $input['provider'] = $provider;
        }
        if ($value !== null) {
            $input['current_value'] = $value;
        }

        return $input;
    }

    // ─── Shared helpers ─────────────────────────────────────────────

    /**
     * Split a multi-entity message on natural connector phrases. The
     * pattern is intentionally conservative — dropping on "and" only
     * when it is followed by whitespace so "Legal and General" is not
     * split in half. Commas, semicolons, bullet points, newlines, "plus",
     * "also", and "&" (outside provider names) are also treated as
     * separators.
     *
     * @return list<string>
     */
    private function splitOnConnectors(string $message): array
    {
        // Normalise provider names that contain legal-and-general style
        // ampersands so the splitter doesn't break them apart. Translate
        // known multi-word provider names to a single token.
        $normalised = $message;
        foreach (['Legal & General' => 'Legal_and_General', 'Legal and General' => 'Legal_and_General', 'S&S ISA' => 'SS_ISA', 'Stocks & Shares' => 'Stocks_and_Shares'] as $from => $to) {
            $normalised = str_ireplace($from, $to, $normalised);
        }

        // Protect thousand-separator commas by masking them before the split.
        // £300,000 and 300,000 — the comma is a formatting artefact, not a
        // connector. Any comma followed by exactly three digits (with an
        // optional further group of three, ad infinitum) is a thousand
        // separator and MUST NOT split the message.
        $normalised = preg_replace('/,(?=\d{3}\b)/u', '<COMMA>', $normalised) ?? $normalised;

        $pattern = '/\s*(?:,\s*and\s+|\s+and\s+|\s*,\s*|\s*;\s*|\s*\band\s+also\s+|\s+plus\s+|\s+also\s+|\n|\*\s+)\s*/iu';
        $parts = preg_split($pattern, $normalised);
        if ($parts === false) {
            return [$message];
        }

        return array_values(array_filter(array_map(
            fn (string $p): string => trim(str_replace(
                ['Legal_and_General', 'SS_ISA', 'Stocks_and_Shares', '<COMMA>'],
                ['Legal & General', 'S&S ISA', 'Stocks & Shares', ','],
                $p
            )),
            $parts
        ), fn (string $p): bool => $p !== ''));
    }

    /**
     * Pull a monetary amount out of a chunk. Prefers £-marked values so
     * "Aviva 2024 policy" doesn't turn into £2,024. Supports k / m suffixes
     * and commas.
     */
    private function extractAmount(string $chunk): ?float
    {
        if (preg_match('/£\s*([\d,]+(?:\.\d+)?)\s*(k|m|K|M|\bmillion\b|\bthousand\b)?/u', $chunk, $m) === 1) {
            return $this->scaleAmount((float) str_replace(',', '', $m[1]), $m[2] ?? '');
        }

        if (preg_match('/\b(\d{1,3}(?:,\d{3})+(?:\.\d+)?)\b/u', $chunk, $m) === 1) {
            // Bare comma-grouped number like "300,000"
            return (float) str_replace(',', '', $m[1]);
        }

        if (preg_match('/\b(\d+(?:\.\d+)?)\s*(k|m|K|M|million|thousand)\b/u', $chunk, $m) === 1) {
            return $this->scaleAmount((float) $m[1], $m[2]);
        }

        return null;
    }

    private function scaleAmount(float $n, string $suffix): float
    {
        $s = mb_strtolower(trim($suffix));

        return match ($s) {
            'k', 'thousand' => $n * 1000,
            'm', 'million' => $n * 1_000_000,
            default => $n,
        };
    }

    /**
     * Pull a monthly premium (e.g. "£35/mo", "£20 per month") out of a chunk.
     */
    private function extractPremium(string $chunk): ?float
    {
        if (preg_match('/£\s*(\d+(?:\.\d+)?)\s*(?:\/|\s*per\s+)?(?:mo(?:nth)?|m)\b/ui', $chunk, $m) === 1) {
            return (float) $m[1];
        }

        return null;
    }

    private function extractKnownProvider(string $chunk): ?string
    {
        foreach (self::KNOWN_PROVIDERS as $provider) {
            if (stripos($chunk, $provider) !== false) {
                return match ($provider) {
                    'L&G', 'Legal and General' => 'Legal & General',
                    'LV' => 'LV=',
                    default => $provider,
                };
            }
        }

        return null;
    }

    /**
     * Stable identity key used to decide whether a given extracted entity
     * has already been emitted by the LLM. Two levels of normalisation
     * matter:
     *   - Policy types map to coarse groups (life / ci / ip) because the
     *     LLM often picks a slightly different enum value than the
     *     extractor (e.g. 'term' vs 'level_term'); we still consider
     *     them the same entity.
     *   - Provider / institution strings are lower-cased and stripped of
     *     non-word chars so "L&G" and "Legal & General" both key to
     *     "legalgeneral".
     *
     * The $fromLlm flag lets us accept the slightly different field
     * names used by fill_form payloads ('coverage_amount' instead of
     * 'sum_assured', 'policyType' instead of 'policy_type').
     *
     * @param  array<string, mixed>  $fields
     */
    private function identityKey(string $focus, array $fields, bool $fromLlm): string
    {
        return match ($focus) {
            'protection' => $this->protectionIdentityKey($fields, $fromLlm),
            'savings', 'budgeting' => $this->savingsIdentityKey($fields, $fromLlm),
            'retirement' => $this->pensionIdentityKey($fields, $fromLlm),
            'investment' => $this->investmentIdentityKey($fields, $fromLlm),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function protectionIdentityKey(array $fields, bool $fromLlm): string
    {
        // fill_form payload normalises policy_type → policyType (life / criticalIllness / incomeProtection).
        $rawType = (string) ($fields['policyType'] ?? $fields['policy_type'] ?? '');
        $group = match ($rawType) {
            'life', 'level_term', 'term', 'whole_of_life', 'decreasing_term', 'family_income_benefit' => 'life',
            'criticalIllness', 'standalone_ci', 'accelerated_ci' => 'ci',
            'incomeProtection', 'income_protection' => 'ip',
            default => 'other',
        };

        $provider = $this->normaliseProviderKey((string) ($fields['provider'] ?? ''));

        return $group.'|'.$provider;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function savingsIdentityKey(array $fields, bool $fromLlm): string
    {
        $institution = $this->normaliseProviderKey(
            (string) ($fields['institution'] ?? $fields['account_name'] ?? '')
        );
        $isIsa = ! empty($fields['is_isa']);

        return 'savings|'.$institution.'|'.($isIsa ? 'isa' : 'std');
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function pensionIdentityKey(array $fields, bool $fromLlm): string
    {
        $provider = $this->normaliseProviderKey(
            (string) ($fields['provider'] ?? $fields['scheme_name'] ?? '')
        );
        $category = (string) ($fields['pension_category'] ?? 'dc');

        return 'pension|'.$category.'|'.$provider;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function investmentIdentityKey(array $fields, bool $fromLlm): string
    {
        $provider = $this->normaliseProviderKey(
            (string) ($fields['provider'] ?? $fields['account_name'] ?? '')
        );
        $type = (string) ($fields['account_type'] ?? '');

        return 'invest|'.$type.'|'.$provider;
    }

    private function normaliseProviderKey(string $s): string
    {
        // Canonicalise common aliases so the LLM saying "L&G" and the
        // extractor saying "Legal & General" key to the same thing.
        $canonical = mb_strtolower(trim($s));
        $canonical = match ($canonical) {
            'l&g', 'l and g', 'legal and general' => 'legal & general',
            'lv' => 'lv=',
            default => $canonical,
        };

        return preg_replace('/[^a-z0-9]/u', '', $canonical) ?? '';
    }
}
