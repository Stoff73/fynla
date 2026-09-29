<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Agents\ProtectionAgent;
use App\Models\ProtectionProfile;
use App\Models\User;

/**
 * The ONE write path for the protection cover a user's employer provides:
 * death in service, group income protection, group critical illness and
 * private medical insurance, on the user's protection profile. The web form
 * (ProtectionController::updateEmployerBenefits) and Fyn's capture tool
 * (capture_employer_benefits) both save through here, with the same rules
 * (CSJ 2026-09-29: the gap analysis read these fields, but nothing wrote them).
 *
 * Every save stamps employer_benefits_recorded_at, so an answer of "none" is
 * an answer: has_employer_pmi is NOT NULL DEFAULT false, so without the stamp
 * "never asked" and "nothing provided" read the same.
 */
final class EmployerBenefitsWriter
{
    /** The fields a save writes; a field left out is saved as not provided. */
    public const FIELDS = [
        'employer_name',
        'death_in_service_multiple',
        'group_ip_benefit_percent',
        'group_ip_benefit_months',
        'group_ip_definition',
        'group_ci_amount',
        'has_employer_pmi',
    ];

    /** Group income protection pays if you cannot do your own job, or only any job. */
    public const IP_DEFINITIONS = ['own', 'any'];

    public function __construct(private readonly ProtectionAgent $agent) {}

    /**
     * Bounds for both writers. Each fits its column (protection_profiles:
     * decimal(5,2), decimal(5,2), int, varchar(50), decimal(15,2), boolean).
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'none' => ['sometimes', 'boolean'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'death_in_service_multiple' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'group_ip_benefit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'group_ip_benefit_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'group_ip_definition' => ['nullable', 'string', 'in:'.implode(',', self::IP_DEFINITIONS)],
            'group_ci_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'has_employer_pmi' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Save the user's answer. `none` clears every benefit; the employer's
     * name is kept either way.
     *
     * @param  array<string, mixed>  $input  already validated against rules()
     */
    public function save(User $user, array $input): ProtectionProfile
    {
        $none = (bool) ($input['none'] ?? false);
        $values = [];
        foreach (self::FIELDS as $field) {
            $value = $input[$field] ?? null;
            $values[$field] = $none && $field !== 'employer_name' ? null : ($value === '' ? null : $value);
        }
        $values['has_employer_pmi'] = (bool) ($values['has_employer_pmi'] ?? false);
        $values['employer_benefits_recorded_at'] = now();

        $profile = ProtectionProfile::firstOrCreate(['user_id' => $user->id], ProtectionProfile::blankFor($user->id));
        $profile->fill($values)->save();
        $this->agent->invalidateCache($user->id);

        return $profile->refresh();
    }
}
