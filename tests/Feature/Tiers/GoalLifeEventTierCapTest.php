<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\User;
use App\Services\Stores\GoalStore;
use App\Services\Stores\IngestSource;
use App\Services\Stores\LifeEventStore;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

function goalPayload(string $name): array
{
    return [
        'goal_name' => $name,
        'goal_type' => 'holiday',
        'target_amount' => 5000,
        'target_date' => now()->addYear()->toDateString(),
        'priority' => 'medium',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ];
}

function lifeEventPayload(string $name): array
{
    return [
        'event_name' => $name,
        'event_type' => 'bonus',
        'amount' => 1000,
        'impact_type' => 'income',
        'expected_date' => now()->addYear()->toDateString(),
        'certainty' => 'likely',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ];
}

it('allows a second Free Goal over HTTP and returns tier_limit_reached on the third', function () {
    $user = User::factory()->create(['tier' => 'free']);
    Goal::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/goals', goalPayload('Second Goal'))
        ->assertCreated();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/goals', goalPayload('Third Goal'))
        ->assertForbidden()
        ->assertJsonPath('error', 'tier_limit_reached')
        ->assertJsonPath('entity_key', 'goal')
        ->assertJsonPath('hard_limit', 2);

    expect(Goal::forUserOrJoint($user->id)->count())->toBe(2);
});

it('blocks a second Free Life Event over HTTP', function () {
    $user = User::factory()->create(['tier' => 'free']);
    LifeEvent::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/life-events', lifeEventPayload('Second Event'))
        ->assertForbidden()
        ->assertJsonPath('error', 'tier_limit_reached')
        ->assertJsonPath('entity_key', 'life_event')
        ->assertJsonPath('hard_limit', 1);

    expect(LifeEvent::forUserOrJoint($user->id)->count())->toBe(1);
});

it('returns structured cap responses from Fyn direct writes', function (string $tool, array $input, string $model, int $cap) {
    $user = User::factory()->create(['tier' => 'free']);

    if ($model === Goal::class) {
        Goal::factory($cap)->create(['user_id' => $user->id]);
    } else {
        LifeEvent::factory($cap)->create(['user_id' => $user->id]);
    }

    $result = app(CoordinatingAgent::class)->executeTool($tool, $input, $user);

    expect($result)->toMatchArray([
        'error' => true,
        'error_type' => 'tier_limit_reached',
        'hard_limit' => $cap,
        'required_tier' => 'premium',
    ])->and($model::forUserOrJoint($user->id)->count())->toBe($cap);
})->with([
    'Goal' => [
        'create_goal',
        [
            'name' => 'Third Goal',
            'goal_type' => 'holiday',
            'target_amount' => 5000,
            'target_date' => '2028-01-01',
            'priority' => 'medium',
        ],
        Goal::class,
        2,
    ],
    'Life Event' => [
        'create_life_event',
        [
            'event_name' => 'Second Event',
            'event_type' => 'bonus',
            'event_date' => '2028-01-01',
            'estimated_amount' => 1000,
        ],
        LifeEvent::class,
        1,
    ],
]);

it('keeps Premium Goal and Life Event creation unlimited', function () {
    $user = User::factory()->withActivePremiumSubscription()->create();
    Goal::factory(3)->create(['user_id' => $user->id]);
    LifeEvent::factory(3)->create(['user_id' => $user->id]);

    app(GoalStore::class)->create(goalPayload('Fourth Goal'), $user, IngestSource::FORM);
    app(LifeEventStore::class)->create(lifeEventPayload('Fourth Event'), $user, IngestSource::FORM);

    expect(Goal::forUserOrJoint($user->id)->count())->toBe(4)
        ->and(LifeEvent::forUserOrJoint($user->id)->count())->toBe(4);
});

it('counts joint records once for a household member', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $owner = User::factory()->create(['tier' => 'free']);
    Goal::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
    ]);
    Goal::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/goals', goalPayload('Blocked Goal'))
        ->assertForbidden()
        ->assertJsonPath('current_count', 2);
});

it('lets an over-cap Free user read update and delete existing rows but blocks creation', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $goals = Goal::factory(3)->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/goals')
        ->assertOk()
        ->assertJsonPath('data.count', 3);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/goals/{$goals[0]->id}", ['goal_name' => 'Updated Goal'])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/goals/{$goals[1]->id}")
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/goals', goalPayload('Still Blocked'))
        ->assertForbidden()
        ->assertJsonPath('error', 'tier_limit_reached');

    expect(Goal::forUserOrJoint($user->id)->count())->toBe(2)
        ->and($goals[0]->fresh()->goal_name)->toBe('Updated Goal');
});
