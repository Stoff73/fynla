<?php

declare(strict_types=1);

it('server-renders the canonical Free and Premium pricing contract', function () {
    $response = $this->get('/pricing');

    $response->assertOk()
        ->assertSee('Free')
        ->assertSee('Premium')
        ->assertSee('id="plan-free"', false)
        ->assertSee('id="plan-premium"', false)
        ->assertSee('href="/register?plan=premium&amp;billing=yearly"', false)
        ->assertSee('2 cash or savings accounts')
        ->assertSee('2 investments')
        ->assertSee('2 pensions')
        ->assertSee('1 property')
        ->assertSee('2 Goals')
        ->assertSee('1 Life Event')
        ->assertSee('1 GB document storage')
        ->assertSee('100,000 Fyn tokens each week')
        ->assertSee('500,000 Fyn tokens each week')
        ->assertSee('Full available history')
        ->assertSee('Payments are processed securely through Revolut.')
        ->assertSee('Cancel a paid subscription at any time; paid access continues until the end of the current billing period.')
        ->assertDontSee('plan-tier1')
        ->assertDontSee('plan-tier2')
        ->assertDontSee('plan-tier3')
        ->assertDontSee('Payments are processed securely through Stripe')
        ->assertDontSee('downgrade at any time')
        ->assertDontSee('free trial', false)
        ->assertDontSee('For demonstration purposes only')
        ->assertDontSee('href="#"', false)
        ->assertDontSee('plan-card__check', false);

    $html = $response->getContent();

    expect(substr_count($html, 'id="plan-free"'))->toBe(1)
        ->and(substr_count($html, 'id="plan-premium"'))->toBe(1)
        ->and(substr_count($html, '<article id="plan-'))->toBe(2);
});

it('renders every approved comparison row in semantic table markup', function () {
    $response = $this->get('/pricing');

    $response->assertOk()
        ->assertSee('<table class="comparison-table"', false);

    foreach ([
        'cash-accounts',
        'investments',
        'property',
        'pensions',
        'personal-valuables',
        'net-worth',
        'combined-household',
        'projections',
        'save-tax',
        'inheritance-tax',
        'risk',
        'goals',
        'life-events',
        'fyn',
        'balance-history',
        'basic-expenditure',
        'detailed-expenditure',
        'document-storage',
        'letter-to-spouse',
        'retirement-planning',
        'what-if',
        'plan-export',
        'statement-upload',
        'investment-fee-analysis',
    ] as $row) {
        $response->assertSee('id="comparison-'.$row.'"', false);
    }
});
