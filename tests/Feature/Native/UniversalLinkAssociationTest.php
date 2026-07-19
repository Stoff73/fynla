<?php

declare(strict_types=1);

it('publishes real production and staging universal-link associations with exclusions', function (): void {
    $path = public_path('.well-known/apple-app-site-association');
    $contents = (string) file_get_contents($path);
    $apacheRules = (string) file_get_contents(public_path('.well-known/.htaccess'));
    $association = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    $details = $association['applinks']['details'] ?? [];

    expect($contents)->not->toContain('TEAMID')
        ->and($apacheRules)->toContain('ForceType application/json')
        ->and($association['applinks']['apps'] ?? null)->toBe([])
        ->and($details)->toHaveCount(2)
        ->and($details[0]['appIDs'] ?? [])->toBe(['99S3M8JLLF.org.fynla.app'])
        ->and($details[1]['appIDs'] ?? [])->toBe(['99S3M8JLLF.org.fynla.app.dev']);

    $productionComponents = collect($details[0]['components'] ?? []);
    $stagingComponents = collect($details[1]['components'] ?? []);

    foreach (['/admin/*', '/api/*', '/.well-known/*', '/payment*', '/update-payment*', '/webhook*'] as $pathPattern) {
        expect($productionComponents->contains(
            fn (array $component): bool => ($component['/'] ?? null) === $pathPattern
                && ($component['exclude'] ?? false) === true
        ))->toBeTrue();
    }

    foreach (['/dashboard', '/net-worth/*', '/protection/policy/*', '/savings/account/*', '/retirement/pension/*'] as $pathPattern) {
        expect($productionComponents->contains(
            fn (array $component): bool => ($component['/'] ?? null) === $pathPattern
                && ! ($component['exclude'] ?? false)
        ))->toBeTrue();
        expect($stagingComponents->contains(
            fn (array $component): bool => ($component['/'] ?? null) === '/fynla'.$pathPattern
                && ! ($component['exclude'] ?? false)
        ))->toBeTrue();
    }
});
