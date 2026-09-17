<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Income\EmploymentIncomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    it('totals self-employment separately — the two are taxed differently', function () {
        $user = User::factory()->create(['employment_status' => 'self_employed']);

        $this->service->recordJob($user, 'Own practice', 'Consultant', 40000.0);

        $user->refresh();
        expect((float) $user->annual_self_employment_income)->toBe(40000.0)
            ->and((float) $user->annual_employment_income)->toBe(0.0);
    });
});
