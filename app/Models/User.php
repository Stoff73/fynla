<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\Investment\InvestmentAccount;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
    use Auditable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * High-frequency columns excluded from audit logging — these change on
     * almost every request and would drown the audit table. Identity / billing /
     * privilege / lifecycle changes still get audited via the trait defaults.
     */
    protected $auditExcludeFields = [
        'last_login_at',
        'last_failed_login_at',
        'locked_until',
        'failed_login_count',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'password',
        'apple_app_account_token',
        'last_active_at',
        'last_seen_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    /**
     * Monthly charitable giving, with the annual figure derived from it.
     *
     * `annual_charitable_donations` is read by IHT planning, ResolvesIncome and
     * PersonalAccountsService, so it stays — but it is now derived here rather
     * than written by each caller. Before this, the monthly figure had no column
     * at all: the Expenditure form's "Charitable Donations" line was discarded on
     * save, and the annual field was set by a side-channel call that nothing read
     * back, so the form showed 0 and the next save committed that 0.
     */
    protected function charitableDonations(): Attribute
    {
        return Attribute::make(
            set: static fn ($value): array => [
                'charitable_donations' => $value,
                'annual_charitable_donations' => $value === null ? null : round((float) $value * 12, 2),
            ],
        );
    }

    protected $guarded = [
        'id',
        'is_admin',
        'is_preview_user',
        'is_advisor',        // Privilege flag — set only via markAsAdvisor()
        'email_verified_at', // Verification state — never from request input
        'mfa_enabled',       // Auth state — set individually by MFAService
        'mfa_secret',         // Auth secret — set individually by MFAService
        'remember_token',
        'apple_app_account_token',
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
        'apple_app_account_token',
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
        'charitable_donations' => 'decimal:2',
        'regular_savings' => 'decimal:2',
        'other_expenditure' => 'decimal:2',
        'rent' => 'decimal:2',
        'utilities' => 'decimal:2',
        'liabilities_reviewed' => 'boolean',
        'onboarding_completed' => 'boolean',
        'onboarding_skipped_steps' => 'array',
        'onboarding_started_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'onboarding_asset_flags' => 'array',
        'onboarding_fyn_context' => 'array',
        'funnel_answers' => 'array',
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
        // Lifecycle email e2e testing
        'is_lifecycle_test_user' => 'boolean',
        // SaveTax campaign — household tax-strategy
        'marriage_allowance_eligible' => 'boolean',
        // Account deletion lifecycle
        'deletion_scheduled_for' => 'datetime',
        'restored_at' => 'datetime',
        'purge_eligible_at' => 'datetime',
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
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function deletionReminderLog()
    {
        return $this->hasMany(AccountDeletionReminderLog::class);
    }

    /**
     * Get all of the user's subscriptions over their lifetime.
     * Used by the lifecycle email engine for eligibility queries that
     * need to match against any past/present subscription record.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function premiumEntitlements(): HasMany
    {
        return $this->hasMany(PremiumEntitlement::class);
    }

    public function appleTransactions(): HasMany
    {
        return $this->hasMany(AppleTransaction::class);
    }

    public function appleNotificationRecoveries(): HasMany
    {
        return $this->hasMany(AppleNotificationRecovery::class);
    }

    /**
     * Get the user's notification preferences (single row per user).
     */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Get the user's lifecycle email log entries (dedup + click tracking).
     */
    public function lifecycleEmails(): HasMany
    {
        return $this->hasMany(LifecycleEmailLog::class);
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
     * Check if user has an active (paid) plan.
     */
    public function hasActivePlan(): bool
    {
        $subscription = $this->relationLoaded('subscription') ? $this->subscription : $this->subscription()->first();

        return $subscription && $subscription->isActive();
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
     * Eligibility for the Student subscription plan.
     *
     * UK university students have institutional emails ending in `.ac.uk`
     * (e.g. `@manchester.ac.uk`, `@student.ox.ac.uk`). We use the email
     * suffix as a first-pass gate — matches how most UK student-discount
     * services (Railcard, Spotify Student, etc.) verify domain. Backend
     * write-path only; frontend UI also hides the Student plan for
     * ineligible users but this method is the source of truth.
     */
    public function isEligibleForStudentPlan(): bool
    {
        $email = strtolower(trim((string) $this->email));

        return str_ends_with($email, '.ac.uk');
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
     * The spouse's id, but only while their account is live.
     *
     * `spouse_id` deliberately survives the spouse deleting their account —
     * everything is retained for regulatory purposes — but from that moment the
     * surviving partner must stop seeing their data. Reading the raw column
     * answers "were these two ever linked"; this answers "may I show their
     * information", which is the question every consumer of shared spouse data
     * actually has. The soft-delete filter on the relation does the work.
     */
    public function liveSpouseId(): ?int
    {
        return $this->liveSpouse()?->id;
    }

    /**
     * The spouse's id while they are still the spouse, live account or not.
     *
     * There are THREE spouse questions in this application and they are not
     * interchangeable. Pick by what you actually need to know:
     *
     * | Question | Method | On account deletion |
     * |---|---|---|
     * | May I show their data? | `liveSpouseId()` | null |
     * | May I attach a `joint_owner_id`? | `hasReciprocalSpouseLink()` | false |
     * | **Are these two married?** | **this** | **unchanged** |
     *
     * The first two must go dark when an account is deleted. This one must not:
     * deleting an account is not a divorce, and `spouse_id` is retained on both
     * sides precisely so the fact survives. The link ends when it is genuinely
     * broken, which nulls the column on both sides (`FamilyMembersController`).
     *
     * **Do NOT route this through `hasReciprocalSpouseLink()`.** It looks like the
     * obvious consolidation and is not: its existence check runs under the
     * `SoftDeletes` global scope, so it returns false once the spouse's account
     * goes. W-0368 measured what that costs — IHTA 1984 s161 values a spouse's
     * related property on a substituted basis, so asking this question through a
     * soft-delete-scoped answer let a deleted account switch an undivided-share
     * discount ON over a spouse's share and understate Inheritance Tax. Pinned by
     * `DeletedSpouseVisibilityTest`.
     */
    public function spouseIdRegardlessOfAccountState(): ?int
    {
        return $this->spouse_id === null ? null : (int) $this->spouse_id;
    }

    /**
     * The spouse's account while it is live, or null once it is deleted.
     *
     * Resolved WITHOUT lazy loading — `Model::preventLazyLoading()` is on, so
     * reaching for `$this->spouse` on a model loaded as part of a collection
     * throws. Uses the eager-loaded relation when there is one, queries
     * explicitly when there is not, and caches the result so repeated calls in
     * a request cost one query rather than one each.
     */
    public function liveSpouse(): ?self
    {
        if ($this->spouse_id === null) {
            return null;
        }

        if ($this->relationLoaded('spouse')) {
            return $this->getRelation('spouse');
        }

        if (! $this->liveSpouseResolved) {
            $this->liveSpouseCache = $this->spouse()->first();
            $this->liveSpouseResolved = true;
        }

        return $this->liveSpouseCache;
    }

    /**
     * Cached deliberately OUTSIDE the relation registry. Calling setRelation()
     * here would flip relationLoaded('spouse') to true, and UserResource builds
     * `has_spouse` before its `spouse` block — so merely asking whether the
     * spouse is live would have started including their id, name and email in
     * every payload that previously omitted them.
     */
    private ?self $liveSpouseCache = null;

    private bool $liveSpouseResolved = false;

    /**
     * THE single authorization rule for attaching a joint_owner_id (Rule 20):
     * an attached id grants the linked account visibility of the record, so
     * only the user's reciprocally linked spouse qualifies. A joint record
     * with NO id (co-owner not on the platform) is first-class app-wide and
     * needs no authorization — callers must not require an id.
     */
    public function hasReciprocalSpouseLink(int $candidateId): bool
    {
        return (int) $this->spouse_id === $candidateId
            && static::query()->whereKey($candidateId)->where('spouse_id', $this->id)->exists();
    }

    /**
     * The spouse's account when the link is live AND reciprocal — the only spouse
     * a caller may read financial records from, or write records into.
     *
     * **W-0350 — one helper, because five idioms for one question is why the census
     * that found this needed four agents rather than one grep.** `spouse_id` is a
     * column written ABOUT the account holder: "I say N is my spouse" is not "N's
     * records are mine". Reading it raw, or through `liveSpouse()`, answers a
     * different question — `User` soft-deletes, so `liveSpouse()` already excludes a
     * deleted partner and buys almost nothing over the raw column for authorization.
     * **The live hole is the one-sided link, and only reciprocity closes it.**
     *
     * Promoted from `LifeCoverReach::coveringSpouse()`, which was the only reader in
     * the application that got this right first time, and hand-rolled in
     * `MilestoneDetectionService`, which got it right and wrote it out again.
     *
     * **Not for `hasAcceptedSpousePermission()`'s job.** That asks whether the couple
     * have agreed to share financial data; this asks whether the couple exist. A
     * reader of financial data wants both, and they are separate questions.
     *
     * **Not for `spouseIdRegardlessOfAccountState()`'s job either** — see its docblock
     * for why "are these two married?" must survive a deleted account.
     */
    public function reciprocalLiveSpouse(): ?self
    {
        $spouse = $this->liveSpouse();

        if ($spouse === null || ! $this->hasReciprocalSpouseLink($spouse->id)) {
            return null;
        }

        return $spouse;
    }

    /**
     * May this account's figures be pooled with its spouse's? — **W-0529, CSJ 2026-08-29.**
     *
     * ONE derivation of `$dataSharingEnabled`, because there were **eight**, in six
     * shapes, for one question:
     *
     * - `$spouse !== null` alone (`EstateAgent`, twice) — no consent at all, so Fyn
     *   pooled an estate the screen would not have pooled and quoted a different figure
     *   from the one the user was looking at. **This is the one CSJ ruled on.**
     * - `hasAcceptedSpousePermission()` alone (`HouseholdPlanningService`, three times)
     *   — consent with no check that a spouse is there to consent.
     * - Four more spellings of "a spouse, and permission", each correct and each
     *   written out again.
     *
     * Reciprocity is folded in because the two questions are one decision at the point
     * of use: pooling reads the other account's financial records, so it needs both a
     * link they made too and their consent to share.
     *
     * **This answers whether to POOL, not whether they are married.** Callers must keep
     * resolving the spouse separately and pass it even when this is false — the estate
     * engine reads `$spouse` for `$isMarried`, and handing it null makes a couple report
     * as single, which is the misleading artefact W-0154 recorded as a near-miss.
     */
    public function sharesFinancialDataWithSpouse(): bool
    {
        return $this->financiallySharedSpouse() !== null;
    }

    /**
     * The spouse whose financial records this account may read — **W-0530**.
     *
     * The same rule as `sharesFinancialDataWithSpouse()` in the shape most callers want,
     * so a reader asks once instead of resolving the spouse and then asking whether it
     * may look at them. The boolean is expressed in terms of THIS rather than the other
     * way round, so there is still one derivation.
     *
     * **Financial reads only.** Identity and family reads — who the spouse is, the
     * couple's children — stop at `reciprocalLiveSpouse()`. `DependantsReach`'s docblock
     * makes the argument and it is right: the permission gate governs financial data,
     * and a child is not that. Consent decides what may be READ ABOUT MONEY; reciprocity
     * decides whether the couple exist at all.
     */
    public function financiallySharedSpouse(): ?self
    {
        $spouse = $this->reciprocalLiveSpouse();

        if ($spouse === null || ! $this->hasAcceptedSpousePermission()) {
            return null;
        }

        return $spouse;
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
        return $this->hasMany(Liability::class);
    }

    /**
     * Get the user's trusts (Estate module).
     */
    public function trusts(): HasMany
    {
        return $this->hasMany(Trust::class);
    }

    /**
     * Get the user's IHT profile (Estate module).
     */
    public function ihtProfile(): HasOne
    {
        return $this->hasOne(IHTProfile::class);
    }

    /**
     * Get the user's estate assets (Estate module).
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get the user's gifts (Estate module).
     */
    public function gifts(): HasMany
    {
        return $this->hasMany(Gift::class);
    }

    /**
     * Get the user's Lasting Powers of Attorney (Estate module).
     */
    public function lastingPowersOfAttorney(): HasMany
    {
        return $this->hasMany(LastingPowerOfAttorney::class);
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
        return $this->hasMany(InvestmentAccount::class);
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
     * SaveTax campaign — household tax-strategy inputs (spouse data).
     */
    public function taxStrategyHouseholdInput(): HasOne
    {
        return $this->hasOne(TaxStrategyHouseholdInput::class);
    }

    public function gamification(): HasOne
    {
        return $this->hasOne(UserGamification::class);
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
     * Whether this user may see their spouse's data.
     *
     * There used to be a shortcut here: linked, and both `married`, meant
     * sharing was on with no permission record at all. It was added to fix
     * "spouse data doesn't display even though accounts are linked during
     * onboarding" — a real symptom with a different cause. Linking forged the
     * consent (`SpouseLinkingService`) and the screen that would have collected
     * it was never mounted, so no permission record could ever exist; the
     * shortcut made the product work by making consent optional.
     *
     * Two things it broke. `marital_status` was writable by the OTHER party
     * under the old linking flow, so an attacker set both halves of its own
     * precondition (W-0347). And it made `DELETE /api/spouse-permission/revoke`
     * a no-op: the row went, the shortcut kept returning true, and a user who
     * withdrew sharing was still sharing. Revoke is now the invitee's remedy,
     * so it has to actually work.
     *
     * An accepted `spouse_permissions` row is the only thing that grants this.
     *
     * **W-0347 G2 — this paragraph asserted the opposite of what ships.** It said
     * existing links were backfilled with an accepted row "so no household lost
     * access on deploy", and named a migration that has since been DELETED. CSJ's
     * decision was to re-ask rather than to grandfather, so
     * `2026_08_24_130000_reask_spouse_permissions_nobody_granted` turns every row
     * nobody granted into an unanswered request: **every affected household DOES lose
     * access at release, until somebody accepts.** That is the intended behaviour, and
     * a docblock claiming a safeguard the code does not perform is precisely the
     * defect compliance flagged as F1 — here in the model that is the gate for the
     * whole application.
     */
    public function hasAcceptedSpousePermission(): bool
    {
        // No LIVE spouse — no sharing. The raw spouse_id survives the partner
        // deleting their account, deliberately: everything is retained for
        // regulatory purposes. Their `accepted` permission row is retained with
        // it, and the legacy fallback below used to find that row and keep
        // sharing switched on for an account that no longer exists. Measured on
        // csjones: three survivors, all returning true (CSJ decision D1/D2,
        // 2026-08-19 — retain the rows, ignore them at read time).
        $spouse = $this->liveSpouse();
        if ($spouse === null) {
            return false;
        }

        // Reciprocity first. A half-written link is what every gate in the
        // application mistakes for a real one, and it was forgeable until
        // W-0347 — the server wrote the other person's half.
        if ((int) $spouse->spouse_id !== (int) $this->id) {
            return false;
        }

        $permission = SpousePermission::where(function ($query) use ($spouse) {
            $query->where('user_id', $this->id)
                ->where('spouse_id', $spouse->id);
        })->orWhere(function ($query) use ($spouse) {
            $query->where('user_id', $spouse->id)
                ->where('spouse_id', $this->id);
        })
            // W-0347 F5 — a couple could hold a row in each direction, and this
            // read and `revoke()` both took `first()` with no order. Withdraw on
            // the row one query happens to find and the other still says yes.
            // The migration collapses the historic pairs; this makes the read
            // deterministic whatever arrives later.
            ->orderBy('id')
            ->first();

        // An explicit row is the answer whenever there is one — including a
        // withdrawal, which is why `revoke` now marks the row rather than
        // deleting it. Deleting it left no trace of the decision, and absence
        // read as "never asked", so revoking put sharing straight back on.
        if ($permission !== null) {
            return $permission->status === 'accepted';
        }

        // No row at all: a reciprocal link that predates the consent flow, or
        // one built by a seeder or a test. Honoured. Since W-0347 a reciprocal
        // link cannot be created without someone accepting — `accept()` writes
        // both halves together, and nothing else does — so a link with no row
        // is history, not a bypass.
        //
        // W-0347 G9 — this default is FAIL-OPEN, and the re-ask migration closes it
        // for existing data only by giving every reciprocal pair a row. The branch
        // stays live, so any future path that creates a reciprocal link WITHOUT a row
        // silently grants consent, in the method whose whole job is to be the gate.
        // Left as is because inverting it is a behaviour change that would also cut
        // off seeded and test data; named here so the next person choosing sees it.
        return true;
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

        $arrivalDate = Carbon::parse($this->uk_arrival_date);
        $now = Carbon::now();

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

    /**
     * Account is scheduled for deletion but not yet executed.
     */
    public function isScheduledForDeletion(): bool
    {
        return $this->deletion_scheduled_for !== null
            && $this->deleted_at === null;
    }

    /**
     * Account is currently in the deleted state and within the retention window
     * (i.e. data still on disk and the row is soft-deleted, not legacy-purged).
     */
    public function canBeRestored(): bool
    {
        return $this->trashed()
            && $this->deletion_reason !== 'legacy_purged'
            && ($this->purge_eligible_at === null || $this->purge_eligible_at->isFuture());
    }

    /**
     * Promote this user to advisor. Replaces the DB::table()->update workaround
     * used by AdvisorClientSeeder and exposes a semantic entry point for any
     * future advisor-onboarding flow.
     */
    public function markAsAdvisor(): bool
    {
        $this->is_advisor = true;

        return $this->save();
    }
}
