<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id',
        'is_admin',
        'is_preview_user',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'failed_login_count',
        'locked_until',
        'last_failed_login_at',
        'national_insurance_number',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'is_advisor' => 'boolean',
        'is_preview_user' => 'boolean',
        'must_change_password' => 'boolean',
        // MFA fields
        'mfa_enabled' => 'boolean',
        'mfa_recovery_codes' => 'array',
        'mfa_confirmed_at' => 'datetime',
        // Lockout fields
        'failed_login_count' => 'integer',
        'locked_until' => 'datetime',
        'last_failed_login_at' => 'datetime',
        'date_of_birth' => 'date',
        'life_expectancy_override' => 'integer',
        'retirement_date' => 'date',
        'is_primary_account' => 'boolean',
        'annual_employment_income' => 'decimal:2',
        'annual_self_employment_income' => 'decimal:2',
        'annual_rental_income' => 'decimal:2',
        'annual_dividend_income' => 'decimal:2',
        'annual_interest_income' => 'decimal:2',
        'annual_other_income' => 'decimal:2',
        'annual_trust_income' => 'decimal:2',
        'is_registered_blind' => 'boolean',
        'annual_charitable_donations' => 'decimal:2',
        'is_gift_aid' => 'boolean',
        'payday_day_of_month' => 'integer',
        'monthly_expenditure' => 'decimal:2',
        'annual_expenditure' => 'decimal:2',
        'retired_budget_overrides' => 'array',
        'widowed_budget_overrides' => 'array',
        'food_groceries' => 'decimal:2',
        'transport_fuel' => 'decimal:2',
        'healthcare_medical' => 'decimal:2',
        'insurance' => 'decimal:2',
        'mobile_phones' => 'decimal:2',
        'internet_tv' => 'decimal:2',
        'subscriptions' => 'decimal:2',
        'clothing_personal_care' => 'decimal:2',
        'entertainment_dining' => 'decimal:2',
        'holidays_travel' => 'decimal:2',
        'pets' => 'decimal:2',
        'childcare' => 'decimal:2',
        'school_fees' => 'decimal:2',
        'school_lunches' => 'decimal:2',
        'school_extras' => 'decimal:2',
        'university_fees' => 'decimal:2',
        'children_activities' => 'decimal:2',
        'gifts_charity' => 'decimal:2',
        'regular_savings' => 'decimal:2',
        'other_expenditure' => 'decimal:2',
        'rent' => 'decimal:2',
        'utilities' => 'decimal:2',
        'charitable_bequest' => 'boolean',
        'liabilities_reviewed' => 'boolean',
        'onboarding_completed' => 'boolean',
        'onboarding_skipped_steps' => 'array',
        'onboarding_started_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'onboarding_asset_flags' => 'array',
        'journey_states' => 'array',
        'journey_selections' => 'array',
        'life_stage_completed_steps' => 'array',
        'dismissed_prompts' => 'array',
        'uk_arrival_date' => 'date',
        'deemed_domicile_date' => 'date',
        // Guidance system casts
        'guidance_active' => 'boolean',
        'guidance_completed' => 'boolean',
        'guidance_current_step' => 'integer',
        'info_guide_enabled' => 'boolean',
        // Dashboard preferences
        'dashboard_widget_order' => 'array',
        // Subscription fields
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Sync is_admin flag when role_id changes.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('role_id') && $user->role_id) {
                $role = Role::find($user->role_id);
                if ($role) {
                    $user->is_admin = $role->name === Role::ROLE_ADMIN;
                }
            }
        });
    }

    /**
     * Get the user's subscription.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Get the user's payments.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function referralsSent(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /**
     * Check if user is currently on a trial.
     */
    public function onTrial(): bool
    {
        $subscription = $this->relationLoaded('subscription') ? $this->subscription : $this->subscription()->first();

        return $subscription && $subscription->isTrialing();
    }

    /**
     * Check if user has an active (paid) plan.
     */
    public function hasActivePlan(): bool
    {
        $subscription = $this->relationLoaded('subscription') ? $this->subscription : $this->subscription()->first();

        return $subscription && $subscription->isActive();
    }

    /**
     * Get number of days remaining in trial.
     */
    public function trialDaysRemaining(): int
    {
        $subscription = $this->relationLoaded('subscription') ? $this->subscription : $this->subscription()->first();

        return $subscription ? $subscription->daysLeftInTrial() : 0;
    }

    /**
     * Check if user is in the 30-day data retention grace period.
     */
    public function isInGracePeriod(): bool
    {
        $subscription = $this->relationLoaded('subscription') ? $this->subscription : $this->subscription()->first();

        return $subscription && $subscription->isInGracePeriod();
    }

    /**
     * Check if user is on a specific plan.
     */
    public function planIs(string $plan): bool
    {
        return $this->plan === $plan;
    }

    /**
     * Get the user's full name (backwards compatibility accessor).
     *
     * If the new name fields exist (first_name, surname), combines them.
     * Otherwise, falls back to the legacy 'name' column from the database.
     */
    public function getNameAttribute(): string
    {
        // Check if new name columns have values
        $firstName = $this->attributes['first_name'] ?? null;
        $surname = $this->attributes['surname'] ?? null;

        if ($firstName || $surname) {
            // Use new name structure
            return trim(implode(' ', array_filter([
                $firstName,
                $this->attributes['middle_name'] ?? null,
                $surname,
            ]))) ?: 'User';
        }

        // Fall back to legacy 'name' column
        return $this->attributes['name'] ?? 'User';
    }

    /**
     * Get the user's protection profile.
     */
    public function protectionProfile(): HasOne
    {
        return $this->hasOne(ProtectionProfile::class);
    }

    /**
     * Get the user's life insurance policies.
     */
    public function lifeInsurancePolicies(): HasMany
    {
        return $this->hasMany(LifeInsurancePolicy::class);
    }

    /**
     * Get the user's critical illness policies.
     */
    public function criticalIllnessPolicies(): HasMany
    {
        return $this->hasMany(CriticalIllnessPolicy::class);
    }

    /**
     * Get the user's income protection policies.
     */
    public function incomeProtectionPolicies(): HasMany
    {
        return $this->hasMany(IncomeProtectionPolicy::class);
    }

    /**
     * Get the user's disability policies.
     */
    public function disabilityPolicies(): HasMany
    {
        return $this->hasMany(DisabilityPolicy::class);
    }

    /**
     * Get the user's sickness/illness policies.
     */
    public function sicknessIllnessPolicies(): HasMany
    {
        return $this->hasMany(SicknessIllnessPolicy::class);
    }

    /**
     * Get the household this user belongs to.
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the user's role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the user's spouse.
     */
    public function spouse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spouse_id');
    }

    /**
     * Get the user's active sessions.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Get the user's consent records.
     */
    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    /**
     * Get the user's data export requests.
     */
    public function dataExports(): HasMany
    {
        return $this->hasMany(DataExport::class);
    }

    /**
     * Get the user's erasure requests.
     */
    public function erasureRequests(): HasMany
    {
        return $this->hasMany(ErasureRequest::class);
    }

    /**
     * Get the user's family members.
     */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * Get the letter to spouse for the user
     */
    public function letterToSpouse(): HasOne
    {
        return $this->hasOne(LetterToSpouse::class);
    }

    /**
     * Get the user's properties.
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Get the user's mortgages.
     */
    public function mortgages(): HasMany
    {
        return $this->hasMany(Mortgage::class);
    }

    /**
     * Get the user's liabilities.
     */
    public function liabilities(): HasMany
    {
        return $this->hasMany(\App\Models\Estate\Liability::class);
    }

    /**
     * Get the user's trusts (Estate module).
     */
    public function trusts(): HasMany
    {
        return $this->hasMany(\App\Models\Estate\Trust::class);
    }

    /**
     * Get the user's IHT profile (Estate module).
     */
    public function ihtProfile(): HasOne
    {
        return $this->hasOne(\App\Models\Estate\IHTProfile::class);
    }

    /**
     * Get the user's estate assets (Estate module).
     */
    public function assets(): HasMany
    {
        return $this->hasMany(\App\Models\Estate\Asset::class);
    }

    /**
     * Get the user's gifts (Estate module).
     */
    public function gifts(): HasMany
    {
        return $this->hasMany(\App\Models\Estate\Gift::class);
    }

    /**
     * Get the user's Lasting Powers of Attorney (Estate module).
     */
    public function lastingPowersOfAttorney(): HasMany
    {
        return $this->hasMany(\App\Models\Estate\LastingPowerOfAttorney::class);
    }

    /**
     * Get the user's business interests.
     */
    public function businessInterests(): HasMany
    {
        return $this->hasMany(BusinessInterest::class);
    }

    /**
     * Get the user's chattels.
     */
    public function chattels(): HasMany
    {
        return $this->hasMany(Chattel::class);
    }

    /**
     * Get the user's cash accounts.
     */
    public function cashAccounts(): HasMany
    {
        return $this->hasMany(CashAccount::class);
    }

    /**
     * Get the user's personal account entries.
     */
    public function personalAccounts(): HasMany
    {
        return $this->hasMany(PersonalAccount::class);
    }

    /**
     * Get the user's investment accounts.
     */
    public function investmentAccounts(): HasMany
    {
        return $this->hasMany(\App\Models\Investment\InvestmentAccount::class);
    }

    /**
     * Get the user's DC (Defined Contribution) pensions.
     */
    public function dcPensions(): HasMany
    {
        return $this->hasMany(DCPension::class);
    }

    /**
     * Get the user's DB (Defined Benefit) pensions.
     */
    public function dbPensions(): HasMany
    {
        return $this->hasMany(DBPension::class);
    }

    /**
     * Get the user's state pension.
     */
    public function statePension(): HasOne
    {
        return $this->hasOne(StatePension::class);
    }

    /**
     * Get the user's retirement profile.
     */
    public function retirementProfile(): HasOne
    {
        return $this->hasOne(RetirementProfile::class);
    }

    /**
     * Get the spouse permission requests sent by this user
     */
    public function spousePermissionRequests(): HasMany
    {
        return $this->hasMany(SpousePermission::class, 'user_id');
    }

    /**
     * Get the spouse permission requests received by this user
     */
    public function receivedSpousePermissions(): HasMany
    {
        return $this->hasMany(SpousePermission::class, 'spouse_id');
    }

    /**
     * Get the user's onboarding progress records
     */
    public function onboardingProgress(): HasMany
    {
        return $this->hasMany(OnboardingProgress::class);
    }

    /**
     * Get the user's expenditure profile.
     */
    public function expenditureProfile(): HasOne
    {
        return $this->hasOne(ExpenditureProfile::class);
    }

    /**
     * Get the user's savings accounts.
     */
    public function savingsAccounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class);
    }

    /**
     * Get the user's goals.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    /**
     * Get clients managed by this advisor.
     */
    public function advisorClients(): HasMany
    {
        return $this->hasMany(AdvisorClient::class, 'advisor_id');
    }

    /**
     * Get advisors managing this client.
     */
    public function advisors(): HasMany
    {
        return $this->hasMany(AdvisorClient::class, 'client_id');
    }

    /**
     * Get the user's planning assumptions.
     */
    public function assumptions(): HasMany
    {
        return $this->hasMany(UserAssumption::class);
    }

    /**
     * Check if user has accepted permission to share data with spouse
     *
     * IMPORTANT: If spouse accounts are linked (spouse_id is set) and both users
     * are married, data sharing is automatically enabled. No separate permission
     * record required. This fixes the persistent issue where spouse data doesn't
     * display in the Estate module even though accounts are linked during onboarding.
     */
    public function hasAcceptedSpousePermission(): bool
    {
        // No spouse linked
        if (! $this->spouse_id) {
            return false;
        }

        // If both users are married and linked, enable data sharing automatically
        // Use existing relationship to avoid N+1 queries when eager-loaded
        if ($this->marital_status === 'married') {
            $spouse = $this->relationLoaded('spouse') ? $this->spouse : $this->spouse()->first();
            if ($spouse && $spouse->marital_status === 'married' && $spouse->spouse_id === $this->id) {
                return true;
            }
        }

        // Fallback: Check for explicit permission record (legacy/optional)
        $permission = SpousePermission::where(function ($query) {
            $query->where('user_id', $this->id)
                ->where('spouse_id', $this->spouse_id);
        })->orWhere(function ($query) {
            $query->where('user_id', $this->spouse_id)
                ->where('spouse_id', $this->id);
        })->where('status', 'accepted')->first();

        return $permission !== null;
    }

    /**
     * Calculate years of UK residence based on uk_arrival_date
     *
     * @return int|null Number of complete years, or null if no arrival date set
     */
    public function calculateYearsUKResident(): ?int
    {
        if (! $this->uk_arrival_date) {
            return null;
        }

        $arrivalDate = \Carbon\Carbon::parse($this->uk_arrival_date);
        $now = \Carbon\Carbon::now();

        return $arrivalDate->diffInYears($now);
    }

    /**
     * Check if user is deemed domiciled under the 15/20 year rule
     *
     * UK residence-based system (post-April 2025):
     * - User is deemed domiciled if they have been UK resident for at least 15 of the last 20 years
     * - For simplicity, we calculate based on continuous residence from uk_arrival_date
     *
     * @return bool True if deemed domiciled, false otherwise
     */
    public function isDeemedDomiciled(): bool
    {
        // If explicitly set as UK domiciled, return true
        if ($this->domicile_status === 'uk_domiciled') {
            return true;
        }

        // If no UK arrival date, cannot calculate deemed domicile
        if (! $this->uk_arrival_date) {
            return false;
        }

        $yearsResident = $this->calculateYearsUKResident();

        // Deemed domiciled if resident for 15+ years
        return $yearsResident !== null && $yearsResident >= 15;
    }

    /**
     * Get domicile status with explanation
     */
    public function getDomicileInfo(): array
    {
        $yearsResident = $this->calculateYearsUKResident();
        $isDeemedDomiciled = $this->isDeemedDomiciled();

        return [
            'domicile_status' => $this->domicile_status,
            'country_of_birth' => $this->country_of_birth,
            'uk_arrival_date' => $this->uk_arrival_date?->format('Y-m-d'),
            'years_uk_resident' => $yearsResident,
            'is_deemed_domiciled' => $isDeemedDomiciled,
            'deemed_domicile_date' => $this->deemed_domicile_date?->format('Y-m-d'),
            'explanation' => $this->getDomicileExplanation($yearsResident, $isDeemedDomiciled),
        ];
    }

    /**
     * Get human-readable explanation of domicile status
     */
    private function getDomicileExplanation(?int $yearsResident, bool $isDeemedDomiciled): string
    {
        if ($this->domicile_status === 'uk_domiciled') {
            return 'You are UK domiciled.';
        }

        if ($this->domicile_status === 'non_uk_domiciled') {
            if ($isDeemedDomiciled) {
                return "You are deemed UK domiciled for tax purposes. You have been UK resident for {$yearsResident} years, which exceeds the 15-year threshold.";
            }

            if ($yearsResident !== null) {
                $yearsRemaining = max(0, 15 - $yearsResident);
                if ($yearsRemaining > 0) {
                    return "You are non-UK domiciled. You need {$yearsRemaining} more year(s) of UK residence to become deemed domiciled (15 of 20 year rule).";
                }
            }

            return 'You are non-UK domiciled.';
        }

        return 'Domicile status not set. Please update your profile.';
    }
}
