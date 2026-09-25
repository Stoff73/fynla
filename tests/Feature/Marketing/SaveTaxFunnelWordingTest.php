<?php

declare(strict_types=1);

it('asks about a spouse or civil partner on the public funnel', function () {
    $html = file_get_contents(public_path('pages/savetax.php'));

    expect($html)->toContain('Do you have a spouse or civil partner?')
        ->and($html)->toContain("What is your spouse or civil partner's annual income?")
        ->and($html)->not->toContain('Do you have a spouse?</h2>');
});
