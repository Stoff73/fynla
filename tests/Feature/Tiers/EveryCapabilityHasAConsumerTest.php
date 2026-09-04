<?php

declare(strict_types=1);

/**
 * W-0499 acceptance 3, generalised.
 *
 * `investments_exotic` was `none` on free and `full` on premium, was sold in two
 * pieces of customer-facing copy, and **nothing in the application read it**. That is
 * the W-0463 / W-0498 shape — a configured rule with no consumer — and reading either
 * the matrix or the code alone showed nothing wrong, which is why it survived.
 *
 * This compares the two directly. A capability added to the matrix without anything
 * enforcing it turns this red on the day it lands, rather than being discovered by an
 * audit months later.
 *
 * The exception list is deliberately explicit. A capability may legitimately be
 * configured before its feature exists — but then it must be named here with a reason,
 * and it must not be sold in the meantime.
 */
it('has something in the application reading every configured capability', function () {
    $seeder = (string) file_get_contents(base_path('database/seeders/TierConfigurationSeeder.php'));
    preg_match_all("/'([a-z_]+)' => '(?:none|teaser|limited|full)'/", $seeder, $matches);

    $capabilities = array_values(array_unique($matches[1]));
    expect($capabilities)->not->toBeEmpty();

    // Configured, and nothing reads them. Each is a real gap rather than a tolerated
    // one — measured 2026-09-01 while closing W-0499.
    //
    // `family_module` and `benefits_child` were on this list and are no longer:
    // both were named in the pricing comparison and enforced nowhere, which is the
    // `investments_exotic` shape, and both are gated as of W-0532. The two that
    // remain are not sold anywhere, which is the difference.
    $knownUnconsumed = [
        'future_value_projections',
        'property_buy_to_let_analysis',
    ];

    $unconsumed = [];
    foreach ($capabilities as $capability) {
        if (in_array($capability, $knownUnconsumed, true)) {
            continue;
        }

        // The two customer-facing copy sites are excluded from the search: naming a
        // capability in an advert is what makes an ungated one a problem, not what
        // resolves it.
        $found = shell_exec(sprintf(
            'grep -rl %s %s 2>/dev/null | grep -v TierComparisonService | grep -v PaymentController',
            escapeshellarg($capability),
            escapeshellarg(app_path())
        ));

        if (trim((string) $found) === '') {
            $unconsumed[] = $capability;
        }
    }

    expect($unconsumed)->toBe([]);
});

it('enforces investments_exotic, the capability this test was written for', function () {
    // Named explicitly so a rename cannot pass by disappearing from both lists at once.
    $store = (string) file_get_contents(app_path('Services/Stores/InvestmentAccountStore.php'));

    expect($store)->toContain('investments_exotic')
        ->and($store)->toContain('EXOTIC_ACCOUNT_TYPES');
});
