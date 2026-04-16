<?php

declare(strict_types=1);

use App\Models\DiscountCode;
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

    $this->service = new AwinTrackingService;
});

describe('buildSaleParams', function () {
    it('converts pence amount to decimal GBP string', function () {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'amount' => 1099,
            'currency' => 'GBP',
            'plan_slug' => 'standard',
            'awin_order_ref' => 'FYN-PAY-42',
            'awin_customer_acquisition' => 'new',
            'awin_cks' => 'abc-123-def',
        ]);

        $params = $this->service->buildSaleParams($payment);

        expect($params['order_subtotal'])->toBe('10.99');
        expect($params['sale_amount'])->toBe('10.99');
        expect($params['currency_code'])->toBe('GBP');
        expect($params['order_ref'])->toBe('FYN-PAY-42');
        expect($params['commission_group'])->toBe('SUB');
        expect($params['voucher_code'])->toBe('');
        expect($params['customer_acquisition'])->toBe('new');
        expect($params['awc'])->toBe('abc-123-def');
    });

    it('includes voucher code when payment has a discount code', function () {
        $user = User::factory()->create();
        $discount = DiscountCode::factory()->create(['code' => 'LAUNCH50']);
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'amount' => 5000,
            'currency' => 'GBP',
            'plan_slug' => 'family',
            'discount_code_id' => $discount->id,
            'awin_customer_acquisition' => 'existing',
        ]);

        $params = $this->service->buildSaleParams($payment->fresh('discountCode'));

        expect($params['voucher_code'])->toBe('LAUNCH50');
        expect($params['customer_acquisition'])->toBe('existing');
    });

    it('falls back to computed order_ref when column is null', function () {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'awin_order_ref' => null,
        ]);

        $params = $this->service->buildSaleParams($payment);

        expect($params['order_ref'])->toBe("FYN-PAY-{$payment->id}");
    });

    it('emits empty awc when payment has no captured cookie', function () {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'awin_cks' => null,
        ]);

        $params = $this->service->buildSaleParams($payment);

        expect($params['awc'])->toBe('');
    });
});

describe('isCustomerAcquisition', function () {
    it('returns true when user has zero completed payments', function () {
        $user = User::factory()->create();

        expect($this->service->isCustomerAcquisition($user))->toBeTrue();
    });

    it('returns false when user has a prior completed payment', function () {
        $user = User::factory()->create();
        Payment::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        expect($this->service->isCustomerAcquisition($user))->toBeFalse();
    });

    it('ignores pending and failed payments', function () {
        $user = User::factory()->create();
        Payment::factory()->pending()->create(['user_id' => $user->id]);
        Payment::factory()->failed()->create(['user_id' => $user->id]);

        expect($this->service->isCustomerAcquisition($user))->toBeTrue();
    });

    it('excludes the current payment from the acquisition check', function () {
        $user = User::factory()->create();
        $current = Payment::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        expect($this->service->isCustomerAcquisition($user, $current->id))->toBeTrue();
    });

    it('returns false when a prior completed payment exists in addition to the current one', function () {
        $user = User::factory()->create();
        Payment::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
        $current = Payment::factory()->create(['user_id' => $user->id, 'status' => 'completed']);

        expect($this->service->isCustomerAcquisition($user, $current->id))->toBeFalse();
    });
});

describe('commissionGroupFor', function () {
    it('maps every Fynla plan slug to the configured default group', function () {
        expect($this->service->commissionGroupFor('student'))->toBe('SUB');
        expect($this->service->commissionGroupFor('standard'))->toBe('SUB');
        expect($this->service->commissionGroupFor('family'))->toBe('SUB');
        expect($this->service->commissionGroupFor('pro'))->toBe('SUB');
    });

    it('maps unknown slugs to the default group as a safety net', function () {
        expect($this->service->commissionGroupFor('enterprise'))->toBe('SUB');
        expect($this->service->commissionGroupFor(null))->toBe('SUB');
    });
});

describe('orderRefFor', function () {
    it('produces the FYN-PAY-{id} form', function () {
        $user = User::factory()->create();
        $payment = Payment::factory()->create(['user_id' => $user->id]);

        expect($this->service->orderRefFor($payment))->toBe("FYN-PAY-{$payment->id}");
    });
});

describe('fireServerToServer', function () {
    it('sends a correctly-encoded GET to the Awin sread endpoint and returns true on 200', function () {
        Http::fake([
            'www.awin1.com/*' => Http::response('OK', 200),
        ]);

        $result = $this->service->fireServerToServer([
            'order_subtotal' => '10.99',
            'currency_code' => 'GBP',
            'order_ref' => 'FYN-PAY-42',
            'commission_group' => 'SUB',
            'sale_amount' => '10.99',
            'voucher_code' => 'LAUNCH50',
            'customer_acquisition' => 'new',
            'awc' => 'abc-123-def',
        ]);

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            $url = $request->url();
            $data = $request->data();

            return str_starts_with($url, 'https://www.awin1.com/sread.php')
                && $data['tt'] === 'ss'
                && $data['tv'] === '2'
                && $data['merchant'] === '126105'
                && $data['amount'] === '10.99'
                && $data['ch'] === 'aw'
                && $data['cr'] === 'GBP'
                && $data['ref'] === 'FYN-PAY-42'
                && $data['parts'] === 'SUB:10.99'
                && $data['vc'] === 'LAUNCH50'
                && $data['customeracquisition'] === 'new'
                && $data['cks'] === 'abc-123-def';
        });
    });

    it('omits the cks param when awc is empty', function () {
        Http::fake([
            'www.awin1.com/*' => Http::response('OK', 200),
        ]);

        $this->service->fireServerToServer([
            'order_subtotal' => '10.99',
            'currency_code' => 'GBP',
            'order_ref' => 'FYN-PAY-1',
            'commission_group' => 'SUB',
            'sale_amount' => '10.99',
            'voucher_code' => '',
            'customer_acquisition' => 'existing',
            'awc' => '',
        ]);

        Http::assertSent(function ($request) {
            return ! array_key_exists('cks', $request->data());
        });
    });

    it('returns false on a non-2xx response without throwing', function () {
        Http::fake([
            'www.awin1.com/*' => Http::response('boom', 500),
        ]);

        $result = $this->service->fireServerToServer([
            'order_subtotal' => '10.99',
            'currency_code' => 'GBP',
            'order_ref' => 'FYN-PAY-1',
            'commission_group' => 'SUB',
            'sale_amount' => '10.99',
            'voucher_code' => '',
            'customer_acquisition' => 'new',
            'awc' => '',
        ]);

        expect($result)->toBeFalse();
    });

    it('returns false and swallows connection exceptions', function () {
        Http::fake([
            'www.awin1.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'),
        ]);

        $result = $this->service->fireServerToServer([
            'order_subtotal' => '10.99',
            'currency_code' => 'GBP',
            'order_ref' => 'FYN-PAY-1',
            'commission_group' => 'SUB',
            'sale_amount' => '10.99',
            'voucher_code' => '',
            'customer_acquisition' => 'new',
            'awc' => '',
        ]);

        expect($result)->toBeFalse();
    });
});
