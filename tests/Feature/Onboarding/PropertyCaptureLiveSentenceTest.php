<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\Property;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * Live prod conversation 874 (CSJ, 2026-09-15 14:39 BST), replayed: at the
 * Save Tax property step the model refused twice and the backstop sent a
 * home with no figures marked "individual"; the gate refused everything and
 * the user saw "That sounds like something worth saving. Want me to save it
 * to your plan now?". The contract: the buy to let lands, the home asks for
 * its share (property is the one type whose share genuinely varies), the
 * next reply lands the home, and the offer never appears at a capture step.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

afterEach(function (): void {
    Mockery::close();
});

const LIVE_PROPERTY_SENTENCE = 'My home which I own with my wife, worth 750000 with a mortgage of 325000 and a buy to let worth 450000 mortgage of 100000 rental income of 1000 per month I own this myself';
const CANNED = 'I can only help with financial planning questions. How can I assist with your finances?';

function propertyStepUser(bool $premium = false): User
{
    $factory = $premium ? User::factory()->withActivePremiumSubscription() : User::factory();

    return $factory->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'marital_status' => 'married',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['property']],
    ]);
}

it('lands the buy to let, asks only for the home\'s share, and never offers to "save it to your plan"', function (): void {
    $user = propertyStepUser();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();

    FynStreamHarness::fake()->textTurn(CANNED)->textTurn(CANNED)->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, LIVE_PROPERTY_SENTENCE
    ), false);
    $shown = collect($events)->where('type', 'content')->pluck('text')->implode(' ');

    $btl = Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->first();
    expect($btl)->not->toBeNull('buy to let not saved; shown: '.$shown)
        ->and((float) $btl->current_value)->toBe(450000.0)
        ->and((float) $btl->outstanding_mortgage)->toBe(100000.0)
        ->and((float) $btl->monthly_rental_income)->toBe(1000.0)
        ->and($btl->ownership_type)->toBe('individual')
        ->and(Property::where('user_id', $user->id)->where('property_type', 'main_residence')->exists())->toBeFalse()
        ->and($shown)->toContain('ownership share')
        ->and($shown)->not->toContain('worth saving')
        ->and($shown)->not->toContain('I can only help')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);
});

it('lands the home as joint at the stated share on the next reply', function (): void {
    $user = propertyStepUser(premium: true);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();

    FynStreamHarness::fake()->textTurn(CANNED)->textTurn(CANNED)->textTurn(CANNED)->textTurn(CANNED)->bind();
    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, LIVE_PROPERTY_SENTENCE), false);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user->fresh(), $conversation->fresh(), '50%'), false);
    $shown = collect($events)->where('type', 'content')->pluck('text')->implode(' ');

    $home = Property::where('user_id', $user->id)->where('property_type', 'main_residence')->first();
    expect($home)->not->toBeNull('home not saved on the share reply; shown: '.$shown)
        ->and((float) $home->current_value)->toBe(750000.0)
        ->and((float) $home->outstanding_mortgage)->toBe(325000.0)
        ->and($home->ownership_type)->toBe('joint')
        ->and((float) $home->ownership_percentage)->toBe(50.0)
        ->and(Property::where('user_id', $user->id)->count())->toBe(2)
        ->and($shown)->not->toContain('worth saving');
});

it('lets a Free user hold a home and a buy to let — the plan allows two properties', function (): void {
    // CSJ 2026-09-15: Free holds two properties (TierConfigurationSeeder +
    // the 2026_09_15_170000 migration). The buy to let took one slot on the
    // first turn; the home lands on the share reply.
    $user = propertyStepUser();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();

    FynStreamHarness::fake()->textTurn(CANNED)->textTurn(CANNED)->textTurn(CANNED)->textTurn(CANNED)->bind();
    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, LIVE_PROPERTY_SENTENCE), false);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user->fresh(), $conversation->fresh(), '50%'), false);
    $shown = collect($events)->where('type', 'content')->pluck('text')->implode(' ');

    expect(Property::where('user_id', $user->id)->count())->toBe(2, 'shown: '.$shown)
        ->and($shown)->not->toContain('property limit')
        ->and($shown)->not->toContain('worth saving');
});

it('lands the home when the model answers the share reply with every unstated field as null and an invented co-owner', function (): void {
    // The live csjones "50%" turn (user 396, 2026-09-15 16:15): the model called
    // create_property with tenure_type null and joint_owner_name "Worth". The
    // write must land with the default tenure and no invented name.
    $user = propertyStepUser(premium: true);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();

    FynStreamHarness::fake()
        ->textTurn(CANNED)->textTurn(CANNED)
        ->toolTurn('create_property', [
            'city' => null, 'county' => null, 'postcode' => null, 'trust_id' => null, 'tenure_type' => null,
            'has_mortgage' => true, 'current_value' => 750000, 'mortgage_type' => null, 'property_type' => 'main_residence',
            'address_line_1' => null, 'joint_owner_id' => null, 'ownership_type' => 'joint', 'joint_owner_name' => 'Worth',
            'ownership_percentage' => 50, 'monthly_rental_income' => null, 'mortgage_outstanding_balance' => 325000,
        ], 'toolu_home')
        ->bind();
    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, LIVE_PROPERTY_SENTENCE), false);
    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user->fresh(), $conversation->fresh(), '50%'), false);
    $shown = collect($events)->where('type', 'content')->pluck('text')->implode(' ');

    $home = Property::where('user_id', $user->id)->where('property_type', 'main_residence')->first();
    expect($home)->not->toBeNull('home not saved; shown: '.$shown)
        ->and($home->tenure_type)->toBe('freehold')
        ->and($home->joint_owner_name)->toBeNull()
        ->and((float) $home->ownership_percentage)->toBe(50.0)
        ->and(Property::where('user_id', $user->id)->count())->toBe(2)
        ->and($shown)->not->toContain('try again');
});
