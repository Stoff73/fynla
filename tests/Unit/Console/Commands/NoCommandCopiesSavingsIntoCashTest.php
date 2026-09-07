<?php

declare(strict_types=1);

use App\Models\CashAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\HouseholdPlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

// `Pest.php` binds per directory by name and does not bind this one, so the base case
// is declared here as every sibling in it does (tests/CLAUDE.md).
uses(TestCase::class, RefreshDatabase::class);

/**
 * W-0489 — nothing copies a savings account into `cash_accounts`.
 *
 * `php artisan migrate:savings-to-cash` was live and registered, and read like routine
 * maintenance. It copied every `savings_accounts` row into `cash_accounts` with **no
 * idempotency guard**, and it never deleted, flagged or otherwise marked the source rows.
 * `HouseholdPlanningService` sums both tables into one net-worth total, so one run would
 * have doubled every household's cash and a second run would have tripled it. It was
 * harmless only because nobody had run it — `cash_accounts` held zero rows.
 *
 * **The root was a three-way contradiction about what `cash_accounts` is for.** The
 * command said it replaced `savings_accounts`; the `CashAccount` docblock and
 * `HouseholdPlanningService` said it was current accounts, distinct from savings. CSJ
 * settled it on 2026-08-28: they are separate asset classes, and the command was the odd
 * voice out.
 *
 * **What can and cannot be guarded here.** Once the two tables are distinct by design,
 * "the same money in both tables" is not a state the application can reach — there is no
 * marker that would let a test recognise a duplicate, because duplicates are not
 * supposed to be producible. So the guard is the one that would actually have caught
 * this: nothing may re-register a command that copies between them, and the totals must
 * stay additive.
 */
it('registers no artisan command that migrates savings into cash', function () {
    // The gun is out of `artisan list`, and this fails if it is put back.
    $commands = array_keys(Artisan::all());

    expect($commands)->not->toContain('migrate:savings-to-cash');

    $copiers = array_filter(
        $commands,
        fn (string $name) => str_contains($name, 'savings') && str_contains($name, 'cash')
    );

    expect($copiers)->toBe([]);
});

it('keeps savings and cash as separate money in the household total', function () {
    // The two tables are separate asset classes, so a household holding both sees both
    // — the sum is additive precisely BECAUSE nothing copies between them. If a copier
    // ever returns, this figure is what it corrupts.
    $user = User::factory()->create();

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 40_000,
    ]);

    CashAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 6_000,
    ]);

    $result = app(HouseholdPlanningService::class)->calculateHouseholdNetWorth($user->fresh());
    $byType = $result['breakdown_by_type'];

    expect((float) $byType['savings']['total'])->toEqualWithDelta(40_000.0, 0.01)
        ->and((float) $byType['cash']['total'])->toEqualWithDelta(6_000.0, 0.01)
        // £46,000, not £86,000 — which is what one run of the deleted command produced.
        ->and((float) $result['total_assets'])->toEqualWithDelta(46_000.0, 0.01);
});
