<?php

declare(strict_types=1);

/**
 * W-0507 acceptance 3, and the third instance of this defect.
 *
 * `IHTCalculationService` publishes its caveats as finished sentences precisely so
 * every surface can render the same words (Rule 20). Three times now, a caveat has
 * been attached to the component where the figure was *thought* to live, and a second
 * component printing the same figure has carried none of it:
 *
 *   W-0466 F3  the full table had it, another surface did not
 *   W-0482     the caveat went with its cause and arrived with the fix
 *   W-0507     the free-tier teaser — every user who has not upgraded, and every demo
 *              persona a prospective customer sees
 *
 * A manual fix each time has not held. This asserts the correspondence: a surface
 * that prints an Inheritance Tax figure renders the caveats published with it.
 */
$caveatKeys = ['unmodelled_relief_caveat', 'projected_pension_inclusion_caveat'];

it('publishes both caveats as engine sentences, not as component copy', function () use ($caveatKeys) {
    $service = (string) file_get_contents(app_path('Services/Estate/IHTCalculationService.php'));

    foreach ($caveatKeys as $key) {
        expect($service)->toContain("'{$key}' =>");
    }
});

it('passes both caveats through the free-tier teaser detector', function () use ($caveatKeys) {
    // The teaser is a different component behind the upgrade gate, so it reads the
    // detector rather than the full calculation. A caveat the detector drops cannot
    // reach the surface however carefully the surface is written.
    $detector = (string) file_get_contents(app_path('Services/Tiers/EstateIhtExposureDetector.php'));

    foreach ($caveatKeys as $key) {
        expect($detector)->toContain($key);
    }
});

it('renders both caveats on every surface that prints the teaser figure', function () use ($caveatKeys) {
    // Named files rather than a directory walk: these are the two surfaces that print
    // `estimated_liability_gbp`, and a third would have to be added here deliberately.
    $surfaces = [
        base_path('resources/js/views/Estate/EstateDashboard.vue'),
        base_path('resources/mobile/views/modules/Estate.vue'),
    ];

    foreach ($surfaces as $surface) {
        $markup = (string) file_get_contents($surface);

        expect($markup)->toContain('estimated_liability_gbp');

        foreach ($caveatKeys as $key) {
            expect($markup)->toContain($key);
        }
    }
});

it('writes neither engine sentence into a frontend bundle', function () {
    // The two bundles share no constants, so a copy of either sentence in one of them
    // is a Rule 20 violation waiting to drift. A distinctive fragment of each, taken
    // from where the engine writes it.
    //
    // Note what this does NOT cover, because the distinction cost a round here:
    // `IHTPlanning.vue:620-630` carries a component-authored sentence about the
    // CURRENT column excluding pensions and the configured date that changes. It is a
    // different statement from either caveat, it is true, and the engine publishes no
    // equivalent — so it is not duplicated copy. It is worth publishing from the
    // engine one day so the teaser can say it too; it is not this test's business.
    foreach ([base_path('resources/js'), base_path('resources/mobile')] as $root) {
        foreach ([
            'Agricultural Property Relief, and does not apply',
            'It does not include lump sum death benefits',
        ] as $phrase) {
            $found = shell_exec(sprintf(
                'grep -rl %s %s 2>/dev/null',
                escapeshellarg($phrase),
                escapeshellarg($root)
            ));

            expect(trim((string) $found))->toBe('', "Engine caveat copy duplicated in a frontend bundle: {$phrase}");
        }
    }
});
