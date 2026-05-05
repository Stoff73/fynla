<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B-4 — "Sam aged 8" → DOB saved as YYYY-01-01.
 *
 * This is intentionally approximate: when the user only provides an age,
 * the director sets date_of_birth to startOfYear in the year the child
 * was age-age. The notes column must explain the approximation so the
 * user can edit the exact DOB via UserProfile.vue if they want. This
 * test pins that contract: age-only messages never save a full-precision
 * DOB, but they also never lose the signal needed for the user to spot
 * and correct it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('saves age-only dependants with a Jan-1 DOB and an informative notes field', function () {
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
    $createDependants->invoke($director, $user, 'Sam aged 8 and Emily aged 5');

    $children = FamilyMember::where('user_id', $user->id)->orderBy('id')->get();
    expect($children)->toHaveCount(2);

    $currentYear = (int) now()->format('Y');

    // Sam: age 8 → DOB = Jan 1 of (currentYear - 8)
    expect($children[0]->date_of_birth->format('Y-m-d'))
        ->toBe(($currentYear - 8).'-01-01');
    expect($children[0]->relationship)->toBe('child');
    expect($children[0]->notes)->toContain('Age 8 inferred from');

    // Emily: age 5 → DOB = Jan 1 of (currentYear - 5)
    expect($children[1]->date_of_birth->format('Y-m-d'))
        ->toBe(($currentYear - 5).'-01-01');
    expect($children[1]->relationship)->toBe('child');
    expect($children[1]->notes)->toContain('Age 5 inferred from');

    // B-2 integration — household_id propagated correctly.
    expect($children[0]->household_id)->not->toBeNull();
    expect($children[1]->household_id)->toBe($children[0]->household_id);
});

it('computes the right age accessor from the Jan-1 fallback DOB', function () {
    $child = new FamilyMember([
        'first_name' => 'Sam',
        'date_of_birth' => now()->subYears(8)->startOfYear()->toDateString(),
        'relationship' => 'child',
    ]);

    // The age accessor must return the stable 8 even though the DOB is
    // Jan-1 rather than the true birthday. This is the contract B-3 pins.
    expect($child->age)->toBe(8);
});

it('serialises the computed age onto toArray so the UI sees it', function () {
    $user = User::factory()->create();
    $child = FamilyMember::create([
        'user_id' => $user->id,
        'household_id' => null,
        'relationship' => 'child',
        'first_name' => 'Sam',
        'date_of_birth' => now()->subYears(8)->startOfYear()->toDateString(),
        'is_dependent' => true,
    ]);

    $array = $child->toArray();

    expect($array)->toHaveKey('age');
    expect($array['age'])->toBe(8);
});
