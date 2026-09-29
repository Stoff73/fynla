<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Income\EmploymentIncomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(EmploymentIncomeService::class);
});

describe('EmploymentIncomeService::recordJob', function () {
    // The bug this exists to stop: onboarding invites a second job, and the
    // second write replaced the first, leaving the user on the smaller salary.
    it('keeps every job and totals them onto the user', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);

        $this->service->recordJob($user, 'Northwind Ltd', 'Analyst', 48000.0);
        $this->service->recordJob($user, 'Bluewater Consulting', 'Evening Tutor', 9000.0);

        expect($user->fresh()->employments)->toHaveCount(2)
            ->and((float) $user->fresh()->annual_employment_income)->toBe(57000.0);
    });

    it('does not double a salary when the same job is sent twice', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);

        $this->service->recordJob($user, 'Northwind Ltd', 'Analyst', 48000.0);
        $this->service->recordJob($user, 'Northwind Ltd', 'Analyst', 48000.0);

        expect($user->fresh()->employments)->toHaveCount(1)
            ->and((float) $user->fresh()->annual_employment_income)->toBe(48000.0);
    });

    it('corrects the figure in hand rather than adding a second row', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);

        $this->service->recordJob($user, 'Northwind Ltd', 'Analyst', 48000.0);
        $this->service->recordJob($user, 'Northwind Ltd', 'Analyst', 52000.0);

        expect($user->fresh()->employments)->toHaveCount(1)
            ->and((float) $user->fresh()->annual_employment_income)->toBe(52000.0);
    });

    it('fills in an employer volunteered after the salary', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);

        $this->service->recordJob($user, null, null, 48000.0);
        $this->service->recordJob($user, 'Northwind Ltd', 'Analyst', null);

        $jobs = $user->fresh()->employments;
        expect($jobs)->toHaveCount(1)
            ->and($jobs->first()->employer)->toBe('Northwind Ltd')
            ->and((float) $user->fresh()->annual_employment_income)->toBe(48000.0);
    });

    // An unnamed job the person gave themselves is still their job: naming a
    // second one must not overwrite it. Only a copied estimate is replaced.
    it('keeps an unnamed job of the person\'s own when a named second job arrives', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);

        $this->service->recordJob($user, null, null, 48000.0);
        $this->service->recordJob($user, 'Bluewater Consulting', 'Evening Tutor', 9000.0);

        expect($user->fresh()->employments)->toHaveCount(2)
            ->and((float) $user->fresh()->annual_employment_income)->toBe(57000.0);
    });

    it('replaces an estimate with the person\'s own job', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);

        $this->service->recordEstimate($user, 30000.0);
        $this->service->recordJob($user, 'Northwind Ltd', 'Analyst', 32000.0);

        $jobs = $user->fresh()->employments;
        expect($jobs)->toHaveCount(1)
            ->and($jobs->first()->employer)->toBe('Northwind Ltd')
            ->and((bool) $jobs->first()->is_estimate)->toBeFalse()
            ->and((float) $user->fresh()->annual_employment_income)->toBe(32000.0);
    });

    // Review of #963: naming the job without a figure must not count as the
    // person speaking for the FIGURE. The estimate stays an estimate until an
    // income arrives, or the next full payload becomes a second row.
    it('keeps the estimate flag when only the job is named, so the form still replaces it', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);

        $this->service->recordEstimate($user, 32000.0);
        $this->service->recordJob($user, null, 'Teacher', null);
        expect($user->fresh()->employments->first()->is_estimate)->toBeTrue();

        $this->service->recordJob($user, 'Harbour Lane Primary School', 'Teacher', 32000.0);

        $jobs = $user->fresh()->employments;
        expect($jobs)->toHaveCount(1)
            ->and($jobs->first()->employer)->toBe('Harbour Lane Primary School')
            ->and($jobs->first()->is_estimate)->toBeFalse()
            ->and((float) $user->fresh()->annual_employment_income)->toBe(32000.0);
    });

    it('totals self-employment separately — the two are taxed differently', function () {
        $user = User::factory()->create(['employment_status' => 'self_employed']);

        $this->service->recordJob($user, 'Own practice', 'Consultant', 40000.0);

        $user->refresh();
        expect((float) $user->annual_self_employment_income)->toBe(40000.0)
            ->and((float) $user->annual_employment_income)->toBe(0.0);
    });
});

// Carried over from #969 (closed as a duplicate of this fix).

// Production live test 2026-09-29, defect C1: the salary an inviting spouse gave
// is copied onto the spouse's account as an estimate. The spouse's own job must
// replace it, never be added to it.
describe('EmploymentIncomeService — a job someone else estimated', function () {
    function estimatedIncomeJob(User $user, float $income): void
    {
        app(EmploymentIncomeService::class)->recordEstimate($user, $income);
    }

    it('is replaced by the first job the user states, at the same figure', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);
        estimatedIncomeJob($user, 32000.0);

        $this->service->recordJob($user, 'Harbour Lane Primary School', 'Teacher', 32000.0);

        $jobs = $user->fresh()->employments;
        expect($jobs)->toHaveCount(1)
            ->and($jobs->first()->employer)->toBe('Harbour Lane Primary School')
            ->and($jobs->first()->is_estimate)->toBeFalse()
            ->and((float) $user->fresh()->annual_employment_income)->toBe(32000.0);
    });

    it('is replaced when the user gives a different figure', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);
        estimatedIncomeJob($user, 32000.0);

        $this->service->recordJob($user, 'Harbour Lane Primary School', 'Teacher', 34500.0);

        expect($user->fresh()->employments)->toHaveCount(1)
            ->and((float) $user->fresh()->annual_employment_income)->toBe(34500.0);
    });

    it('is replaced by a salary given with no employer', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);
        estimatedIncomeJob($user, 32000.0);

        $this->service->recordJob($user, null, null, 30000.0);

        expect($user->fresh()->employments)->toHaveCount(1)
            ->and((float) $user->fresh()->annual_employment_income)->toBe(30000.0);
    });

    it('counts a second job after the estimate was replaced', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);
        estimatedIncomeJob($user, 32000.0);

        $this->service->recordJob($user, 'Harbour Lane Primary School', 'Teacher', 32000.0);
        $this->service->recordJob($user, 'Bluewater Tutoring', 'Evening Tutor', 6000.0);

        expect($user->fresh()->employments)->toHaveCount(2)
            ->and((float) $user->fresh()->annual_employment_income)->toBe(38000.0);
    });

    it('keeps the estimate when the user only adds an employer', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);
        estimatedIncomeJob($user, 32000.0);

        $this->service->recordJob($user, 'Harbour Lane Primary School', null, null);

        $jobs = $user->fresh()->employments;
        expect($jobs)->toHaveCount(1)
            ->and($jobs->first()->is_estimate)->toBeTrue()
            ->and((float) $user->fresh()->annual_employment_income)->toBe(32000.0);
    });

    it('moves to self-employment when that is what the user states', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);
        estimatedIncomeJob($user, 32000.0);
        $user->update(['employment_status' => 'self_employed']);

        $this->service->recordJob($user->fresh(), 'Own studio', 'Designer', 28000.0);

        $user->refresh();
        expect($user->employments)->toHaveCount(1)
            ->and((float) $user->annual_self_employment_income)->toBe(28000.0)
            ->and((float) $user->annual_employment_income)->toBe(0.0);
    });

    it('is confirmed when the user edits it', function () {
        $user = User::factory()->create(['employment_status' => 'full_time']);
        estimatedIncomeJob($user, 32000.0);
        $job = $user->employments()->first();

        $this->service->updateJob($user, $job, null, null, 33000.0);
        $this->service->recordJob($user, 'Bluewater Tutoring', 'Evening Tutor', 6000.0);

        expect($user->fresh()->employments)->toHaveCount(2)
            ->and((float) $user->fresh()->annual_employment_income)->toBe(39000.0);
    });
});

// C1 follow-up: the income page writes the user's own total. An estimate
// someone else gave must go, or the next syncTotals sums it back in.
it('drops the estimate when the user states their own total on the income page', function () {
    $user = User::factory()->create(['employment_status' => 'full_time']);
    app(EmploymentIncomeService::class)->recordEstimate($user, 32000.0);

    Sanctum::actingAs($user->fresh());
    $this->putJson('/api/user/profile/income-occupation', ['annual_employment_income' => 30000])->assertOk();
    app(EmploymentIncomeService::class)->recordJob($user->fresh(), 'Bluewater Tutoring', 'Evening Tutor', 6000.0);

    $user->refresh();
    expect($user->employments()->where('is_estimate', true)->count())->toBe(0)
        ->and((float) $user->annual_employment_income)->toBe(36000.0);
});
