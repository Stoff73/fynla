<?php

declare(strict_types=1);

namespace App\Http\Controllers\TestSupport;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\EmailVerificationCode;
use App\Models\ExpenditureProfile;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\InvestmentAccountValueSnapshot;
use App\Models\LifeEvent;
use App\Models\PendingRegistration;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class E2EController extends Controller
{
    public function verificationCode(Request $request): JsonResponse
    {
        $this->ensureE2EEnvironment();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $userId = User::query()
            ->where('email', $validated['email'])
            ->value('id');

        if ($userId !== null) {
            $code = EmailVerificationCode::query()
                ->where('user_id', $userId)
                ->whereNull('verified_at')
                ->where('expires_at', '>', now())
                ->where('failed_attempts', '<', 5)
                ->latest('id')
                ->value('code');
        } else {
            $code = PendingRegistration::query()
                ->where('email', $validated['email'])
                ->where('expires_at', '>', now())
                ->latest('id')
                ->value('verification_code');
        }

        abort_if($code === null, 404);

        return response()->json(['code' => $code]);
    }

    public function user(Request $request): JsonResponse
    {
        $this->ensureE2EEnvironment();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->select([
                'email',
                'email_verified_at',
                'is_preview_user',
                'onboarding_completed',
            ])
            ->where('email', $validated['email'])
            ->firstOrFail();

        return response()->json([
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'is_preview_user' => (bool) $user->is_preview_user,
            'onboarding_completed' => (bool) $user->onboarding_completed,
        ]);
    }

    public function restorableUser(Request $request): JsonResponse
    {
        $this->ensureE2EEnvironment();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::factory()->create([
            'first_name' => 'Returner',
            'surname' => 'Campaign',
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_preview_user' => false,
            'deletion_reason' => 'user_requested',
            'deletion_source' => 'settings',
            'purge_eligible_at' => now()->addYear(),
        ]);
        $user->delete();

        return response()->json([
            'email' => $user->email,
            'restorable' => true,
        ], 201);
    }

    public function activeUser(
        Request $request,
        InvestmentAccountStore $investmentAccountStore,
        PensionStore $pensionStore,
        PropertyStore $propertyStore,
        SavingsStore $savingsStore,
    ): JsonResponse {
        $this->ensureE2EEnvironment();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'tier' => ['sometimes', 'string', 'in:free,premium'],
            'with_balance_history' => ['sometimes', 'boolean'],
            'with_freemium_caps' => ['sometimes', 'boolean'],
        ]);

        $user = User::factory()->create([
            'first_name' => 'Existing',
            'surname' => 'Campaign',
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'is_preview_user' => false,
            'plan' => $validated['tier'] ?? 'free',
            'tier' => $validated['tier'] ?? 'free',
            'onboarding_completed' => true,
            'onboarding_fyn_step' => null,
        ]);

        if (($validated['tier'] ?? 'free') === 'premium') {
            Subscription::query()->create([
                'user_id' => $user->id,
                'plan' => 'premium',
                'billing_cycle' => 'monthly',
                'amount' => 699,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'status' => 'active',
                'auto_renew' => true,
            ]);
        }

        if ($validated['with_balance_history'] ?? false) {
            $account = $investmentAccountStore->create([
                'account_name' => 'Long-term portfolio',
                'account_type' => 'gia',
                'provider' => 'Test provider',
                'current_value' => 10000,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
            ], $user, IngestSource::FORM);
            InvestmentAccountValueSnapshot::query()
                ->where('investment_account_id', $account->id)
                ->update(['taken_at' => now()->subMonthsNoOverflow(13)]);
            $investmentAccountStore->update(
                $account->id,
                ['current_value' => 12500],
                $user,
                IngestSource::FORM,
            );
        }

        if ($validated['with_freemium_caps'] ?? false) {
            foreach (['Everyday savings', 'Emergency savings'] as $accountName) {
                $savingsStore->create([
                    'account_name' => $accountName,
                    'account_type' => 'easy_access',
                    'institution' => 'Test provider',
                    'current_balance' => 1000,
                    'is_isa' => false,
                    'ownership_type' => 'individual',
                    'ownership_percentage' => 100,
                ], $user, IngestSource::FORM);
            }
            InvestmentAccount::factory()->count(2)->create([
                'user_id' => $user->id,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
            ]);
            foreach (['Workplace pension', 'Personal pension'] as $schemeName) {
                $pensionStore->createDc([
                    'scheme_name' => $schemeName,
                    'pension_type' => 'personal',
                    'current_fund_value' => 1000,
                ], $user, IngestSource::FORM);
            }
            $propertyStore->create([
                'property_type' => 'main_residence',
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
                'address_line_1' => '1 Test Street',
                'current_value' => 250000,
            ], $user, IngestSource::FORM);
            Goal::factory()->count(2)->create(['user_id' => $user->id]);
            LifeEvent::factory()->create(['user_id' => $user->id]);
        }

        $token = $user->createToken('e2e-active-user', ['mfa_verified'])->plainTextToken;

        return response()->json([
            'email' => $user->email,
            'active' => true,
            'tier' => $user->tier,
            'token' => $token,
        ], 201);
    }

    public function freemiumCounts(
        Request $request,
        PensionStore $pensionStore,
        PropertyStore $propertyStore,
        SavingsStore $savingsStore,
    ): JsonResponse {
        $this->ensureE2EEnvironment();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $user = User::query()->where('email', $validated['email'])->firstOrFail();
        $pensions = $pensionStore->forUser($user);

        return response()->json([
            'savings_account' => $savingsStore->countForUser($user),
            'investment' => InvestmentAccount::query()->where('user_id', $user->id)->count(),
            'pension_account' => $pensions['dc']->count() + $pensions['db']->count(),
            'property' => $propertyStore->forUser($user)->count(),
            'goal' => Goal::query()->where('user_id', $user->id)->count(),
            'life_event' => LifeEvent::query()->where('user_id', $user->id)->count(),
        ]);
    }

    public function onboardingScenarioUser(Request $request, ConsentService $consentService): JsonResponse
    {
        $this->ensureE2EEnvironment();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'scenario' => ['required', 'string', 'in:spouse_skip,dependant_exact'],
        ]);
        $step = $validated['scenario'] === 'spouse_skip'
            ? OnboardingStateMachine::STATE_BASE_SPOUSE
            : OnboardingStateMachine::STATE_BASE_DEPENDANTS;

        $user = User::factory()->create([
            'first_name' => 'Scenario',
            'surname' => 'Campaign',
            'email' => $validated['email'],
            'email_verified_at' => now(),
            'is_preview_user' => false,
            'onboarding_completed' => false,
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
            'onboarding_fyn_step' => $step,
            'marital_status' => $validated['scenario'] === 'spouse_skip' ? 'married' : 'single',
            'funnel_answers' => [
                'campaign' => 'savetax',
                'spouse' => $validated['scenario'] === 'spouse_skip' ? 'yes' : 'no',
            ],
        ]);
        $consentService->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
        AiConversation::query()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
            'metadata' => ['source' => 'fyn_onboarding'],
        ]);
        $token = $user->createToken('e2e-mobile-scenario', ['mfa_verified'])->plainTextToken;

        return response()->json([
            'email' => $user->email,
            'token' => $token,
            'scenario' => $validated['scenario'],
        ], 201);
    }

    public function resetRegistrationThrottle(Request $request): JsonResponse
    {
        $this->ensureE2EEnvironment();

        $key = 'api/auth/register|'.$request->ip();
        RateLimiter::clear(md5('auth-5'.$key));
        RateLimiter::clear('auth-5:'.$key);

        return response()->json(['reset' => true]);
    }

    public function campaignState(Request $request, SavingsStore $savingsStore): JsonResponse
    {
        $this->ensureE2EEnvironment();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $user = User::query()->where('email', $validated['email'])->firstOrFail();
        $expenditure = ExpenditureProfile::query()->where('user_id', $user->id)->first();

        return response()->json([
            'onboarding_step' => $user->onboarding_fyn_step,
            'onboarding_completed' => (bool) $user->onboarding_completed,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'marital_status' => $user->marital_status,
            'spouse_id' => $user->spouse_id,
            'monthly_expenditure' => (float) ($user->monthly_expenditure ?? 0),
            'profile_monthly_expenditure' => (float) ($expenditure?->total_monthly_expenditure ?? 0),
            'savings' => $savingsStore->forUser($user)
                ->map(fn ($account): array => $account->only([
                    'account_name',
                    'account_type',
                    'is_isa',
                    'ownership_type',
                    'current_balance',
                ]))
                ->values()
                ->all(),
            'family_members' => FamilyMember::query()
                ->where('user_id', $user->id)
                ->get(['relationship', 'first_name', 'date_of_birth'])
                ->map(fn (FamilyMember $member): array => [
                    'relationship' => $member->relationship,
                    'first_name' => $member->first_name,
                    'date_of_birth' => $member->date_of_birth?->toDateString(),
                    'age' => $member->age,
                ])
                ->all(),
        ]);
    }

    private function ensureE2EEnvironment(): void
    {
        abort_unless(app()->environment('e2e'), 404);
    }
}
