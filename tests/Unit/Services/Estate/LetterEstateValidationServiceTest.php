<?php

declare(strict_types=1);

use App\Models\Chattel;
use App\Models\Estate\Will;
use App\Models\LetterToSpouse;
use App\Models\LifeInsurancePolicy;
use App\Models\Property;
use App\Models\User;
use App\Services\Estate\LetterEstateValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new LetterEstateValidationService;
    $this->user = User::factory()->create();
});

describe('LetterEstateValidationService', function () {
    it('returns empty warnings when no letter exists', function () {
        $result = $this->service->validateLetterAgainstEstate($this->user);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns empty warnings when letter and estate data match', function () {
        $executorName = 'John Smith';

        LetterToSpouse::factory()->create([
            'user_id' => $this->user->id,
            'executor_name' => $executorName,
            'executor_contact' => '07700 900000',
        ]);

        Will::factory()->withWill()->create([
            'user_id' => $this->user->id,
            'executor_name' => $executorName,
        ]);

        $result = $this->service->validateLetterAgainstEstate($this->user);

        $executorWarnings = array_filter($result, fn ($w) => $w['type'] === 'executor_mismatch');
        expect($executorWarnings)->toBeEmpty();
    });

    describe('executor cross-check', function () {
        it('detects executor name mismatch between letter and will', function () {
            LetterToSpouse::factory()->create([
                'user_id' => $this->user->id,
                'executor_name' => 'Alice Jones',
            ]);

            Will::factory()->withWill()->create([
                'user_id' => $this->user->id,
                'executor_name' => 'Bob Williams',
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $executorWarnings = array_filter($result, fn ($w) => $w['type'] === 'executor_mismatch');
            expect($executorWarnings)->toHaveCount(1);

            $warning = array_values($executorWarnings)[0];
            expect($warning['severity'])->toBe('high');
            expect($warning['message'])->toContain('Alice Jones');
            expect($warning['message'])->toContain('Bob Williams');
        });

        it('warns when will has executor but letter does not', function () {
            LetterToSpouse::factory()->create([
                'user_id' => $this->user->id,
                'executor_name' => null,
            ]);

            Will::factory()->withWill()->create([
                'user_id' => $this->user->id,
                'executor_name' => 'Jane Doe',
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $executorWarnings = array_filter($result, fn ($w) => $w['type'] === 'executor_mismatch');
            expect($executorWarnings)->not->toBeEmpty();

            $warning = array_values($executorWarnings)[0];
            expect($warning['severity'])->toBe('medium');
            expect($warning['message'])->toContain('Jane Doe');
        });

        it('matches executor names case-insensitively', function () {
            LetterToSpouse::factory()->create([
                'user_id' => $this->user->id,
                'executor_name' => 'john smith',
            ]);

            Will::factory()->withWill()->create([
                'user_id' => $this->user->id,
                'executor_name' => 'John Smith',
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $executorWarnings = array_filter($result, fn ($w) => $w['type'] === 'executor_mismatch');
            expect($executorWarnings)->toBeEmpty();
        });

        it('matches executor names ignoring titles', function () {
            LetterToSpouse::factory()->create([
                'user_id' => $this->user->id,
                'executor_name' => 'Mr. John Smith',
            ]);

            Will::factory()->withWill()->create([
                'user_id' => $this->user->id,
                'executor_name' => 'John Smith',
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $executorWarnings = array_filter($result, fn ($w) => $w['type'] === 'executor_mismatch');
            expect($executorWarnings)->toBeEmpty();
        });
    });

    describe('insurance cross-check', function () {
        it('detects system policies not mentioned in letter', function () {
            LetterToSpouse::factory()->create([
                'user_id' => $this->user->id,
                'insurance_policies_info' => 'Legal & General policy details here',
            ]);

            LifeInsurancePolicy::factory()->create([
                'user_id' => $this->user->id,
                'provider' => 'Aviva',
                'policy_number' => 'LI123456',
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $insuranceWarnings = array_filter($result, fn ($w) => $w['type'] === 'insurance_unmatched');
            expect($insuranceWarnings)->not->toBeEmpty();

            $warning = array_values($insuranceWarnings)[0];
            expect($warning['message'])->toContain('Aviva');
        });

        it('warns when system has policies but letter has no insurance info', function () {
            LetterToSpouse::factory()->create([
                'user_id' => $this->user->id,
                'insurance_policies_info' => null,
            ]);

            LifeInsurancePolicy::factory()->create([
                'user_id' => $this->user->id,
                'provider' => 'Aviva',
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $insuranceWarnings = array_filter($result, fn ($w) => $w['type'] === 'insurance_unmatched');
            expect($insuranceWarnings)->not->toBeEmpty();

            $warning = array_values($insuranceWarnings)[0];
            expect($warning['severity'])->toBe('medium');
        });

        it('no insurance warning when provider is mentioned in letter', function () {
            LetterToSpouse::factory()->create([
                'user_id' => $this->user->id,
                'insurance_policies_info' => 'Life Insurance - Aviva, Policy LI123456',
            ]);

            LifeInsurancePolicy::factory()->create([
                'user_id' => $this->user->id,
                'provider' => 'Aviva',
                'policy_number' => 'LI123456',
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $insuranceWarnings = array_filter($result, fn ($w) => $w['type'] === 'insurance_unmatched');
            expect($insuranceWarnings)->toBeEmpty();
        });
    });

    describe('asset cross-check', function () {
        it('flags cryptocurrency mentioned in letter as untracked', function () {
            LetterToSpouse::factory()->withCryptocurrency()->create([
                'user_id' => $this->user->id,
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $cryptoWarnings = array_filter($result, fn ($w) => $w['type'] === 'asset_untracked' && str_contains($w['message'], 'cryptocurrency'));
            expect($cryptoWarnings)->not->toBeEmpty();

            $warning = array_values($cryptoWarnings)[0];
            expect($warning['severity'])->toBe('medium');
        });

        it('flags vehicles in letter when no chattels recorded', function () {
            LetterToSpouse::factory()->withVehicles()->create([
                'user_id' => $this->user->id,
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $vehicleWarnings = array_filter($result, fn ($w) => $w['type'] === 'asset_untracked' && str_contains($w['message'], 'vehicle'));
            expect($vehicleWarnings)->not->toBeEmpty();
        });

        it('flags system vehicles not mentioned in letter', function () {
            LetterToSpouse::factory()->create([
                'user_id' => $this->user->id,
                'vehicles_info' => null,
            ]);

            Chattel::factory()->create([
                'user_id' => $this->user->id,
                'chattel_type' => 'vehicle',
                'name' => 'BMW 3 Series',
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $missingWarnings = array_filter($result, fn ($w) => $w['type'] === 'missing_in_letter' && str_contains($w['message'], 'vehicle'));
            expect($missingWarnings)->not->toBeEmpty();
        });
    });

    describe('completeness check', function () {
        it('warns when properties exist but letter has no real estate info', function () {
            LetterToSpouse::factory()->create([
                'user_id' => $this->user->id,
                'real_estate_info' => null,
            ]);

            Property::factory()->create([
                'user_id' => $this->user->id,
            ]);

            $result = $this->service->validateLetterAgainstEstate($this->user);

            $propertyWarnings = array_filter($result, fn ($w) => $w['type'] === 'missing_in_letter' && str_contains($w['message'], 'propert'));
            expect($propertyWarnings)->not->toBeEmpty();

            $warning = array_values($propertyWarnings)[0];
            expect($warning['severity'])->toBe('medium');
        });
    });
});
