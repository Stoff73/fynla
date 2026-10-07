<?php

declare(strict_types=1);

use App\Agents\EstateAgent;
use App\Models\DCPension;
use App\Models\Estate\Gift;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Trust;
use App\Models\Property;
use App\Models\User;
use App\Services\Estate\EstateActionDefinitionService;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * Item 9 (CSJ 2026-10-07, D1 to D6): the estate cards. Gifts are counted by
 * the one gift engine (exempt gifts never), a Lasting Power of Attorney counts
 * only once registered (Mental Capacity Act 2005 s9(2)(b)), a trust card falls
 * on the ten-year anniversary (IHTA 1984 s64), a pension card only where no
 * beneficiary is recorded, and the Inheritance Tax card's sentence adds up.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(EstateActionDefinitionSeeder::class);
    $this->user = User::factory()->create(['marital_status' => 'single', 'date_of_birth' => '1960-01-01']);
    Property::factory()->create([
        'user_id' => $this->user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 200_000,
        'property_type' => 'main_residence',
    ]);
});

function estateCardsFor(User $user, string $key): array
{
    return collect(app(EstateActionDefinitionService::class)->evaluateActions($user->fresh())['recommendations'])
        ->where('definition_key', $key)
        ->values()
        ->all();
}

function estateCardGift(User $user, string $type, string $date, float $value): void
{
    Gift::create(['user_id' => $user->id, 'gift_type' => $type, 'gift_date' => $date, 'gift_value' => $value, 'recipient' => 'Child']);
}

it('never counts exempt gifts as inside the seven years', function () {
    estateCardGift($this->user, 'annual_exemption', now()->subYears(2)->toDateString(), 3000);
    estateCardGift($this->user, 'annual_exemption', now()->subYear()->toDateString(), 3000);

    expect(estateCardsFor($this->user, 'gifts_pet_window'))->toBe([]);
});

it('counts a gift to a person and says how much band it uses', function () {
    estateCardGift($this->user, 'pet', now()->subYears(2)->toDateString(), 20000);
    estateCardGift($this->user, 'annual_exemption', now()->subYear()->toDateString(), 3000);

    [$card] = estateCardsFor($this->user, 'gifts_pet_window');

    expect($card['figures']['gift_count'])->toBe('1')
        ->and($card['figures']['gift_total'])->toBe('£20,000')
        ->and($card['figures']['has_gift_tax'])->toBeFalse()
        ->and($card['description'])->toContain('1 gift totalling £20,000')
        ->and($card['description'])->toContain(now()->subYears(2)->addYears(7)->format('j F Y'));
});

it('counts only a registered Lasting Power of Attorney', function () {
    LastingPowerOfAttorney::create(['user_id' => $this->user->id, 'lpa_type' => 'property_financial', 'status' => 'draft']);
    LastingPowerOfAttorney::create(['user_id' => $this->user->id, 'lpa_type' => 'health_welfare', 'status' => 'registered', 'is_registered_with_opg' => true]);

    [$card] = estateCardsFor($this->user, 'no_lpa');

    expect($card['figures']['missing_financial'])->toBeTrue()
        ->and($card['figures']['missing_health'])->toBeFalse()
        ->and($card['figures']['has_unregistered'])->toBeTrue()
        ->and($card['figures']['missing_text'])->toBe('property and financial affairs');

    LastingPowerOfAttorney::where('user_id', $this->user->id)->where('lpa_type', 'property_financial')
        ->update(['status' => 'registered', 'is_registered_with_opg' => true]);

    expect(estateCardsFor($this->user, 'no_lpa'))->toBe([]);
});

it('dates a discretionary trust by its ten-year anniversary, not a yearly review', function () {
    $base = ['user_id' => $this->user->id, 'trust_type' => 'discretionary', 'initial_value' => 100000, 'current_value' => 100000];
    Trust::create($base + ['trust_name' => 'Soon', 'trust_creation_date' => now()->subYears(9)->toDateString()]);
    Trust::create($base + ['trust_name' => 'Later', 'trust_creation_date' => now()->subYears(5)->toDateString()]);

    $cards = estateCardsFor($this->user, 'trust_anniversary_due');

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['title'])->toBe('Soon reaches ten years on '.now()->subYears(9)->addYears(10)->format('j F Y'))
        ->and($cards[0]['description'])->toContain('up to 6%');
});

it('names a pension only when it has no beneficiary recorded', function () {
    DCPension::factory()->create(['user_id' => $this->user->id, 'scheme_name' => 'Nominated', 'beneficiary_name' => 'Sam']);
    DCPension::factory()->create(['user_id' => $this->user->id, 'scheme_name' => 'Unnominated', 'beneficiary_name' => null, 'beneficiary_id' => null]);

    $cards = estateCardsFor($this->user, 'pension_no_beneficiary');

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['title'])->toBe('No beneficiary recorded for Unnominated');
});

it('states an Inheritance Tax figure that the estate and allowances add up to', function () {
    Property::where('user_id', $this->user->id)->update(['current_value' => 1_000_000]);

    [$card] = estateCardsFor($this->user, 'iht_position');
    $f = $card['figures'];
    $pounds = fn (string $v): float => (float) str_replace(['£', ','], '', $v);

    expect(round(($pounds($f['net_estate']) - $pounds($f['allowances'])) * ((int) $f['rate_percent'] / 100)))
        ->toBe(round($pounds($f['iht_liability'])))
        ->and($card['description'])->not->toMatch('/\b40%\s+resulting|nil-rate band/i');
});

it('says a married user\'s figure is their own records alone when the partner is not linked (W-0467)', function () {
    // brett-a on csjones (2026-10-07): married, partner with no account, told
    // "If you died today, £105,200 would be due" — but what passes to a wife is
    // free of the tax (IHTA 1984 s18). The teaser's approved sentences apply.
    $this->user->update(['marital_status' => 'married']);
    Property::where('user_id', $this->user->id)->update(['current_value' => 1_000_000]);

    [$card] = estateCardsFor($this->user, 'iht_position');

    expect($card['description'])->toStartWith('Based on your own records alone, your estate of')
        ->and($card['figures']['has_partner_note'])->toBeTrue()
        ->and($card['figures']['partner_note'])->toBe('This figure does not allow for anything passing to your partner. Linking your accounts gives a fuller picture.');

    $this->user->update(['marital_status' => 'single']);
    Cache::flush(); // the agent's cached analysis; a real profile edit clears it
    [$single] = estateCardsFor($this->user->fresh(), 'iht_position');
    expect($single['description'])->toStartWith('Your estate of')
        ->and($single['figures']['has_partner_note'])->toBeFalse();
});

it('carries the engine\'s caveat on the current figure, in its words (W-0534)', function () {
    Property::where('user_id', $this->user->id)->update(['current_value' => 1_000_000]);
    DCPension::factory()->create(['user_id' => $this->user->id, 'current_fund_value' => 100_000]);

    [$card] = estateCardsFor($this->user, 'iht_position');
    $engine = app(EstateAgent::class)->analyze($this->user->id)['data']['iht_calculation'];

    expect($engine['pension_exclusion_caveat'])->not->toBeNull()
        ->and($card['figures']['has_pension_caveat'])->toBeTrue()
        ->and($card['figures']['pension_caveat'])->toBe($engine['pension_exclusion_caveat']);
});
