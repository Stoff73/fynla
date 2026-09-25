<?php

declare(strict_types=1);

use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * gov.uk "How ISAs work"
 * (https://www.gov.uk/individual-savings-accounts/how-isas-work):
 * - Example 3 pays into two cash ISAs and a stocks and shares ISA in one
 *   tax year.
 * - "You can only pay into one Lifetime ISA in a tax year."
 * - "The tax year runs from 6 April to 5 April."
 * No page may state the old one-of-each-type rule.
 */
uses(RefreshDatabase::class);

it('never states the outdated one-ISA-of-each-type rule', function (string $file) {
    $text = file_get_contents(base_path($file));

    expect($text)->not->toMatch('/one Cash ISA and one|only pay into one of each|one of each type per tax year|hold one of each type/i')
        ->and($text)->not->toContain('April 5');
})->with([
    'public/pages/help.php',
    'resources/js/views/Help.vue',
    'resources/js/views/Public/insights/IsaGuideUkPage.vue',
    'resources/js/views/Public/insights/IsaAllowance202526Page.vue',
]);

it('renders the current ISA rule with the configured allowance on the public help page', function () {
    $this->seed(TaxConfigurationSeeder::class);
    $allowance = (int) app(TaxConfigService::class)->getISAAllowances()['annual_allowance'];

    $this->get('/help')->assertOk()
        ->assertSee('more than one ISA of the same type', false)
        ->assertSee('£'.number_format($allowance), false);
});
