<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

it('denies Free users at every implemented Premium capability boundary', function (string $method, string $uri) {
    $user = User::factory()->create(['tier' => 'free']);

    $response = $this->actingAs($user, 'sanctum')->json($method, $uri);

    $response->assertForbidden()
        ->assertJsonPath('error', 'capability_denied')
        ->assertJsonPath('required_tier', 'premium');
})->with([
    'Estate Planning' => ['POST', '/api/estate/profile'],
    'Holistic Plan' => ['POST', '/api/holistic/analyze'],
    'What If' => ['POST', '/api/what-if-scenarios'],
    'statement extraction' => ['POST', '/api/documents/upload'],
    'document upload' => ['POST', '/api/documents/upload-only'],
    'investment cost analysis' => ['GET', '/api/investment/fees/analyze'],
    'joint household view' => ['GET', '/api/household/net-worth'],
    // W-0426 — named as a WRITE, because that is all this capability gates. The two
    // GET rows above are the capabilities whose routes sit outside the read-only
    // excluded prefixes and so are genuinely read-gated. A GET row for the letter is
    // deliberately absent: asserting a 403 there would assert a behaviour the
    // application does not have. The read-side hole is covered by the reflection test
    // below, which compares the two lists rather than probing one endpoint.
    'Letter to Spouse (write only — see W-0426)' => ['PUT', '/api/user/letter-to-spouse'],
]);

it('does not stop Premium requests at the capability boundary', function (string $method, string $uri) {
    $user = User::factory()->withActivePremiumSubscription()->create();

    $response = $this->actingAs($user, 'sanctum')->json($method, $uri);

    expect($response->status())->not->toBe(403)
        ->and($response->json('error'))->not->toBe('capability_denied');
})->with([
    'Estate Planning' => ['POST', '/api/estate/profile'],
    'Holistic Plan' => ['POST', '/api/holistic/analyze'],
    'What If' => ['POST', '/api/what-if-scenarios'],
    'statement extraction' => ['POST', '/api/documents/upload'],
    'document upload' => ['POST', '/api/documents/upload-only'],
    'investment cost analysis' => ['GET', '/api/investment/fees/analyze'],
    'joint household view' => ['GET', '/api/household/net-worth'],
    'Letter to Spouse' => ['PUT', '/api/user/letter-to-spouse'],
]);

it('does not let the letter financial position outrun the letter itself for a Free user', function () {
    // `CheckSubscription::READ_ONLY_EXCLUDED_PATHS` contains `api/user/`, and
    // `isExcludedPath()` short-circuits on any GET BEFORE `checkCapability()`
    // runs. So the `letter_to_spouse` capability is write-only in practice, and
    // `GET /api/user/letter-to-spouse` has never been gated either (W-0426).
    //
    // That is a product decision about the letter, not about this route. What
    // this case pins is the property that IS mine to guarantee: the new
    // financial-position endpoint is **never more permissive than the letter it
    // belongs to**, whichever way that decision goes. Asserting a flat 403 here
    // would assert a behaviour the application does not have.
    $free = User::factory()->create(['tier' => 'free']);

    $letter = $this->actingAs($free, 'sanctum')->getJson('/api/user/letter-to-spouse');
    $position = $this->actingAs($free, 'sanctum')->getJson('/api/user/letter-to-spouse/financial-position');

    expect($position->status())->toBe($letter->status());

    // And if the letter is ever closed to Free, this must close with it rather
    // than becoming the way round the gate.
    if ($letter->status() === 403) {
        expect($position->json('error'))->toBe($letter->json('error'));
    }
});

/**
 * W-0426 acceptance 2. **A dataset of writes cannot see a read-side hole**, and this
 * file's datasets were writes plus two already-gated GETs — so a test named after
 * capability enforcement went green over an ungated read for as long as the hole
 * existed.
 *
 * `CheckSubscription::handle()` returns at `isExcludedPath()` BEFORE the capability map
 * is consulted, so any capability whose route sits under a `READ_ONLY_EXCLUDED_PATHS`
 * prefix is **unreachable for GET by construction** — not by oversight at the call site,
 * which is why reading either list alone shows nothing wrong.
 *
 * This compares the two lists directly. It does not assert that reads SHOULD be gated —
 * whether Letter to Loved Ones is premium-to-read is CSJ's call — it asserts that the
 * set of capabilities in that state is the one we know about. A new capability landing
 * under an excluded prefix turns this red on the day it lands, instead of shipping a
 * silent read-side hole with a green suite.
 */
it('names every capability whose reads the excluded prefixes make unreachable', function () {
    $middleware = new ReflectionClass(\App\Http\Middleware\CheckSubscription::class);
    $capabilityMap = $middleware->getConstant('CAPABILITY_ROUTE_MAP');
    $readOnlyExcluded = $middleware->getConstant('READ_ONLY_EXCLUDED_PATHS');

    $unreachableForGet = [];
    foreach ($capabilityMap as $routePrefix => $entityKey) {
        foreach ($readOnlyExcluded as $excludedPrefix) {
            if (str_starts_with($routePrefix, $excludedPrefix)) {
                $unreachableForGet[$routePrefix] = $entityKey;
                break;
            }
        }
    }

    // The one known instance, pending the product decision on W-0426 acceptance 1.
    // If reads are gated, narrow `READ_ONLY_EXCLUDED_PATHS` to the specific paths a
    // churned PAID user needs — profile, settings, subscription — rather than removing
    // the entry, which is the defect that exclusion was added to prevent.
    expect($unreachableForGet)->toBe([
        'api/user/letter-to-spouse' => 'letter_to_spouse',
    ]);
});

it('removes detailed expenditure fields from Free responses', function () {
    $free = User::factory()->create([
        'tier' => 'free',
        'monthly_expenditure' => 1000,
        'food_groceries' => 300,
    ]);

    $this->actingAs($free, 'sanctum')
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonMissingPath('data.user.food_groceries');

    $this->actingAs($free, 'sanctum')
        ->getJson('/api/user/profile')
        ->assertOk()
        ->assertJsonMissingPath('data.expenditure.categories');
});

it('returns detailed expenditure fields to Premium', function () {
    $premium = User::factory()->withActivePremiumSubscription()->create([
        'monthly_expenditure' => 1000,
        'food_groceries' => 300,
    ]);

    $this->actingAs($premium, 'sanctum')
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.user.food_groceries', '300.00');

    $this->actingAs($premium, 'sanctum')
        ->getJson('/api/user/profile')
        ->assertOk()
        ->assertJsonPath('data.expenditure.categories.food_groceries', '300.00');
});

it('allows base expenditure but denies detailed category writes for Free', function () {
    $free = User::factory()->create(['tier' => 'free']);

    $this->actingAs($free, 'sanctum')
        ->putJson('/api/user/profile/expenditure', ['monthly_expenditure' => 1200])
        ->assertOk();

    $this->actingAs($free, 'sanctum')
        ->putJson('/api/user/profile/expenditure', ['food_groceries' => 300])
        ->assertForbidden()
        ->assertJsonPath('error', 'capability_denied')
        ->assertJsonPath('required_tier', 'premium');
});
