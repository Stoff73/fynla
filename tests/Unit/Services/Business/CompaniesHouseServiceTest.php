<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Services\Business\CompaniesHouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** The shape the Public Data API returns for GET /company/{number}. */
function companiesHouseBody(array $overrides = []): array
{
    return array_replace_recursive([
        'company_name' => 'CS JONES LIMITED',
        'company_number' => '12248522',
        'company_status' => 'active',
        'accounts' => [
            'next_due' => '2026-12-31',
            'overdue' => false,
            'next_accounts' => [
                'due_on' => '2026-12-31',
                'overdue' => false,
            ],
        ],
        'confirmation_statement' => [
            'next_due' => '2026-10-14',
            'overdue' => false,
        ],
    ], $overrides);
}

beforeEach(function () {
    config()->set('services.companies_house.key', 'test-key');
    $this->service = app(CompaniesHouseService::class);
});

describe('normaliseNumber', function () {
    it('pads a short all-digit number to eight characters', function () {
        expect(CompaniesHouseService::normaliseNumber('123456'))->toBe('00123456');
    });

    it('keeps a full eight-digit number', function () {
        expect(CompaniesHouseService::normaliseNumber('12248522'))->toBe('12248522');
    });

    it('accepts a two-letter prefixed number and strips spacing and case', function () {
        expect(CompaniesHouseService::normaliseNumber(' sc 123456 '))->toBe('SC123456');
    });

    it('rejects anything that cannot be a company number', function (?string $input) {
        expect(CompaniesHouseService::normaliseNumber($input))->toBeNull();
    })->with([
        null,
        '',
        'not-a-number',
        '123456789',            // too long
        'S1234567',             // one letter
        '../../etc/passwd',     // path traversal into the outbound URL
        '12248522/officers',
    ]);
});

describe('fetchFilingProfile', function () {
    it('reads both due dates from the register', function () {
        Http::fake([
            'api.company-information.service.gov.uk/company/12248522' => Http::response(companiesHouseBody()),
        ]);

        $profile = $this->service->fetchFilingProfile('12248522');

        expect($profile)->not->toBeNull()
            ->and($profile['company_name'])->toBe('CS JONES LIMITED')
            ->and($profile['accounts_due_on'])->toBe('2026-12-31')
            ->and($profile['confirmation_statement_due_on'])->toBe('2026-10-14')
            ->and($profile['accounts_overdue'])->toBeFalse();
    });

    it('falls back to the deprecated accounts.next_due when next_accounts is absent', function () {
        $body = companiesHouseBody();
        unset($body['accounts']['next_accounts']);

        Http::fake(['*' => Http::response($body)]);

        expect($this->service->fetchFilingProfile('12248522')['accounts_due_on'])->toBe('2026-12-31');
    });

    it('reports an overdue filing', function () {
        Http::fake(['*' => Http::response(companiesHouseBody([
            'accounts' => ['next_accounts' => ['overdue' => true]],
            'confirmation_statement' => ['overdue' => true],
        ]))]);

        $profile = $this->service->fetchFilingProfile('12248522');

        expect($profile['accounts_overdue'])->toBeTrue()
            ->and($profile['confirmation_statement_overdue'])->toBeTrue();
    });

    it('returns null for an unknown company without throwing', function () {
        Http::fake(['*' => Http::response(['errors' => []], 404)]);

        expect($this->service->fetchFilingProfile('99999999'))->toBeNull();
    });

    it('returns null when the API is unreachable', function () {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        expect($this->service->fetchFilingProfile('12248522'))->toBeNull();
    });

    it('makes no request at all when no key is provisioned', function () {
        config()->set('services.companies_house.key', null);
        Http::fake();

        expect(app(CompaniesHouseService::class)->fetchFilingProfile('12248522'))->toBeNull();
        Http::assertNothingSent();
    });

    it('makes no request for a malformed number', function () {
        Http::fake();

        expect($this->service->fetchFilingProfile('not-a-company'))->toBeNull();
        Http::assertNothingSent();
    });
});

describe('sync', function () {
    it('writes both dates and a sync timestamp onto the business', function () {
        Http::fake(['*' => Http::response(companiesHouseBody())]);

        $business = BusinessInterest::factory()->create([
            'company_number' => '12248522',
            'business_type' => 'limited_company',
        ]);

        $this->service->sync($business);

        expect($business->fresh()->accounts_due_on->toDateString())->toBe('2026-12-31')
            ->and($business->fresh()->confirmation_statement_due_on->toDateString())->toBe('2026-10-14')
            ->and($business->fresh()->companies_house_synced_at)->not->toBeNull();
    });

    it('leaves previously cached dates intact when the lookup fails', function () {
        Http::fake(['*' => Http::response([], 500)]);

        $business = BusinessInterest::factory()->create(['company_number' => '12248522']);
        $business->forceFill(['accounts_due_on' => '2026-12-31'])->save();

        expect($this->service->sync($business))->toBeNull()
            ->and($business->fresh()->accounts_due_on->toDateString())->toBe('2026-12-31');
    });

    it('does nothing for a business with no company number', function () {
        Http::fake();

        $business = BusinessInterest::factory()->create(['company_number' => null]);

        expect($this->service->sync($business))->toBeNull();
        Http::assertNothingSent();
    });
});

describe('BusinessInterest::nextFiling', function () {
    it('returns the sooner of the two filings', function () {
        $business = BusinessInterest::factory()->create();
        $business->forceFill([
            'accounts_due_on' => today()->addDays(40)->toDateString(),
            'confirmation_statement_due_on' => today()->addDays(12)->toDateString(),
        ])->save();

        $filing = $business->fresh()->nextFiling();

        expect($filing['type'])->toBe('confirmation')
            ->and($filing['days_until'])->toBe(12);
    });

    it('reports a passed deadline as a negative day count', function () {
        $business = BusinessInterest::factory()->create();
        $business->forceFill(['accounts_due_on' => today()->subDays(9)->toDateString()])->save();

        expect($business->fresh()->nextFiling()['days_until'])->toBe(-9);
    });

    it('returns null when the company has never been synced', function () {
        expect(BusinessInterest::factory()->create()->nextFiling())->toBeNull();
    });
});
