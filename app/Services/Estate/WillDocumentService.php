<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\FamilyMember;
use App\Models\User;

class WillDocumentService
{
    /**
     * Gather pre-populated data from the user's existing profile.
     */
    public function prePopulateData(User $user): array
    {
        $addressParts = array_filter([
            $user->address_line_1,
            $user->address_line_2,
            $user->city,
            $user->county,
            $user->postcode,
        ]);

        $fullName = trim(implode(' ', array_filter([
            $user->first_name,
            $user->middle_name,
            $user->surname,
        ])));

        // Get spouse details
        $spouse = null;
        if ($user->spouse_id) {
            $spouseUser = User::find($user->spouse_id);
            if ($spouseUser) {
                $spouseAddressParts = array_filter([
                    $spouseUser->address_line_1,
                    $spouseUser->address_line_2,
                    $spouseUser->city,
                    $spouseUser->county,
                    $spouseUser->postcode,
                ]);

                $spouse = [
                    'full_name' => trim(implode(' ', array_filter([
                        $spouseUser->first_name,
                        $spouseUser->middle_name,
                        $spouseUser->surname,
                    ]))),
                    'address' => implode(', ', $spouseAddressParts),
                    'date_of_birth' => $spouseUser->date_of_birth?->format('Y-m-d'),
                    'occupation' => $spouseUser->occupation,
                ];
            }
        }

        // Get children from family_members
        $children = FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'child')
            ->get()
            ->map(fn (FamilyMember $child) => [
                'full_name' => $child->full_name,
                'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
                'is_dependent' => $child->is_dependent,
                'is_minor' => $child->date_of_birth
                    ? $child->date_of_birth->age < 18
                    : false,
            ])
            ->values()
            ->toArray();

        // Get existing executor from will record
        $will = Will::where('user_id', $user->id)->first();
        $executorName = $will?->executor_name;

        return [
            'testator' => [
                'full_name' => $fullName,
                'address' => implode(', ', $addressParts),
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'occupation' => $user->occupation,
            ],
            'spouse' => $spouse,
            'children' => $children,
            'has_minor_children' => collect($children)->contains('is_minor', true),
            'existing_executor_name' => $executorName,
            'domicile_status' => $user->domicile_status,
            'marital_status' => $user->marital_status,
            'has_spouse' => $user->spouse_id !== null,
        ];
    }

    /**
     * Create a new draft will document.
     */
    public function createDraft(User $user, array $data): WillDocument
    {
        $will = Will::where('user_id', $user->id)->first();

        return WillDocument::create([
            'user_id' => $user->id,
            'will_id' => $will?->id,
            'will_type' => $data['will_type'] ?? 'simple',
            'status' => 'draft',
            'testator_full_name' => $data['testator_full_name'] ?? '',
            'testator_address' => $data['testator_address'] ?? null,
            'testator_date_of_birth' => $data['testator_date_of_birth'] ?? null,
            'testator_occupation' => $data['testator_occupation'] ?? null,
            'domicile_confirmed' => $data['domicile_confirmed'] ?? null,
        ]);
    }

    /**
     * Update a specific wizard step.
     */
    public function updateStep(WillDocument $doc, string $step, array $data): WillDocument
    {
        $updateData = match ($step) {
            'personal' => [
                'testator_full_name' => $data['testator_full_name'] ?? $doc->testator_full_name,
                'testator_address' => $data['testator_address'] ?? $doc->testator_address,
                'testator_date_of_birth' => $data['testator_date_of_birth'] ?? $doc->testator_date_of_birth,
                'testator_occupation' => $data['testator_occupation'] ?? $doc->testator_occupation,
            ],
            'executors' => [
                'executors' => $data['executors'] ?? [],
            ],
            'guardians' => [
                'guardians' => $data['guardians'] ?? [],
            ],
            'gifts' => [
                'specific_gifts' => $data['specific_gifts'] ?? [],
            ],
            'residuary' => [
                'residuary_estate' => $data['residuary_estate'] ?? [],
            ],
            'funeral' => [
                'funeral_preference' => $data['funeral_preference'] ?? null,
                'funeral_wishes_notes' => $data['funeral_wishes_notes'] ?? null,
            ],
            'digital' => [
                'digital_executor_name' => $data['digital_executor_name'] ?? null,
                'digital_assets_instructions' => $data['digital_assets_instructions'] ?? null,
            ],
            'intro' => [
                'will_type' => $data['will_type'] ?? $doc->will_type,
                'domicile_confirmed' => $data['domicile_confirmed'] ?? $doc->domicile_confirmed,
            ],
            default => [],
        };

        if (! empty($updateData)) {
            $updateData['last_edited_at'] = now();
            $doc->update($updateData);
        }

        return $doc->fresh();
    }

    /**
     * Validate the document and return any warnings.
     */
    public function validateDocument(WillDocument $doc): array
    {
        $warnings = [];

        // Must have at least one executor
        $executors = $doc->executors ?? [];
        if (empty($executors)) {
            $warnings[] = [
                'field' => 'executors',
                'message' => 'You must appoint at least one executor.',
                'severity' => 'error',
            ];
        }

        // Check executor has required fields
        foreach ($executors as $i => $executor) {
            if (empty($executor['name'])) {
                $warnings[] = [
                    'field' => 'executors',
                    'message' => 'Executor '.($i + 1).' is missing a name.',
                    'severity' => 'error',
                ];
            }
            if (empty($executor['address'])) {
                $warnings[] = [
                    'field' => 'executors',
                    'message' => 'Executor '.($i + 1).' is missing an address.',
                    'severity' => 'warning',
                ];
            }
        }

        // Residuary estate must sum to 100%
        $residuary = $doc->residuary_estate ?? [];
        if (empty($residuary)) {
            $warnings[] = [
                'field' => 'residuary_estate',
                'message' => 'You must specify how to distribute your residuary estate.',
                'severity' => 'error',
            ];
        } else {
            $totalPercentage = array_sum(array_column($residuary, 'percentage'));
            if (abs($totalPercentage - 100) > 0.01) {
                $warnings[] = [
                    'field' => 'residuary_estate',
                    'message' => "Residuary estate percentages total {$totalPercentage}% — they must add up to 100%.",
                    'severity' => 'error',
                ];
            }
        }

        // Check for minor children without guardians
        $user = $doc->user;
        $hasMinorChildren = FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'child')
            ->get()
            ->contains(fn (FamilyMember $child) => $child->date_of_birth && $child->date_of_birth->age < 18);

        if ($hasMinorChildren && empty($doc->guardians)) {
            $warnings[] = [
                'field' => 'guardians',
                'message' => 'You have children under 18 but have not appointed a guardian.',
                'severity' => 'warning',
            ];
        }

        // Testator must be 18+
        if ($doc->testator_date_of_birth) {
            $age = $doc->testator_date_of_birth->age;
            if ($age < 18) {
                $warnings[] = [
                    'field' => 'personal',
                    'message' => 'You must be 18 or older to create a valid will in England and Wales.',
                    'severity' => 'error',
                ];
            }
        }

        // Domicile check
        if ($doc->domicile_confirmed && ! in_array($doc->domicile_confirmed, ['england_wales'])) {
            $warnings[] = [
                'field' => 'domicile',
                'message' => 'This will builder is designed for England and Wales law only. Different rules apply in Scotland and Northern Ireland.',
                'severity' => 'warning',
            ];
        }

        // Recommend backup executor
        if (count($executors) < 2) {
            $warnings[] = [
                'field' => 'executors',
                'message' => 'Consider appointing a backup executor in case your primary executor is unable to act.',
                'severity' => 'info',
            ];
        }

        return $warnings;
    }

    /**
     * Generate a mirror will for the spouse.
     */
    public function generateMirrorWill(WillDocument $primary): WillDocument
    {
        $user = $primary->user;
        $spouse = $user->spouse_id ? User::find($user->spouse_id) : null;

        if (! $spouse) {
            throw new \RuntimeException('Cannot generate mirror will: no spouse found.');
        }

        $spouseFullName = trim(implode(' ', array_filter([
            $spouse->first_name,
            $spouse->middle_name,
            $spouse->surname,
        ])));

        $spouseAddressParts = array_filter([
            $spouse->address_line_1,
            $spouse->address_line_2,
            $spouse->city,
            $spouse->county,
            $spouse->postcode,
        ]);

        // Swap residuary estate: primary testator becomes spouse's primary beneficiary
        $mirrorResiduary = $this->swapResiduaryForMirror(
            $primary->residuary_estate ?? [],
            $primary->testator_full_name,
            $spouseFullName
        );

        $spouseWill = Will::where('user_id', $spouse->id)->first();

        $mirror = WillDocument::create([
            'user_id' => $spouse->id,
            'will_id' => $spouseWill?->id,
            'mirror_document_id' => $primary->id,
            'will_type' => 'mirror',
            'status' => 'draft',
            'testator_full_name' => $spouseFullName,
            'testator_address' => implode(', ', $spouseAddressParts),
            'testator_date_of_birth' => $spouse->date_of_birth,
            'testator_occupation' => $spouse->occupation,
            'executors' => $primary->executors,
            'guardians' => $primary->guardians,
            'specific_gifts' => $primary->specific_gifts,
            'residuary_estate' => $mirrorResiduary,
            'funeral_preference' => $primary->funeral_preference,
            'funeral_wishes_notes' => null,
            'digital_executor_name' => $primary->digital_executor_name,
            'digital_assets_instructions' => null,
            'survivorship_days' => $primary->survivorship_days,
            'domicile_confirmed' => $primary->domicile_confirmed,
        ]);

        // Link the primary to the mirror
        $primary->update(['mirror_document_id' => $mirror->id]);

        return $mirror;
    }

    /**
     * Mark a will document as complete.
     */
    public function markComplete(WillDocument $doc): WillDocument
    {
        $errors = collect($this->validateDocument($doc))
            ->where('severity', 'error');

        if ($errors->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot complete will: '.$errors->first()['message']
            );
        }

        $doc->update([
            'status' => 'complete',
            'generated_at' => now(),
            'last_edited_at' => now(),
        ]);

        // Sync with the wills table
        $will = Will::firstOrCreate(
            ['user_id' => $doc->user_id],
            ['has_will' => true]
        );

        // Build executor name string from WillDocument executors JSON
        $executorNames = collect($doc->executors ?? [])
            ->pluck('name')
            ->filter()
            ->implode(', ');

        $will->update([
            'has_will' => true,
            'will_last_updated' => now(),
            'last_reviewed_date' => now(),
            'will_document_id' => $doc->id,
            'executor_name' => $executorNames ?: null,
        ]);

        $doc->update(['will_id' => $will->id]);

        return $doc->fresh();
    }

    /**
     * Get the user's current draft or completed will document.
     */
    public function getForUser(User $user): ?WillDocument
    {
        return WillDocument::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Swap residuary beneficiaries for the mirror will.
     */
    private function swapResiduaryForMirror(array $residuary, string $primaryName, string $spouseName): array
    {
        return array_map(function (array $beneficiary) use ($primaryName, $spouseName) {
            $name = $beneficiary['beneficiary_name'] ?? '';

            if (mb_strtolower($name) === mb_strtolower($spouseName)) {
                $beneficiary['beneficiary_name'] = $primaryName;
            } elseif (mb_strtolower($name) === mb_strtolower($primaryName)) {
                $beneficiary['beneficiary_name'] = $spouseName;
            }

            return $beneficiary;
        }, $residuary);
    }
}
