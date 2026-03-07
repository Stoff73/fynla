<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Chattel;
use App\Models\Estate\Will;
use App\Models\LifeInsurancePolicy;
use App\Models\Property;
use App\Models\User;
use App\Traits\StructuredLogging;

class LetterEstateValidationService
{
    use StructuredLogging;

    /**
     * Validate letter to spouse content against estate planning data.
     *
     * @return array<int, array{type: string, severity: string, message: string, action: string}>
     */
    public function validateLetterAgainstEstate(User $user): array
    {
        $letter = $user->letterToSpouse;

        if (! $letter) {
            return [];
        }

        $warnings = [];

        $warnings = array_merge(
            $warnings,
            $this->checkExecutorConsistency($user, $letter),
            $this->checkInsuranceCoverage($user, $letter),
            $this->checkAssetCoverage($user, $letter),
            $this->checkCompleteness($user, $letter),
        );

        $this->logInfo('Letter estate validation completed', [
            'user_id' => $user->id,
            'warning_count' => count($warnings),
        ]);

        return $warnings;
    }

    /**
     * Compare executor names in letter with executors in will.
     */
    private function checkExecutorConsistency(User $user, $letter): array
    {
        $warnings = [];
        $will = Will::where('user_id', $user->id)->first();

        $letterExecutor = trim($letter->executor_name ?? '');
        $willExecutor = trim($will->executor_name ?? '');

        if ($letterExecutor !== '' && $willExecutor !== '' && ! $this->namesMatch($letterExecutor, $willExecutor)) {
            $warnings[] = [
                'type' => 'executor_mismatch',
                'severity' => 'high',
                'message' => "The executor named in your letter (\"{$letterExecutor}\") does not match the executor in your will (\"{$willExecutor}\"). These should be consistent to avoid confusion.",
                'action' => 'Update either your letter or your will so the executor details match.',
            ];
        }

        if ($letterExecutor === '' && $willExecutor !== '') {
            $warnings[] = [
                'type' => 'executor_mismatch',
                'severity' => 'medium',
                'message' => "Your will names \"{$willExecutor}\" as executor, but your letter does not include executor contact details.",
                'action' => 'Add your executor\'s name and contact details to your letter.',
            ];
        }

        if ($letterExecutor !== '' && $will && $willExecutor === '') {
            $warnings[] = [
                'type' => 'executor_mismatch',
                'severity' => 'medium',
                'message' => "Your letter names \"{$letterExecutor}\" as executor, but no executor is recorded in your will.",
                'action' => 'Update your will to include executor details.',
            ];
        }

        return $warnings;
    }

    /**
     * Compare insurance details in letter with system life insurance policies.
     */
    private function checkInsuranceCoverage(User $user, $letter): array
    {
        $warnings = [];

        $systemPolicies = LifeInsurancePolicy::where('user_id', $user->id)->get();
        $letterInsuranceInfo = trim($letter->insurance_policies_info ?? '');

        $policyCount = $systemPolicies->count();

        if ($policyCount > 0 && $letterInsuranceInfo === '') {
            $warnings[] = [
                'type' => 'insurance_unmatched',
                'severity' => 'medium',
                'message' => "You have {$policyCount} life insurance ".($policyCount === 1 ? 'policy' : 'policies').' recorded in the system, but your letter does not mention insurance policy details.',
                'action' => 'Review and update the insurance section of your letter to include policy locations and claim contact details.',
            ];
        }

        if ($policyCount > 0 && $letterInsuranceInfo !== '') {
            foreach ($systemPolicies as $policy) {
                $provider = $policy->provider ?? '';
                if ($provider !== '' && stripos($letterInsuranceInfo, $provider) === false) {
                    $warnings[] = [
                        'type' => 'insurance_unmatched',
                        'severity' => 'low',
                        'message' => "Your {$provider} life insurance policy (policy number: {$policy->policy_number}) is not mentioned in your letter's insurance section.",
                        'action' => "Add {$provider} policy details and claims contact information to your letter.",
                    ];
                }
            }
        }

        if ($policyCount === 0 && $letterInsuranceInfo !== '') {
            $warnings[] = [
                'type' => 'insurance_unmatched',
                'severity' => 'low',
                'message' => 'Your letter references insurance policies, but no life insurance policies are recorded in the system.',
                'action' => 'Add your life insurance policies in the Protection module, or update the letter if the reference is outdated.',
            ];
        }

        return $warnings;
    }

    /**
     * Check for assets mentioned in letter but not tracked, and vice versa.
     */
    private function checkAssetCoverage(User $user, $letter): array
    {
        $warnings = [];

        // Cryptocurrency check
        $letterCrypto = trim($letter->cryptocurrency_info ?? '');
        if ($letterCrypto !== '') {
            $warnings[] = [
                'type' => 'asset_untracked',
                'severity' => 'medium',
                'message' => 'Your letter mentions cryptocurrency holdings, but cryptocurrency is not tracked as a separate asset class in the system. This may affect your estate valuation.',
                'action' => 'Consider adding cryptocurrency as a chattel or estate asset to ensure accurate estate planning calculations.',
            ];
        }

        // Vehicle check
        $letterVehicles = trim($letter->vehicles_info ?? '');
        $systemVehicles = Chattel::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id);
        })->where('chattel_type', 'vehicle')->get();

        if ($letterVehicles !== '' && $systemVehicles->isEmpty()) {
            $warnings[] = [
                'type' => 'asset_untracked',
                'severity' => 'low',
                'message' => 'Your letter mentions vehicles, but no vehicles are recorded as chattels in the system.',
                'action' => 'Add your vehicles in the Estate Planning chattels section for a complete estate picture.',
            ];
        }

        if ($letterVehicles === '' && $systemVehicles->isNotEmpty()) {
            $vehicleCount = $systemVehicles->count();
            $warnings[] = [
                'type' => 'missing_in_letter',
                'severity' => 'low',
                'message' => "You have {$vehicleCount} ".($vehicleCount === 1 ? 'vehicle' : 'vehicles').' recorded as chattels, but your letter does not mention vehicle information.',
                'action' => 'Add vehicle details (location, keys, registration) to your letter so your spouse can locate them.',
            ];
        }

        // Valuable items check
        $letterValuables = trim($letter->valuable_items_info ?? '');
        $systemValuables = Chattel::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id);
        })->whereIn('chattel_type', ['art', 'antique', 'jewelry', 'collectible'])->get();

        if ($letterValuables === '' && $systemValuables->isNotEmpty()) {
            $valuableCount = $systemValuables->count();
            $warnings[] = [
                'type' => 'missing_in_letter',
                'severity' => 'low',
                'message' => "You have {$valuableCount} valuable ".($valuableCount === 1 ? 'item' : 'items').' recorded as chattels, but your letter does not mention them.',
                'action' => 'Add details about the location and handling of your valuable items to your letter.',
            ];
        }

        return $warnings;
    }

    /**
     * Check completeness: system data not referenced in letter.
     */
    private function checkCompleteness(User $user, $letter): array
    {
        $warnings = [];

        // Property check
        $letterRealEstate = trim($letter->real_estate_info ?? '');
        $propertyCount = Property::where('user_id', $user->id)->count();

        if ($propertyCount > 0 && $letterRealEstate === '') {
            $warnings[] = [
                'type' => 'missing_in_letter',
                'severity' => 'medium',
                'message' => "You have {$propertyCount} ".($propertyCount === 1 ? 'property' : 'properties').' recorded, but your letter does not include property details.',
                'action' => 'Add property information to your letter including title deed locations and mortgage details.',
            ];
        }

        // Liabilities check
        $letterLiabilities = trim($letter->liabilities_info ?? '');
        $liabilityCount = $user->liabilities()->count() + $user->mortgages()->count();

        if ($liabilityCount > 0 && ($letterLiabilities === '' || $letterLiabilities === 'No outstanding liabilities recorded.')) {
            $warnings[] = [
                'type' => 'missing_in_letter',
                'severity' => 'medium',
                'message' => "You have {$liabilityCount} outstanding ".($liabilityCount === 1 ? 'liability' : 'liabilities').' recorded, but your letter does not include liability details.',
                'action' => 'Add liability and mortgage information to your letter so your spouse knows what payments to manage.',
            ];
        }

        return $warnings;
    }

    /**
     * Fuzzy name comparison (case-insensitive, ignores titles).
     */
    private function namesMatch(string $name1, string $name2): bool
    {
        $normalise = function (string $name): string {
            $name = mb_strtolower(trim($name));
            // Remove common titles
            $name = (string) preg_replace('/^(mr\.?|mrs\.?|ms\.?|miss|dr\.?|prof\.?)\s+/i', '', $name);
            // Remove extra whitespace
            $name = (string) preg_replace('/\s+/', ' ', $name);

            return $name;
        };

        return $normalise($name1) === $normalise($name2);
    }
}
