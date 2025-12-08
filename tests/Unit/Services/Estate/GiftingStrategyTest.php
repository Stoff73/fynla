<?php

declare(strict_types=1);

use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\GiftingStrategy;
use Carbon\Carbon;

beforeEach(function () {
    // Ensure active tax configuration exists
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $taxConfig = app(\App\Services\TaxConfigService::class);
    $this->strategy = new GiftingStrategy($taxConfig);
    $this->user = User::factory()->create();
});

describe('analyzePETs', function () {
    it('identifies PETs within 7 years', function () {
        $gifts = collect([
            new Gift([
                'id' => 1,
                'gift_date' => Carbon::now()->subYears(3),
                'recipient' => 'Child',
                'gift_type' => 'pet',
                'gift_value' => 50000,
            ]),
            new Gift([
                'id' => 2,
                'gift_date' => Carbon::now()->subYears(5),
                'recipient' => 'Grandchild',
                'gift_type' => 'pet',
                'gift_value' => 30000,
            ]),
        ]);

        $result = $this->strategy->analyzePETs($gifts);

        expect($result['active_pets_count'])->toBe(2)
            ->and($result['total_pet_value'])->toBe(80000.0)
            ->and($result['pets'])->toHaveCount(2);
    });

    it('filters out PETs older than 7 years', function () {
        $gifts = collect([
            new Gift([
                'id' => 1,
                'gift_date' => Carbon::now()->subYears(8),
                'recipient' => 'Child',
                'gift_type' => 'pet',
                'gift_value' => 50000,
            ]),
        ]);

        $result = $this->strategy->analyzePETs($gifts);

        expect($result['active_pets_count'])->toBe(0)
            ->and($result['total_pet_value'])->toBe(0.0);
    });

    it('includes years remaining until full exemption', function () {
        $gifts = collect([
            new Gift([
                'id' => 1,
                'gift_date' => Carbon::now()->subYears(5),
                'recipient' => 'Child',
                'gift_type' => 'pet',
                'gift_value' => 50000,
            ]),
        ]);

        $result = $this->strategy->analyzePETs($gifts);

        expect($result['pets'][0]['years_remaining'])->toBe(2)
            ->and($result['pets'][0]['years_ago'])->toBe(5);
    });
});

describe('calculateAnnualExemption', function () {
    it('returns full allowance when no gifts made', function () {
        $result = $this->strategy->calculateAnnualExemption($this->user->id, '2024');

        expect($result)->toBe(6000.0); // £3k current + £3k carry forward
    });

    it('reduces available exemption when gifts have been made', function () {
        Gift::create([
            'user_id' => $this->user->id,
            'gift_date' => Carbon::createFromFormat('Y-m-d', '2024-06-01'),
            'recipient' => 'Child',
            'gift_type' => 'pet',
            'gift_value' => 2000,
        ]);

        $result = $this->strategy->calculateAnnualExemption($this->user->id, '2024');

        expect($result)->toBe(4000.0); // £1k current + £3k carry forward
    });

    it('accounts for carry forward from previous year', function () {
        // Gift in previous tax year
        Gift::create([
            'user_id' => $this->user->id,
            'gift_date' => Carbon::createFromFormat('Y-m-d', '2023-06-01'),
            'recipient' => 'Child',
            'gift_type' => 'pet',
            'gift_value' => 1000,
        ]);

        $result = $this->strategy->calculateAnnualExemption($this->user->id, '2024');

        expect($result)->toBe(5000.0); // £3k current + £2k carry forward
    });
});

describe('identifySmallGifts', function () {
    it('identifies valid small gifts under £250', function () {
        $gifts = collect([
            new Gift([
                'gift_date' => Carbon::createFromFormat('Y-m-d', '2024-06-01'),
                'recipient' => 'Friend 1',
                'gift_type' => 'small_gift',
                'gift_value' => 200,
            ]),
            new Gift([
                'gift_date' => Carbon::createFromFormat('Y-m-d', '2024-07-01'),
                'recipient' => 'Friend 2',
                'gift_type' => 'small_gift',
                'gift_value' => 250,
            ]),
        ]);

        $result = $this->strategy->identifySmallGifts($gifts);

        expect($result['small_gifts_count'])->toBe(2)
            ->and($result['total_value'])->toBe(450.0)
            ->and($result['by_recipient'])->toHaveCount(2);
    });

    it('flags invalid small gifts over £250 to same recipient', function () {
        $gifts = collect([
            new Gift([
                'gift_date' => Carbon::createFromFormat('Y-m-d', '2024-06-01'),
                'recipient' => 'Friend',
                'gift_type' => 'small_gift',
                'gift_value' => 200,
            ]),
            new Gift([
                'gift_date' => Carbon::createFromFormat('Y-m-d', '2024-07-01'),
                'recipient' => 'Friend',
                'gift_type' => 'small_gift',
                'gift_value' => 100,
            ]),
        ]);

        $result = $this->strategy->identifySmallGifts($gifts);

        expect($result['by_recipient'][0]['is_valid'])->toBe(false)
            ->and($result['by_recipient'][0]['warning'])->toContain('Exceeds £250 limit');
    });
});

describe('calculateMarriageGifts', function () {
    it('returns £5,000 for child', function () {
        $amount = $this->strategy->calculateMarriageGifts('child');

        expect($amount)->toBe(5000.0);
    });

    it('returns £2,500 for grandchild', function () {
        $amount = $this->strategy->calculateMarriageGifts('grandchild');

        expect($amount)->toBe(2500.0);
    });

    it('returns £2,500 for great grandchild', function () {
        $amount = $this->strategy->calculateMarriageGifts('great_grandchild');

        expect($amount)->toBe(2500.0);
    });

    it('returns £1,000 for other relationships', function () {
        $amount = $this->strategy->calculateMarriageGifts('friend');

        expect($amount)->toBe(1000.0);
    });
});

describe('recommendOptimalGiftingStrategy', function () {
    it('recommends annual exemption when IHT liability exists', function () {
        $profile = new IHTProfile([
            'marital_status' => 'single',
            'has_spouse' => false,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $result = $this->strategy->recommendOptimalGiftingStrategy(600000, $profile);

        expect($result['current_iht_liability'])->toBe(110000.0);

        $hasAnnualExemption = collect($result['recommendations'])
            ->contains(fn ($rec) => str_contains($rec['strategy'], 'Annual Exemption'));

        expect($hasAnnualExemption)->toBeTrue();
    });

    it('recommends charitable giving when below 10%', function () {
        $profile = new IHTProfile([
            'marital_status' => 'single',
            'has_spouse' => false,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $result = $this->strategy->recommendOptimalGiftingStrategy(600000, $profile);

        $hasCharitableRecommendation = collect($result['recommendations'])
            ->contains(fn ($rec) => str_contains($rec['strategy'], 'Charitable Giving'));

        expect($hasCharitableRecommendation)->toBe(true);
    });

    it('includes priority ranking for recommendations', function () {
        $profile = new IHTProfile([
            'marital_status' => 'single',
            'has_spouse' => false,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $result = $this->strategy->recommendOptimalGiftingStrategy(600000, $profile);

        expect($result['priority'])->toBeArray()
            ->and($result['priority'][0]['strategy'])->toBe('Annual Exemption')
            ->and($result['priority'][0]['priority'])->toBe(1);
    });

    it('calculates zero IHT when estate is below NRB', function () {
        $profile = new IHTProfile([
            'marital_status' => 'single',
            'has_spouse' => false,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $result = $this->strategy->recommendOptimalGiftingStrategy(300000, $profile);

        expect($result['current_iht_liability'])->toBe(0.0);
    });
});
