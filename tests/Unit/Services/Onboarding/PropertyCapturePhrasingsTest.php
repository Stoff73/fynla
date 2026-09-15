<?php

declare(strict_types=1);

use App\Services\Onboarding\AssetCaptureEntityExtractor;
use App\Services\Onboarding\CaptureAccuracyGate;
use App\Services\Stores\Normalisers\PropertyNormaliser;

/**
 * Property capture phrasings — the deterministic backstop and the accuracy
 * gate, driven by every way a person describes their homes in one message
 * (CSJ 2026-09-15, live prod conversation 874: "My home which I own with my
 * wife, worth 750000 with a mortgage of 325000 and a buy to let worth 450000
 * mortgage of 100000 rental income of 1000 per month I own this myself"
 * produced a home with no value, both properties "individual", and four
 * gate refusals).
 *
 * Each case lists the entities the extractor must return, in order, with the
 * fields that matter. `share` null means the extractor leaves the share to the
 * gate; the gate case then says whether each write is allowed or which field
 * it asks for.
 */
beforeEach(function (): void {
    $this->extractor = new AssetCaptureEntityExtractor;
});

function propertyCases(): array
{
    return [
        'the live two-property sentence' => [
            'My home which I own with my wife, worth 750000 with a mortgage of 325000 and a buy to let worth 450000 mortgage of 100000 rental income of 1000 per month I own this myself',
            [
                ['property_type' => 'main_residence', 'current_value' => 750000.0, 'mortgage_outstanding_balance' => 325000.0, 'ownership_type' => 'joint'],
                ['property_type' => 'buy_to_let', 'current_value' => 450000.0, 'mortgage_outstanding_balance' => 100000.0, 'ownership_type' => 'individual', 'monthly_rental_income' => 1000.0],
            ],
        ],
        'home with left-on-the-mortgage and an equal split' => [
            'Our home is worth 450000 with 200000 left on the mortgage, joint with my wife 50/50',
            [['property_type' => 'main_residence', 'current_value' => 450000.0, 'mortgage_outstanding_balance' => 200000.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0]],
        ],
        'pound signs, k suffix, in my name only' => [
            'My house is worth £650k, mortgage of £180,000, in my name only',
            [['property_type' => 'main_residence', 'current_value' => 650000.0, 'mortgage_outstanding_balance' => 180000.0, 'ownership_type' => 'individual']],
        ],
        'flat owned outright, no ownership stated' => [
            'I have a flat worth 300k which I own outright',
            [['property_type' => 'main_residence', 'current_value' => 300000.0, 'has_mortgage' => false]],
        ],
        'valued at, no mortgage, half each with husband' => [
            'Main residence valued at 900,000, no mortgage, owned jointly with my husband, half each',
            [['property_type' => 'main_residence', 'current_value' => 900000.0, 'has_mortgage' => false, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0]],
        ],
        'buy to let with a bare mortgage figure and rent a month, mine' => [
            'A buy to let in Leeds worth 220000 with a 150000 mortgage, rent 950 a month, mine',
            [['property_type' => 'buy_to_let', 'current_value' => 220000.0, 'mortgage_outstanding_balance' => 150000.0, 'monthly_rental_income' => 950.0, 'ownership_type' => 'individual']],
        ],
        'BTL, owe on it, tenants pay pcm, joint with partner' => [
            'BTL flat worth £180k, owe £90k on it, tenants pay £850 pcm, joint with my partner 50/50',
            [['property_type' => 'buy_to_let', 'current_value' => 180000.0, 'mortgage_outstanding_balance' => 90000.0, 'monthly_rental_income' => 850.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0]],
        ],
        'second home, mortgage free, both our names' => [
            'Second home in Cornwall worth 400k, mortgage free, both our names',
            [['property_type' => 'secondary_residence', 'current_value' => 400000.0, 'has_mortgage' => false, 'ownership_type' => 'joint']],
        ],
        'two properties, yearly rent, ownership stated once for both' => [
            'House worth 500000 mortgage 250000 and a rental property worth 200000 no mortgage rental income 12000 a year, both jointly owned with my wife 50/50',
            [
                ['property_type' => 'main_residence', 'current_value' => 500000.0, 'mortgage_outstanding_balance' => 250000.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0],
                ['property_type' => 'buy_to_let', 'current_value' => 200000.0, 'has_mortgage' => false, 'monthly_rental_income' => 1000.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0],
            ],
        ],
        'my wife and I own it together survives the connector split' => [
            'my home worth 750000, 325000 left on the mortgage, my wife and I own it together',
            [['property_type' => 'main_residence', 'current_value' => 750000.0, 'mortgage_outstanding_balance' => 325000.0, 'ownership_type' => 'joint']],
        ],
        'we have a house, outstanding mortgage of' => [
            'we have a house valued at 425000 with an outstanding mortgage of 210000',
            [['property_type' => 'main_residence', 'current_value' => 425000.0, 'mortgage_outstanding_balance' => 210000.0]],
        ],
        'three properties with mixed ownership' => [
            'Home worth 600k mortgage 200k joint with my wife 50/50, a buy to let worth 250k mortgage 150k rent 1100 a month in my name, and a holiday home worth 300k no mortgage joint 50/50',
            [
                ['property_type' => 'main_residence', 'current_value' => 600000.0, 'mortgage_outstanding_balance' => 200000.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0],
                ['property_type' => 'buy_to_let', 'current_value' => 250000.0, 'mortgage_outstanding_balance' => 150000.0, 'monthly_rental_income' => 1100.0, 'ownership_type' => 'individual'],
                ['property_type' => 'secondary_residence', 'current_value' => 300000.0, 'has_mortgage' => false, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0],
            ],
        ],
        'a bare flat with nothing else' => [
            'I have a flat',
            [['property_type' => 'main_residence']],
        ],
        'postcode and still owe on the mortgage' => [
            'House at SW1A 1AA worth 800000, still owe 320000 on the mortgage, just me',
            [['property_type' => 'main_residence', 'postcode' => 'SW1A1AA', 'current_value' => 800000.0, 'mortgage_outstanding_balance' => 320000.0, 'ownership_type' => 'individual']],
        ],
        'yearly rental income with a pound sign, mortgage balance is' => [
            'Rental property worth about 300k, mortgage balance is 120,000, rental income of £18,000 a year, it is mine',
            [['property_type' => 'buy_to_let', 'current_value' => 300000.0, 'mortgage_outstanding_balance' => 120000.0, 'monthly_rental_income' => 1500.0, 'ownership_type' => 'individual']],
        ],
        'remaining mortgage and value is' => [
            'apartment, value is 300000, remaining mortgage 100000, jointly with my husband, my share is 60%',
            [['property_type' => 'main_residence', 'current_value' => 300000.0, 'mortgage_outstanding_balance' => 100000.0, 'ownership_type' => 'joint', 'ownership_percentage' => 60.0]],
        ],
        'outstanding on the mortgage, paid off elsewhere' => [
            'My house is worth 520000 with £140,000 outstanding on the mortgage and a BTL worth 210000 that is paid off, rents for 900 per month, both in my sole name',
            [
                ['property_type' => 'main_residence', 'current_value' => 520000.0, 'mortgage_outstanding_balance' => 140000.0, 'ownership_type' => 'individual'],
                ['property_type' => 'buy_to_let', 'current_value' => 210000.0, 'has_mortgage' => false, 'monthly_rental_income' => 900.0, 'ownership_type' => 'individual'],
            ],
        ],
        'weekly rent and lets for' => [
            'investment property worth 175000, no mortgage, lets for £230 a week, mine alone',
            [['property_type' => 'buy_to_let', 'current_value' => 175000.0, 'has_mortgage' => false, 'monthly_rental_income' => 996.67, 'ownership_type' => 'individual']],
        ],
        'bungalow roughly, mortgage is, in both our names' => [
            'bungalow roughly 350000, mortgage is 90000, in both our names, split equally',
            [['property_type' => 'main_residence', 'current_value' => 350000.0, 'mortgage_outstanding_balance' => 90000.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0]],
        ],
    ];
}

describe('extractProperties', function (): void {
    it('reads every property phrasing', function (string $message, array $expected): void {
        $actual = $this->extractor->extractForFocus('property', $message);

        expect($actual)->toHaveCount(count($expected), 'entity count for: '.$message);
        foreach ($expected as $index => $fields) {
            foreach ($fields as $key => $value) {
                $got = $actual[$index][$key] ?? null;
                $label = "entity {$index} {$key} for: {$message}";
                if (is_float($value)) {
                    expect($got)->not->toBeNull($label)
                        ->and(round((float) $got, 2))->toBe($value, $label);
                } else {
                    expect($got)->toBe($value, $label);
                }
            }
            // Never a field the user did not state.
            foreach (['ownership_type', 'ownership_percentage', 'monthly_rental_income', 'mortgage_outstanding_balance', 'current_value'] as $key) {
                if (! array_key_exists($key, $fields)) {
                    expect($actual[$index])->not->toHaveKey($key, "unstated {$key} on entity {$index} for: {$message}");
                }
            }
            expect($actual[$index])->not->toHaveKey('_evidence');
        }
    })->with(propertyCases());

    it('never invents an ownership for a second property from the first one\'s words', function (): void {
        $actual = $this->extractor->extractForFocus(
            'property',
            'My home is worth 400000, mine, and a buy to let worth 150000',
        );

        expect($actual)->toHaveCount(2)
            ->and($actual[0]['ownership_type'] ?? null)->toBe('individual')
            ->and($actual[1])->not->toHaveKey('ownership_type');
    });
});

describe('CaptureAccuracyGate on a multi-property message', function (): void {
    it('lets the buy to let through and asks only for the home\'s share on the live sentence', function (): void {
        $text = 'My home which I own with my wife, worth 750000 with a mortgage of 325000 and a buy to let worth 450000 mortgage of 100000 rental income of 1000 per month I own this myself';
        $gate = app(CaptureAccuracyGate::class);

        $btl = $gate->inspect('create_property', [
            'property_type' => 'buy_to_let', 'current_value' => 450000, 'has_mortgage' => true,
            'mortgage_outstanding_balance' => 100000, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual',
        ], $text);
        $home = $gate->inspect('create_property', [
            'property_type' => 'main_residence', 'current_value' => 750000, 'has_mortgage' => true,
            'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint',
        ], $text);

        expect($btl['allowed'])->toBeTrue($btl['reason'] ?? '')
            ->and($home['allowed'])->toBeFalse()
            ->and($home['missing'])->toBe(['ownership_percentage']);
    });

    it('refuses the invented individual home the backstop used to send', function (): void {
        $text = 'My home which I own with my wife, worth 750000 with a mortgage of 325000 and a buy to let worth 450000 mortgage of 100000 rental income of 1000 per month I own this myself';
        $home = app(CaptureAccuracyGate::class)->inspect('create_property', [
            'property_type' => 'main_residence', 'ownership_type' => 'individual',
        ], $text);

        expect($home['allowed'])->toBeFalse()
            ->and($home['missing'])->toContain('ownership_type');
    });

    it('allows both when the share is stated for the joint one', function (): void {
        $text = 'Home worth 600k mortgage 200k joint with my wife 50/50, a buy to let worth 250k mortgage 150k rent 1100 a month in my name';
        $gate = app(CaptureAccuracyGate::class);

        $home = $gate->inspect('create_property', [
            'property_type' => 'main_residence', 'current_value' => 600000, 'ownership_type' => 'joint', 'ownership_percentage' => 50,
        ], $text);
        $btl = $gate->inspect('create_property', [
            'property_type' => 'buy_to_let', 'current_value' => 250000, 'ownership_type' => 'individual',
        ], $text);

        expect($home['allowed'])->toBeTrue($home['reason'] ?? '')
            ->and($btl['allowed'])->toBeTrue($btl['reason'] ?? '');
    });

    it('applies a group ownership clause to every property in the message', function (): void {
        $text = 'House worth 500000 mortgage 250000 and a rental property worth 200000 no mortgage, both jointly owned with my wife 50/50';
        $gate = app(CaptureAccuracyGate::class);

        $home = $gate->inspect('create_property', [
            'property_type' => 'main_residence', 'current_value' => 500000, 'ownership_type' => 'joint', 'ownership_percentage' => 50,
        ], $text);
        $btl = $gate->inspect('create_property', [
            'property_type' => 'buy_to_let', 'current_value' => 200000, 'ownership_type' => 'joint', 'ownership_percentage' => 50,
        ], $text);

        expect($home['allowed'])->toBeTrue($home['reason'] ?? '')
            ->and($btl['allowed'])->toBeTrue($btl['reason'] ?? '');
    });

    it('drops a co-owner name the user never said and keeps one they did', function (): void {
        $gate = app(CaptureAccuracyGate::class);
        $text = 'My home which I own with my wife, worth 750000 with a mortgage of 325000';

        $invented = $gate->inspect('create_property', [
            'property_type' => 'main_residence', 'current_value' => 750000, 'ownership_type' => 'joint',
            'ownership_percentage' => 50, 'joint_owner_name' => 'Worth',
        ], $text."\n50%");
        $stated = $gate->inspect('create_property', [
            'property_type' => 'main_residence', 'current_value' => 750000, 'ownership_type' => 'joint',
            'ownership_percentage' => 50, 'joint_owner_name' => 'Sarah',
        ], 'My home worth 750000 which my wife Sarah and I own jointly 50/50');
        $relationship = $gate->inspect('create_property', [
            'property_type' => 'main_residence', 'current_value' => 750000, 'ownership_type' => 'joint',
            'ownership_percentage' => 50, 'joint_owner_name' => 'my wife',
        ], $text."\n50%");

        expect($invented['allowed'])->toBeTrue($invented['reason'] ?? '')
            ->and($invented['repaired'] ?? [])->toHaveKey('joint_owner_name')
            ->and($invented['repaired']['joint_owner_name'])->toBeNull()
            ->and($stated['allowed'])->toBeTrue($stated['reason'] ?? '')
            ->and($stated['repaired'] ?? [])->not->toHaveKey('joint_owner_name')
            ->and($relationship['allowed'])->toBeTrue($relationship['reason'] ?? '')
            ->and($relationship['repaired'] ?? [])->not->toHaveKey('joint_owner_name');
    });
});

describe('PropertyNormaliser::fromFyn', function (): void {
    it('omits a null tenure type so the NOT NULL default applies', function (): void {
        $canonical = app(PropertyNormaliser::class)->fromFyn([
            'property_type' => 'main_residence', 'current_value' => 750000, 'ownership_type' => 'joint',
            'ownership_percentage' => 50, 'tenure_type' => null, 'city' => null, 'postcode' => null,
        ]);

        expect($canonical)->not->toHaveKey('tenure_type')
            ->and(app(PropertyNormaliser::class)->fromFyn(['property_type' => 'buy_to_let', 'tenure_type' => 'leasehold'])['tenure_type'] ?? null)->toBe('leasehold');
    });
});
