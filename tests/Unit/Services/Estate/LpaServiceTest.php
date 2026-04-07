<?php

declare(strict_types=1);

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\LpaAttorney;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\LpaService;

beforeEach(function () {
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $this->service = new LpaService(app(\App\Services\Cache\CacheInvalidationService::class));
    $this->user = User::factory()->create();
});

describe('getLpasForUser', function () {
    it('returns empty array when user has no LPAs', function () {
        $result = $this->service->getLpasForUser($this->user);
        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns all LPAs for the user with related data', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id]);

        $result = $this->service->getLpasForUser($this->user);

        expect($result)->toHaveCount(1)
            ->and($result[0]['lpa_type'])->toBe('property_financial')
            ->and($result[0]['attorneys'])->toHaveCount(1);
    });

    it('does not return other users LPAs', function () {
        $otherUser = User::factory()->create();
        LastingPowerOfAttorney::factory()->create(['user_id' => $otherUser->id]);

        $result = $this->service->getLpasForUser($this->user);
        expect($result)->toBeEmpty();
    });
});

describe('createLpa', function () {
    it('creates an LPA with attorneys and notification persons', function () {
        $data = [
            'lpa_type' => 'property_financial',
            'status' => 'draft',
            'donor_full_name' => 'John Smith',
            'donor_date_of_birth' => '1970-01-15',
            'attorney_decision_type' => 'jointly_and_severally',
            'when_attorneys_can_act' => 'only_when_lost_capacity',
            'certificate_provider_name' => 'Dr Jane Doe',
            'certificate_provider_known_years' => 5,
            'attorneys' => [
                [
                    'attorney_type' => 'primary',
                    'full_name' => 'Sarah Smith',
                    'relationship_to_donor' => 'Spouse',
                ],
                [
                    'attorney_type' => 'replacement',
                    'full_name' => 'Tom Smith',
                    'relationship_to_donor' => 'Son',
                ],
            ],
            'notification_persons' => [
                [
                    'full_name' => 'Bob Jones',
                    'address_line_1' => '10 High Street',
                ],
            ],
        ];

        $lpa = $this->service->createLpa($this->user, $data);

        expect($lpa->donor_full_name)->toBe('John Smith')
            ->and($lpa->lpa_type)->toBe('property_financial')
            ->and($lpa->attorneys)->toHaveCount(2)
            ->and($lpa->notificationPersons)->toHaveCount(1);
    });

    it('sets completed_at when status is completed', function () {
        $data = [
            'lpa_type' => 'health_welfare',
            'status' => 'completed',
            'donor_full_name' => 'John Smith',
            'donor_date_of_birth' => '1970-01-15',
        ];

        $lpa = $this->service->createLpa($this->user, $data);
        expect($lpa->completed_at)->not->toBeNull();
    });
});

describe('updateLpa', function () {
    it('updates LPA fields', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->draft()
            ->create(['user_id' => $this->user->id]);

        $updated = $this->service->updateLpa($lpa, [
            'donor_full_name' => 'Updated Name',
            'preferences' => 'New preferences',
        ]);

        expect($updated->donor_full_name)->toBe('Updated Name')
            ->and($updated->preferences)->toBe('New preferences');
    });

    it('syncs attorneys when provided', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->propertyFinancial()
            ->create(['user_id' => $this->user->id]);
        LpaAttorney::factory()->create(['lasting_power_of_attorney_id' => $lpa->id]);

        $updated = $this->service->updateLpa($lpa, [
            'attorneys' => [
                ['attorney_type' => 'primary', 'full_name' => 'New Attorney 1'],
                ['attorney_type' => 'primary', 'full_name' => 'New Attorney 2'],
            ],
        ]);

        expect($updated->attorneys)->toHaveCount(2)
            ->and($updated->attorneys[0]->full_name)->toBe('New Attorney 1');
    });
});

describe('deleteLpa', function () {
    it('soft deletes an LPA', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->create(['user_id' => $this->user->id]);

        $this->service->deleteLpa($lpa);

        expect(LastingPowerOfAttorney::find($lpa->id))->toBeNull()
            ->and(LastingPowerOfAttorney::withTrashed()->find($lpa->id))->not->toBeNull();
    });
});

describe('markAsRegistered', function () {
    it('marks an LPA as registered with OPG', function () {
        $lpa = LastingPowerOfAttorney::factory()
            ->draft()
            ->create(['user_id' => $this->user->id]);

        $updated = $this->service->markAsRegistered($lpa, [
            'registration_date' => '2024-06-15',
            'opg_reference' => 'OPG-1234567',
        ]);

        expect($updated->status)->toBe('registered')
            ->and($updated->is_registered_with_opg)->toBeTrue()
            ->and($updated->opg_reference)->toBe('OPG-1234567');
    });
});

describe('autoFillDonorDetails', function () {
    it('returns donor details from user profile', function () {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'surname' => 'Doe',
            'date_of_birth' => '1980-05-20',
        ]);

        $defaults = $this->service->autoFillDonorDetails($user);

        expect($defaults['donor_date_of_birth'])->toBe('1980-05-20');
    });
});
