<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

final class CaptureAccuracyGate
{
    private const OWNERSHIP_TOOLS = [
        'create_savings_account',
        'create_investment_account',
        'create_property',
        'create_liability',
    ];

    /**
     * Preserve a name that is explicit in the user's sentence when an
     * extraction model returns the matching date but drops the optional name.
     */
    public function restoreDependantNames(array $arguments, string $latestUserText): array
    {
        $datePattern = '(?:\d{1,2}(?:st|nd|rd|th)?\s+(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\.?[,]?\s+\d{4}|\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[/.\-]\d{1,2}[/.\-]\d{4})';
        preg_match_all(
            '~\b(?<name>[A-Z][a-zA-Z\'’\-]{1,30})\s*,?\s*(?:was\s+)?(?:born|date of birth|dob(?:\s+is)?)\s*(?:on\s+)?(?<date>'.$datePattern.')~u',
            $latestUserText,
            $matches,
            PREG_SET_ORDER
        );

        $namesByDate = [];
        foreach ($matches as $match) {
            $name = (string) $match['name'];
            if (in_array(mb_strtolower($name), ['he', 'she', 'they', 'child', 'dependant'], true)) {
                continue;
            }
            $dates = $this->explicitDatesFromText(mb_strtolower((string) $match['date']));
            if (count($dates) === 1) {
                $namesByDate[$dates[0]] = $name;
            }
        }

        if ($namesByDate === [] || ! is_array($arguments['dependants'] ?? null)) {
            return $arguments;
        }

        foreach ($arguments['dependants'] as $index => $dependant) {
            $firstName = trim((string) ($dependant['first_name'] ?? ''));
            $dateOfBirth = trim((string) ($dependant['date_of_birth'] ?? ''));
            if ($firstName === '' && isset($namesByDate[$dateOfBirth])) {
                $arguments['dependants'][$index]['first_name'] = $namesByDate[$dateOfBirth];
            }
        }

        return $arguments;
    }

    /**
     * @return array{allowed: bool, reason?: string, missing?: list<string>}
     */
    public function inspect(string $tool, array $arguments, string $latestUserText): array
    {
        if ($tool === 'capture_dependants') {
            return $this->inspectDependantDates($arguments, mb_strtolower(trim($latestUserText)));
        }

        if (! in_array($tool, self::OWNERSHIP_TOOLS, true)) {
            return ['allowed' => true];
        }

        $text = $this->evidenceForEntity(mb_strtolower(trim($latestUserText)), $arguments);
        $missing = [];
        $reasons = [];

        $isIsaWrite = $this->isIsaWrite($tool, $arguments, $text);
        if ($isIsaWrite) {
            $textSubtype = $this->isaSubtypeFromText($text);
            $argumentSubtype = $this->isaSubtypeFromArguments($tool, $arguments);
            if ($textSubtype === null || $argumentSubtype !== $textSubtype) {
                $missing[] = 'isa_subtype';
                $reasons[] = 'I need the ISA type before I can save it';
            }
        }

        $argumentOwnership = $arguments['ownership_type'] ?? null;
        $textOwnership = $this->ownershipFromText($text);
        if ($tool === 'create_investment_account'
            && in_array($argumentOwnership, ['tenants_in_common', 'trust'], true)) {
            $missing[] = 'ownership_type';
            $reasons[] = 'Investment accounts can only be recorded as individually or jointly owned';
        }
        if ($isIsaWrite && $argumentOwnership !== 'individual') {
            $missing[] = 'ownership_type';
            $reasons[] = 'An ISA must be recorded as individually owned';
        }
        if (! is_string($argumentOwnership)
            || ! in_array($argumentOwnership, ['individual', 'joint', 'tenants_in_common', 'trust'], true)
            || $textOwnership === null
            || $argumentOwnership !== $textOwnership) {
            $missing[] = 'ownership_type';
            $reasons[] = 'I need you to confirm whether you own it individually or with someone else';
        }

        if (in_array($argumentOwnership, ['joint', 'tenants_in_common'], true)) {
            if (! isset($arguments['joint_owner_id']) || ! is_numeric($arguments['joint_owner_id'])) {
                $missing[] = 'joint_owner_id';
                $reasons[] = 'I need to know who the joint owner is';
            }
            $evidencedShare = $this->ownershipShareFromText($text);
            if (! isset($arguments['ownership_percentage'])
                || ! is_numeric($arguments['ownership_percentage'])
                || $evidencedShare === null
                || abs((float) $arguments['ownership_percentage'] - $evidencedShare) > 0.001) {
                $missing[] = 'ownership_percentage';
                $reasons[] = 'I need the ownership share';
            }
        } elseif (in_array($argumentOwnership, ['individual', 'trust'], true)
            && isset($arguments['ownership_percentage'])
            && (! is_numeric($arguments['ownership_percentage'])
                || abs((float) $arguments['ownership_percentage'] - 100.0) > 0.001)) {
            $missing[] = 'ownership_percentage';
            $reasons[] = 'An individually or trust-owned record must be recorded at 100% ownership';
        } elseif (isset($arguments['joint_owner_id'])) {
            $missing[] = 'ownership_type';
            $reasons[] = 'The ownership details conflict, so I need you to confirm them again';
        }

        if ($missing === []) {
            return ['allowed' => true];
        }

        return [
            'allowed' => false,
            'reason' => implode('. ', array_values(array_unique($reasons))).'.',
            'missing' => array_values(array_unique($missing)),
        ];
    }

    /**
     * A model-supplied YYYY-MM-DD value is not evidence that the user supplied
     * an exact date. Match every proposed dependant date against an explicit
     * day, month and four-digit year in the user's own messages before writing.
     * Direct internal calls without conversation evidence remain the handler's
     * responsibility.
     */
    private function inspectDependantDates(array $arguments, string $text): array
    {
        if ($text === '') {
            return ['allowed' => true];
        }

        $explicitDates = $this->explicitDatesFromText($text);
        $dependants = is_array($arguments['dependants'] ?? null) ? $arguments['dependants'] : [];
        $missing = [];

        foreach ($dependants as $index => $dependant) {
            $dateOfBirth = trim((string) ($dependant['date_of_birth'] ?? ''));
            if ($dateOfBirth === '' || ! in_array($dateOfBirth, $explicitDates, true)) {
                $missing[] = "dependants.{$index}.date_of_birth";
            }
        }

        if ($missing === []) {
            return ['allowed' => true];
        }

        return [
            'allowed' => false,
            'reason' => 'I need the exact date of birth for each dependant before I can save anything.',
            'missing' => $missing,
        ];
    }

    /** @return list<string> */
    private function explicitDatesFromText(string $text): array
    {
        $dates = [];

        preg_match_all('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u', $text, $isoDates, PREG_SET_ORDER);
        foreach ($isoDates as $match) {
            $this->appendValidDate($dates, (int) $match[1], (int) $match[2], (int) $match[3]);
        }

        preg_match_all('#\b(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})\b#u', $text, $numericDates, PREG_SET_ORDER);
        foreach ($numericDates as $match) {
            $this->appendValidDate($dates, (int) $match[3], (int) $match[2], (int) $match[1]);
        }

        $months = [
            'january' => 1, 'jan' => 1,
            'february' => 2, 'feb' => 2,
            'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'may' => 5,
            'june' => 6, 'jun' => 6,
            'july' => 7, 'jul' => 7,
            'august' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9, 'sept' => 9,
            'october' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'december' => 12, 'dec' => 12,
        ];
        preg_match_all(
            '/\b(\d{1,2})(?:st|nd|rd|th)?\s+(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec)\.?[,]?\s+(\d{4})\b/u',
            $text,
            $textualDates,
            PREG_SET_ORDER
        );
        foreach ($textualDates as $match) {
            $this->appendValidDate($dates, (int) $match[3], $months[$match[2]], (int) $match[1]);
        }

        return array_values(array_unique($dates));
    }

    /** @param list<string> $dates */
    private function appendValidDate(array &$dates, int $year, int $month, int $day): void
    {
        if (checkdate($month, $day, $year)) {
            $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    private function isIsaWrite(string $tool, array $arguments, string $text): bool
    {
        if (! in_array($tool, ['create_savings_account', 'create_investment_account'], true)) {
            return false;
        }

        return str_contains($text, 'isa')
            || ! empty($arguments['is_isa'])
            || str_contains((string) ($arguments['account_type'] ?? ''), 'isa');
    }

    private function isaSubtypeFromText(string $text): ?string
    {
        $explicit = $this->latestCategorisedMatch($text, [
            'stocks_and_shares' => '/\bstocks?\s*(?:&|and)\s*shares?\s+isa\b/u',
            'lifetime' => '/(?:\blifetime\s+isa\b|\blisa\b)/u',
            'innovative_finance' => '/\binnovative\s+finance\s+isa\b/u',
            'junior' => '/\bjunior\s+isa\b/u',
            'cash' => '/\bcash\s+isa\b/u',
        ]);
        if ($explicit !== null) {
            return $explicit;
        }

        if (! $this->hasPositiveIsaContext($text)) {
            return null;
        }

        return $this->latestCategorisedMatch($text, [
            'stocks_and_shares' => '/(?:^|[.!?,\n]\s*)stocks?\s*(?:&|and)\s*shares?(?:\s*[.!?,\n]|$)/u',
            'lifetime' => '/(?:^|[.!?,\n]\s*)lifetime(?:\s*[.!?,\n]|$)/u',
            'innovative_finance' => '/(?:^|[.!?,\n]\s*)innovative\s+finance(?:\s*[.!?,\n]|$)/u',
            'junior' => '/(?:^|[.!?,\n]\s*)junior(?:\s*[.!?,\n]|$)/u',
            'cash' => '/(?:^|[.!?,\n]\s*)cash(?:\s+and\b|\s*[.!?,\n]|$)/u',
        ]);
    }

    private function isaSubtypeFromArguments(string $tool, array $arguments): ?string
    {
        $type = (string) ($arguments['account_type'] ?? '');
        $explicit = (string) ($arguments['isa_type'] ?? '');

        if ($tool === 'create_savings_account') {
            if (! (bool) ($arguments['is_isa'] ?? false)) {
                return null;
            }

            return match ($type) {
                'cash_isa' => 'cash',
                'junior_isa' => 'junior',
                default => null,
            };
        }

        $wrapperSubtype = match ($type) {
            'stocks_shares_isa' => 'stocks_and_shares',
            'lifetime_isa' => 'lifetime',
            'innovative_finance_isa' => 'innovative_finance',
            default => null,
        };
        if ($wrapperSubtype !== null) {
            return $explicit === '' || $explicit === $wrapperSubtype ? $wrapperSubtype : null;
        }

        return $type === 'isa' && in_array($explicit, ['stocks_and_shares', 'lifetime', 'innovative_finance'], true)
            ? $explicit
            : null;
    }

    private function ownershipFromText(string $text): ?string
    {
        return $this->latestCategorisedMatch($text, [
            'tenants_in_common' => '/\btenants?\s+in\s+common\b/u',
            'joint' => '/\b(?:joint|jointly|we\s+own|owned\s+with|with\s+my\s+(?:spouse|partner)|50\s*\/\s*50)\b/u',
            'individual' => '/\b(?:solely|mine\s+alone|just\s+me|only\s+me|individually)\b/u',
            'trust' => '/\b(?:held\s+in\s+trust|trust-owned|owned\s+by\s+the\s+trust)\b/u',
        ]);
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private function latestCategorisedMatch(string $text, array $patterns): ?string
    {
        $events = [];

        foreach ($patterns as $category => $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] ?? [] as $match) {
                $events[] = [
                    'offset' => $match[1],
                    'category' => $this->isNegatedMatch($text, $match[1]) ? null : $category,
                    'corrected' => $this->isCorrectionMatch($text, $match[1]),
                ];
            }
        }

        if ($events === []) {
            return null;
        }

        usort($events, static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']);
        $latest = end($events);
        $positiveCategories = array_values(array_unique(array_filter(array_column($events, 'category'))));

        if (count($positiveCategories) > 1 && ! $latest['corrected']) {
            return null;
        }

        return $latest['category'];
    }

    private function ownershipShareFromText(string $text): ?float
    {
        $events = [];
        $patterns = [
            ['/\b(?:i\s+own|my\s+share\s+(?:(?:is|at)\s+)?)(\d{1,3}(?:\.\d+)?)\s*%/u', 1],
            ['/\b(\d{1,3}(?:\.\d+)?)\s*%\s+(?:is\s+)?(?:owned\s+)?(?:by\s+)?me\b/u', 1],
            ['/\bowned\s+(\d{1,3}(?:\.\d+)?)\s*%/u', 1],
            ['/\b(?:actually|correction|rather|instead)[,:]?\s*(?:my\s+share\s+(?:(?:is|at)\s+)?)?(\d{1,3}(?:\.\d+)?)\s*%/u', 1],
            ['/(?:^|[.!?\n]\s*)(\d{1,3}(?:\.\d+)?)\s*%(?:\s*[.!?\n]|$)/u', 1],
        ];
        foreach ($patterns as [$pattern, $capture]) {
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($matches as $match) {
                $events[] = [
                    'offset' => $match[0][1],
                    'value' => $this->isNegatedMatch($text, $match[0][1]) ? null : (float) $match[$capture][0],
                    'corrected' => $this->isCorrectionMatch($text, $match[0][1])
                        || preg_match('/\b(?:actually|correction|rather|instead)\b/u', $match[0][0]) === 1,
                ];
            }
        }

        $equalPatterns = [
            '/\b(?:ownership|owned|share|split)\b.{0,20}\b(?:half|equal(?:ly)?|in\s+equal\s+shares|50\s*\/\s*50)\b/u',
            '/(?:^|[.!?\n]\s*)(?:half|equal(?:ly)?|in\s+equal\s+shares|50\s*\/\s*50)(?:\s*[.!?\n]|$)/u',
        ];
        foreach ($equalPatterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] ?? [] as $match) {
                $events[] = [
                    'offset' => $match[1],
                    'value' => $this->isNegatedMatch($text, $match[1]) || $this->containsNegation($match[0])
                        ? null
                        : 50.0,
                    'corrected' => $this->isCorrectionMatch($text, $match[1]),
                ];
            }
        }

        if ($events === []) {
            return null;
        }

        usort($events, static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']);
        $latest = end($events);
        $positiveShares = array_values(array_unique(array_map(
            static fn (float $share): string => number_format($share, 3, '.', ''),
            array_filter(array_column($events, 'value'), static fn (mixed $value): bool => is_float($value))
        )));

        if (count($positiveShares) > 1 && ! $latest['corrected']) {
            return null;
        }

        return $latest['value'];
    }

    private function hasPositiveIsaContext(string $text): bool
    {
        preg_match_all('/\bisa\b/u', $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] ?? [] as $match) {
            if (! $this->isNegatedMatch($text, $match[1])) {
                return true;
            }
        }

        return false;
    }

    private function isNegatedMatch(string $text, int $offset): bool
    {
        $prefix = substr($text, max(0, $offset - 40), min(40, $offset));

        return preg_match('/\b(?:not|never|isn[\x{2019}\x{0027}]?t|is\s+not)\s+(?:an?\s+)?(?:\w+\s+){0,2}$/u', $prefix) === 1;
    }

    private function containsNegation(string $text): bool
    {
        return preg_match('/\b(?:not|never|isn[\x{2019}\x{0027}]?t|is\s+not)\b/u', $text) === 1;
    }

    private function isCorrectionMatch(string $text, int $offset): bool
    {
        $prefix = substr($text, max(0, $offset - 50), min(50, $offset));

        return preg_match('/\b(?:actually|correction|rather|instead)\b[^.!?\n]{0,35}$/u', $prefix) === 1;
    }

    private function evidenceForEntity(string $text, array $arguments): string
    {
        $segments = preg_split(
            '/(?<=[.!?])\s+|\n+|\s*[;\x{2014}\x{2013}]\s*|,\s+(?=(?:actually|correction|rather|instead)\b)|,\s+(?=(?:mine\s+alone|just\s+me|only\s+me|individually|joint(?:ly)?|tenants?\s+in\s+common|held\s+in\s+trust)\b)|(?<!correction)(?<!actually)(?<!rather)(?<!instead),\s+(?=(?:(?:actually|correction|rather|instead)[,:]?\s+)?(?:my|our|the|another|a\s+second)\b)|\s+(?:and|but|while|whereas)\s+(?=(?:(?:actually|correction|rather|instead)\b|(?:my|our|the|another|a\s+second)\b))/u',
            $text
        ) ?: [];
        $strongNeedles = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? mb_strtolower(trim($value)) : '',
            [
                $arguments['account_name'] ?? null,
                $arguments['name'] ?? null,
                $arguments['address'] ?? null,
                $arguments['property_address'] ?? null,
                $arguments['address_line_1'] ?? null,
                $arguments['liability_name'] ?? null,
            ]
        ), static fn (string $value): bool => mb_strlen($value) >= 3));
        $weakNeedles = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? mb_strtolower(trim($value)) : '',
            [
                $arguments['provider'] ?? null,
                $arguments['institution'] ?? null,
                $arguments['platform'] ?? null,
                isset($arguments['property_type']) ? str_replace('_', ' ', (string) $arguments['property_type']) : null,
                $arguments['lender'] ?? null,
                $arguments['creditor'] ?? null,
                $arguments['lender_name'] ?? null,
            ]
        ), static fn (string $value): bool => mb_strlen($value) >= 3));

        $targetIndexes = [];
        foreach ($strongNeedles as $needle) {
            $indexes = $this->matchingSegmentIndexes($segments, $needle);
            if (count($indexes) === 1) {
                $targetIndexes[] = $indexes[0];
            }
        }

        $targetIndexes = array_values(array_unique($targetIndexes));
        if (count($targetIndexes) > 1) {
            return '';
        }

        if ($targetIndexes === []) {
            $weakMatches = [];
            foreach ($weakNeedles as $needle) {
                array_push($weakMatches, ...$this->matchingSegmentIndexes($segments, $needle));
            }
            $weakMatches = array_values(array_unique($weakMatches));
            if (count($weakMatches) > 1) {
                return '';
            }
            $targetIndexes = $weakMatches;
        }

        if ($targetIndexes === []) {
            $nonFactIndexes = array_keys(array_filter(
                $segments,
                fn (string $segment): bool => ! $this->isStandaloneEvidence($segment)
            ));

            return count($nonFactIndexes) <= 1 ? $text : '';
        }

        $targetIndex = $targetIndexes[0];
        $matched = [$segments[$targetIndex]];
        for ($index = $targetIndex + 1; $index < count($segments); $index++) {
            $isImmediateAnaphoricContinuation = $index === $targetIndex + 1
                && $this->isAnaphoricEvidenceContinuation($segments[$index]);
            if (! $this->isStandaloneEvidence($segments[$index]) && ! $isImmediateAnaphoricContinuation) {
                break;
            }
            $matched[] = $segments[$index];
        }

        return implode("\n", array_values(array_unique($matched)));
    }

    /**
     * @param  list<string>  $segments
     * @return list<int>
     */
    private function matchingSegmentIndexes(array $segments, string $needle): array
    {
        return array_values(array_keys(array_filter(
            $segments,
            static fn (string $segment): bool => str_contains($segment, $needle)
        )));
    }

    private function isStandaloneEvidence(string $segment): bool
    {
        $joined = preg_split('/\s+and\s+/u', trim($segment));
        if (is_array($joined) && count($joined) > 1) {
            return collect($joined)->every(fn (string $part): bool => $this->isStandaloneEvidence($part));
        }

        return preg_match('/^\s*(?:(?:actually|correction|rather|instead)[,:]?\s*)?(?:it\s+is\s+)?(?:not\s+)?(?:cash(?:\s+isa)?|junior(?:\s+isa)?|lifetime(?:\s+isa)?|innovative\s+finance(?:\s+isa)?|stocks?\s*(?:&|and)\s*shares?(?:\s+isa)?|solely|mine\s+alone|just\s+me|only\s+me|individually|joint(?:ly)?(?:\s+owned)?|tenants?\s+in\s+common|held\s+in\s+trust|(?:my\s+share\s+(?:(?:is|at)\s+)?)?\d{1,3}(?:\.\d+)?\s*%|(?:my\s+share\s+)?isn[\x{2019}\x{0027}]?t\s+half|(?:ownership\s+is\s+)?not\s+equal|half|equal(?:ly)?)\s*[.!?]*\s*$/u', $segment) === 1;
    }

    private function isAnaphoricEvidenceContinuation(string $segment): bool
    {
        if (preg_match('/\b(?:it|this\s+(?:account|isa|property|liability)|that\s+(?:account|isa|property|liability))\b/u', $segment) !== 1) {
            return false;
        }

        if (preg_match('/\b(?:my|our|another|a\s+second)\b[^.!?]{0,60}\b(?:account|isa|saver|property|mortgage|loan|liability)\b/u', $segment) === 1) {
            return false;
        }

        return $this->ownershipFromText($segment) !== null
            || $this->isaSubtypeFromText($segment) !== null
            || $this->ownershipShareFromText($segment) !== null;
    }
}
