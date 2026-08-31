<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\LastingPowerOfAttorney;
use Carbon\Carbon;

/**
 * Runs a fixed list of checks over the Lasting Power of Attorney details a user
 * has entered, and reports what each check found.
 *
 * It does NOT determine whether the instrument is compliant, valid or
 * sufficient, and no caller may present its result as if it did. What Fynla is
 * entitled to say — and the disclosure that must accompany it — lives in
 * `LpaCheckPolicy`, which is the one home for that wording (Rule 20). Before
 * 2026-08-21 this method returned the literal string 'compliant' and the web
 * checklist rendered it in the success colour; see W-0100 and the docblock on
 * `LpaCheckPolicy` for why that was wrong on grounds independent of how good
 * these checks are.
 *
 * The checks run against stored form data. They cannot observe the donor's
 * capacity at signing, whether the certificate provider gave the certificate
 * required by Mental Capacity Act 2005 Sch 1 para 2(1)(e), how the instrument
 * was executed, or whether the Public Guardian has registered it.
 */
class LpaComplianceService
{
    /**
     * Run every check for a Lasting Power of Attorney and return what they
     * found, together with the disclosure `LpaCheckPolicy` requires alongside
     * it. There is deliberately no key asserting an overall status of the
     * instrument.
     *
     * @return array{
     *     checks: array,
     *     passed: int,
     *     failed: int,
     *     warnings: int,
     *     outcome: string,
     *     outcome_label: string,
     *     heading: string,
     *     not_checked_heading: string,
     *     not_checked_intro: string,
     *     not_checked: list<string>,
     *     not_checked_close: string,
     *     referral: string
     * }
     */
    public function checkCompliance(LastingPowerOfAttorney $lpa): array
    {
        $lpa->load(['attorneys', 'notificationPersons']);

        $checks = [
            $this->checkDonorAge($lpa),
            $this->checkAttorneyAges($lpa),
            $this->checkAttorneyBankruptcy($lpa),
            $this->checkAtLeastOneAttorney($lpa),
            $this->checkDecisionType($lpa),
            $this->checkCertificateProvider($lpa),
            $this->checkCertificateProviderKnownYears($lpa),
            $this->checkNotificationPersonLimit($lpa),
            $this->checkReplacementAttorneys($lpa),
            $this->checkRegistrationStatus($lpa),
            // Returns a list, not a single result — see checkPartyRoles().
            ...$this->checkPartyRoles($lpa),
        ];

        // Type-specific checks
        if ($lpa->isPropertyFinancial()) {
            $checks[] = $this->checkWhenAttorneysCanAct($lpa);
        }

        if ($lpa->isHealthWelfare()) {
            $checks[] = $this->checkLifeSustainingTreatment($lpa);
        }

        $passed = count(array_filter($checks, fn (array $c): bool => $c['status'] === 'pass'));
        $failed = count(array_filter($checks, fn (array $c): bool => $c['status'] === 'fail'));
        $warnings = count(array_filter($checks, fn (array $c): bool => $c['status'] === 'warning'));

        return [
            'checks' => $checks,
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            ...LpaCheckPolicy::payload($failed, $warnings),
        ];
    }

    /**
     * Check 1: Donor must be 18 or older.
     */
    private function checkDonorAge(LastingPowerOfAttorney $lpa): array
    {
        if (! $lpa->donor_date_of_birth) {
            return $this->result(
                'donor_age',
                'fail',
                'Donor date of birth is required',
                'The donor must be 18 or older at the time of creating the Lasting Power of Attorney.'
            );
        }

        $age = Carbon::parse($lpa->donor_date_of_birth)->age;

        if ($age < 18) {
            return $this->result(
                'donor_age',
                'fail',
                'Donor must be 18 or older',
                'The donor is currently '.$age.' years old. They must be at least 18 to create a Lasting Power of Attorney.'
            );
        }

        return $this->result(
            'donor_age',
            'pass',
            'Donor is 18 or older',
            'The donor is '.$age.' years old and meets the minimum age requirement.'
        );
    }

    /**
     * Every attorney must be 18 or older — Mental Capacity Act 2005 s10(1)(a).
     *
     * **W-0104.** The donor's age was checked and the attorneys' was not, though
     * `lpa_attorneys.date_of_birth` is captured for every one of them. **A child
     * could be appointed attorney**, and the instrument would have been presented
     * to the user as compliant right up to the point the Office of the Public
     * Guardian refused to register it.
     *
     * The same statute sets both ages, which is why the omission is easy to miss:
     * the donor check reads as though it covers "the age requirement".
     *
     * A missing date of birth FAILS rather than passing quietly. An attorney whose
     * age cannot be established is exactly the case this check exists for, and
     * treating unknown as acceptable would reproduce the defect for anyone who
     * left the field blank.
     */
    private function checkAttorneyAges(LastingPowerOfAttorney $lpa): array
    {
        $attorneys = $lpa->attorneys;

        if ($attorneys->isEmpty()) {
            // Nothing to judge. `checkAtLeastOneAttorney()` owns the "none
            // appointed" failure; reporting it twice would double-count it.
            return $this->result(
                'attorney_ages',
                'pass',
                'No attorneys to check',
                'Attorney ages will be checked once an attorney is appointed.'
            );
        }

        $undated = $attorneys->filter(fn ($attorney): bool => ! $attorney->date_of_birth);

        if ($undated->isNotEmpty()) {
            return $this->result(
                'attorney_ages',
                'fail',
                'Attorney date of birth is required',
                'A date of birth is missing for '.$undated->pluck('full_name')->filter()->implode(', ')
                    .'. Every attorney must be 18 or older, and that cannot be confirmed without it.'
            );
        }

        $underage = $attorneys->filter(
            fn ($attorney): bool => Carbon::parse($attorney->date_of_birth)->age < 18
        );

        if ($underage->isNotEmpty()) {
            return $this->result(
                'attorney_ages',
                'fail',
                'Every attorney must be 18 or older',
                $underage->pluck('full_name')->filter()->implode(', ')
                    .' is under 18. An attorney must be at least 18 when the Lasting Power of Attorney is made.'
            );
        }

        return $this->result(
            'attorney_ages',
            'pass',
            'Every attorney is 18 or older',
            'All '.$attorneys->count().' appointed attorneys meet the minimum age requirement.'
        );
    }

    /**
     * A bankrupt attorney cannot act on a property and financial affairs LPA.
     *
     * **W-0105.** Mental Capacity Act 2005 s13(8)-(9). The question was never
     * asked at all: there was no column, no field and no check, so an instrument
     * naming a bankrupt attorney was presented as compliant and would have been
     * refused registration by the Office of the Public Guardian.
     *
     * **Type-dependent, which is why a blanket bar would have been wrong.** The
     * disqualification applies to PROPERTY AND FINANCIAL AFFAIRS only — a
     * bankrupt person may perfectly well act as attorney for health and welfare,
     * and refusing them there would invent a restriction the statute does not
     * impose.
     *
     * An unanswered question is reported as a WARNING, not a failure. The donor
     * may simply not have been asked yet, and the application has only just begun
     * asking; treating silence as a breach would fail every instrument created
     * before this field existed.
     */
    private function checkAttorneyBankruptcy(LastingPowerOfAttorney $lpa): array
    {
        if ($lpa->lpa_type !== 'property_financial') {
            return $this->result(
                'attorney_bankruptcy',
                'pass',
                'Bankruptcy does not disqualify a health and welfare attorney',
                'The bankruptcy restriction in s13(8) applies to property and financial affairs only.'
            );
        }

        $attorneys = $lpa->attorneys;

        if ($attorneys->isEmpty()) {
            return $this->result(
                'attorney_bankruptcy',
                'pass',
                'No attorneys to check',
                'Bankruptcy will be checked once an attorney is appointed.'
            );
        }

        $bankrupt = $attorneys->filter(fn ($attorney): bool => $attorney->is_bankrupt === true);

        if ($bankrupt->isNotEmpty()) {
            return $this->result(
                'attorney_bankruptcy',
                'fail',
                'A bankrupt attorney cannot manage property and financial affairs',
                $bankrupt->pluck('full_name')->filter()->implode(', ')
                    .' is recorded as bankrupt. Under the Mental Capacity Act 2005 they cannot act as attorney '
                    .'for property and financial affairs, and this Lasting Power of Attorney cannot be registered '
                    .'while they are named.'
            );
        }

        $unanswered = $attorneys->filter(fn ($attorney): bool => $attorney->is_bankrupt === null);

        if ($unanswered->isNotEmpty()) {
            return $this->result(
                'attorney_bankruptcy',
                'warning',
                'Bankruptcy has not been confirmed for every attorney',
                'Confirm whether '.$unanswered->pluck('full_name')->filter()->implode(', ')
                    .' has been made bankrupt. A bankrupt attorney cannot act for property and financial affairs.'
            );
        }

        return $this->result(
            'attorney_bankruptcy',
            'pass',
            'No attorney is bankrupt',
            'Every appointed attorney is confirmed as not bankrupt.'
        );
    }

    /**
     * Check 2: At least one primary attorney must be appointed.
     */
    private function checkAtLeastOneAttorney(LastingPowerOfAttorney $lpa): array
    {
        $primaryCount = $lpa->attorneys->where('attorney_type', 'primary')->count();

        if ($primaryCount === 0) {
            return $this->result(
                'attorney_count',
                'fail',
                'At least one attorney is required',
                'You must appoint at least one primary attorney to act on your behalf.'
            );
        }

        return $this->result(
            'attorney_count',
            'pass',
            $primaryCount === 1
                ? '1 primary attorney appointed'
                : $primaryCount.' primary attorneys appointed',
            'The required minimum of one primary attorney has been met.'
        );
    }

    /**
     * Check 3: Decision type required if 2+ primary attorneys.
     */
    private function checkDecisionType(LastingPowerOfAttorney $lpa): array
    {
        $primaryCount = $lpa->attorneys->where('attorney_type', 'primary')->count();

        if ($primaryCount < 2) {
            return $this->result(
                'decision_type',
                'pass',
                'Decision type not required (single attorney)',
                'With only one primary attorney, a decision type is not needed.'
            );
        }

        if (! $lpa->attorney_decision_type) {
            return $this->result(
                'decision_type',
                'fail',
                'Decision type required for multiple attorneys',
                'You must specify how your '.$primaryCount.' primary attorneys should make decisions: jointly, jointly and severally, or jointly for some decisions.'
            );
        }

        if ($lpa->attorney_decision_type === 'jointly_for_some' && empty($lpa->jointly_for_some_details)) {
            return $this->result(
                'decision_type',
                'fail',
                'Details required for "jointly for some decisions"',
                'You must specify which decisions require all attorneys to agree and which can be made individually.'
            );
        }

        $typeLabels = [
            'jointly' => 'Jointly (all must agree)',
            'jointly_and_severally' => 'Jointly and severally (together or independently)',
            'jointly_for_some' => 'Jointly for some decisions, severally for others',
        ];

        return $this->result(
            'decision_type',
            'pass',
            'Decision type set: '.($typeLabels[$lpa->attorney_decision_type] ?? $lpa->attorney_decision_type),
            'The decision-making arrangement for your attorneys has been specified.'
        );
    }

    /**
     * Check 4: Certificate provider must be named.
     */
    private function checkCertificateProvider(LastingPowerOfAttorney $lpa): array
    {
        if (empty($lpa->certificate_provider_name)) {
            return $this->result(
                'certificate_provider',
                'fail',
                'Certificate provider is required',
                'A certificate provider must confirm that you understand the Lasting Power of Attorney and are not under pressure to create it.'
            );
        }

        return $this->result(
            'certificate_provider',
            'pass',
            'Certificate provider named: '.$lpa->certificate_provider_name,
            'A certificate provider has been appointed.'
        );
    }

    /**
     * Check 5: Certificate provider must have known donor for 2+ years.
     */
    private function checkCertificateProviderKnownYears(LastingPowerOfAttorney $lpa): array
    {
        if (empty($lpa->certificate_provider_name)) {
            return $this->result(
                'certificate_provider_years',
                'fail',
                'Certificate provider details incomplete',
                'A certificate provider must be named before the relationship duration can be checked.'
            );
        }

        if ($lpa->certificate_provider_known_years === null) {
            return $this->result(
                'certificate_provider_years',
                'warning',
                'Years known not specified',
                'Please confirm how long the certificate provider has known you. They must have known you for at least 2 years.'
            );
        }

        if ($lpa->certificate_provider_known_years < 2) {
            return $this->result(
                'certificate_provider_years',
                'fail',
                'Certificate provider must have known you for at least 2 years',
                'Your certificate provider has known you for '.$lpa->certificate_provider_known_years.' year(s). The minimum is 2 years.'
            );
        }

        return $this->result(
            'certificate_provider_years',
            'pass',
            'Certificate provider has known you for '.$lpa->certificate_provider_known_years.' years',
            'The minimum 2-year relationship requirement is met.'
        );
    }

    /**
     * Check 6: Maximum 5 notification persons.
     */
    private function checkNotificationPersonLimit(LastingPowerOfAttorney $lpa): array
    {
        $count = $lpa->notificationPersons->count();

        if ($count > 5) {
            return $this->result(
                'notification_limit',
                'fail',
                'Too many people to notify (maximum 5)',
                'You have '.$count.' people to notify. The maximum allowed is 5.'
            );
        }

        if ($count === 0) {
            return $this->result(
                'notification_limit',
                'warning',
                'No people to notify (optional)',
                'You have not listed anyone to be notified when the Lasting Power of Attorney is registered. While optional, it provides an additional safeguard.'
            );
        }

        return $this->result(
            'notification_limit',
            'pass',
            $count.' '.($count === 1 ? 'person' : 'people').' to notify',
            'Notification persons are within the allowed limit of 5.'
        );
    }

    /**
     * Check 7: Replacement attorneys recommended.
     */
    private function checkReplacementAttorneys(LastingPowerOfAttorney $lpa): array
    {
        $replacementCount = $lpa->attorneys->where('attorney_type', 'replacement')->count();

        if ($replacementCount === 0) {
            return $this->result(
                'replacement_attorneys',
                'warning',
                'No replacement attorneys (recommended)',
                'Appointing replacement attorneys is recommended in case your primary attorneys can no longer act. Without replacements, the Lasting Power of Attorney may become invalid if all primary attorneys are unable to serve.'
            );
        }

        return $this->result(
            'replacement_attorneys',
            'pass',
            $replacementCount.' replacement '.($replacementCount === 1 ? 'attorney' : 'attorneys').' appointed',
            'Replacement attorneys have been appointed as a safeguard.'
        );
    }

    /**
     * Check 8: Registration status.
     */
    private function checkRegistrationStatus(LastingPowerOfAttorney $lpa): array
    {
        if ($lpa->is_registered_with_opg) {
            return $this->result(
                'registration',
                'pass',
                'Registered with the Office of the Public Guardian',
                'This Lasting Power of Attorney has been registered and can be used when needed.'
                .($lpa->opg_reference ? ' Reference: '.$lpa->opg_reference : '')
            );
        }

        if ($lpa->status === 'draft') {
            return $this->result(
                'registration',
                'warning',
                'Not yet registered (currently in draft)',
                'A Lasting Power of Attorney must be registered with the Office of the Public Guardian before it can be used. Registration takes up to 8 weeks and costs £82.'
            );
        }

        return $this->result(
            'registration',
            'warning',
            'Not yet registered with the Office of the Public Guardian',
            'This Lasting Power of Attorney should be registered before it is needed. Registration takes up to 8 weeks and costs £82.'
        );
    }

    /**
     * Check 9 (Property only): When attorneys can act must be specified.
     */
    private function checkWhenAttorneysCanAct(LastingPowerOfAttorney $lpa): array
    {
        if (! $lpa->when_attorneys_can_act) {
            return $this->result(
                'when_can_act',
                'fail',
                'Specify when attorneys can act',
                'For a Property & Financial Affairs Lasting Power of Attorney, you must choose whether your attorneys can act while you still have mental capacity or only when you have lost capacity.'
            );
        }

        $label = $lpa->when_attorneys_can_act === 'while_has_capacity'
            ? 'While the donor still has mental capacity'
            : 'Only when the donor has lost mental capacity';

        return $this->result(
            'when_can_act',
            'pass',
            'Attorneys can act: '.$label,
            'The timing of when attorneys can act has been specified.'
        );
    }

    /**
     * Check 10 (Health only): Life-sustaining treatment decision.
     */
    private function checkLifeSustainingTreatment(LastingPowerOfAttorney $lpa): array
    {
        if (! $lpa->life_sustaining_treatment) {
            return $this->result(
                'life_sustaining',
                'fail',
                'Life-sustaining treatment decision required',
                'For a Health & Welfare Lasting Power of Attorney, you must decide whether your attorneys can give or refuse consent to life-sustaining treatment on your behalf.'
            );
        }

        $label = $lpa->life_sustaining_treatment === 'can_consent'
            ? 'Attorneys can give or refuse consent'
            : 'Attorneys cannot give or refuse consent';

        return $this->result(
            'life_sustaining',
            'pass',
            'Life-sustaining treatment: '.$label,
            'The life-sustaining treatment decision has been recorded.'
        );
    }

    /**
     * The party-role check — W-0102, W-0103 and W-0151, one mechanism.
     *
     * Nothing compared the names in any two roles until 2026-08-21, so the same
     * person could be the certificate provider and an attorney: the generated
     * document printed them as "Attorney 1" and again as the certifier of the
     * donor's understanding and of the absence of fraud or undue pressure. That is
     * the W-0024 defect shape with a statute behind it rather than an inference.
     *
     * **Name comparison routes to `WillDocumentService::isSameParty()`.** That is
     * already the one home for "do these two free-text names refer to the same
     * person", used by the mirror-will swap, the executor-is-testator block and
     * Fyn's create_will handler. It is deliberately case-and-whitespace only —
     * two people can share a name, and guessing at nicknames in a legal document
     * would be worse than the bug. Reusing it keeps one behaviour rather than two;
     * the limit it imposes is disclosed once in `LpaCheckPolicy::NOT_CHECKED`
     * rather than hedged into each message below.
     *
     * **Two statuses, and the split is the ruling.** The certificate-provider limbs
     * `fail` because an instrument prohibits them — MCA 2005 Sch 1 para 2(6) and
     * SI 2007/1253 reg 8(3)(b), (c). The rest `warning`, because compliance went
     * looking for an express prohibition on a donor naming themselves and **did not
     * find one**: s.10(1) says what a donee must be without excluding the donor,
     * and reg 8(3)'s eight disqualifications reach the donor's family but not the
     * donor. Reporting those as failures would assert a rule that may not exist —
     * the same overclaim as the badge W-0100 removed, pointing the other way. They
     * are contradictions in what the user typed, and are worded as exactly that.
     *
     * Every string below is compliance-lead's, verbatim (provisional ruling,
     * 2026-08-21, on W-0102/W-0103/W-0151). Do not paraphrase them. In particular
     * the pass title is **"The names in each role are different"** and never "no
     * conflict found": a string comparison that finds no match is not evidence that
     * no conflict exists, and the pass must not be written as though it were.
     *
     * @return list<array<string, string>>
     */
    private function checkPartyRoles(LastingPowerOfAttorney $lpa): array
    {
        $conflicts = [];
        $donor = (string) ($lpa->donor_full_name ?? '');
        $certificateProvider = (string) ($lpa->certificate_provider_name ?? '');
        $primary = $lpa->attorneys->where('attorney_type', 'primary');
        $replacements = $lpa->attorneys->where('attorney_type', 'replacement');

        // W-0102 — MCA 2005 Sch 1 para 2(6) and SI 2007/1253 reg 8(3)(b).
        foreach ($lpa->attorneys as $attorney) {
            if (WillDocumentService::isSameParty($certificateProvider, (string) $attorney->full_name)) {
                $conflicts[] = $this->result(
                    'party_roles_certificate_provider_attorney',
                    'fail',
                    'Your certificate provider is also named as an attorney',
                    'You entered '.$certificateProvider.' as your certificate provider and as an attorney. '
                    .'The Mental Capacity Act 2005 does not allow the certificate to be given by someone appointed '
                    .'as an attorney (Schedule 1, paragraph 2(6)), and the 2007 regulations disqualify an attorney '
                    .'from giving it (regulation 8(3)(b)). Check which person you meant in each role.'
                );
                break;
            }
        }

        // W-0151 — reg 8(3)(c), a donee of any OTHER power of attorney the same
        // donor made. The one disqualification beyond the attorney limb that Fynla
        // holds the data to detect, because it keeps both instrument types per user.
        foreach ($this->otherInstrumentAttorneyNames($lpa) as $name) {
            if (WillDocumentService::isSameParty($certificateProvider, $name)) {
                $conflicts[] = $this->result(
                    'party_roles_certificate_provider_other_instrument',
                    'fail',
                    'Your certificate provider is an attorney on your other Lasting Power of Attorney',
                    'You entered '.$certificateProvider.' as your certificate provider, and the same name is an '
                    .'attorney on the other Lasting Power of Attorney you have recorded. The 2007 regulations '
                    .'disqualify an attorney under any other power of attorney you have made from giving the '
                    .'certificate (regulation 8(3)(c)). Check which person you meant in each role.'
                );
                break;
            }
        }

        // W-0103 conflict 1 — described, not prohibited.
        foreach ($lpa->attorneys as $attorney) {
            if (WillDocumentService::isSameParty($donor, (string) $attorney->full_name)) {
                $conflicts[] = $this->result(
                    'party_roles_donor_attorney',
                    'warning',
                    'You are named as your own attorney',
                    'A Lasting Power of Attorney is the record of one person giving another the authority to act '
                    .'for them (Mental Capacity Act 2005, section 9(1)), so naming yourself as your own attorney '
                    .'is a contradiction Fynla cannot resolve for you. Check who you meant.'
                );
                break;
            }
        }

        // W-0103 conflict 2 — described, not prohibited. The citation supports what
        // the certificate IS, on the face of para 2(1)(e). It is deliberately not
        // offered as authority that the donor is disqualified.
        if (WillDocumentService::isSameParty($donor, $certificateProvider)) {
            $conflicts[] = $this->result(
                'party_roles_donor_certificate_provider',
                'warning',
                'You are named as your own certificate provider',
                'The certificate is a statement by someone else that you understand this document and are not '
                .'under pressure to make it (Mental Capacity Act 2005, Schedule 1, paragraph 2(1)(e)). Naming '
                .'yourself is a contradiction Fynla cannot resolve for you. Check who you meant.'
            );
        }

        // W-0103 conflict 3 — both halves: one person in two attorney roles, and one
        // person entered twice in the same list.
        foreach ($primary as $attorney) {
            foreach ($replacements as $replacement) {
                if (WillDocumentService::isSameParty((string) $attorney->full_name, (string) $replacement->full_name)) {
                    $conflicts[] = $this->result(
                        'party_roles_attorney_and_replacement',
                        'warning',
                        $attorney->full_name.' is named as both an attorney and a replacement attorney',
                        'A replacement attorney steps in if an attorney can no longer act, so someone in both '
                        .'roles would be replacing themselves. Check which role you meant.'
                    );
                    break 2;
                }
            }
        }

        foreach ([$primary, $replacements] as $group) {
            $names = $group->pluck('full_name')->all();
            foreach ($names as $index => $name) {
                foreach (array_slice($names, $index + 1) as $other) {
                    if (WillDocumentService::isSameParty((string) $name, (string) $other)) {
                        $conflicts[] = $this->result(
                            'party_roles_duplicate_attorney',
                            'warning',
                            $name.' is named twice',
                            'You entered '.$name.' more than once in your list of attorneys. '
                            .'Check whether you meant two different people.'
                        );
                        break 3;
                    }
                }
            }
        }

        if ($conflicts !== []) {
            return $conflicts;
        }

        return [$this->result(
            'party_roles',
            'pass',
            'The names in each role are different',
            'The certificate provider and attorney names you entered do not match each other.'
        )];
    }

    /**
     * Attorney names on every OTHER Lasting Power of Attorney this donor has
     * recorded. reg 8(3)(c) is about any other power of attorney executed by the
     * same donor, and Fynla holds one row per instrument type per user.
     *
     * @return list<string>
     */
    private function otherInstrumentAttorneyNames(LastingPowerOfAttorney $lpa): array
    {
        if ($lpa->user_id === null) {
            return [];
        }

        return LastingPowerOfAttorney::forUser((int) $lpa->user_id)
            ->where('id', '!=', $lpa->id)
            ->with('attorneys')
            ->get()
            ->flatMap(fn (LastingPowerOfAttorney $other): array => $other->attorneys->pluck('full_name')->all())
            ->filter()
            ->map(fn ($name): string => (string) $name)
            ->values()
            ->all();
    }

    /**
     * Build a structured check result.
     */
    private function result(string $key, string $status, string $title, string $description): array
    {
        return [
            'key' => $key,
            'status' => $status,
            'title' => $title,
            'description' => $description,
        ];
    }
}
