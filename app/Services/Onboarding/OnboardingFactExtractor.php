<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AiConversation;

/**
 * Regex-based fact extractor for the Fyn onboarding flow.
 *
 * Runs on every user message in OnboardingChatDirector::handleUserMessage,
 * BEFORE state-specific processing. Extracts structured facts into the
 * ai_conversations.onboarding_parked_facts JSON column across five
 * buckets: personal, spouse, dependants, employment, expenditure.
 *
 * IMPORTANT — parking only. This extractor NEVER writes to users.* or
 * family_members. Backing-record writes stay the responsibility of the
 * existing grouped_extract tool handlers (capture_personal_details,
 * capture_spouse_details, capture_dependants, capture_work_details)
 * which fire on the explicit state for each bucket. The parking column
 * lets those state handlers (or the new profile-review pauses) consult
 * what the user has already volunteered and short-circuit or gap-fill
 * rather than re-asking from scratch.
 *
 * Regex runs on the original-case input so the \b[A-Z][a-z]+\b patterns
 * for names work correctly. Never lowercase before matching (AMENDMENTS §F).
 */
final class OnboardingFactExtractor
{
    /**
     * Words that follow a spouse trigger ("married to", "wife", "husband",
     * "spouse", "partner") but are NOT first names. The match is rejected
     * if the captured token (lowercased) appears here. Single- and two-letter
     * pronouns ("i", "me", "we", "us", "my") are filtered automatically by
     * the regex's `{3,21}` length quantifier.
     */
    private const SPOUSE_NAME_STOP_WORDS = [
        // Pronouns / possessives
        'her', 'him', 'them', 'his', 'hers', 'their', 'theirs', 'ours', 'yours', 'mine',
        // Articles / determiners
        'the', 'this', 'that', 'these', 'those',
        // Indefinites
        'someone', 'anyone', 'everyone', 'nobody', 'none',
        // Relations
        'mother', 'father', 'parent', 'parents', 'sister', 'brother',
        'cousin', 'aunt', 'uncle', 'niece', 'nephew',
        'sibling', 'siblings', 'child', 'children', 'son', 'sons',
        'daughter', 'daughters', 'kid', 'kids', 'baby', 'babies', 'twin', 'twins',
        // Self-referential / generic
        'name', 'spouse', 'wife', 'husband', 'partner', 'family',
    ];

    /**
     * Captured tokens that follow an employer trigger ("work for", "work at",
     * "employed by") but are NOT company names. The match is rejected if
     * the token (lowercased) appears here.
     */
    private const EMPLOYER_NAME_STOP_WORDS = [
        // Pronouns / self-referential — except "myself" which signals self-employed,
        // handled by dedicated branch in extractEmployment via parseEmploymentFromText.
        'them', 'him', 'her', 'his', 'hers', 'theirs', 'ours', 'yours',
        'myself', 'yourself', 'himself', 'herself', 'ourselves', 'themselves', 'itself',
        // Generic "places" that aren't companies
        'home', 'work', 'office', 'shop', 'site', 'company', 'business', 'employer',
        'somewhere', 'anywhere', 'nowhere', 'everywhere',
        // Articles
        'the', 'this', 'that',
    ];

    /**
     * Captured tokens that match the dependant name+age shape but are NOT
     * actual dependant names. Heavier list than the spouse stop-words because
     * the dependants regex has no trigger word, so any "noun + 1-2-digit
     * number" pair would match without filtering.
     */
    private const DEPENDANT_NAME_STOP_WORDS = [
        // Trigger / context nouns (already covered by count_hint regex)
        'kid', 'kids', 'son', 'sons', 'daughter', 'daughters', 'child', 'children',
        'twin', 'twins', 'dependent', 'dependents', 'dependant', 'dependants',
        'sibling', 'siblings',
        // Common verbs near a number
        'earning', 'earn', 'earned', 'earns', 'spend', 'spends', 'spending', 'spent',
        'have', 'had', 'has', 'get', 'got', 'gets', 'getting', 'give', 'gave', 'given',
        'work', 'works', 'worked', 'working', 'born', 'made', 'make', 'makes',
        'making', 'paying', 'paid', 'pays', 'pay',
        // Money / income
        'income', 'salary', 'salaries', 'wage', 'wages', 'pension', 'pensions',
        // Time / age
        'age', 'aged', 'year', 'years', 'month', 'months', 'week', 'weeks', 'day', 'days',
        'old', 'older', 'young', 'younger', 'birthday',
        // Pronouns / relations
        'they', 'them', 'their', 'theirs', 'his', 'her', 'hers', 'mine', 'ours', 'yours',
        'mother', 'father', 'parent', 'parents', 'mum', 'dad', 'mom', 'mommy', 'daddy',
        'wife', 'husband', 'spouse', 'partner', 'family',
        'aunt', 'uncle', 'cousin', 'niece', 'nephew',
        // Connectives / generic
        'and', 'but', 'or', 'for', 'with', 'without', 'after', 'before', 'about',
        'around', 'over', 'under', 'name', 'names',
        // Number words (would only match if 3+ chars)
        'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'first', 'second', 'third', 'last', 'next',
    ];

    /**
     * Extract facts from a single user turn and merge them into the
     * conversation's onboarding_parked_facts column.
     */
    public function extractAndPark(AiConversation $conversation, string $userMessage): void
    {
        $facts = $this->extract($userMessage);

        if ($facts === []) {
            return;
        }

        $existing = (array) ($conversation->onboarding_parked_facts ?? []);

        $merged = $this->mergeFacts($existing, $facts);

        $conversation->update(['onboarding_parked_facts' => $merged]);
    }

    /**
     * Extract structured facts from a user message. Returns a bucketed
     * array; buckets are only populated when something matched.
     *
     * @return array<string, array<string, mixed>>
     */
    public function extract(string $message): array
    {
        $facts = [];

        $personal = $this->extractPersonal($message);
        if ($personal !== []) {
            $facts['personal'] = $personal;
        }

        $spouse = $this->extractSpouse($message);
        if ($spouse !== []) {
            $facts['spouse'] = $spouse;
        }

        $dependants = $this->extractDependants($message);
        if ($dependants !== []) {
            $facts['dependants'] = $dependants;
        }

        $employment = $this->extractEmployment($message);
        if ($employment !== []) {
            $facts['employment'] = $employment;
        }

        $expenditure = $this->extractExpenditure($message);
        if ($expenditure !== []) {
            $facts['expenditure'] = $expenditure;
        }

        return $facts;
    }

    // ─── Personal ────────────────────────────────────────────────────

    private function extractPersonal(string $message): array
    {
        $personal = [];

        // Marital status
        if (preg_match('/\b(married|civil\s*partner(?:ship)?)\b/i', $message) === 1) {
            $personal['marital_status'] = preg_match('/civil/i', $message) === 1
                ? 'civil_partnership'
                : 'married';
        } elseif (preg_match('/\b(single|unmarried|not\s+married)\b/i', $message) === 1) {
            $personal['marital_status'] = 'single';
        } elseif (preg_match('/\b(divorced|separated)\b/i', $message) === 1) {
            $personal['marital_status'] = 'divorced';
        } elseif (preg_match('/\b(widow(?:ed|er)?)\b/i', $message) === 1) {
            $personal['marital_status'] = 'widowed';
        }

        // Age hint (e.g., "I'm 40", "aged 40")
        if (preg_match('/\b(?:i\'?m|i\s*am|age[ds]?)\s*(\d{2})\b/i', $message, $m) === 1) {
            $age = (int) $m[1];
            if ($age >= 18 && $age <= 110) {
                $personal['age_hint'] = $age;
            }
        }

        // Explicit date of birth — reuse the existing parser.
        $dob = OnboardingValueInterpreter::parseDateOfBirth($message);
        if ($dob !== null) {
            $personal['date_of_birth'] = $dob;
        }

        return $personal;
    }

    // ─── Spouse ──────────────────────────────────────────────────────

    private function extractSpouse(string $message): array
    {
        $spouse = [];

        // "married to Angela", "wife Emily", "husband Dave", "partner Jamie".
        // Case-insensitive — chat input is rarely auto-capitalised, and users
        // routinely type "married to angela". Captured token is normalised to
        // title case ("ANGELA"/"angela"/"AnGeLa" → "Angela") and rejected if
        // it falls in SPOUSE_NAME_STOP_WORDS (pronouns, relation nouns, etc.)
        // so phrases like "wife sister angela" or "married to her" do not
        // produce a false-positive spouse first_name.
        if (preg_match('/\b(?:married\s+to|wife|husband|spouse|partner)\s+([a-zA-Z]{3,21})\b/i', $message, $m) === 1) {
            $candidate = $m[1];
            if (! in_array(strtolower($candidate), self::SPOUSE_NAME_STOP_WORDS, true)) {
                $spouse['first_name'] = ucfirst(strtolower($candidate));
            }
        }

        // "Angela, 45" right after the wife/partner noun. Match against the
        // user's original casing so the age picker locates the same token.
        if (isset($spouse['first_name'])) {
            $pattern = '/'.preg_quote($spouse['first_name'], '/').',?\s+(\d{2})\b/i';
            if (preg_match($pattern, $message, $m) === 1) {
                $age = (int) $m[1];
                if ($age >= 18 && $age <= 110) {
                    $spouse['age_hint'] = $age;
                }
            }
        }

        // Email hint anywhere in message ("her email is x@y.com")
        if (preg_match('/\b[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}\b/', $message, $m) === 1) {
            $spouse['email'] = $m[0];
        }

        return $spouse;
    }

    // ─── Dependants ──────────────────────────────────────────────────

    private function extractDependants(string $message): array
    {
        $dependants = [];

        // Explicit count first: "two kids", "three children"
        $wordToNumber = [
            'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
            'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        ];

        if (preg_match('/\b(\d|one|two|three|four|five|six|seven|eight|nine|ten)\s+(kids?|children|son[s]?|daughter[s]?|dependents?|dependants?)\b/i', $message, $m) === 1) {
            $raw = mb_strtolower($m[1]);
            $count = is_numeric($raw) ? (int) $raw : ($wordToNumber[$raw] ?? null);
            if ($count !== null && $count >= 1 && $count <= 10) {
                $dependants['count_hint'] = $count;
            }
        }

        // People hints — "Sam 8 and Eli 6", "sam 8 and eli 6", "SAM 8 AND ELI 6".
        // Case-insensitive so chat-style lowercase ("sam 8 and eli 6") still
        // populates people_hint. Captured token is title-cased and rejected
        // when it appears in DEPENDANT_NAME_STOP_WORDS — heavier list than the
        // spouse extractor because there is no trigger word ("married to",
        // "wife") to anchor the match, so common verbs/relations/pronouns
        // adjacent to a 1-2-digit number would otherwise produce false
        // positives ("earning 75", "born 18", "have 3").
        if (preg_match_all('/\b([a-zA-Z]{3,21})\s+(?:aged?\s+)?(\d{1,2})\b/i', $message, $matches, PREG_SET_ORDER) > 0) {
            $people = [];
            foreach ($matches as $match) {
                $candidate = $match[1];
                if (in_array(strtolower($candidate), self::DEPENDANT_NAME_STOP_WORDS, true)) {
                    continue;
                }
                $age = (int) $match[2];
                if ($age >= 0 && $age <= 25) {
                    $people[] = [
                        'name' => ucfirst(strtolower($candidate)),
                        'age_hint' => $age,
                    ];
                }
            }
            if ($people !== []) {
                $dependants['people_hint'] = $people;
            }
        }

        return $dependants;
    }

    // ─── Employment ──────────────────────────────────────────────────

    private function extractEmployment(string $message): array
    {
        $employment = [];

        $status = OnboardingValueInterpreter::parseEmploymentFromText($message);
        if ($status !== null) {
            $employment['status'] = $status;
        }

        // Employer name: "I work for X", "work at X", "employed by X". Trigger
        // matches case-insensitively. Captured token preserves original casing
        // (companies vary — Apple, HSBC, NatWest — and title-casing would
        // mangle acronyms). Only the first word of the company is captured by
        // this regex; multi-word names like "Royal Bank of Scotland" are
        // truncated to "Royal" — same behaviour as before.
        if (preg_match('/\b(?:work(?:ing)?\s+(?:for|at)|employed\s+by)\s+([a-zA-Z][A-Za-z0-9& \-]{2,40})/i', $message, $m) === 1) {
            $candidate = trim($m[1]);
            if (! in_array(strtolower($candidate), self::EMPLOYER_NAME_STOP_WORDS, true)) {
                // Lowercase-only token gets a leading capital so "i work for
                // hsbc" parks as "Hsbc" rather than "hsbc". Mixed-case tokens
                // ("HSBC", "NatWest") preserve the user's casing.
                $employment['employer'] = ctype_lower($candidate) ? ucfirst($candidate) : $candidate;
            }
        }

        // Income hint — use existing parser but only record when currency signal present
        if (preg_match('/£[\d,.]+\s*(k|m)?|\b\d{1,3}(?:,\d{3})*(?:\.\d+)?\s*(k|m)\b/i', $message)) {
            $income = OnboardingValueInterpreter::parseIncomeAmount($message);
            if ($income !== null) {
                $employment['annual_income'] = $income;
            }
        }

        return $employment;
    }

    // ─── Expenditure ─────────────────────────────────────────────────

    private function extractExpenditure(string $message): array
    {
        $expenditure = [];

        // Very conservative — we only park a total when the user explicitly
        // phrases it as monthly spending.
        if (preg_match('/\b(?:spend|expenditure|outgoing(?:s)?)\s+(?:is|are|of|around|about)?\s*£?([\d,.]+)\s*(k|m)?\s*(?:a|per)?\s*(?:month|monthly)\b/i', $message, $m) === 1) {
            $raw = $m[1].($m[2] ?? '');
            $amount = OnboardingValueInterpreter::parseExpenditureAmount('£'.$raw.' per month');
            if ($amount !== null) {
                $expenditure['monthly_total_hint'] = $amount;
            }
        }

        return $expenditure;
    }

    // ─── Merging ─────────────────────────────────────────────────────

    /**
     * Deep-merge two fact arrays by bucket. Later values overwrite earlier
     * keys except that arrays within a bucket (people_hint) append
     * without duplicates.
     *
     * @param  array<string, array<string, mixed>>  $existing
     * @param  array<string, array<string, mixed>>  $incoming
     * @return array<string, array<string, mixed>>
     */
    private function mergeFacts(array $existing, array $incoming): array
    {
        $merged = $existing;

        foreach ($incoming as $bucket => $bucketValues) {
            if (! isset($merged[$bucket]) || ! is_array($merged[$bucket])) {
                $merged[$bucket] = [];
            }

            foreach ($bucketValues as $key => $value) {
                // Append + dedupe when the key is a list of people.
                if ($key === 'people_hint' && is_array($value)) {
                    $existingPeople = $merged[$bucket][$key] ?? [];
                    $allPeople = array_merge($existingPeople, $value);
                    $seen = [];
                    $deduped = [];
                    foreach ($allPeople as $person) {
                        $k = ($person['name'] ?? '').'|'.($person['age_hint'] ?? '');
                        if (! isset($seen[$k])) {
                            $seen[$k] = true;
                            $deduped[] = $person;
                        }
                    }
                    $merged[$bucket][$key] = $deduped;

                    continue;
                }

                $merged[$bucket][$key] = $value;
            }
        }

        return $merged;
    }
}
