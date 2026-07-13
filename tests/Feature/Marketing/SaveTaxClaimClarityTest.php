<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Services\Marketing\SaveTaxEstimateService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;

function marketingCssToken(string $stylesheet, string $token): string
{
    preg_match('/'.preg_quote($token, '/').'\s*:\s*(#[0-9A-Fa-f]{6})/', $stylesheet, $matches);

    return $matches[1] ?? '';
}

function marketingRelativeLuminance(string $hex): float
{
    $channels = array_map(
        static fn (string $channel): float => hexdec($channel) / 255,
        str_split(ltrim($hex, '#'), 2)
    );
    $linear = array_map(
        static fn (float $channel): float => $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4,
        $channels
    );

    return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

function marketingContrastRatio(string $foreground, string $background): float
{
    $lighter = max(marketingRelativeLuminance($foreground), marketingRelativeLuminance($background));
    $darker = min(marketingRelativeLuminance($foreground), marketingRelativeLuminance($background));

    return ($lighter + 0.05) / ($darker + 0.05);
}

beforeEach(function (): void {
    TaxConfiguration::query()->delete();
    $this->seed(TaxConfigurationSeeder::class);
});

it('renders the SaveTax estimate as an average rather than a personal promise', function (): void {
    $response = $this->get('/savetax/plan?income=50271_100000&spouse=no&assets=savings');

    $response->assertOk()
        ->assertSee('An average estimated saving of up to £', false)
        ->assertSee('each year', false)
        ->assertSee('This is an average based on your answers — not your personal potential savings per year. Register for free and get your personalised tax strategy.', false)
        ->assertSee('This is an illustrative estimate, not personal financial advice.', false)
        ->assertSee('Tax year', false);

    expect(substr_count($response->getContent(), 'href="#hero"'))->toBe(1);
});

it('renders a neutral state when the estimate service is unavailable', function (): void {
    $service = Mockery::mock(SaveTaxEstimateService::class);
    $service->shouldReceive('estimate')->andThrow(new RuntimeException('unavailable'));
    app()->instance(SaveTaxEstimateService::class, $service);

    $content = $this->get('/savetax/plan')->assertOk()->getContent();

    expect($content)->toContain('Your estimate is temporarily unavailable')
        ->toContain('id="allowances-total">Unavailable')
        ->not->toContain('£3,100')
        ->not->toContain('£96,330');
});

it('removes unverifiable member counts and testimonials from the SaveTax result', function (): void {
    $page = file_get_contents(public_path('pages/savetax-plan.php'));
    $script = file_get_contents(public_path('pages/js/savetax-plan-v4.js'));

    expect($page)->not->toContain('Could this be you?')
        ->and($script)->not->toContain('testimonials')
        ->not->toContain('members ')
        ->not->toContain('readAnswers');
});

it('renders every public tax-year claim from configuration', function (): void {
    $source = file_get_contents(public_path('pages/savetax-plan.php'));

    expect($source)->not->toContain('2026/27')
        ->and($source)->toContain('$savetaxTaxYear');
});

it('renders funnel income bands from the active tax configuration', function (): void {
    $configuration = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $configuration->config_data;
    $data['income_tax']['higher_rate_threshold'] = 60000;
    $data['income_tax']['personal_allowance_taper_threshold'] = 110000;
    $data['income_tax']['additional_rate_threshold'] = 140000;
    $configuration->update(['config_data' => $data]);
    app()->forgetInstance(TaxConfigService::class);

    $this->get('/savetax')
        ->assertOk()
        ->assertSee('Up to £60,000', false)
        ->assertSee('£60,001 to £110,000', false)
        ->assertSee('£110,001 to £135,140', false)
        ->assertSee('Above £135,140', false)
        ->assertDontSee('Up to £50,270', false);
});

it('bounds the public claim to allowances considered by the estimate', function (): void {
    $content = $this->get('/savetax/plan')->assertOk()->getContent();

    expect($content)->toContain('key UK tax allowances considered by this estimate')
        ->not->toContain('every UK tax allowance');
});

it('uses the agreed homepage allowance wording', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('the UK tax allowances you could be missing out on', false);
});

it('provides an accessible sign-in recovery action without inline error colour', function (): void {
    $script = file_get_contents(public_path('pages/js/savetax-plan-v4.js'));
    $stylesheet = file_get_contents(public_path('pages/css/savetax-plan-v4.css'));

    expect($script)
        ->toContain('/login?from=savetax')
        ->not->toContain('style.cssText')
        ->not->toContain('#E6C9A8')
        ->and($stylesheet)->toContain('.sp4-register__error');
});

it('keeps existing-account error text and links above WCAG AA contrast', function (): void {
    $tokens = file_get_contents(public_path('pages/css/global.css'));
    $white = marketingCssToken($tokens, '--white');
    $errorText = marketingCssToken($tokens, '--horizon-600');
    $errorLink = marketingCssToken($tokens, '--raspberry-600');

    expect($white)->not->toBe('')
        ->and(marketingContrastRatio($errorText, $white))->toBeGreaterThanOrEqual(4.5)
        ->and(marketingContrastRatio($errorLink, $white))->toBeGreaterThanOrEqual(4.5);
});

it('derives the yearly saving claim from live pricing data', function (): void {
    $page = file_get_contents(public_path('pages/pricing.php'));
    $script = file_get_contents(public_path('pages/js/pricing.js'));

    expect($page)->toContain('id="save-label"')
        ->and($page)->not->toContain('Save 17%')
        ->and($script)->toContain('annualSavingPercent')
        ->and($script)->toContain('monthlyPence * 12');
});
