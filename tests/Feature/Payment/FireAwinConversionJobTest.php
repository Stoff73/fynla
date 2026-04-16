<?php

declare(strict_types=1);

use App\Jobs\FireAwinConversionJob;
use App\Models\Payment;
use App\Models\User;
use App\Services\Marketing\AwinTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('awin.enabled', true);
    config()->set('awin.merchant_id', '126105');
    config()->set('awin.s2s_base_url', 'https://www.awin1.com/sread.php');
    config()->set('awin.default_commission_group', 'SUB');
    config()->set('awin.http_timeout_seconds', 3);
});

it('fires the Awin S2S call and sets awin_fired_at on success', function () {
    Http::fake(['www.awin1.com/*' => Http::response('OK', 200)]);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'amount' => 1099,
        'status' => 'completed',
        'plan_slug' => 'standard',
        'awin_order_ref' => 'FYN-PAY-99',
        'awin_customer_acquisition' => 'new',
        'awin_cks' => 'click-ref-xyz',
        'awin_fired_at' => null,
    ]);

    (new FireAwinConversionJob($payment->id))->handle(app(AwinTrackingService::class));

    expect($payment->fresh()->awin_fired_at)->not->toBeNull();
    Http::assertSentCount(1);
});

it('is idempotent — does not fire when awin_fired_at is already set', function () {
    Http::fake(['www.awin1.com/*' => Http::response('OK', 200)]);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
        'awin_fired_at' => now()->subMinute(),
    ]);

    (new FireAwinConversionJob($payment->id))->handle(app(AwinTrackingService::class));

    Http::assertNothingSent();
});

it('throws to trigger retry when the S2S call returns non-2xx', function () {
    Http::fake(['www.awin1.com/*' => Http::response('boom', 500)]);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
        'awin_order_ref' => 'FYN-PAY-1',
        'awin_customer_acquisition' => 'new',
        'awin_fired_at' => null,
    ]);

    $job = new FireAwinConversionJob($payment->id);

    expect(fn () => $job->handle(app(AwinTrackingService::class)))
        ->toThrow(\RuntimeException::class);

    expect($payment->fresh()->awin_fired_at)->toBeNull();
});

it('short-circuits when AWIN_ENABLED is false', function () {
    config()->set('awin.enabled', false);
    Http::fake(['www.awin1.com/*' => Http::response('OK', 200)]);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
        'awin_fired_at' => null,
    ]);

    (new FireAwinConversionJob($payment->id))->handle(app(AwinTrackingService::class));

    Http::assertNothingSent();
    expect($payment->fresh()->awin_fired_at)->toBeNull();
});

it('does not fire for non-completed payments', function () {
    Http::fake(['www.awin1.com/*' => Http::response('OK', 200)]);

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'awin_fired_at' => null,
    ]);

    (new FireAwinConversionJob($payment->id))->handle(app(AwinTrackingService::class));

    Http::assertNothingSent();
    expect($payment->fresh()->awin_fired_at)->toBeNull();
});

it('handles missing payment without throwing', function () {
    Http::fake(['www.awin1.com/*' => Http::response('OK', 200)]);

    (new FireAwinConversionJob(999999))->handle(app(AwinTrackingService::class));

    Http::assertNothingSent();
});

it('exposes the backoff schedule 30/300/1800', function () {
    $job = new FireAwinConversionJob(1);

    expect($job->backoff())->toBe([30, 300, 1800]);
    expect($job->tries)->toBe(3);
});
