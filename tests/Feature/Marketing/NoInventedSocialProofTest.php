<?php

declare(strict_types=1);

use Database\Seeders\TaxConfigurationSeeder;

/*
 * CSJ 2026-09-29: the public plan pages showed invented member counts
 * ("12,400 members…") and invented five-star testimonials ("Patricia H.",
 * "Rated 5 out of 5") under "Could this be you? … Join them". Fake consumer
 * reviews are banned (Digital Markets, Competition and Consumers Act 2024,
 * Schedule 20) and invented figures break Rule 23. They are removed and must
 * not come back.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

it('shows no invented reviews or member counts on the public plan pages', function (string $path): void {
    $this->get($path)->assertOk()
        ->assertDontSee('Could this be you?', false)
        ->assertDontSee('Rated 5 out of 5', false)
        ->assertDontSee('Join them', false);
})->with(['/pensioncheck/plan', '/savetax/plan/v4']);

it('ships no invented testimonials in the Pension Check script', function (): void {
    $script = file_get_contents(public_path('pages/js/pensioncheck-plan.js'));

    expect($script)->not->toContain('Rated 5 out of 5')
        ->and($script)->not->toContain('Patricia H.')
        ->and($script)->not->toContain('12,400');
});
