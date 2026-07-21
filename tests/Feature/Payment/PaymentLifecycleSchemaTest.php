<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('enforces provider and financial idempotency with unique indexes', function (string $table, string $index) {
    $indexes = collect(DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name');

    expect($indexes)->toContain($index);
})->with([
    ['payments', 'payments_revolut_order_id_unique'],
    ['payments', 'payments_active_checkout_user_id_unique'],
    ['invoices', 'invoices_payment_id_unique'],
    ['discount_code_usages', 'discount_code_usages_payment_id_unique'],
    ['referrals', 'referrals_referee_id_unique'],
]);

it('stores durable delivery claims for confirmation email and Awin', function () {
    expect(DB::getSchemaBuilder()->hasColumns('payments', [
        'confirmation_email_claimed_at',
        'awin_claimed_at',
    ]))->toBeTrue();
});

it('releases the active checkout slot when a pending payment is soft deleted', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->pending()->create(['user_id' => $user->id]);
    $old = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'subscription_id' => $subscription->id,
    ]);

    $old->delete();

    $replacement = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'subscription_id' => $subscription->id,
    ]);

    $deleted = Payment::withTrashed()->findOrFail($old->id);
    expect($deleted->status)->toBe('failed')
        ->and($deleted->active_checkout_user_id)->toBeNull()
        ->and($replacement->active_checkout_user_id)->toBe($user->id);
});
