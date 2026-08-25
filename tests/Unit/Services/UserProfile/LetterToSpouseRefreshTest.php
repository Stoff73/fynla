<?php

declare(strict_types=1);

use App\Models\LetterToSpouse;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Estate\LetterEstateValidationService;
use App\Services\UserProfile\LetterToSpouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0022 — the letter to loved ones told a surviving partner "No outstanding
 * liabilities recorded." while a £65,000 mortgage existed.
 *
 * The letter row was written once, minutes before the mortgage was added, and
 * never revisited. Two failures, not one: content frozen at creation, and an
 * empty section rendered as a positive denial rather than as nothing recorded.
 */
beforeEach(function () {
    $this->service = app(LetterToSpouseService::class);
});

function letterUserWithMortgage(User $user, float $balance = 65000): Mortgage
{
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 850000,
    ]);

    return Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'lender_name' => 'HSBC',
        'outstanding_balance' => $balance,
        'monthly_payment' => 1200,
    ]);
}

describe('auto-populated sections stay live', function () {
    it('never asserts an absence it has not just checked', function () {
        $user = User::factory()->create();

        $letter = $this->service->getOrCreateLetter($user);

        expect($letter->liabilities_info)->toBeNull();
    });

    it('picks up a mortgage added after the letter row was created', function () {
        $user = User::factory()->create();

        $this->service->getOrCreateLetter($user);
        letterUserWithMortgage($user);

        $letter = $this->service->getOrCreateLetter($user->fresh());

        expect($letter->liabilities_info)->toContain('HSBC');
        expect($letter->liabilities_info)->toContain('65,000');
    });

    it('never overwrites a section the user has written', function () {
        $user = User::factory()->create();
        $this->service->getOrCreateLetter($user);

        $this->service->updateLetter($user, [
            'liabilities_info' => 'Ask Michael at the bank — he has the paperwork.',
        ]);

        letterUserWithMortgage($user);
        $letter = $this->service->getOrCreateLetter($user->fresh());

        expect($letter->liabilities_info)->toBe('Ask Michael at the bank — he has the paperwork.');
    });

    it('adopts a legacy row still holding the old denial, and refreshes it', function () {
        $user = User::factory()->create();

        // A row written before auto_populated_fields existed.
        LetterToSpouse::create([
            'user_id' => $user->id,
            'liabilities_info' => 'No outstanding liabilities recorded.',
            'funeral_preference' => 'not_specified',
        ]);
        LetterToSpouse::where('user_id', $user->id)->update(['auto_populated_fields' => null]);

        letterUserWithMortgage($user);
        $letter = $this->service->getOrCreateLetter($user->fresh());

        expect($letter->liabilities_info)->toContain('HSBC');
    });

    it('leaves a legacy row holding the user\'s own words alone', function () {
        $user = User::factory()->create();

        LetterToSpouse::create([
            'user_id' => $user->id,
            'liabilities_info' => 'The mortgage is with HSBC, account details in the safe.',
            'funeral_preference' => 'not_specified',
        ]);
        LetterToSpouse::where('user_id', $user->id)->update(['auto_populated_fields' => null]);

        letterUserWithMortgage($user);
        $letter = $this->service->getOrCreateLetter($user->fresh());

        expect($letter->liabilities_info)->toBe('The mortgage is with HSBC, account details in the safe.');
    });
});

describe('one answer to what the household owes', function () {
    it('stops the letter and its consistency checker contradicting each other', function () {
        $user = User::factory()->create();

        $this->service->getOrCreateLetter($user);
        letterUserWithMortgage($user);

        $user = $user->fresh();
        $letter = $this->service->getOrCreateLetter($user);
        $warnings = app(LetterEstateValidationService::class)->validateLetterAgainstEstate($user);

        expect($this->service->outstandingLiabilityCount($user))->toBe(1);
        expect($letter->liabilities_info)->toContain('HSBC');

        // The checker used to report a liability while the letter denied one.
        $liabilityWarnings = array_filter(
            $warnings,
            fn (array $w) => str_contains($w['message'], 'outstanding'),
        );
        expect($liabilityWarnings)->toBeEmpty();
    });
});
