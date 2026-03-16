<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\LastingPowerOfAttorney;
use Carbon\Carbon;

/**
 * Compliance checks for Lasting Powers of Attorney against UK law
 * (Mental Capacity Act 2005).
 */
class LpaComplianceService
{
    /**
     * Run all compliance checks for an LPA.
     *
     * @return array{checks: array, passed: int, failed: int, warnings: int, overall_status: string}
     */
    public function checkCompliance(LastingPowerOfAttorney $lpa): array
    {
        $lpa->load(['attorneys', 'notificationPersons']);

        $checks = [
            $this->checkDonorAge($lpa),
            $this->checkAtLeastOneAttorney($lpa),
            $this->checkDecisionType($lpa),
            $this->checkCertificateProvider($lpa),
            $this->checkCertificateProviderKnownYears($lpa),
            $this->checkNotificationPersonLimit($lpa),
            $this->checkReplacementAttorneys($lpa),
            $this->checkRegistrationStatus($lpa),
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

        $overallStatus = $failed > 0 ? 'incomplete' : ($warnings > 0 ? 'review_needed' : 'compliant');

        return [
            'checks' => $checks,
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'overall_status' => $overallStatus,
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
