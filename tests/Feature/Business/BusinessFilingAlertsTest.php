<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Models\User;
use App\Notifications\CompanyFilingDueNotification;
use App\Services\Business\BusinessInterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function businessDueIn(int $days, array $attrs = []): BusinessInterest
{
    $business = BusinessInterest::factory()->create(array_merge([
        'company_number' => '12248522',
        'business_type' => 'limited_company',
        'business_name' => 'CS Jones Limited',
    ], $attrs));

    // Fresh sync timestamp so the command reads the cached dates rather than
    // reaching for the network.
    $business->forceFill([
        'accounts_due_on' => today()->addDays($days)->toDateString(),
        'companies_house_synced_at' => now(),
    ])->save();

    return $business->fresh();
}

describe('business:send-filing-alerts', function () {
    it('sends a reminder on every rung of the ladder', function (int $days) {
        Notification::fake();
        businessDueIn($days);

        $this->artisan('business:send-filing-alerts')->assertSuccessful();

        Notification::assertCount(1);
    })->with([30, 20, 15, 10, 5, 4, 3, 2, 1, 0]);

    it('stays silent on a day that is not a rung', function (int $days) {
        Notification::fake();
        businessDueIn($days);

        $this->artisan('business:send-filing-alerts')->assertSuccessful();

        Notification::assertNothingSent();
    })->with([45, 29, 21, 16, 11, 6, -2, -3, -8]);

    it('warns again after the deadline passes', function (int $days) {
        Notification::fake();
        businessDueIn($days);

        $this->artisan('business:send-filing-alerts')->assertSuccessful();

        Notification::assertCount(1);
    })->with([-1, -7, -14, -30]);

    it('notifies both owners of a jointly held company', function () {
        Notification::fake();
        $spouse = User::factory()->create();
        businessDueIn(10, ['ownership_type' => 'joint', 'joint_owner_id' => $spouse->id]);

        $this->artisan('business:send-filing-alerts')->assertSuccessful();

        Notification::assertCount(2);
        Notification::assertSentTo($spouse, CompanyFilingDueNotification::class);
    });

    it('skips a deactivated joint owner', function () {
        Notification::fake();
        $spouse = User::factory()->create();
        businessDueIn(10, ['ownership_type' => 'joint', 'joint_owner_id' => $spouse->id]);
        $spouse->delete();

        $this->artisan('business:send-filing-alerts')->assertSuccessful();

        Notification::assertCount(1);
    });

    it('skips preview personas', function () {
        Notification::fake();
        businessDueIn(10, ['user_id' => User::factory()->create(['is_preview_user' => true])->id]);

        $this->artisan('business:send-filing-alerts')->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('ignores a business with no company number', function () {
        Notification::fake();
        $business = BusinessInterest::factory()->create(['company_number' => null]);
        $business->forceFill([
            'accounts_due_on' => today()->addDays(10)->toDateString(),
            'companies_house_synced_at' => now(),
        ])->save();

        $this->artisan('business:send-filing-alerts')->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('alerts on both filings independently', function () {
        Notification::fake();
        $business = businessDueIn(10);
        $business->forceFill([
            'confirmation_statement_due_on' => today()->addDays(5)->toDateString(),
        ])->save();

        $this->artisan('business:send-filing-alerts')->assertSuccessful();

        Notification::assertCount(2);
    });
});

describe('CompanyFilingDueNotification copy', function () {
    it('names the automatic penalty only for overdue accounts', function () {
        $payload = (new CompanyFilingDueNotification(
            'CS Jones Limited', CompanyFilingDueNotification::TYPE_ACCOUNTS, '2026-08-14', -7
        ))->toArray(new User);

        expect($payload['title'])->toBe('Annual accounts overdue')
            ->and($payload['body'])->toContain('automatic late filing penalty')
            ->and($payload['body'])->toContain('7 days ago')
            ->and($payload['data']['overdue'])->toBeTrue();
    });

    it('says an overdue confirmation statement carries no fine but risks strike-off', function () {
        $payload = (new CompanyFilingDueNotification(
            'CS Jones Limited', CompanyFilingDueNotification::TYPE_CONFIRMATION, '2026-08-14', -7
        ))->toArray(new User);

        expect($payload['body'])->toContain('no financial penalty')
            ->and($payload['body'])->toContain('strike the company off the register')
            ->and($payload['body'])->not->toContain('automatic late filing penalty');
    });

    it('reads naturally on the approaching rungs', function () {
        $payload = (new CompanyFilingDueNotification(
            'CS Jones Limited', CompanyFilingDueNotification::TYPE_ACCOUNTS, '2026-12-31', 1
        ))->toArray(new User);

        expect($payload['title'])->toBe('Annual accounts due soon')
            ->and($payload['body'])->toBe(
                'The annual accounts for CS Jones Limited are due on 31 December 2026, in 1 day.'
            );
    });
});

describe('getTaxDeadlines', function () {
    it('uses the Companies House date rather than the nine-month estimate', function () {
        $business = BusinessInterest::factory()->create([
            'business_type' => 'limited_company',
            'company_number' => '12248522',
            'tax_year_end' => today()->addMonths(2)->toDateString(),
        ]);
        $business->forceFill([
            'accounts_due_on' => '2026-12-31',
            'confirmation_statement_due_on' => '2026-10-14',
        ])->save();

        $deadlines = collect(app(BusinessInterestService::class)->getTaxDeadlines($business->fresh()))
            ->keyBy('type');

        expect($deadlines['accounts']['date'])->toBe('2026-12-31')
            ->and($deadlines['accounts']['estimated'])->toBeFalse()
            ->and($deadlines['confirmation']['date'])->toBe('2026-10-14')
            ->and($deadlines['confirmation']['estimated'])->toBeFalse();
    });

    it('falls back to the estimate when the company has never been synced', function () {
        $business = BusinessInterest::factory()->create([
            'business_type' => 'limited_company',
            'company_number' => null,
            'tax_year_end' => today()->addMonths(2)->toDateString(),
        ]);

        $deadlines = collect(app(BusinessInterestService::class)->getTaxDeadlines($business))
            ->keyBy('type');

        expect($deadlines['accounts']['estimated'])->toBeTrue()
            ->and($deadlines['confirmation']['estimated'])->toBeTrue();
    });
});

describe('BusinessInterestObserver', function () {
    beforeEach(function () {
        config()->set('services.companies_house.key', 'test-key');
        Http::fake(['*' => Http::response([
            'company_name' => 'CS JONES LIMITED',
            'accounts' => ['next_accounts' => ['due_on' => '2026-12-31', 'overdue' => false]],
            'confirmation_statement' => ['next_due' => '2026-10-14', 'overdue' => false],
        ])]);
    });

    it('reads the register when a company number arrives on create', function () {
        $business = BusinessInterest::factory()->create(['company_number' => '12248522']);

        expect($business->fresh()->accounts_due_on->toDateString())->toBe('2026-12-31');
    });

    it('re-reads the register when the number is changed', function () {
        $business = BusinessInterest::factory()->create(['company_number' => null]);
        expect($business->fresh()->accounts_due_on)->toBeNull();

        $business->update(['company_number' => 'SC123456']);

        expect($business->fresh()->accounts_due_on->toDateString())->toBe('2026-12-31');
    });

    it('clears stale dates when the number is removed', function () {
        $business = BusinessInterest::factory()->create(['company_number' => '12248522']);
        expect($business->fresh()->accounts_due_on)->not->toBeNull();

        $business->update(['company_number' => null]);

        expect($business->fresh()->accounts_due_on)->toBeNull()
            ->and($business->fresh()->companies_house_synced_at)->toBeNull();
    });

    it('does not re-read the register when an unrelated field changes', function () {
        $business = BusinessInterest::factory()->create(['company_number' => '12248522']);
        Http::fake();  // any further request would now return an empty 200

        $business->update(['business_name' => 'Renamed Limited']);

        Http::assertNothingSent();
        expect($business->fresh()->accounts_due_on->toDateString())->toBe('2026-12-31');
    });
});
