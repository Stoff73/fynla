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

it('renders the SaveTax estimate as an "up to" figure that names the income it assumes', function (): void {
    $response = $this->get('/savetax/plan?income=50271_100000&spouse=no&assets=savings');

    $response->assertOk()
        ->assertSee('An estimated saving of up to £', false)
        ->assertDontSee('average', false)
        ->assertSee('each year', false)
        ->assertSee('Worked out for an income of £100,000, the top of the band you chose. Register for free and get your personalised tax strategy, worked out from your real figures.', false)
        ->assertSee('This is an illustrative estimate, not personal financial advice.', false)
        ->assertSee('Tax year', false);

    expect(substr_count($response->getContent(), 'href="#register-form"'))->toBe(2)
        ->and($response->getContent())->not->toContain('href="#hero"');
});

it("credits the partner only when part of the figure is the partner's", function (): void {
    $withTrap = $this->get('/savetax/plan?employment=not-employed&income=zero&spouse=yes&spouseIncome=100001_125140')->assertOk();
    $withTrap->assertSee('Worked out for no income of your own, and it includes £15,060 from your partner&#039;s side.', false);

    $noPartnerLine = $this->get('/savetax/plan?income=upto_50270&spouse=yes&spouseIncome=zero')->assertOk();
    $noPartnerLine->assertDontSee('partner&#039;s side', false)
        ->assertDontSee('bigger because of you and your partner', false);
});

it('calls the open top band an example income, not the top of the band', function (): void {
    $this->get('/savetax/plan?income=over_125140&spouse=no')
        ->assertOk()
        ->assertSee('Worked out for an example income of £150,000 in the band you chose.', false)
        ->assertDontSee('the top of the band you chose', false);
});

it('caps a retiree at the non-earner pension limit on the page (F2)', function (): void {
    $this->get('/savetax/plan?employment=retired&income=upto_50270&spouse=no')
        ->assertOk()
        ->assertSee('An estimated saving of up to <span class="sp4-savings__figure" id="savings-figure">£720</span>', false);
});

// F10: a partner in the taper band whose income is not from work is capped at
// the basic amount (FA 2004 s190): £2,160, not the £15,060 a working partner
// saves. The headline adds the user's own £720 non-earner line to each.
it("asks the partner's employment and caps a retired partner on the page (F10)", function (): void {
    $this->get('/savetax')->assertOk()
        ->assertSee('id="s-spouse-employment"', false)
        ->assertSee("What is your spouse or civil partner's employment status?", false);

    $this->get('/savetax/plan?employment=not-employed&income=zero&spouse=yes&spouseIncome=100001_125140&spouseEmployment=retired')
        ->assertOk()
        ->assertSee('<span class="sp4-savings__figure" id="savings-figure">£2,880</span>', false);
    $this->get('/savetax/plan?employment=not-employed&income=zero&spouse=yes&spouseIncome=100001_125140&spouseEmployment=full-time')
        ->assertOk()
        ->assertSee('<span class="sp4-savings__figure" id="savings-figure">£15,780</span>', false);
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
