<?php

declare(strict_types=1);

use App\Models\DiscountCode;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TaxConfiguration;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function legacyPlanCollapseMigration(): Migration
{
    return require database_path('migrations/2026_09_08_100000_collapse_legacy_plans_to_premium.php');
}

// The migration runs real DDL, which MySQL commits implicitly, so RefreshDatabase's
// transaction cannot roll it back. Every row this test makes is removed by name.
afterEach(function (): void {
    Invoice::query()->whereIn('user_id', User::withTrashed()->where('email', 'like', 'legacy-collapse-%')->pluck('id'))->delete();
    DB::table('invoice_sequences')->delete();
    Payment::query()->whereIn('user_id', User::withTrashed()->where('email', 'like', 'legacy-collapse-%')->pluck('id'))->delete();
    Subscription::query()->whereIn('user_id', User::withTrashed()->where('email', 'like', 'legacy-collapse-%')->pluck('id'))->delete();
    DiscountCode::query()->where('code', 'like', 'LEGACYCOLLAPSE%')->delete();
    User::withTrashed()->where('email', 'like', 'legacy-collapse-%')->forceDelete();
    TaxConfiguration::query()->where('tax_year', '2019/20')->delete();
});

it('rewrites every legacy plan as premium in account state and financial history, and narrows the enums', function () {
    $migration = legacyPlanCollapseMigration();
    $migration->down();

    try {
        $live = User::factory()->create(['email' => 'legacy-collapse-live@example.com', 'plan' => 'pro', 'tier' => null]);
        $liveSubscription = Subscription::factory()->plan('pro')->create([
            'user_id' => $live->id,
            'status' => 'active',
            'amount' => 1999,
            'current_period_end' => now()->addMonth(),
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $live->id,
            'subscription_id' => $liveSubscription->id,
            'plan_slug' => 'pro',
            'upgrade_from_plan' => 'standard',
            'amount' => 1999,
            'status' => 'completed',
        ]);
        $invoice = Invoice::factory()->create([
            'user_id' => $live->id,
            'subscription_id' => $liveSubscription->id,
            'payment_id' => $payment->id,
            'plan_name' => 'Pro',
            'subtotal_amount' => 1999,
            'total_amount' => 1999,
        ]);

        $lapsed = User::factory()->create(['email' => 'legacy-collapse-lapsed@example.com', 'plan' => 'standard', 'tier' => null]);
        $lapsedSubscription = Subscription::factory()->expired()->plan('standard')->create([
            'user_id' => $lapsed->id,
            'amount' => 1099,
        ]);

        $deleted = User::factory()->create([
            'email' => 'legacy-collapse-deleted@example.com',
            'plan' => 'free',
            'tier' => null,
            'deletion_reason' => 'trial_expired',
        ]);
        $deleted->delete();

        $free = User::factory()->create(['email' => 'legacy-collapse-free@example.com', 'plan' => 'free', 'tier' => 'free']);

        $code = DiscountCode::factory()->create([
            'code' => 'LEGACYCOLLAPSE1',
            'applicable_plans' => ['student', 'standard', 'family'],
        ]);
        $premiumCode = DiscountCode::factory()->create([
            'code' => 'LEGACYCOLLAPSE2',
            'applicable_plans' => ['premium'],
        ]);

        $migration->up();

        expect($live->fresh()->plan)->toBe('premium')
            ->and($liveSubscription->fresh()->plan)->toBe('premium')
            ->and($liveSubscription->fresh()->status)->toBe('active')
            ->and((int) $liveSubscription->fresh()->amount)->toBe(1999)
            ->and($payment->fresh()->upgrade_from_plan)->toBe('premium')
            // CSJ, 2026-09-08: history is rewritten too; only the money is untouched.
            ->and($payment->fresh()->plan_slug)->toBe('premium')
            ->and((int) $payment->fresh()->amount)->toBe(1999)
            ->and($invoice->fresh()->plan_name)->toBe('Premium')
            ->and((int) $invoice->fresh()->total_amount)->toBe(1999);

        expect($lapsed->fresh()->plan)->toBe('premium')
            ->and($lapsedSubscription->fresh()->plan)->toBe('premium')
            ->and($lapsedSubscription->fresh()->status)->toBe('expired');

        expect(User::withTrashed()->find($deleted->id)->deletion_reason)->toBe('subscription_cancelled_grace_ended')
            ->and($free->fresh()->plan)->toBe('free');

        expect($code->fresh()->applicable_plans)->toBe(['premium'])
            ->and($premiumCode->fresh()->applicable_plans)->toBe(['premium']);

        expect(DB::selectOne("SHOW COLUMNS FROM users LIKE 'plan'")->Type)->toBe("enum('free','premium')")
            ->and(DB::selectOne("SHOW COLUMNS FROM subscriptions LIKE 'plan'")->Type)->toBe("enum('free','premium')")
            ->and(DB::selectOne("SHOW COLUMNS FROM users LIKE 'deletion_reason'")->Type)
            ->toBe("enum('user_requested','subscription_cancelled_grace_ended','admin_initiated','legacy_purged')");
    } finally {
        // Leave the schema as every other test expects it: collapsed.
        $migration->down();
        $migration->up();
    }
});

it('leaves the schema unable to store a legacy plan name', function () {
    $user = User::factory()->create(['email' => 'legacy-collapse-enum@example.com']);

    expect(fn () => DB::table('users')->where('id', $user->id)->update(['plan' => 'pro']))
        ->toThrow(QueryException::class);
});
