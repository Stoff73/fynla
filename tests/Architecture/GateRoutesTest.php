<?php

declare(strict_types=1);

use App\Constants\GateRoutes;

it('resolves every canonical gate destination for desktop and mobile', function (): void {
    $expected = [
        GateRoutes::PERSONAL_DETAILS => ['label' => 'Personal Details', 'web' => '/settings/personal', 'mobile' => null],
        GateRoutes::FAMILY_DETAILS => ['label' => 'Family Details', 'web' => '/settings/family', 'mobile' => null],
        GateRoutes::INCOME => ['label' => 'Income', 'web' => '/valuable-info?section=income', 'mobile' => '/income'],
        GateRoutes::EXPENDITURE => ['label' => 'Expenditure', 'web' => '/valuable-info?section=expenditure', 'mobile' => '/expenditure'],
        GateRoutes::PROTECTION => ['label' => 'Protection', 'web' => '/protection', 'mobile' => '/protection'],
        GateRoutes::SAVINGS => ['label' => 'Savings', 'web' => '/savings', 'mobile' => '/savings'],
        GateRoutes::LIABILITIES => ['label' => 'Liabilities', 'web' => '/net-worth/liabilities', 'mobile' => '/net-worth/liabilities'],
        GateRoutes::RETIREMENT => ['label' => 'Retirement', 'web' => '/retirement', 'mobile' => '/retirement'],
        GateRoutes::INVESTMENT => ['label' => 'Investments', 'web' => '/investment', 'mobile' => '/investment'],
        GateRoutes::RISK_PROFILE => ['label' => 'Risk Profile', 'web' => '/risk-profile', 'mobile' => null],
        GateRoutes::ESTATE => ['label' => 'Estate Planning', 'web' => '/estate', 'mobile' => '/estate'],
        GateRoutes::GOALS => ['label' => 'Goals', 'web' => '/goals', 'mobile' => '/goals'],
        GateRoutes::TAX_STRATEGY => ['label' => 'Tax Strategy', 'web' => '/tax-strategy', 'mobile' => '/tax-strategy'],
        GateRoutes::PROPERTY => ['label' => 'Property', 'web' => '/net-worth/property', 'mobile' => '/net-worth/property'],
        GateRoutes::HOLISTIC_PLAN => ['label' => 'Holistic Financial Plan', 'web' => '/holistic-plan', 'mobile' => '/holistic-plan'],
        GateRoutes::DASHBOARD => ['label' => 'Dashboard', 'web' => '/dashboard', 'mobile' => '/dashboard'],
    ];

    foreach ($expected as $destination => $route) {
        expect(GateRoutes::resolve($destination))->toBe($route);
    }
});

it('maps legacy readiness paths to canonical route metadata', function (string $legacyPath, string $destination): void {
    expect(GateRoutes::destinationForRoute($legacyPath))->toBe($destination);
})->with([
    ['/profile/personal', GateRoutes::PERSONAL_DETAILS],
    ['/profile/employment', GateRoutes::INCOME],
    ['/profile/expenditure', GateRoutes::EXPENDITURE],
    ['/retirement/pensions', GateRoutes::RETIREMENT],
    ['/investment/risk-profile', GateRoutes::RISK_PROFILE],
    ['/properties', GateRoutes::PROPERTY],
]);

it('backs every canonical route with the real desktop and mobile routers', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $desktop = file_get_contents($projectRoot.'/resources/js/router/index.js');
    $mobile = file_get_contents($projectRoot.'/resources/mobile/router.js');

    foreach (GateRoutes::destinations() as $route) {
        $webPath = strtok($route['web'], '?');
        $webDeclared = str_contains($desktop, "path: '{$webPath}'")
            || (str_starts_with($webPath, '/net-worth/')
                && str_contains($desktop, "path: '/net-worth'")
                && str_contains($desktop, "path: '".basename($webPath)."'"));
        expect($webDeclared)->toBeTrue("Missing desktop gate route {$webPath}");

        if ($route['mobile'] !== null) {
            $mobilePath = $route['mobile'];
            $mobileDeclared = str_contains($mobile, "path: '{$mobilePath}'")
                || (str_starts_with($mobilePath, '/net-worth/')
                    && str_contains($mobile, "path: '/net-worth/:category'"));
            expect($mobileDeclared)->toBeTrue("Missing mobile gate route {$mobilePath}");
        }
    }
});

it('rejects an unknown gate destination instead of leaking it into model-facing text', function (): void {
    GateRoutes::resolve('/internal/dead-route');
})->throws(InvalidArgumentException::class);
