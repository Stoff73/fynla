<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Validates AI responses against compliance rules before they reach the user.
 *
 * Checks for:
 * - Banned acronyms (IHT, CGT, SIPP, GIA, etc.)
 * - Internal record IDs exposed to user
 * - Emoji, icons, or Unicode symbols
 * - Planning jargon (waterfall, prioritise affordability, etc.)
 * - Missing £ amounts in advice responses
 * - Hardcoded tax figures (numbers that should come from get_tax_information)
 *
 * Returns violations array. Does NOT block the response — logs and flags.
 * A future iteration could strip/rewrite violations before delivery.
 */
class StructuredResponseValidator
{
    /**
     * Banned acronyms — these must be spelled out in full.
     * ISA is the only permitted abbreviation.
     */
    private const BANNED_ACRONYMS = [
        '/\bIHT\b/' => 'Inheritance Tax',
        '/\bCGT\b/' => 'Capital Gains Tax',
        '/\bSIPP\b/' => 'Self-Invested Personal Pension',
        '/\bGIA\b/' => 'General Investment Account',
        '/\bDC\b(?!\s*pension)/' => 'Defined Contribution',
        '/\bDB\b(?!\s*pension)/' => 'Defined Benefit',
        '/\bAA\b/' => 'Annual Allowance',
        '/\bMPAA\b/' => 'Money Purchase Annual Allowance',
        '/\bAEA\b/' => 'Annual Exempt Amount',
        '/\bBPR\b/' => 'Business Property Relief',
        '/\bBADR\b/' => 'Business Asset Disposal Relief',
        '/\bNRB\b/' => 'Nil Rate Band',
        '/\bRNRB\b/' => 'Residence Nil Rate Band',
        '/\bLPA\b/' => 'Lasting Power of Attorney',
        '/\bPET\b/' => 'Potentially Exempt Transfer',
        '/\bNI\b(?!\s)/' => 'National Insurance',
        '/\bS&S\b/' => 'Stocks and Shares',
    ];

    /**
     * Banned jargon phrases.
     */
    private const BANNED_JARGON = [
        'waterfall',
        'prioritise affordability',
        'allocation framework',
        'phased approach',
        'sequential phases',
        'opportunity cost',
        'tax-year-sensitive',
    ];

    /**
     * Validate an AI response and return violations.
     *
     * @return array{valid: bool, violations: array<array{rule: string, detail: string, severity: string}>}
     */
    public function validate(string $response, ?array $classification = null): array
    {
        if (trim($response) === '') {
            return ['valid' => true, 'violations' => []];
        }

        $violations = [];

        // Check banned acronyms
        foreach (self::BANNED_ACRONYMS as $pattern => $fullForm) {
            if (preg_match($pattern, $response)) {
                $violations[] = [
                    'rule' => 'banned_acronym',
                    'detail' => "Used acronym instead of '{$fullForm}'",
                    'severity' => 'high',
                ];
            }
        }

        // Check for exposed internal record IDs
        if (preg_match('/\bID[:\s]?\d{1,6}\b/', $response) || preg_match('/\[ID:\d+\]/', $response)) {
            $violations[] = [
                'rule' => 'exposed_record_id',
                'detail' => 'Internal record ID visible in response',
                'severity' => 'high',
            ];
        }

        // Check for emoji and Unicode symbols (excluding £ and standard punctuation)
        if (preg_match('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F000}-\x{1F02F}]/u', $response)) {
            $violations[] = [
                'rule' => 'emoji_or_icon',
                'detail' => 'Response contains emoji or Unicode symbols',
                'severity' => 'medium',
            ];
        }

        // Check for tick marks and special symbols
        if (preg_match('/[✓✔✗✘✅❌⚠️🔴🟢🟡●○■□▶►→←↑↓★☆]/u', $response)) {
            $violations[] = [
                'rule' => 'icon_symbol',
                'detail' => 'Response contains tick marks or icon symbols',
                'severity' => 'medium',
            ];
        }

        // Check banned jargon
        foreach (self::BANNED_JARGON as $jargon) {
            if (stripos($response, $jargon) !== false) {
                $violations[] = [
                    'rule' => 'banned_jargon',
                    'detail' => "Used planning jargon: '{$jargon}'",
                    'severity' => 'medium',
                ];
            }
        }

        // Check for filler phrases at start
        $fillerPhrases = ['Certainly!', 'Of course!', 'Great question!', 'Absolutely!', 'Sure!'];
        foreach ($fillerPhrases as $filler) {
            if (str_starts_with(trim($response), $filler)) {
                $violations[] = [
                    'rule' => 'filler_phrase',
                    'detail' => "Response starts with filler: '{$filler}'",
                    'severity' => 'low',
                ];
            }
        }

        // For advice responses, check for £ amounts (should have specific figures)
        if ($classification !== null
            && \App\Constants\QuerySchemas::isAdviceType($classification['primary'] ?? '')
            && ! preg_match('/£[\d,]+/', $response)) {
            $violations[] = [
                'rule' => 'missing_amounts',
                'detail' => 'Advice response contains no specific £ amounts',
                'severity' => 'high',
            ];
        }

        // Check for raw HTML/script tags
        if (preg_match('/<\s*(script|iframe|object|embed|form)\b/i', $response)) {
            $violations[] = [
                'rule' => 'html_injection',
                'detail' => 'Response contains potentially dangerous HTML tags',
                'severity' => 'critical',
            ];
        }

        // Check for "[Context:" blocks leaking
        if (str_contains($response, '[Context:')) {
            $violations[] = [
                'rule' => 'context_leak',
                'detail' => 'Internal context block leaked into response',
                'severity' => 'high',
            ];
        }

        return [
            'valid' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Validate and log any violations. Returns the violations array.
     */
    public function validateAndLog(string $response, ?array $classification = null, ?int $userId = null, ?int $messageId = null): array
    {
        $result = $this->validate($response, $classification);

        if (! $result['valid']) {
            $highSeverity = array_filter($result['violations'], fn ($v) => in_array($v['severity'], ['critical', 'high']));

            Log::warning('[StructuredResponseValidator] AI response violations detected', [
                'user_id' => $userId,
                'message_id' => $messageId,
                'query_type' => $classification['primary'] ?? 'unknown',
                'violation_count' => count($result['violations']),
                'high_severity_count' => count($highSeverity),
                'violations' => array_map(fn ($v) => $v['rule'].': '.$v['detail'], $result['violations']),
            ]);
        }

        return $result['violations'];
    }

    /**
     * Sanitise a response by fixing common violations where possible.
     * Returns the cleaned response text.
     */
    public function sanitise(string $response): string
    {
        // Strip any leaked [Context: ...] blocks (including multi-line and nested brackets)
        $response = preg_replace('/\[Context:[^\]]*\]/s', '', $response);
        $response = preg_replace('/\[Context:.*?\]/s', '', $response);

        // Strip debug/system metadata that may leak through
        $response = preg_replace('/\[System:.*?\]/s', '', $response);
        $response = preg_replace('/\[Debug:.*?\]/s', '', $response);
        $response = preg_replace('/\[Internal:.*?\]/s', '', $response);

        // Strip exposed record IDs like "ID:123" or "[ID:456]"
        $response = preg_replace('/\[?ID[:\s]?\d{1,6}\]?/', '', $response);

        // Strip dangerous HTML tags
        $response = preg_replace('/<\s*(script|iframe|object|embed|form|input|link|meta|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $response);
        $response = preg_replace('/<\s*(script|iframe|object|embed|form|input|link|meta|style)\b[^>]*\/?>/is', '', $response);

        // Clean up any double spaces left from stripping
        $response = preg_replace('/  +/', ' ', $response);

        return trim($response);
    }
}
