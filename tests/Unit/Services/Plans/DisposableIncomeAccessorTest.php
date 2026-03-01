<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Plans\DisposableIncomeAccessor;
use App\Services\UserProfile\UserProfileService;

describe('DisposableIncomeAccessor', function () {
    it('returns disposable income from user profile service', function () {
        $user = Mockery::mock(User::class);

        $profileService = Mockery::mock(UserProfileService::class);
        $profileService->shouldReceive('getCompleteProfile')
            ->with($user)
            ->once()
            ->andReturn([
                'income_occupation' => [
                    'net_income' => 45000.00,
                    'annual_expenditure' => 30000.00,
                    'disposable_income' => 15000.00,
                    'monthly_disposable' => 1250.00,
                ],
            ]);

        $accessor = new DisposableIncomeAccessor($profileService);
        $result = $accessor->getForUser($user);

        expect($result)->toBe([
            'annual' => 15000.00,
            'monthly' => 1250.00,
            'net_income' => 45000.00,
            'annual_expenditure' => 30000.00,
        ]);
    });

    it('returns zero values when income data is missing', function () {
        $user = Mockery::mock(User::class);

        $profileService = Mockery::mock(UserProfileService::class);
        $profileService->shouldReceive('getCompleteProfile')
            ->with($user)
            ->once()
            ->andReturn([]);

        $accessor = new DisposableIncomeAccessor($profileService);
        $result = $accessor->getForUser($user);

        expect($result)->toBe([
            'annual' => 0.0,
            'monthly' => 0.0,
            'net_income' => 0.0,
            'annual_expenditure' => 0.0,
        ]);
    });

    it('returns monthly figure via convenience method', function () {
        $user = Mockery::mock(User::class);

        $profileService = Mockery::mock(UserProfileService::class);
        $profileService->shouldReceive('getCompleteProfile')
            ->with($user)
            ->once()
            ->andReturn([
                'income_occupation' => [
                    'net_income' => 50000.00,
                    'annual_expenditure' => 36000.00,
                    'disposable_income' => 14000.00,
                    'monthly_disposable' => 1166.67,
                ],
            ]);

        $accessor = new DisposableIncomeAccessor($profileService);

        expect($accessor->getMonthlyForUser($user))->toBe(1166.67);
    });

    it('returns annual figure via convenience method', function () {
        $user = Mockery::mock(User::class);

        $profileService = Mockery::mock(UserProfileService::class);
        $profileService->shouldReceive('getCompleteProfile')
            ->with($user)
            ->once()
            ->andReturn([
                'income_occupation' => [
                    'net_income' => 50000.00,
                    'annual_expenditure' => 36000.00,
                    'disposable_income' => 14000.00,
                    'monthly_disposable' => 1166.67,
                ],
            ]);

        $accessor = new DisposableIncomeAccessor($profileService);

        expect($accessor->getAnnualForUser($user))->toBe(14000.00);
    });

    afterEach(function () {
        Mockery::close();
    });
});
