<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Constants\PensionEnums;

class PensionNormaliser
{
    private const ALLOWED_DB_SCHEME_TYPES = ['final_salary', 'career_average', 'public_sector'];

    private const ALLOWED_DC_PENSION_TYPES = ['occupational', 'sipp', 'personal', 'stakeholder'];

    /**
     * Map whatever a caller calls a Defined Benefit scheme's status onto the one
     * stored vocabulary, `PensionEnums::SCHEME_STATUSES` (W-0032).
     *
     * Three writers reach `db_pensions` and they do not agree on presentation. The
     * web forms send the stored values (their title-case text is only a label).
     * Fyn's `create_pension` schema declares a title-case enum — "Active",
     * "Deferred", "In Payment" — and re-recording that schema to match would mean
     * re-recording its golden master, which is not worth doing for a mapping this
     * small. A document import can produce whatever the extractor read off a
     * statement. Normalising here — the layer every one of those paths already
     * passes through — is why there is one vocabulary rather than three.
     *
     * An unrecognised value returns null rather than a guess. Null is meaningful:
     * `DBPension::isInPayment()` falls back to age against the Normal Retirement
     * Age, which is the correct behaviour for a status nobody has stated.
     */
    /**
     * Shape a partial Defined Benefit field set — a correction rather than a whole
     * record — onto the stored vocabulary.
     *
     * `update_record` hands PensionStore a bare field list, so it does not pass
     * through any of the `from*` methods. Without this it would be the one write
     * path with a different idea of what "In Payment" means, which is the disease
     * Rule 20 exists to stop.
     */
    public function normaliseDbFields(array $fields): array
    {
        if (array_key_exists('scheme_status', $fields)) {
            $fields['scheme_status'] = $this->normaliseSchemeStatus($fields['scheme_status']);
        }

        return $fields;
    }

    private function normaliseSchemeStatus(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $candidate = str_replace([' ', '-'], '_', strtolower(trim($raw)));

        return in_array($candidate, PensionEnums::SCHEME_STATUSES, true) ? $candidate : null;
    }

    /**
     * Every `dc_pensions` column that is NOT NULL, carries a database default, AND
     * can be reached from `StoreDCPensionRequest`.
     *
     * A null for any of these must be DROPPED, never passed through: the column
     * cannot store it, and omitting the key is what lets the default apply. Same
     * rule, same reasoning and the same drift test as
     * `InvestmentAccountNormaliser::NOT_NULL_WITH_DEFAULT` (W-0052) and
     * `HoldingValuation::NOT_NULL_WITH_DEFAULT` (W-0261) — this is the third table
     * to need it.
     *
     * It became reachable here the moment W-0262 gave the six dropped fields their
     * validation rules: before that, `validated()` stripped them and the question
     * could not arise. The sweep in F-0023 §6.1 already listed
     * `dc_pensions.current_fund_value` as a column a form sends null for, so this
     * closes that latent 500 in the same pass rather than waiting for a user.
     *
     * @var list<string>
     */
    public const DC_NOT_NULL_WITH_DEFAULT = [
        'current_fund_value',
        'has_custom_risk',
        'has_flexibly_accessed',
        'pension_type',
        'platform_fee_frequency',
        'platform_fee_type',
    ];

    public function fromFormDc(array $request): array
    {
        $data = $request;
        $data['type'] = 'dc';
        $data['pension_type'] = $data['pension_type'] ?? 'occupational';
        $data['provider'] = $data['provider'] ?? ($data['scheme_name'] ?? null);

        // `has_custom_risk` is the flag every reader of the per-pension risk
        // override gates on — RetirementController:865, PensionProjector:291 and
        // PortfolioPresentationService:204 all test
        // `has_custom_risk && risk_preference`. No client has ever sent it: before
        // W-0262 the only writers in the entire codebase were the seeders, so for
        // every real user the flag sat at its column default of 0 and the override
        // was inert even where a risk_preference existed. Storing the preference
        // without this would have fixed the save and left the feature doing
        // nothing, which is the worse of the two failures because it looks fixed.
        //
        // Derived rather than asked for: choosing a level on the form IS the act of
        // overriding, so a second control saying "and mean it" would be a
        // mechanism the user has to operate to make the first one work. Only when
        // the key was sent — an edit that omits it leaves the stored flag alone,
        // the same discipline as `scheme_status` in fromFormDb below.
        if (array_key_exists('risk_preference', $data)) {
            $data['has_custom_risk'] = $data['risk_preference'] !== null && $data['risk_preference'] !== '';
        }

        // Runs LAST, so it also catches a null the derivation above did not set.
        foreach (self::DC_NOT_NULL_WITH_DEFAULT as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === null || $data[$field] === '')) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    public function fromFormDb(array $request): array
    {
        $data = $request;
        $data['type'] = 'db';
        $rawSchemeType = $data['scheme_type'] ?? 'final_salary';
        $data['scheme_type'] = in_array($rawSchemeType, self::ALLOWED_DB_SCHEME_TYPES, true)
            ? $rawSchemeType
            : 'final_salary';

        // Only when the key was sent: an edit that omits it must leave the stored
        // status alone rather than clearing it to null.
        if (array_key_exists('scheme_status', $data)) {
            $data['scheme_status'] = $this->normaliseSchemeStatus($data['scheme_status']);
        }

        return $data;
    }

    public function fromFormState(array $request): array
    {
        $data = $request;
        $data['type'] = 'state';

        return $data;
    }

    public function fromFynPension(array $toolParams): array
    {
        $category = $toolParams['pension_category'] ?? 'dc';

        if ($category === 'db') {
            $rawSchemeType = $toolParams['scheme_type'] ?? 'final_salary';
            $schemeType = in_array($rawSchemeType, self::ALLOWED_DB_SCHEME_TYPES, true)
                ? $rawSchemeType
                : 'final_salary';

            $canonical = [
                'type' => 'db',
                'scheme_name' => $toolParams['scheme_name'],
                'scheme_type' => $schemeType,
            ];

            foreach (['accrued_annual_pension', 'pensionable_service_years', 'pensionable_salary', 'spouse_pension_percent', 'lump_sum_entitlement'] as $f) {
                if (isset($toolParams[$f]) && is_numeric($toolParams[$f])) {
                    $canonical[$f] = (float) $toolParams[$f];
                }
            }
            if (isset($toolParams['normal_retirement_age']) && is_numeric($toolParams['normal_retirement_age'])) {
                $canonical['normal_retirement_age'] = (int) $toolParams['normal_retirement_age'];
            }
            foreach (['revaluation_method', 'inflation_protection'] as $f) {
                if (isset($toolParams[$f]) && $toolParams[$f] !== '') {
                    $canonical[$f] = $toolParams[$f];
                }
            }
            // Fyn's schema has always asked for this ("Active" / "Deferred" / "In
            // Payment") and the answer was discarded until W-0032 gave it a column.
            $schemeStatus = $this->normaliseSchemeStatus($toolParams['scheme_status'] ?? null);
            if ($schemeStatus !== null) {
                $canonical['scheme_status'] = $schemeStatus;
            }

            return $canonical;
        }

        // CSJ 2026-08-17: the default is ALWAYS a personal pension. It used to be
        // 'occupational', so an unstated or unrecognised scheme type silently
        // became a workplace pension — the 2026-08-17 "Sip" incident, where the
        // user said SIPP and got "Aviva workplace pension" recorded.
        //
        // Matched case-insensitively: the tool schema advertises lowercase values
        // but the model naturally emits "SIPP", and a case-sensitive match sent
        // every one of those to the default arm.
        $rawSchemeType = strtolower(trim((string) ($toolParams['scheme_type'] ?? '')));

        $pensionType = match ($rawSchemeType) {
            'workplace', 'occupational' => 'occupational',
            'sipp', 'self_invested', 'self-invested' => 'sipp',
            'stakeholder' => 'stakeholder',
            'personal', 'personal_pension' => 'personal',
            default => 'personal',
        };

        $canonical = [
            'type' => 'dc',
            'scheme_name' => $toolParams['scheme_name'],
            'pension_type' => $pensionType,
            'provider' => ! empty($toolParams['provider']) ? $toolParams['provider'] : $toolParams['scheme_name'],
        ];

        foreach (['current_fund_value', 'annual_salary', 'employee_contribution_percent', 'employer_contribution_percent', 'employer_matching_limit', 'monthly_contribution_amount', 'lump_sum_contribution', 'expected_return_percent', 'platform_fee_percent', 'advisor_fee_percent'] as $f) {
            if (isset($toolParams[$f]) && is_numeric($toolParams[$f])) {
                $canonical[$f] = (float) $toolParams[$f];
            }
        }
        if (isset($toolParams['retirement_age']) && is_numeric($toolParams['retirement_age'])) {
            $canonical['retirement_age'] = (int) $toolParams['retirement_age'];
        }
        foreach (['member_number', 'investment_strategy'] as $f) {
            if (isset($toolParams[$f]) && $toolParams[$f] !== '') {
                $canonical[$f] = $toolParams[$f];
            }
        }
        // false is a stated fact ("it's not salary sacrifice"), distinct
        // from absent (unknown) — dropping it persisted NULL and downstream
        // salary-sacrifice advice re-asked (live 2026-07-23, DCPension 175).
        if (isset($toolParams['salary_sacrifice']) && is_bool($toolParams['salary_sacrifice'])) {
            $canonical['salary_sacrifice'] = $toolParams['salary_sacrifice'];
        }

        return $canonical;
    }

    public function fromFynInputHistory(array $toolParams): array
    {
        $history = $toolParams['history'] ?? $toolParams;
        if (! is_array($history)) {
            return ['type' => 'pension_input_history', 'entries' => []];
        }

        $entries = [];
        foreach ($history as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $taxYear = isset($entry['tax_year']) ? (string) $entry['tax_year'] : null;
            $amount = isset($entry['pension_input_amount']) ? (float) $entry['pension_input_amount'] : null;
            if ($taxYear === null || $taxYear === '' || $amount === null || $amount < 0) {
                continue;
            }
            $entries[] = [
                'tax_year' => $taxYear,
                'pension_input_amount' => $amount,
            ];
        }

        return [
            'type' => 'pension_input_history',
            'entries' => $entries,
        ];
    }

    public function fromUploadDc(array $extraction): array
    {
        $canonical = [
            'type' => 'dc',
            'scheme_name' => $extraction['scheme_name'] ?? $extraction['provider'] ?? 'Imported pension',
            'pension_type' => $extraction['pension_type'] ?? 'occupational',
            'provider' => $extraction['provider'] ?? ($extraction['scheme_name'] ?? null),
            'current_fund_value' => (float) ($extraction['current_fund_value'] ?? 0),
        ];

        foreach (['annual_salary', 'employee_contribution_percent', 'employer_contribution_percent', 'monthly_contribution_amount', 'retirement_age', 'member_number', 'investment_strategy', 'platform_fee_percent'] as $optional) {
            if (array_key_exists($optional, $extraction)) {
                $canonical[$optional] = $extraction[$optional];
            }
        }

        return $canonical;
    }

    public function fromUploadDb(array $extraction): array
    {
        $rawSchemeType = $extraction['scheme_type'] ?? 'final_salary';
        $schemeType = in_array($rawSchemeType, self::ALLOWED_DB_SCHEME_TYPES, true)
            ? $rawSchemeType
            : 'final_salary';

        $canonical = [
            'type' => 'db',
            'scheme_name' => $extraction['scheme_name'] ?? 'Imported DB pension',
            'scheme_type' => $schemeType,
        ];

        foreach (['accrued_annual_pension', 'pensionable_service_years', 'pensionable_salary', 'normal_retirement_age', 'spouse_pension_percent', 'lump_sum_entitlement', 'inflation_protection'] as $optional) {
            if (array_key_exists($optional, $extraction)) {
                $canonical[$optional] = $extraction[$optional];
            }
        }

        $schemeStatus = $this->normaliseSchemeStatus($extraction['scheme_status'] ?? null);
        if ($schemeStatus !== null) {
            $canonical['scheme_status'] = $schemeStatus;
        }

        return $canonical;
    }
}
