<?php

declare(strict_types=1);

namespace App\Services\GDPR;

use App\Models\User;
use App\Models\UserConsent;

class ConsentService
{
    /**
     * Record user consent
     */
    public function recordConsent(User $user, string $consentType, bool $consented = true): UserConsent
    {
        return UserConsent::recordConsent($user->id, $consentType, $consented);
    }

    /**
     * Record multiple consents at once
     */
    public function recordConsents(User $user, array $consents): array
    {
        $recorded = [];

        foreach ($consents as $type => $consented) {
            $recorded[$type] = $this->recordConsent($user, $type, (bool) $consented);
        }

        return $recorded;
    }

    /**
     * Withdraw consent
     */
    public function withdrawConsent(User $user, string $consentType): void
    {
        $consent = UserConsent::where('user_id', $user->id)
            ->where('consent_type', $consentType)
            ->where('version', UserConsent::CURRENT_VERSIONS[$consentType] ?? 'v1.0')
            ->first();

        if ($consent) {
            $consent->withdraw();
        }
    }

    /**
     * Check if user has given consent
     */
    public function hasConsent(User $user, string $consentType): bool
    {
        return UserConsent::hasConsent($user->id, $consentType);
    }

    /**
     * Check if user has all required consents
     */
    public function hasRequiredConsents(User $user): bool
    {
        return $this->hasConsent($user, UserConsent::TYPE_TERMS)
            && $this->hasConsent($user, UserConsent::TYPE_PRIVACY)
            && $this->hasConsent($user, UserConsent::TYPE_DATA_PROCESSING);
    }

    /**
     * Get all consents for a user
     */
    public function getUserConsents(User $user): array
    {
        return UserConsent::getUserConsents($user->id);
    }

    /**
     * Get consent history for a user
     */
    public function getConsentHistory(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return UserConsent::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Check if user needs to re-consent (e.g., after policy update)
     */
    public function needsReconsent(User $user, string $consentType): bool
    {
        $currentVersion = UserConsent::CURRENT_VERSIONS[$consentType] ?? 'v1.0';

        $latestConsent = UserConsent::where('user_id', $user->id)
            ->where('consent_type', $consentType)
            ->where('consented', true)
            ->orderByDesc('created_at')
            ->first();

        if (! $latestConsent) {
            return true;
        }

        return $latestConsent->version !== $currentVersion;
    }

    /**
     * Get consent types that need re-consent
     */
    public function getConsentTypesNeedingReconsent(User $user): array
    {
        $needed = [];

        foreach (UserConsent::CURRENT_VERSIONS as $type => $version) {
            if ($this->needsReconsent($user, $type)) {
                $needed[] = $type;
            }
        }

        return $needed;
    }
}
