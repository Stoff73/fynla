<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * T25 (walk 2026-10-11, Q1, Q9, N7): what the user never gave was stored as
 * fact. Production mortgages 386 and 387 hold lender "To be completed", type
 * "repayment" (Brett's was interest-only), rate 0.0000 and payment 0.00;
 * property 531 holds city "Unknown" and postcode "N/A". Not given is null,
 * shown "Not recorded" (as R10 did for savings rates).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

it('stores nothing the user did not give when a property form names only a mortgage balance', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false, 'onboarding_completed' => false, 'first_name' => 'Priya',
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, 'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'income' => '50271_100000', 'assets' => ['property']],
    ]);
    FynStreamHarness::fake()->bind();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
    $form = ['name' => CaptureForms::PROPERTY, 'answers' => ['main_residence' => ['current_value' => 725000, 'mortgage_outstanding_balance' => 310000, 'ownership_type' => 'individual']]];

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise($form, $user), null, true, $form), false);

    $property = Property::where('user_id', $user->id)->sole();
    $mortgage = Mortgage::where('property_id', $property->id)->sole();
    expect(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($property->city)->toBeNull()
        ->and($property->postcode)->toBeNull()
        ->and($mortgage->lender_name)->toBeNull()
        ->and($mortgage->mortgage_type)->toBeNull()
        ->and($mortgage->interest_rate)->toBeNull()
        ->and($mortgage->monthly_payment)->toBeNull()
        ->and((float) $mortgage->outstanding_balance)->toBe(310000.0);
});

it('stores nothing the user did not give when Fyn adds a mortgage with only a balance', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $property = Property::factory()->create(['user_id' => $user->id, 'property_type' => 'main_residence', 'ownership_type' => 'individual', 'ownership_percentage' => 100]);

    $result = app(CoordinatingAgent::class)->executeTool('create_mortgage', ['outstanding_balance' => 180000], $user);

    $mortgage = Mortgage::where('property_id', $property->id)->sole();
    expect($result['error'] ?? false)->toBeFalse()
        ->and($mortgage->lender_name)->toBeNull()
        ->and($mortgage->mortgage_type)->toBeNull()
        ->and($mortgage->interest_rate)->toBeNull()
        ->and($mortgage->monthly_payment)->toBeNull();
});
