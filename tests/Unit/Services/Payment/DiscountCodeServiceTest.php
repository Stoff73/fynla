<?php

declare(strict_types=1);

use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Models\User;
use App\Services\Payment\DiscountCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DiscountCodeService();
    $this->user = User::factory()->create();
    // Seed tax config for RefreshDatabase
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

describe('calculateDiscount', function () {
    it('calculates percentage discount correctly', function () {
        $code = DiscountCode::factory()->percentage(20)->create();
        expect($this->service->calculateDiscount($code, 1099))->toBe(220);
    });

    it('calculates fixed amount discount correctly', function () {
        $code = DiscountCode::factory()->fixedAmount(1000)->create();
        expect($this->service->calculateDiscount($code, 1099))->toBe(1000);
    });

    it('caps fixed amount at order total', function () {
        $code = DiscountCode::factory()->fixedAmount(5000)->create();
        expect($this->service->calculateDiscount($code, 1099))->toBe(1099);
    });

    it('returns zero for trial extension', function () {
        $code = DiscountCode::factory()->trialExtension(14)->create();
        expect($this->service->calculateDiscount($code, 1099))->toBe(0);
    });
});

describe('validate', function () {
    it('validates a valid percentage code', function () {
        $code = DiscountCode::factory()->percentage(20)->create(['code' => 'SAVE20']);

        $result = $this->service->validate('SAVE20', $this->user->id, 'standard', 'monthly', 1099);

        expect($result['valid'])->toBeTrue()
            ->and($result['discount_amount'])->toBe(220)
            ->and($result['final_amount'])->toBe(879)
            ->and($result['discount_type'])->toBe('percentage')
            ->and($result['discount_description'])->toBe('20% off');
    });

    it('validates case-insensitively', function () {
        DiscountCode::factory()->percentage(10)->create(['code' => 'LOWER']);
        $result = $this->service->validate('lower', $this->user->id, 'standard', 'monthly', 1099);
        expect($result['valid'])->toBeTrue();
    });

    it('rejects non-existent code', function () {
        $result = $this->service->validate('NONEXIST', $this->user->id, 'standard', 'monthly', 1099);
        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toContain('not found');
    });

    it('rejects inactive code', function () {
        DiscountCode::factory()->inactive()->create(['code' => 'OFF']);
        $result = $this->service->validate('OFF', $this->user->id, 'standard', 'monthly', 1099);
        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toContain('no longer active');
    });

    it('rejects expired code', function () {
        DiscountCode::factory()->expired()->create(['code' => 'OLD']);
        $result = $this->service->validate('OLD', $this->user->id, 'standard', 'monthly', 1099);
        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toContain('expired');
    });

    it('rejects code not yet active', function () {
        DiscountCode::factory()->create(['code' => 'FUTURE', 'starts_at' => now()->addMonth()]);
        $result = $this->service->validate('FUTURE', $this->user->id, 'standard', 'monthly', 1099);
        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toContain('not yet active');
    });

    it('rejects exhausted code', function () {
        DiscountCode::factory()->exhausted()->create(['code' => 'DONE']);
        $result = $this->service->validate('DONE', $this->user->id, 'standard', 'monthly', 1099);
        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toContain('maximum number of uses');
    });

    it('rejects per-user limit exceeded', function () {
        $code = DiscountCode::factory()->create(['code' => 'ONCE', 'max_uses_per_user' => 1]);
        DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'user_id' => $this->user->id,
            'applied_at' => now(),
        ]);

        $result = $this->service->validate('ONCE', $this->user->id, 'standard', 'monthly', 1099);
        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toContain('already used');
    });

    it('rejects code for wrong plan', function () {
        DiscountCode::factory()->forPlans(['student'])->create(['code' => 'STUONLY']);
        $result = $this->service->validate('STUONLY', $this->user->id, 'pro', 'monthly', 1099);
        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toContain('not valid for the selected plan');
    });

    it('accepts code for correct plan', function () {
        DiscountCode::factory()->forPlans(['standard', 'pro'])->create(['code' => 'STDPRO']);
        $result = $this->service->validate('STDPRO', $this->user->id, 'standard', 'monthly', 1099);
        expect($result['valid'])->toBeTrue();
    });

    it('rejects code for wrong cycle', function () {
        DiscountCode::factory()->forCycles(['yearly'])->create(['code' => 'YRONLY']);
        $result = $this->service->validate('YRONLY', $this->user->id, 'standard', 'monthly', 1099);
        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toContain('not valid for the selected billing cycle');
    });
});

describe('apply', function () {
    it('records usage and increments counter', function () {
        $code = DiscountCode::factory()->percentage(20)->create(['code' => 'APPLY20']);

        // Create a real payment to satisfy FK constraint
        $subscription = \App\Models\Subscription::factory()->create(['user_id' => $this->user->id]);
        $payment = \App\Models\Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $this->user->id,
            'revolut_order_id' => 'test-order-123',
            'amount' => 1099,
            'currency' => 'GBP',
            'status' => 'pending',
        ]);

        $discountAmount = $this->service->apply($code, $this->user->id, $payment->id, 1099);

        expect($discountAmount)->toBe(220)
            ->and($code->fresh()->times_used)->toBe(1)
            ->and(DiscountCodeUsage::where('discount_code_id', $code->id)->count())->toBe(1);
    });
});
