<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Models\BusinessInterest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads statutory filing due dates from the Companies House Public Data API.
 *
 * One endpoint, two dates. GET /company/{number} returns the authoritative
 * next-accounts and next-confirmation-statement deadlines for a UK company —
 * the same figures shown on the public register. Nothing else it offers is
 * used here.
 *
 * Free API key: https://developer.company-information.service.gov.uk/
 * Auth is HTTP Basic with the key as username and a blank password.
 * Rate limit is 600 requests per five minutes, well clear of a daily sweep
 * over cached dates.
 *
 * Every failure path returns null. A company the user mistyped, a key that is
 * not provisioned, an API outage — none of those are the user's problem, and
 * none should break saving a business interest.
 */
class CompaniesHouseService
{
    private const BASE_URL = 'https://api.company-information.service.gov.uk';

    /**
     * Re-read dates older than this. Filing deadlines move rarely (a shortened
     * accounting reference date, a filed return rolling the date on a year), so
     * a weekly refresh is ample and keeps the daily sweep off the network.
     */
    public const STALE_AFTER_DAYS = 7;

    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    /**
     * Fetch the filing profile for a company number.
     *
     * @return array{company_number: string, company_name: ?string, company_status: ?string, accounts_due_on: ?string, confirmation_statement_due_on: ?string, accounts_overdue: bool, confirmation_statement_overdue: bool}|null
     */
    public function fetchFilingProfile(string $companyNumber): ?array
    {
        $number = self::normaliseNumber($companyNumber);

        if ($number === null || ! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withBasicAuth($this->key(), '')
                ->acceptJson()
                ->timeout(10)
                ->get(self::BASE_URL.'/company/'.$number);
        } catch (\Throwable $e) {
            Log::warning('Companies House lookup failed', [
                'company_number' => $number,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            // 404 is the ordinary case of a number that is not a company.
            if ($response->status() !== 404) {
                Log::warning('Companies House returned an error', [
                    'company_number' => $number,
                    'status' => $response->status(),
                ]);
            }

            return null;
        }

        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        $accounts = is_array($body['accounts'] ?? null) ? $body['accounts'] : [];
        $statement = is_array($body['confirmation_statement'] ?? null) ? $body['confirmation_statement'] : [];
        $nextAccounts = is_array($accounts['next_accounts'] ?? null) ? $accounts['next_accounts'] : [];

        return [
            'company_number' => $number,
            'company_name' => self::stringOrNull($body['company_name'] ?? null),
            'company_status' => self::stringOrNull($body['company_status'] ?? null),
            // next_accounts.due_on is the current field; accounts.next_due is the
            // deprecated twin, still populated, kept as the fallback.
            'accounts_due_on' => self::stringOrNull($nextAccounts['due_on'] ?? null)
                ?? self::stringOrNull($accounts['next_due'] ?? null),
            'confirmation_statement_due_on' => self::stringOrNull($statement['next_due'] ?? null),
            'accounts_overdue' => (bool) ($nextAccounts['overdue'] ?? $accounts['overdue'] ?? false),
            'confirmation_statement_overdue' => (bool) ($statement['overdue'] ?? false),
        ];
    }

    /**
     * Refresh a business interest's cached filing dates from the register.
     *
     * Returns the profile that was written, or null if nothing could be read —
     * in which case any previously cached dates are left untouched rather than
     * blanked, so a transient outage does not silence a live reminder.
     */
    public function sync(BusinessInterest $business): ?array
    {
        if (blank($business->company_number)) {
            return null;
        }

        $profile = $this->fetchFilingProfile($business->company_number);

        if ($profile === null) {
            return null;
        }

        $business->forceFill([
            'accounts_due_on' => $profile['accounts_due_on'],
            'confirmation_statement_due_on' => $profile['confirmation_statement_due_on'],
            'companies_house_synced_at' => now(),
        ])->save();

        return $profile;
    }

    /**
     * Normalise user input to a Companies House registration number.
     *
     * Numbers are eight characters: eight digits (England and Wales), or a
     * two-letter prefix plus six digits (SC/NI for Scotland and Northern
     * Ireland, OC/SO/NC for LLPs, and others). People routinely drop leading
     * zeros, so an all-digit input is left-padded.
     *
     * Returns null for anything that cannot be a company number — this value
     * is interpolated into an outbound URL, so it is validated, not trusted.
     */
    public static function normaliseNumber(?string $input): ?string
    {
        $candidate = strtoupper(preg_replace('/\s+/', '', (string) $input));

        if ($candidate === '') {
            return null;
        }

        if (ctype_digit($candidate) && strlen($candidate) <= 8) {
            $candidate = str_pad($candidate, 8, '0', STR_PAD_LEFT);
        }

        return preg_match('/^[A-Z]{2}\d{6}$|^\d{8}$/', $candidate) === 1
            ? $candidate
            : null;
    }

    private function key(): string
    {
        return trim((string) config('services.companies_house.key'));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
