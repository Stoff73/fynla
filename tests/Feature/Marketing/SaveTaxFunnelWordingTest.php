<?php

declare(strict_types=1);

it('asks about a spouse or civil partner on the public funnel', function () {
    $html = file_get_contents(public_path('pages/savetax.php'));

    expect($html)->toContain('Do you have a spouse or civil partner?')
        ->and($html)->toContain("What is your spouse or civil partner's annual income?")
        ->and($html)->not->toContain('Do you have a spouse?</h2>');
});

it('asks about a spouse or civil partner on the pension check funnel too', function () {
    // Same FunnelAnswersMapper path: "yes" is recorded as married (ruling b).
    $html = file_get_contents(public_path('pages/pensioncheck.php'));

    expect($html)->toContain('Do you have a spouse or civil partner?')
        ->and($html)->not->toContain('Do you have a spouse?</h2>');
});

it('shows the pension check income bands from tax config, as the Save Tax page does', function () {
    // The options used to be typed in (£50,270 / £100,000 / £125,140), so a
    // threshold change would leave the page and the onboarding recap apart.
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $labels = \App\Services\Onboarding\FunnelIncomeBand::pageLabels();

    $response = $this->get('/pensioncheck');

    $response->assertOk();
    foreach ($labels as $label) {
        $response->assertSee($label, false);
    }
});
