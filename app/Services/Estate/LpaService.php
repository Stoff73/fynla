<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\LpaAttorney;
use App\Models\Estate\LpaNotificationPerson;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Support\Facades\DB;

class LpaService
{
    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Get all LPAs for a user with related data.
     */
    public function getLpasForUser(User $user): array
    {
        $lpas = LastingPowerOfAttorney::forUser($user->id)
            ->with(['attorneys', 'notificationPersons'])
            ->orderBy('lpa_type')
            ->get();

        return $lpas->toArray();
    }

    /**
     * Get a single LPA by ID for the given user.
     */
    public function getLpaForUser(User $user, int $lpaId): ?LastingPowerOfAttorney
    {
        return LastingPowerOfAttorney::forUser($user->id)
            ->with(['attorneys', 'notificationPersons', 'document'])
            ->find($lpaId);
    }

    /**
     * Create a new LPA with attorneys and notification persons.
     */
    public function createLpa(User $user, array $data): LastingPowerOfAttorney
    {
        return DB::transaction(function () use ($user, $data) {
            $lpaData = collect($data)->except(['attorneys', 'notification_persons'])->toArray();
            $lpaData['user_id'] = $user->id;

            // Set completed_at if status is completed or registered
            if (in_array($lpaData['status'] ?? 'draft', ['completed', 'registered'])) {
                $lpaData['completed_at'] = $lpaData['completed_at'] ?? now();
            }

            $lpa = LastingPowerOfAttorney::create($lpaData);

            // Create attorneys
            if (! empty($data['attorneys'])) {
                $this->syncAttorneys($lpa, $data['attorneys']);
            }

            // Create notification persons
            if (! empty($data['notification_persons'])) {
                $this->syncNotificationPersons($lpa, $data['notification_persons']);
            }

            $this->invalidateCache($user->id);

            return $lpa->load(['attorneys', 'notificationPersons']);
        });
    }

    /**
     * Update an existing LPA.
     */
    public function updateLpa(LastingPowerOfAttorney $lpa, array $data): LastingPowerOfAttorney
    {
        return DB::transaction(function () use ($lpa, $data) {
            $lpaData = collect($data)->except(['attorneys', 'notification_persons'])->toArray();

            // Set completed_at if transitioning to completed or registered
            if (in_array($lpaData['status'] ?? $lpa->status, ['completed', 'registered']) && $lpa->completed_at === null) {
                $lpaData['completed_at'] = now();
            }

            $lpa->update($lpaData);

            // Sync attorneys if provided
            if (array_key_exists('attorneys', $data)) {
                $this->syncAttorneys($lpa, $data['attorneys'] ?? []);
            }

            // Sync notification persons if provided
            if (array_key_exists('notification_persons', $data)) {
                $this->syncNotificationPersons($lpa, $data['notification_persons'] ?? []);
            }

            $this->invalidateCache($lpa->user_id);

            return $lpa->fresh(['attorneys', 'notificationPersons']);
        });
    }

    /**
     * Delete an LPA (soft delete).
     */
    public function deleteLpa(LastingPowerOfAttorney $lpa): void
    {
        $lpa->delete();
        $this->invalidateCache($lpa->user_id);
    }

    /**
     * Mark an LPA as registered with the Office of the Public Guardian.
     */
    public function markAsRegistered(LastingPowerOfAttorney $lpa, array $data): LastingPowerOfAttorney
    {
        $lpa->update([
            'status' => 'registered',
            'is_registered_with_opg' => true,
            'registration_date' => $data['registration_date'] ?? now()->toDateString(),
            'opg_reference' => $data['opg_reference'] ?? null,
        ]);

        $this->invalidateCache($lpa->user_id);

        return $lpa->fresh(['attorneys', 'notificationPersons']);
    }

    /**
     * Auto-fill donor details from the user's profile.
     */
    public function autoFillDonorDetails(User $user): array
    {
        return [
            'donor_full_name' => trim(($user->first_name ?? '').' '.($user->surname ?? '')),
            'donor_date_of_birth' => $user->date_of_birth?->toDateString(),
            'donor_address_line_1' => $user->address_line_1 ?? null,
            'donor_address_line_2' => $user->address_line_2 ?? null,
            'donor_address_city' => $user->city ?? null,
            'donor_address_county' => $user->county ?? null,
            'donor_address_postcode' => $user->postcode ?? null,
        ];
    }

    /**
     * Sync attorneys for an LPA — delete existing and recreate.
     */
    private function syncAttorneys(LastingPowerOfAttorney $lpa, array $attorneys): void
    {
        $lpa->attorneys()->delete();

        foreach ($attorneys as $index => $attorney) {
            $attorney['lasting_power_of_attorney_id'] = $lpa->id;
            $attorney['sort_order'] = $attorney['sort_order'] ?? $index;
            LpaAttorney::create($attorney);
        }
    }

    /**
     * Sync notification persons for an LPA — delete existing and recreate.
     */
    private function syncNotificationPersons(LastingPowerOfAttorney $lpa, array $persons): void
    {
        $lpa->notificationPersons()->delete();

        foreach ($persons as $index => $person) {
            $person['lasting_power_of_attorney_id'] = $lpa->id;
            $person['sort_order'] = $person['sort_order'] ?? $index;
            LpaNotificationPerson::create($person);
        }
    }

    /**
     * Invalidate estate planning cache when LPA data changes.
     */
    private function invalidateCache(int $userId): void
    {
        $this->cacheInvalidation->invalidateForUser($userId);
    }
}
