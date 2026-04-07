<?php

declare(strict_types=1);

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\LpaAttorney;
use App\Models\Estate\LpaNotificationPerson;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\LpaComplianceService;

beforeEach(function () {
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $this->service = new LpaComplianceService;
    $this->user = User::factory()->create();
});

describe('checkCompliance', function () {
    it('returns structured compliance result', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        $result = $this->service->checkCompliance($lpa);

        expect($result)->toHaveKeys(['checks', 'passed', 'failed', 'warnings', 'overall_status'])
            ->and($result['checks'])->toBeArray();
    });

    it('fails when no attorneys appointed', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);

        $result = $this->service->checkCompliance($lpa);

        $attorneyCheck = collect($result['checks'])->firstWhere('key', 'attorney_count');
        expect($attorneyCheck['status'])->toBe('fail');
    });

    it('passes when attorney is appointed', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id]);

        $result = $this->service->checkCompliance($lpa);

        $attorneyCheck = collect($result['checks'])->firstWhere('key', 'attorney_count');
        expect($attorneyCheck['status'])->toBe('pass');
    });

    it('fails donor age check when under 18', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'donor_date_of_birth' => now()->subYears(16),
            ]);

        $result = $this->service->checkCompliance($lpa);

        $ageCheck = collect($result['checks'])->firstWhere('key', 'donor_age');
        expect($ageCheck['status'])->toBe('fail');
    });

    it('passes donor age check when 18 or older', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'donor_date_of_birth' => now()->subYears(55),
            ]);

        $result = $this->service->checkCompliance($lpa);

        $ageCheck = collect($result['checks'])->firstWhere('key', 'donor_age');
        expect($ageCheck['status'])->toBe('pass');
    });

    it('requires decision type when multiple primary attorneys', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'attorney_decision_type' => null,
            ]);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id, 'attorney_type' => 'primary']);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id, 'attorney_type' => 'primary']);

        $result = $this->service->checkCompliance($lpa);

        $decisionCheck = collect($result['checks'])->firstWhere('key', 'decision_type');
        expect($decisionCheck['status'])->toBe('fail');
    });

    it('fails certificate provider 2-year rule', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create([
                'user_id' => $this->user->id,
                'certificate_provider_name' => 'Dr Smith',
                'certificate_provider_known_years' => 1,
            ]);

        $result = $this->service->checkCompliance($lpa);

        $yearsCheck = collect($result['checks'])->firstWhere('key', 'certificate_provider_years');
        expect($yearsCheck['status'])->toBe('fail');
    });

    it('fails when more than 5 notification persons', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LpaNotificationPerson::factory(6)->create(['lasting_power_of_attorney_id' => $lpa->id]);

        $result = $this->service->checkCompliance($lpa);

        $notifyCheck = collect($result['checks'])->firstWhere('key', 'notification_limit');
        expect($notifyCheck['status'])->toBe('fail');
    });

    it('warns when no replacement attorneys', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
        ]);

        $result = $this->service->checkCompliance($lpa);

        $replacementCheck = collect($result['checks'])->firstWhere('key', 'replacement_attorneys');
        expect($replacementCheck['status'])->toBe('warning');
    });

    it('checks when_can_act for property financial type', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->create([
                'user_id' => $this->user->id,
                'lpa_type' => 'property_financial',
                'when_attorneys_can_act' => null,
            ]);

        $result = $this->service->checkCompliance($lpa);

        $whenCheck = collect($result['checks'])->firstWhere('key', 'when_can_act');
        expect($whenCheck)->not->toBeNull()
            ->and($whenCheck['status'])->toBe('fail');
    });

    it('checks life sustaining treatment for health welfare type', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->create([
                'user_id' => $this->user->id,
                'lpa_type' => 'health_welfare',
                'life_sustaining_treatment' => null,
            ]);

        $result = $this->service->checkCompliance($lpa);

        $lifeCheck = collect($result['checks'])->firstWhere('key', 'life_sustaining');
        expect($lifeCheck)->not->toBeNull()
            ->and($lifeCheck['status'])->toBe('fail');
    });

    it('does not check when_can_act for health welfare type', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->healthWelfare()
            ->create(['user_id' => $this->user->id]);

        $result = $this->service->checkCompliance($lpa);

        $whenCheck = collect($result['checks'])->firstWhere('key', 'when_can_act');
        expect($whenCheck)->toBeNull();
    });

    it('returns compliant status when all checks pass', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->registered()
            ->create([
                'user_id' => $this->user->id,
                'donor_date_of_birth' => now()->subYears(55),
                'certificate_provider_name' => 'Dr Smith',
                'certificate_provider_known_years' => 5,
                'when_attorneys_can_act' => 'only_when_lost_capacity',
            ]);
        LpaAttorney::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
        ]);
        LpaAttorney::factory()->replacement()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
        ]);
        LpaNotificationPerson::factory()->create([
            'lasting_power_of_attorney_id' => $lpa->id,
        ]);

        $result = $this->service->checkCompliance($lpa);

        expect($result['overall_status'])->toBe('compliant')
            ->and($result['failed'])->toBe(0);
    });
});
