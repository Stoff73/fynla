<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

/*
 * Every action opens its own card (design C, CSJ 2026-09-26). The card is built
 * once on the server from the same pipeline as the actions list, so its id and
 * title are the list's.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

function actionCardUserWithIsaHeadroom(): User
{
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_rental_income' => 0,
        'annual_other_income' => 0,
        'onboarding_completed' => true,
        'marital_status' => 'single',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'easy_access',
        'is_isa' => false,
        'current_balance' => 40000,
        'interest_rate' => 4.5,
        'ownership_type' => 'individual',
        'joint_owner_id' => null,
    ]);

    return $user;
}

function openActions($test): array
{
    return $test->getJson('/api/recommendations/actions')->assertOk()->json('data.open');
}

it('returns the card for an open tax action, with the title the list shows and the user figures', function () {
    $user = actionCardUserWithIsaHeadroom();
    Sanctum::actingAs($user);

    $item = collect(openActions($this))->first(fn ($i) => str_starts_with((string) $i['id'], 'tax_'));
    expect($item)->not->toBeNull();

    $card = $this->getJson('/api/recommendations/actions/'.rawurlencode($item['id']))->assertOk()->json('data');

    expect($card['id'])->toBe($item['id'])
        ->and($card['title'])->toBe($item['title'])
        ->and($card['module_label'])->toBe($item['module_label'])
        ->and($card['description'])->not->toBe('')
        ->and($card['key_figure']['label'])->toBe('Saves about')
        ->and($card['key_figure']['value'])->toMatch('/^£[\d,]+ a year$/')
        ->and($card['primary'])->toBe(['kind' => 'mark_done', 'recommendation_id' => $item['id']])
        ->and($card['done'])->toBeFalse();
});

it('404s an id that is not in the requesting user list', function () {
    $owner = actionCardUserWithIsaHeadroom();
    Sanctum::actingAs($owner);
    $id = collect(openActions($this))->first(fn ($i) => str_starts_with((string) $i['id'], 'tax_'))['id'];

    Sanctum::actingAs(User::factory()->create(['onboarding_completed' => true]));
    $this->getJson('/api/recommendations/actions/'.rawurlencode($id))->assertNotFound();
});

it('shows a completed action as done, not missing', function () {
    $user = actionCardUserWithIsaHeadroom();
    Sanctum::actingAs($user);
    $item = collect(openActions($this))->first(fn ($i) => $i['type'] === 'recommendation');

    $this->postJson('/api/recommendations/'.rawurlencode($item['id']).'/mark-done', [
        'module' => $item['module'],
        'recommendation_text' => $item['title'],
    ])->assertOk();

    $card = $this->getJson('/api/recommendations/actions/'.rawurlencode($item['id']))->assertOk()->json('data');

    expect($card['done'])->toBeTrue()
        ->and($card['completed_at'])->not->toBeNull()
        ->and($card['title'])->toBe($item['title']);
});

it('gives an unlock item the waiting-on-you shape', function () {
    Sanctum::actingAs(User::factory()->create(['onboarding_completed' => true]));
    $unlock = collect(openActions($this))->firstWhere('type', 'unlock');
    expect($unlock)->not->toBeNull();

    $card = $this->getJson('/api/recommendations/actions/'.rawurlencode($unlock['id']))->assertOk()->json('data');

    expect($card['why'])->toBe([])
        ->and($card['what_this_changes'])->not->toBeEmpty()
        ->and($card['primary']['kind'])->toBe('capture')
        ->and($card['primary']['prompt'])->toBe($unlock['action']['prompt']);
});

it('puts the strategy own figures in the why bullets, never a guessed sentence', function () {
    $user = actionCardUserWithIsaHeadroom();
    Sanctum::actingAs($user);
    $items = collect(app(\App\Services\Coordination\ComposedTaxPlanService::class)->forUser($user)['items'])->keyBy('type');
    expect($items->has('pension_tax_relief'))->toBeTrue();
    $pension = $items['pension_tax_relief'];

    $card = $this->getJson('/api/recommendations/actions/tax_pension_tax_relief')->assertOk()->json('data');

    expect($card['why'][0])->toContain('£'.number_format($pension['suggested_contribution']))
        ->and($card['why'][0])->toContain((string) ($pension['relief_rate'] * 100).'%')
        ->and($card['key_figure']['value'])->toBe('£'.number_format($pension['estimated_annual_tax_saved']).' a year');
});

it('keeps the page where a recommendation is actioned as the card go-to link', function () {
    // Review I5: rows now open the card, so the card carries the row's old
    // destination beside Mark as done.
    $user = actionCardUserWithIsaHeadroom();
    Sanctum::actingAs($user);
    $item = collect(openActions($this))->first(fn ($i) => $i['type'] === 'recommendation' && ($i['action']['kind'] ?? '') === 'navigate');
    expect($item)->not->toBeNull();

    $card = $this->getJson('/api/recommendations/actions/'.rawurlencode($item['id']))->assertOk()->json('data');

    expect($card['go_to'])->toBe(['destination' => $item['action']['destination'] ?? null, 'payload' => $item['action']['payload'] ?? null])
        ->and($card['primary']['kind'])->toBe('mark_done');
});
