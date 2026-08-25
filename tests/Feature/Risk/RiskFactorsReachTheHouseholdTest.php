<?php

declare(strict_types=1);

use App\Agents\SavingsAgent;
use App\Constants\PensionDisclosure;
use App\Models\DBPension;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Risk\AutoRiskCalculator;

/**
 * The risk engine's figures, against REAL records — no mocked net worth.
 *
 * The three defects this covers were each a figure the risk engine derived for
 * itself while another mechanism answered the same question differently:
 *
 * - **W-0271** — runway counted only accounts flagged `is_emergency_fund`, so a
 *   household with £130,780 of cash and no ticked boxes read "0 months" on
 *   `/risk-profile` while the dashboard beside it read "well-funded, Excellent".
 * - **W-0272** — dependants were a `user_id`-only query, so the parent who did
 *   not type the children in was told she could take MORE risk for having none.
 * - **W-0273** — investments were summed at 100% of a joint account for the
 *   recorder and 0% for the co-owner.
 *
 * **Every fixture here is asymmetric on purpose.** The persona that surfaced
 * these splits its joint accounts 50/50, which makes the primary owner's share
 * and the co-owner's share the same number — so a card that always shows the
 * primary owner's share is correct for both parties and no test built on it can
 * fail (tests/CLAUDE.md §4, the Collision variant). Every ownership split below
 * is 75/25 or 70/30, and every assertion states both what the figure IS and what
 * the defect WOULD have produced.
 */
function riskHousehold(array $overrides = []): array
{
    $defaults = [
        'date_of_birth' => now()->subYears(45)->toDateString(),
        'employment_status' => 'employed',
        'marital_status' => 'married',
        'annual_employment_income' => 90000,
        'monthly_expenditure' => 2000,
        'target_retirement_age' => 67,
    ];

    $david = User::factory()->create(array_merge($defaults, $overrides));
    $sarah = User::factory()->create(array_merge($defaults, $overrides));

    $david->update(['spouse_id' => $sarah->id]);
    $sarah->update(['spouse_id' => $david->id]);

    return [$david->fresh(), $sarah->fresh()];
}

function riskFactor(User $user, string $factor): array
{
    $breakdown = app(AutoRiskCalculator::class)->calculateRiskProfile($user)['factor_breakdown'];

    return collect($breakdown)->firstWhere('factor', $factor);
}

describe('emergency cash reaches all of a household\'s cash, at the user\'s share', function () {
    it('counts cash that nobody ticked as an emergency fund', function () {
        [$david] = riskHousehold();

        // Exactly the state that produced "0 months": real money, no flags.
        SavingsAccount::factory()->create([
            'user_id' => $david->id,
            'is_emergency_fund' => false,
            'current_balance' => 30000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        $factor = riskFactor($david, 'emergency_cash');

        expect($factor['raw_value'])->toBe(15.0)
            ->and($factor['level'])->toBe('upper_medium')
            ->and($factor['components']['emergency_fund_total'])->toBe(30000.0)
            ->and($factor['description'])->not->toContain('Less than 3 months');
    });

    it('gives the same answer whether or not the flag is ticked', function () {
        [$flagged] = riskHousehold();
        [$unflagged] = riskHousehold();

        foreach ([[$flagged, true], [$unflagged, false]] as [$user, $flag]) {
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_emergency_fund' => $flag,
                'current_balance' => 18000,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
            ]);
        }

        expect(riskFactor($unflagged, 'emergency_cash')['raw_value'])
            ->toBe(riskFactor($flagged, 'emergency_cash')['raw_value'])
            ->toBe(9.0);
    });

    it('moves when the cash moves', function () {
        [$david] = riskHousehold();

        SavingsAccount::factory()->create([
            'user_id' => $david->id,
            'is_emergency_fund' => false,
            'current_balance' => 4000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        expect(riskFactor($david, 'emergency_cash')['raw_value'])->toBe(2.0);

        // A second, also-unflagged account. Under the old rule neither existed,
        // so the answer would sit on zero through both halves of this test.
        SavingsAccount::factory()->create([
            'user_id' => $david->id,
            'is_emergency_fund' => false,
            'current_balance' => 16000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        expect(riskFactor($david->fresh(), 'emergency_cash')['raw_value'])->toBe(10.0);
    });

    it('splits a joint account at the stored share, not down the middle', function () {
        [$david, $sarah] = riskHousehold();

        SavingsAccount::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => $sarah->id,
            'is_emergency_fund' => false,
            'current_balance' => 24000,
            'ownership_type' => 'joint',
            'ownership_percentage' => 75,
        ]);

        $his = riskFactor($david, 'emergency_cash');
        $hers = riskFactor($sarah, 'emergency_cash');

        // £18,000 and £6,000 of one £24,000 record, against £2,000 a month.
        expect($his['components']['emergency_fund_total'])->toBe(18000.0)
            ->and($his['raw_value'])->toBe(9.0)
            ->and($his['level'])->toBe('upper_medium')
            ->and($hers['components']['emergency_fund_total'])->toBe(6000.0)
            ->and($hers['raw_value'])->toBe(3.0)
            ->and($hers['level'])->toBe('medium');

        // The two shapes of the defect, named: the recorder charged the whole
        // balance, and the co-owner shown none of it.
        expect($his['components']['emergency_fund_total'])->not->toBe(24000.0)
            ->and($hers['components']['emergency_fund_total'])->not->toBe(0.0);
    });

    it('says nothing rather than something false when spending is not recorded', function () {
        [$david] = riskHousehold(['monthly_expenditure' => null, 'annual_expenditure' => null]);

        SavingsAccount::factory()->create([
            'user_id' => $david->id,
            'is_emergency_fund' => false,
            'current_balance' => 30000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        $factor = riskFactor($david, 'emergency_cash');

        // The old code invented "12 months" here. Neither that nor "less than 3
        // months" is known to be true.
        expect($factor['value'])->toBe('Not calculated')
            ->and($factor['level'])->toBe('medium')
            ->and($factor['description'])->not->toContain('Less than 3 months')
            ->and($factor['value'])->not->toBe('12 months');
    });

    it('agrees with the savings module to the month, on the same records', function () {
        [$david, $sarah] = riskHousehold();

        SavingsAccount::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => $sarah->id,
            'is_emergency_fund' => false,
            'current_balance' => 24000,
            'ownership_type' => 'joint',
            'ownership_percentage' => 75,
        ]);

        // This is the contract the two defects broke: one household, one pot of
        // money, two mechanisms, and they must not be able to disagree.
        foreach ([$david, $sarah] as $user) {
            $savings = app(SavingsAgent::class)->analyze($user->id);

            expect($savings['emergency_fund']['runway_months'])
                ->toBe(riskFactor($user, 'emergency_cash')['raw_value']);
        }
    });
});

describe('dependants reach both parents of one household', function () {
    it('counts children recorded on the spouse\'s account', function () {
        [$david, $sarah] = riskHousehold();

        FamilyMember::factory()->child()->create([
            'user_id' => $david->id,
            'name' => 'William Jones',
            'first_name' => 'William',
            'last_name' => 'Jones',
            'date_of_birth' => '2007-09-15',
        ]);
        FamilyMember::factory()->child()->create([
            'user_id' => $david->id,
            'name' => 'Charlotte Jones',
            'first_name' => 'Charlotte',
            'last_name' => 'Jones',
            'date_of_birth' => '2010-02-28',
        ]);

        $hers = riskFactor($sarah, 'dependants');

        expect($hers['raw_value'])->toBe(2)
            ->and($hers['level'])->toBe('lower_medium')
            ->and($hers['description'])->not->toContain('No dependants')
            ->and(riskFactor($david, 'dependants')['raw_value'])->toBe(2);
    });

    it('stops counting them when the account link is gone', function () {
        [$david, $sarah] = riskHousehold();

        FamilyMember::factory()->child()->create([
            'user_id' => $david->id,
            'name' => 'William Jones',
            'first_name' => 'William',
            'date_of_birth' => '2007-09-15',
        ]);
        FamilyMember::factory()->child()->create([
            'user_id' => $david->id,
            'name' => 'Charlotte Jones',
            'first_name' => 'Charlotte',
            'date_of_birth' => '2010-02-28',
        ]);

        expect(riskFactor($sarah, 'dependants')['raw_value'])->toBe(2);

        // The answer must MOVE with the real input. Sarah's own count is 0 both
        // before the fix and for a genuinely childless woman — asserting on 0
        // alone would prove nothing (tests/CLAUDE.md §4).
        $sarah->update(['spouse_id' => null]);

        expect(riskFactor($sarah->fresh(), 'dependants')['raw_value'])->toBe(0);
    });

    it('does not reach into a deleted partner\'s records', function () {
        [$david, $sarah] = riskHousehold();

        FamilyMember::factory()->child()->create([
            'user_id' => $david->id,
            'name' => 'William Jones',
            'first_name' => 'William',
            'date_of_birth' => '2007-09-15',
        ]);

        expect(riskFactor($sarah, 'dependants')['raw_value'])->toBe(1);

        // `users.spouse_id` survives the partner deleting their account — the
        // rows are retained for regulatory purposes and must stop being read.
        $david->delete();

        expect(riskFactor($sarah->fresh(), 'dependants')['raw_value'])->toBe(0);
    });

    it('never counts the reader as their own dependant', function () {
        [$david, $sarah] = riskHousehold();

        // A non-earning spouse, recorded by her husband as financially dependent.
        FamilyMember::factory()->create([
            'user_id' => $david->id,
            'linked_user_id' => $sarah->id,
            'relationship' => 'spouse',
            'name' => 'Sarah Jones',
            'first_name' => 'Sarah',
            'is_dependent' => true,
        ]);

        // She depends on him; she does not depend on herself.
        expect(riskFactor($david, 'dependants')['raw_value'])->toBe(1)
            ->and(riskFactor($sarah, 'dependants')['raw_value'])->toBe(0);
    });

    it('counts a child both parents entered once', function () {
        [$david, $sarah] = riskHousehold();

        foreach ([$david, $sarah] as $parent) {
            FamilyMember::factory()->child()->create([
                'user_id' => $parent->id,
                'name' => 'William Jones',
                'first_name' => 'William',
                'last_name' => 'Jones',
                'date_of_birth' => '2007-09-15',
            ]);
        }

        expect(riskFactor($david, 'dependants')['raw_value'])->toBe(1)
            ->and(riskFactor($sarah, 'dependants')['raw_value'])->toBe(1);
    });
});

describe('capacity for loss weighs what this user actually owns', function () {
    it('gives every factor a disclosure key so a surface never has to ask which one it holds', function () {
        [, $sarah] = riskHousehold();

        DBPension::factory()->create([
            'user_id' => $sarah->id,
            'accrued_annual_pension' => 35000,
        ]);

        $factors = app(AutoRiskCalculator::class)->calculateRiskProfile($sarah)['factor_breakdown'];

        expect($factors)->toHaveCount(9);
        foreach ($factors as $factor) {
            expect($factor)->toHaveKey('disclosure');
        }

        $withDisclosure = collect($factors)->filter(fn ($f) => $f['disclosure'] !== null);
        expect($withDisclosure)->toHaveCount(1)
            ->and($withDisclosure->first()['factor'])->toBe('capacity_for_loss');
    });

    it('takes a joint portfolio at the stored share on both accounts', function () {
        [$david, $sarah] = riskHousehold();

        InvestmentAccount::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => $sarah->id,
            'current_value' => 200000,
            'ownership_type' => 'joint',
            'ownership_percentage' => 70,
        ]);

        $his = riskFactor($david, 'capacity_for_loss');
        $hers = riskFactor($sarah, 'capacity_for_loss');

        expect($his['components']['investments_total'])->toBe(140000.0)
            ->and($hers['components']['investments_total'])->toBe(60000.0);

        // The measured defect: £220,000 shown to the recorder of a portfolio
        // worth £172,500 to him, and £85,000 to a co-owner holding £132,500.
        expect($his['components']['investments_total'])->not->toBe(200000.0)
            ->and($hers['components']['investments_total'])->not->toBe(0.0);
    });

    it('measures the ratio against the same set it took the assets from', function () {
        [$david, $sarah] = riskHousehold();

        InvestmentAccount::factory()->create([
            'user_id' => $david->id,
            'joint_owner_id' => $sarah->id,
            'current_value' => 200000,
            'ownership_type' => 'joint',
            'ownership_percentage' => 70,
        ]);
        SavingsAccount::factory()->create([
            'user_id' => $sarah->id,
            'current_balance' => 40000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        $hers = riskFactor($sarah, 'capacity_for_loss');

        // £60,000 of £100,000. Numerator and denominator come from one response,
        // so a share applied to one is applied to the other.
        expect($hers['components']['net_worth'])->toBe(100000.0)
            ->and($hers['raw_value'])->toBe(60.0);
    });

    it('excludes a defined benefit scheme from capital and says so', function () {
        [, $sarah] = riskHousehold();

        InvestmentAccount::factory()->create([
            'user_id' => $sarah->id,
            'current_value' => 100000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);
        DBPension::factory()->create([
            'user_id' => $sarah->id,
            'accrued_annual_pension' => 35000,
            'lump_sum_entitlement' => 105000,
        ]);

        $factor = riskFactor($sarah, 'capacity_for_loss');

        // CSJ's settled ruling on W-0241: exclude and DISCLOSE. A £35,000 income
        // is not capital and must not acquire a valuation here — least of all the
        // ×20 capitalisation that used to live in the net worth item list.
        expect($factor['components']['pensions_total'])->toBe(0.0)
            ->and($factor['components']['pensions_total'])->not->toBe(700000.0)
            ->and($factor['components']['has_defined_benefit_pension'])->toBeTrue()
            // Asserted against the constant, never against a copy of the sentence.
            // A test holding its own copy of user-facing wording is a second home
            // for it, and would go green while the two drift apart (Rule 20).
            ->and($factor['disclosure'])->toBe(PensionDisclosure::DEFINED_BENEFIT_EXCLUDED_SHORT)
            // Its own field, NOT appended to the description: appended, the two
            // sentences render three lines into a two-line clamp on the summary
            // card and the disclosure is the half that gets cut.
            ->and($factor['description'])->not->toContain(PensionDisclosure::DEFINED_BENEFIT_EXCLUDED_SHORT);
    });

    it('says nothing about defined benefit schemes to someone who has none', function () {
        [$david] = riskHousehold();

        InvestmentAccount::factory()->create([
            'user_id' => $david->id,
            'current_value' => 100000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        $factor = riskFactor($david, 'capacity_for_loss');

        expect($factor['components']['has_defined_benefit_pension'])->toBeFalse()
            ->and($factor['disclosure'])->toBeNull()
            ->and($factor['description'])->not->toContain('Defined Benefit');
    });
});
