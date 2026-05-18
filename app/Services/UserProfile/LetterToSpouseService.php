<?php

declare(strict_types=1);

namespace App\Services\UserProfile;

use App\Models\LetterToSpouse;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\SavingsStore;
use Carbon\Carbon;

class LetterToSpouseService
{
    /**
     * Get or create letter for user with auto-populated data
     */
    public function getOrCreateLetter(User $user): LetterToSpouse
    {
        $letter = $user->letterToSpouse;

        if (! $letter) {
            $letter = $this->createWithDefaults($user);
        }

        return $letter;
    }

    /**
     * Create letter with auto-populated defaults from user data
     */
    private function createWithDefaults(User $user): LetterToSpouse
    {
        $data = $this->generateDefaultData($user);

        return LetterToSpouse::create(array_merge(['user_id' => $user->id], $data));
    }

    /**
     * Generate default letter content from user's existing data
     */
    private function generateDefaultData(User $user): array
    {
        return [
            // Part 1: Immediate actions - populate what we know
            'immediate_actions' => $this->generateImmediateActions($user),
            'employer_hr_contact' => $user->employer ? "Contact {$user->employer} HR Department" : null,
            'immediate_funds_access' => $this->generateImmediateFundsInfo($user),

            // Part 2: Accounts - populate from existing data
            'bank_accounts_info' => $this->generateBankAccountsInfo($user),
            'investment_accounts_info' => $this->generateInvestmentAccountsInfo($user),
            'insurance_policies_info' => $this->generateInsurancePoliciesInfo($user),
            'real_estate_info' => $this->generateRealEstateInfo($user),
            'liabilities_info' => $this->generateLiabilitiesInfo($user),

            // Part 3: Long-term plans
            'beneficiary_info' => $this->generateBeneficiaryInfo($user),
            'children_education_plans' => $this->generateEducationPlansInfo($user),
            'financial_guidance' => $this->generateFinancialGuidanceInfo($user),

            // Part 4: Funeral wishes - leave empty for user to fill
            'funeral_preference' => 'not_specified',
        ];
    }

    /**
     * Generate immediate actions text
     */
    private function generateImmediateActions(User $user): string
    {
        $actions = [];

        $actions[] = '1. Contact our executor immediately (details below)';
        $actions[] = "2. Notify my employer's HR department";
        $actions[] = '3. Access joint bank accounts for immediate expenses';
        $actions[] = '4. Contact our financial advisor for guidance';

        if ($user->protectionProfile) {
            $actions[] = '5. Contact life insurance companies to file claims (policy details below)';
        }

        $actions[] = '6. Keep my mobile phone active for account verification';
        $actions[] = '7. Register the death with the local registrar';
        $actions[] = '8. Obtain multiple death certificates (at least 10 copies)';

        return implode("\n", $actions);
    }

    /**
     * Generate immediate funds access information
     */
    private function generateImmediateFundsInfo(User $user): ?string
    {
        $savingsAccounts = app(SavingsStore::class)
            ->forUser($user)
            ->where('user_id', $user->id)
            ->where('ownership_type', 'joint');

        if ($savingsAccounts->isEmpty()) {
            return 'Note: Review which accounts are joint accounts that can be accessed immediately.';
        }

        $info = "Joint Accounts (Accessible Immediately):\n\n";

        foreach ($savingsAccounts as $account) {
            $info .= "• {$account->institution} - £".number_format((float) $account->current_balance, 2)."\n";
        }

        $info .= "\nThese joint accounts remain accessible. Individual accounts may be frozen until probate.";

        return $info;
    }

    /**
     * Generate bank accounts information
     */
    private function generateBankAccountsInfo(User $user): ?string
    {
        $savingsAccounts = app(SavingsStore::class)
            ->forUser($user)
            ->where('user_id', $user->id);

        if ($savingsAccounts->isEmpty()) {
            return null;
        }

        $info = "Bank/Savings Accounts:\n\n";

        foreach ($savingsAccounts as $account) {
            $ownership = ucfirst($account->ownership_type ?? 'individual');
            $info .= "• {$account->institution}\n";
            $info .= '  Account Type: '.ucfirst(str_replace('_', ' ', $account->account_type ?? 'savings'))."\n";
            $info .= "  Ownership: {$ownership}\n";
            $info .= '  Current Balance: £'.number_format((float) $account->current_balance, 2)."\n";
            $info .= "  Sort Code/Account Number: [Please add]\n\n";
        }

        $info .= 'Note: Add login credentials to password manager.';

        return $info;
    }

    /**
     * Generate investment accounts information
     */
    private function generateInvestmentAccountsInfo(User $user): ?string
    {
        $investmentAccounts = $user->investmentAccounts;

        if ($investmentAccounts->isEmpty()) {
            return null;
        }

        $info = "Investment Accounts:\n\n";

        foreach ($investmentAccounts as $account) {
            $ownership = ucfirst($account->ownership_type ?? 'individual');
            $info .= "• {$account->provider}\n";
            $info .= '  Account Type: '.strtoupper($account->account_type)."\n";
            $info .= "  Ownership: {$ownership}\n";
            $info .= '  Current Value: £'.number_format((float) $account->current_value, 2)."\n";
            $info .= "  Account Number: [Please add]\n\n";
        }

        $info .= 'Note: Add login credentials to password manager.';

        return $info;
    }

    /**
     * Generate insurance policies information
     */
    private function generateInsurancePoliciesInfo(User $user): ?string
    {
        $policies = [];

        // Life insurance
        $lifePolicies = $user->lifeInsurancePolicies;
        foreach ($lifePolicies as $policy) {
            $policies[] = [
                'type' => 'Life Insurance',
                'provider' => $policy->provider,
                'sum_assured' => $policy->sum_assured,
                'policy_number' => $policy->policy_number,
            ];
        }

        // Critical illness
        $ciPolicies = $user->criticalIllnessPolicies;
        foreach ($ciPolicies as $policy) {
            $policies[] = [
                'type' => 'Critical Illness',
                'provider' => $policy->provider,
                'sum_assured' => $policy->sum_assured,
                'policy_number' => $policy->policy_number,
            ];
        }

        // Income protection
        $ipPolicies = $user->incomeProtectionPolicies;
        foreach ($ipPolicies as $policy) {
            $policies[] = [
                'type' => 'Income Protection',
                'provider' => $policy->provider,
                'sum_assured' => $policy->monthly_benefit * 12, // Approximate annual
                'policy_number' => $policy->policy_number,
            ];
        }

        if (empty($policies)) {
            return null;
        }

        $info = "Insurance Policies:\n\n";

        foreach ($policies as $policy) {
            $info .= "• {$policy['type']} - {$policy['provider']}\n";
            $info .= "  Policy Number: {$policy['policy_number']}\n";
            $info .= '  Sum Assured: £'.number_format((float) $policy['sum_assured'], 2)."\n";
            $info .= "  Contact: [Add claims phone number]\n\n";
        }

        $info .= "Home Insurance: [Please add details]\n";
        $info .= 'Auto Insurance: [Please add details]';

        return $info;
    }

    /**
     * Generate real estate information
     */
    private function generateRealEstateInfo(User $user): ?string
    {
        $properties = Property::where('user_id', $user->id)->get();

        if ($properties->isEmpty()) {
            return null;
        }

        $info = "Property Ownership:\n\n";

        foreach ($properties as $property) {
            $ownership = ucfirst($property->ownership_type ?? 'individual');
            $info .= "• {$property->address_line_1}, {$property->city}, {$property->postcode}\n";
            $info .= '  Type: '.ucfirst(str_replace('_', ' ', $property->property_type ?? 'residential'))."\n";
            $info .= "  Ownership: {$ownership}\n";
            $info .= '  Current Value: £'.number_format((float) $property->current_value, 2)."\n";
            $info .= '  Use: '.ucfirst($property->property_use ?? 'primary_residence')."\n";

            if ($property->outstanding_mortgage > 0) {
                $info .= '  Outstanding Mortgage: £'.number_format((float) $property->outstanding_mortgage, 2)."\n";
            }

            $info .= "  Title Deeds Location: [Please add]\n\n";
        }

        return $info;
    }

    /**
     * Generate liabilities information
     */
    private function generateLiabilitiesInfo(User $user): ?string
    {
        $liabilities = $user->liabilities;
        $mortgages = $user->mortgages;

        if ($liabilities->isEmpty() && $mortgages->isEmpty()) {
            return 'No outstanding liabilities recorded.';
        }

        $info = "Outstanding Liabilities:\n\n";

        // Mortgages
        foreach ($mortgages as $mortgage) {
            $info .= "• Mortgage - {$mortgage->lender}\n";
            $info .= '  Outstanding: £'.number_format((float) $mortgage->outstanding_balance, 2)."\n";
            $info .= '  Monthly Payment: £'.number_format((float) $mortgage->monthly_payment, 2)."\n";
            $info .= "  Account Number: [Please add]\n\n";
        }

        // Other liabilities
        foreach ($liabilities as $liability) {
            $info .= '• '.ucfirst(str_replace('_', ' ', $liability->liability_type ?? 'loan'))." - {$liability->creditor}\n";
            $info .= '  Outstanding: £'.number_format((float) $liability->outstanding_balance, 2)."\n";
            if ($liability->monthly_payment) {
                $info .= '  Monthly Payment: £'.number_format((float) $liability->monthly_payment, 2)."\n";
            }
            $info .= "  Account Number: [Please add]\n\n";
        }

        return $info;
    }

    /**
     * Generate beneficiary information
     */
    private function generateBeneficiaryInfo(User $user): ?string
    {
        $familyMembers = $user->familyMembers()->where('is_dependent', true)->get();

        if ($familyMembers->isEmpty()) {
            return null;
        }

        $info = "Beneficiaries:\n\n";

        foreach ($familyMembers as $member) {
            $info .= "• {$member->name}\n";
            $info .= '  Relationship: '.ucfirst($member->relationship ?? 'dependent')."\n";
            if ($member->date_of_birth) {
                $age = Carbon::parse($member->date_of_birth)->age;
                $info .= "  Age: {$age}\n";
            }
            $info .= "\n";
        }

        $info .= 'Review life insurance beneficiary designations and pension death benefits.';

        return $info;
    }

    /**
     * Generate education plans information
     */
    private function generateEducationPlansInfo(User $user): ?string
    {
        $children = $user->familyMembers()->where('relationship', 'child')->get();

        if ($children->isEmpty()) {
            return null;
        }

        $info = "Children's Education Plans:\n\n";

        foreach ($children as $child) {
            $info .= "• {$child->name}\n";
            if ($child->date_of_birth) {
                $age = Carbon::parse($child->date_of_birth)->age;
                $info .= "  Current Age: {$age}\n";
            }
            $info .= "  Education Plans: [Please add details about university plans, savings accounts, etc.]\n\n";
        }

        return $info;
    }

    /**
     * Generate financial guidance information
     */
    private function generateFinancialGuidanceInfo(User $user): string
    {
        $info = "Financial Guidance:\n\n";

        if ($user->annual_employment_income > 0 || $user->annual_self_employment_income > 0) {
            $totalIncome = ($user->annual_employment_income ?? 0) + ($user->annual_self_employment_income ?? 0);
            $info .= 'Current Household Income: £'.number_format((float) $totalIncome, 2)." per year\n\n";
        }

        $info .= "Please contact our financial advisor for guidance on:\n";
        $info .= "• State Pension entitlement and timing\n";
        $info .= "• Survivor benefits from my workplace pension\n";
        $info .= "• Tax-efficient withdrawal strategies\n";
        $info .= "• Investment portfolio rebalancing\n";
        $info .= "• Inheritance tax planning\n\n";

        $info .= 'Consider waiting at least 6 months before making major financial decisions.';

        return $info;
    }

    /**
     * Update letter with user data
     */
    public function updateLetter(User $user, array $data): LetterToSpouse
    {
        $letter = $this->getOrCreateLetter($user);
        $letter->update($data);

        return $letter->fresh();
    }
}
