<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('does not manufacture a date of birth from an age', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Parent',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_DEPENDANTS,
        'household_id' => null,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // Mock the coordinating agent so we never hit the LLM for the grouped
    // extract turn — the director falls back to its deterministic parser
    // for dependant age strings.
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')->zeroOrMoreTimes()
        ->andReturnUsing(function () {
            yield ['type' => 'done'];
        });
    // FamilyMember observer fires invalidateUserCache on create — allow it.
    $mock->shouldReceive('invalidateUserCache')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    // Use the private method directly — calling handleUserMessage would
    // go through the whole state machine which is out of scope for this
    // assertion. ReflectionMethod is the cheapest path.
    $director = app(OnboardingChatDirector::class);
    $createDependants = new ReflectionMethod($director, 'createDependantFamilyMembers');
    $createDependants->setAccessible(true);
    $createDependants->invoke($director, $user, 'Sam aged 8');

    expect(FamilyMember::where('user_id', $user->id)->count())->toBe(0);
});

it('stores an explicitly supplied dependant date exactly', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Parent',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL,
        'household_id' => null,
    ]);

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('invalidateUserCache')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    $director = app(OnboardingChatDirector::class);
    $createDependants = new ReflectionMethod($director, 'createDependantFamilyMembers');
    $createDependants->setAccessible(true);
    $createDependants->invoke($director, $user, 'Sam, born 14 September 2017');

    $child = FamilyMember::where('user_id', $user->id)->firstOrFail();
    expect($child->first_name)->toBe('Sam')
        ->and($child->date_of_birth->format('Y-m-d'))->toBe('2017-09-14')
        ->and($child->relationship)->toBe('child')
        ->and($child->household_id)->not->toBeNull();
});

it('does not normalise an impossible dependant date into a different day', function (string $date) {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL,
    ]);
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('invalidateUserCache')->zeroOrMoreTimes();
    $this->instance(CoordinatingAgent::class, $mock);

    $director = app(OnboardingChatDirector::class);
    $createDependants = new ReflectionMethod($director, 'createDependantFamilyMembers');
    $createDependants->setAccessible(true);
    $createDependants->invoke($director, $user, "Sam, born {$date}");

    expect(FamilyMember::where('user_id', $user->id)->count())->toBe(0);
})->with([
    'impossible numeric date' => '31/02/2015',
    'impossible textual date' => '31 February 2015',
]);

it('rejects an age-only dependant tool call without writing a row', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_dependants', [
        'dependants' => [[
            'first_name' => 'Sam',
            'age' => 8,
            'relationship' => 'child',
        ]],
    ], $user);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and($result['details']['missing'] ?? [])->toContain('dependants.0.date_of_birth')
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(0);
});

it('rejects a model-inferred dependant date when the user supplied only an age', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Sam is 8 years old.',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_dependants', [
        'dependants' => [[
            'first_name' => 'Sam',
            'date_of_birth' => '2018-01-01',
            'relationship' => 'child',
        ]],
    ], $user, $conversation->id);

    expect($result['clarification_required'] ?? false)->toBeTrue()
        ->and($result['missing'] ?? [])->toContain('dependants.0.date_of_birth')
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(0);
});

it('preserves an explicitly stated dependant name when the model omits it', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Sam was born on 14 September 2017.',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_dependants', [
        'dependants' => [[
            'first_name' => null,
            'date_of_birth' => '2017-09-14',
            'relationship' => 'child',
        ]],
    ], $user, $conversation->id);

    expect($result['onboarding_capture'] ?? false)->toBeTrue()
        ->and(FamilyMember::where('user_id', $user->id)->sole()->first_name)->toBe('Sam');
});

it('provisions the household before the grouped dependant tool writes', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'household_id' => null,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_dependants', [
        'dependants' => [[
            'first_name' => 'Sam',
            'date_of_birth' => '2017-09-14',
            'relationship' => 'child',
        ]],
    ], $user);

    $child = FamilyMember::where('user_id', $user->id)->sole();
    expect($result['onboarding_capture'] ?? false)->toBeTrue()
        ->and($user->fresh()->household_id)->not->toBeNull()
        ->and($child->household_id)->toBe($user->fresh()->household_id);
});

it('computes age from an exact dependant date of birth', function () {
    $child = new FamilyMember([
        'first_name' => 'Sam',
        'date_of_birth' => now()->subYears(8)->subMonth()->toDateString(),
        'relationship' => 'child',
    ]);

    expect($child->age)->toBe(8);
});

it('serialises the computed age onto toArray so the UI sees it', function () {
    $user = User::factory()->create();
    $child = FamilyMember::create([
        'user_id' => $user->id,
        'household_id' => null,
        'relationship' => 'child',
        'first_name' => 'Sam',
        'date_of_birth' => now()->subYears(8)->subMonth()->toDateString(),
        'is_dependent' => true,
    ]);

    $array = $child->toArray();

    expect($array)->toHaveKey('age');
    expect($array['age'])->toBe(8);
});
