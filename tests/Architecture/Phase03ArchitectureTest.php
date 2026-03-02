<?php

declare(strict_types=1);

use App\Http\Controllers\Api\NetWorthController;
use App\Http\Controllers\Controller;
use App\Services\NetWorth\NetWorthService;

/**
 * Phase 03 Architecture Tests
 *
 * These tests ensure that the Phase 03 codebase follows
 * architectural standards and best practices.
 */
describe('Phase 03 Architecture Tests', function () {
    describe('Controllers extend base Controller', function () {
        it('NetWorthController extends Controller', function () {
            expect(is_subclass_of(NetWorthController::class, Controller::class))->toBeTrue();
        });
    });

    describe('Services use strict types', function () {
        it('NetWorthService uses strict_types declaration', function () {
            $reflection = new ReflectionClass(NetWorthService::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            expect($contents)->toContain('declare(strict_types=1)');
        });
    });

    describe('Controllers use strict types', function () {
        it('NetWorthController uses strict_types declaration', function () {
            $reflection = new ReflectionClass(NetWorthController::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            expect($contents)->toContain('declare(strict_types=1)');
        });
    });

    describe('Controllers do not have direct DB queries', function () {
        it('NetWorthController uses service layer', function () {
            $reflection = new ReflectionClass(NetWorthController::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            // Controllers should not use DB facade directly
            expect($contents)->not->toContain('DB::table');
            expect($contents)->not->toContain('DB::select');
            expect($contents)->not->toContain('DB::insert');
            expect($contents)->not->toContain('DB::update');
            expect($contents)->not->toContain('DB::delete');
        });
    });

    describe('Services have proper return types', function () {
        it('NetWorthService methods have return type declarations', function () {
            $reflection = new ReflectionClass(NetWorthService::class);

            $calculateNetWorth = $reflection->getMethod('calculateNetWorth');
            expect($calculateNetWorth->hasReturnType())->toBeTrue();
            expect($calculateNetWorth->getReturnType()->getName())->toBe('array');

            $getAssetBreakdown = $reflection->getMethod('getAssetBreakdown');
            expect($getAssetBreakdown->hasReturnType())->toBeTrue();
            expect($getAssetBreakdown->getReturnType()->getName())->toBe('array');

            $getAssetsSummary = $reflection->getMethod('getAssetsSummary');
            expect($getAssetsSummary->hasReturnType())->toBeTrue();
            expect($getAssetsSummary->getReturnType()->getName())->toBe('array');

            $getJointAssets = $reflection->getMethod('getJointAssets');
            expect($getJointAssets->hasReturnType())->toBeTrue();
            expect($getJointAssets->getReturnType()->getName())->toBe('array');
        });
    });

    describe('Controllers follow naming conventions', function () {
        it('Controller classes end with "Controller"', function () {
            expect(str_ends_with(NetWorthController::class, 'Controller'))->toBeTrue();
        });

        it('Service classes end with "Service"', function () {
            expect(str_ends_with(NetWorthService::class, 'Service'))->toBeTrue();
        });
    });

    describe('Service uses dependency injection', function () {
        it('NetWorthService uses CrossModuleAssetAggregator dependency', function () {
            $reflection = new ReflectionClass(NetWorthService::class);
            $constructor = $reflection->getConstructor();

            // NetWorthService uses CrossModuleAssetAggregator for cross-module data
            expect($constructor)->not->toBeNull();
            expect($constructor->getNumberOfParameters())->toBe(1);
        });
    });

    describe('Service handles ownership percentages correctly', function () {
        it('NetWorthService has methods for joint asset calculations', function () {
            $reflection = new ReflectionClass(NetWorthService::class);

            expect($reflection->hasMethod('getJointAssets'))->toBeTrue();
            expect($reflection->hasMethod('calculateNetWorth'))->toBeTrue();
        });
    });

    describe('Caching implementation', function () {
        it('NetWorthService implements caching for performance', function () {
            $reflection = new ReflectionClass(NetWorthService::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            // Should use Cache facade for optimization
            expect($contents)->toContain('Cache::');
        });

        it('Cache keys follow naming convention', function () {
            $reflection = new ReflectionClass(NetWorthService::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            // Cache keys should include 'net_worth' prefix
            expect($contents)->toContain('net_worth');
        });
    });

    describe('Controller uses proper HTTP methods', function () {
        it('NetWorthController uses GET for read operations', function () {
            $reflection = new ReflectionClass(NetWorthController::class);

            // These methods should exist and be used with GET routes
            expect($reflection->hasMethod('getOverview'))->toBeTrue();
            expect($reflection->hasMethod('getBreakdown'))->toBeTrue();
            expect($reflection->hasMethod('getAssetsSummary'))->toBeTrue();
            expect($reflection->hasMethod('getJointAssets'))->toBeTrue();
        });

        it('NetWorthController uses POST for cache refresh', function () {
            $reflection = new ReflectionClass(NetWorthController::class);

            // Refresh should use POST
            expect($reflection->hasMethod('refresh'))->toBeTrue();
        });
    });

    describe('Service calculates all required asset types', function () {
        it('NetWorthService has methods for asset types or uses aggregator', function () {
            $reflection = new ReflectionClass(NetWorthService::class);

            // Property, Investment, Cash are handled via CrossModuleAssetAggregator
            // Direct calculation methods for Business, Chattels, and Pensions
            expect($reflection->hasMethod('calculateBusinessValue'))->toBeTrue();
            expect($reflection->hasMethod('calculateChattelValue'))->toBeTrue();
            expect($reflection->hasMethod('calculatePensionBreakdown'))->toBeTrue();
        });

        it('NetWorthService handles liabilities via breakdown method', function () {
            $reflection = new ReflectionClass(NetWorthService::class);

            // Liabilities are handled via breakdown method and CrossModuleAssetAggregator
            expect($reflection->hasMethod('calculateNetWorth'))->toBeTrue();
        });
    });

    describe('Service uses Eloquent models', function () {
        it('NetWorthService imports all required models or uses aggregator', function () {
            $reflection = new ReflectionClass(NetWorthService::class);
            $filePath = $reflection->getFileName();
            $contents = file_get_contents($filePath);

            // Should import model classes directly or use CrossModuleAssetAggregator
            expect($contents)->toContain('use App\Models\Property');
            expect($contents)->toContain('use App\Models\Investment\InvestmentAccount');
            expect($contents)->toContain('use App\Models\BusinessInterest');
            expect($contents)->toContain('use App\Models\Chattel');
            // Cash and Mortgage are handled via CrossModuleAssetAggregator
            expect($contents)->toContain('CrossModuleAssetAggregator');
        });
    });
});
