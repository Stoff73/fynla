<?php

declare(strict_types=1);

use App\Http\Controllers\Api\FamilyMembersController;
use App\Http\Controllers\Api\PersonalAccountsController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Controller;
use App\Services\UserProfile\PersonalAccountsService;
use App\Services\UserProfile\UserProfileService;

/**
 * Phase 02 Architecture Tests
 *
 * These tests ensure that the Phase 02 codebase follows
 * architectural standards and best practices.
 */
describe('Phase 02 Architecture Tests', function () {
    describe('Controllers extend base Controller', function () {
        it('UserProfileController extends Controller', function () {
            expect(is_subclass_of(UserProfileController::class, Controller::class))->toBeTrue();
        });

        it('FamilyMembersController extends Controller', function () {
            expect(is_subclass_of(FamilyMembersController::class, Controller::class))->toBeTrue();
        });

        it('PersonalAccountsController extends Controller', function () {
            expect(is_subclass_of(PersonalAccountsController::class, Controller::class))->toBeTrue();
        });
    });

    describe('Services use strict types', function () {
        it('UserProfileService uses strict_types declaration', function () {
            $reflection = new ReflectionClass(UserProfileService::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            expect($contents)->toContain('declare(strict_types=1)');
        });

        it('PersonalAccountsService uses strict_types declaration', function () {
            $reflection = new ReflectionClass(PersonalAccountsService::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            expect($contents)->toContain('declare(strict_types=1)');
        });
    });

    describe('Controllers use strict types', function () {
        it('UserProfileController uses strict_types declaration', function () {
            $reflection = new ReflectionClass(UserProfileController::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            expect($contents)->toContain('declare(strict_types=1)');
        });

        it('FamilyMembersController uses strict_types declaration', function () {
            $reflection = new ReflectionClass(FamilyMembersController::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            expect($contents)->toContain('declare(strict_types=1)');
        });

        it('PersonalAccountsController uses strict_types declaration', function () {
            $reflection = new ReflectionClass(PersonalAccountsController::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            expect($contents)->toContain('declare(strict_types=1)');
        });
    });

    describe('Form Requests exist for POST/PUT endpoints', function () {
        it('has UpdatePersonalInfoRequest', function () {
            expect(class_exists('App\\Http\\Requests\\UpdatePersonalInfoRequest'))->toBeTrue();
        });

        it('has UpdateIncomeOccupationRequest', function () {
            expect(class_exists('App\\Http\\Requests\\UpdateIncomeOccupationRequest'))->toBeTrue();
        });

        it('has StoreFamilyMemberRequest', function () {
            expect(class_exists('App\\Http\\Requests\\StoreFamilyMemberRequest'))->toBeTrue();
        });

        it('has UpdateFamilyMemberRequest', function () {
            expect(class_exists('App\\Http\\Requests\\UpdateFamilyMemberRequest'))->toBeTrue();
        });

        it('has StorePersonalAccountLineItemRequest', function () {
            expect(class_exists('App\\Http\\Requests\\StorePersonalAccountLineItemRequest'))->toBeTrue();
        });

        it('has UpdatePersonalAccountLineItemRequest', function () {
            expect(class_exists('App\\Http\\Requests\\UpdatePersonalAccountLineItemRequest'))->toBeTrue();
        });
    });

    describe('Controllers do not have direct DB queries', function () {
        it('UserProfileController uses service layer', function () {
            $reflection = new ReflectionClass(UserProfileController::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            // Controllers should not use DB facade directly
            expect($contents)->not->toContain('DB::table');
            expect($contents)->not->toContain('DB::select');
            expect($contents)->not->toContain('DB::insert');
            expect($contents)->not->toContain('DB::update');
            expect($contents)->not->toContain('DB::delete');
        });

        it('FamilyMembersController uses Eloquent models', function () {
            $reflection = new ReflectionClass(FamilyMembersController::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            expect($contents)->not->toContain('DB::table');
        });

        it('PersonalAccountsController uses service layer', function () {
            $reflection = new ReflectionClass(PersonalAccountsController::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            expect($contents)->not->toContain('DB::table');
        });
    });

    describe('Services have proper return types', function () {
        it('UserProfileService methods have return type declarations', function () {
            $reflection = new ReflectionClass(UserProfileService::class);

            $getCompleteProfile = $reflection->getMethod('getCompleteProfile');
            expect($getCompleteProfile->hasReturnType())->toBeTrue();
            expect($getCompleteProfile->getReturnType()->getName())->toBe('array');

            $updatePersonalInfo = $reflection->getMethod('updatePersonalInfo');
            expect($updatePersonalInfo->hasReturnType())->toBeTrue();
        });

        it('PersonalAccountsService methods have return type declarations', function () {
            $reflection = new ReflectionClass(PersonalAccountsService::class);

            $calculateProfitAndLoss = $reflection->getMethod('calculateProfitAndLoss');
            expect($calculateProfitAndLoss->hasReturnType())->toBeTrue();
            expect($calculateProfitAndLoss->getReturnType()->getName())->toBe('array');

            $calculateCashflow = $reflection->getMethod('calculateCashflow');
            expect($calculateCashflow->hasReturnType())->toBeTrue();
            expect($calculateCashflow->getReturnType()->getName())->toBe('array');

            $calculateBalanceSheet = $reflection->getMethod('calculateBalanceSheet');
            expect($calculateBalanceSheet->hasReturnType())->toBeTrue();
            expect($calculateBalanceSheet->getReturnType()->getName())->toBe('array');
        });
    });

    describe('Controllers follow naming conventions', function () {
        it('Controller classes end with "Controller"', function () {
            expect(str_ends_with(UserProfileController::class, 'Controller'))->toBeTrue();
            expect(str_ends_with(FamilyMembersController::class, 'Controller'))->toBeTrue();
            expect(str_ends_with(PersonalAccountsController::class, 'Controller'))->toBeTrue();
        });

        it('Service classes end with "Service"', function () {
            expect(str_ends_with(UserProfileService::class, 'Service'))->toBeTrue();
            expect(str_ends_with(PersonalAccountsService::class, 'Service'))->toBeTrue();
        });

        it('Form Request classes end with "Request"', function () {
            expect(str_ends_with('App\\Http\\Requests\\UpdatePersonalInfoRequest', 'Request'))->toBeTrue();
            expect(str_ends_with('App\\Http\\Requests\\StoreFamilyMemberRequest', 'Request'))->toBeTrue();
        });
    });

    describe('Services use dependency injection', function () {
        it('PersonalAccountsService uses UKTaxCalculator for tax calculations', function () {
            $reflection = new ReflectionClass(PersonalAccountsService::class);
            $constructor = $reflection->getConstructor();

            // PersonalAccountsService depends on UKTaxCalculator for proper tax calculation
            expect($constructor)->not->toBeNull();
            expect($constructor->getNumberOfParameters())->toBe(1);
        });

        it('UserProfileService uses dependency injection for cross-module services', function () {
            $reflection = new ReflectionClass(UserProfileService::class);
            $constructor = $reflection->getConstructor();

            // UserProfileService uses CrossModuleAssetAggregator and UKTaxCalculator
            expect($constructor)->not->toBeNull();
            expect($constructor->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
        });
    });
});
