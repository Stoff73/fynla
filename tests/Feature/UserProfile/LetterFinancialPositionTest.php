<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\Investment\InvestmentAccount;
use App\Models\LetterToSpouse;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\UserProfile\LetterToSpouseService;

/**
 * W-0421 — the Letter to Loved Ones was a fifth mechanism for a question four
 * others already answered, and the only one that leaves the application.
 *
 * It summed `current_balance` / `current_value` client-side at 100% of every
 * record, listed the same raw values in its generated prose, and rendered both
 * into an exportable PDF addressed to the bereaved spouse. So it credited the
 * estate with £177,000 belonging to an off-platform co-owner of one property and
 * charged the household his £72,000 of mortgage.
 *
 * These cases hold the letter to the aggregator's answer rather than to a
 * remembered figure. **The assertions are equalities between two mechanisms** —
 * they cannot pass while the letter still has arithmetic of its own, and they
 * cannot be satisfied by hardcoding a number the way `expect(...)->toBe(99750.0)`
 * can (`tests/CLAUDE.md` §4).
 *
 * **Nothing here is symmetric.** Every split is 40/60, 70/30 or 30/70, because a
 * 50/50 household makes the primary owner's share and the co-owner's share the
 * same figure and no test built on one can tell a correct reader from a broken
 * one (§4, Collision). The fixture also carries the two shapes `peak_earners`
 * lacks and therefore never exercised: a **non-mortgage liability**, and a shared
 * account the user holds as the **secondary** owner.
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['is_preview_user' => false]);
    $this->spouse = User::factory()->create(['is_preview_user' => false, 'spouse_id' => null]);
    $this->owner->update(['spouse_id' => $this->spouse->id]);
    $this->spouse->update(['spouse_id' => $this->owner->id]);

    $this->aggregator = app(CrossModuleAssetAggregator::class);
    $this->service = app(LetterToSpouseService::class);

    // A tenants-in-common property with a co-owner who has NO account here — the
    // shape that put a stranger's money into an estate. 40% is the user's.
    $this->sharedWithStranger = Property::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => null,
        'address_line_1' => 'Unit 12, Victoria Mill',
        'property_type' => 'buy_to_let',
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 40,
        'current_value' => 500_000,
    ]);

    // The mortgage row DISAGREES with the property it secures: it says joint 50%,
    // the property says tenants-in-common 40%. A debt is shared as the asset
    // securing it is shared (W-0228), so the answer must be 40% — and 40 ≠ 50, so
    // a reader that consults the wrong record produces a different number here.
    //
    // £220,000 rather than a rounder figure on purpose: at £200,000 the owner's
    // 40% of this mortgage and the owner's 40% of the property it secures are
    // both £80,000/£200,000, and an assertion about one of them silently passes
    // on the other. Every figure in this fixture is distinct from every other.
    Mortgage::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => null,
        'property_id' => $this->sharedWithStranger->id,
        'lender_name' => 'Stranger Mortgage Co',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 220_000,
    ]);

    // A jointly held home, 70/30 rather than 50/50.
    $this->jointHome = Property::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => $this->spouse->id,
        'address_line_1' => '15 Chestnut Lane',
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'ownership_percentage' => 70,
        'current_value' => 400_000,
    ]);

    Mortgage::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => $this->spouse->id,
        'property_id' => $this->jointHome->id,
        'lender_name' => 'Chestnut Building Society',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 100_000,
    ]);

    SavingsAccount::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => null,
        'institution' => 'SoleBank',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 8_000,
    ]);

    // The untested side: the owner is the SECONDARY holder here. The record's
    // percentage is the primary's 30%, so the owner's share is 70%.
    SavingsAccount::factory()->create([
        'user_id' => $this->spouse->id,
        'joint_owner_id' => $this->owner->id,
        'institution' => 'SecondaryBank',
        'ownership_type' => 'joint',
        'ownership_percentage' => 30,
        'current_balance' => 10_000,
    ]);

    InvestmentAccount::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => $this->spouse->id,
        'provider' => 'Asymmetric Platform',
        'account_type' => 'gia',
        'ownership_type' => 'joint',
        'ownership_percentage' => 60,
        'current_value' => 50_000,
    ]);

    // `peak_earners` holds no non-mortgage debt at all, so nothing in a suite
    // built from it enters this branch (§4, Fixture).
    Liability::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => null,
        'liability_name' => 'Everyday Credit Card',
        'liability_type' => 'credit_card',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 4_000,
        'monthly_payment' => 150,
    ]);
});

describe('the letter states the same position as every other surface', function () {
    it('totals each section to the aggregator answer rather than to its own arithmetic', function () {
        $position = $this->service->financialPosition($this->owner);
        $userId = (int) $this->owner->id;

        expect($position['savings']['total'])
            ->toBe(round($this->aggregator->calculateCashTotal($userId), 2))
            ->and($position['investments']['total'])
            ->toBe(round($this->aggregator->calculateInvestmentTotal($userId), 2))
            ->and($position['properties']['total'])
            ->toBe(round($this->aggregator->calculatePropertyTotal($userId), 2))
            ->and($position['liabilities']['total'])
            ->toBe(round($this->aggregator->calculateLiabilityTotals($userId)['total'], 2));

        // And the answers are the asymmetric ones, so an equality between two
        // mechanisms that had both regressed to 100% could not pass either.
        expect($position['savings']['total'])->toBe(15000.0)          // 8,000 + 70% of 10,000
            ->and($position['investments']['total'])->toBe(30000.0)   // 60% of 50,000
            ->and($position['properties']['total'])->toBe(480000.0)   // 40% of 500k + 70% of 400k
            ->and($position['liabilities']['total'])->toBe(162000.0); // 88,000 + 70,000 + 4,000
    });

    it('holds a co-owner with no account to their own share of the property and its mortgage', function () {
        $position = $this->service->financialPosition($this->owner);

        $unit = collect($position['properties']['items'])
            ->firstWhere('id', $this->sharedWithStranger->id);

        expect($unit)->not->toBeNull()
            ->and($unit['value'])->toBe(200000.0)
            ->and($unit['full_value'])->toBe(500000.0)
            // The 60% belonging to the off-platform co-owner reduces the estate
            // and is credited to nobody.
            ->and($position['properties']['total'])->toBeLessThan(500000.0 + 400000.0);

        // A debt is shared as the asset securing it is shared. The mortgage row
        // says joint 50% (£100,000); the property says 40% (£80,000).
        $mortgage = collect($position['liabilities']['items'])
            ->firstWhere('name', 'Stranger Mortgage Co');

        expect($mortgage)->not->toBeNull()
            ->and($mortgage['value'])->toBe(88000.0)     // 40%, the property's split
            ->and($mortgage['value'])->not->toBe(110000.0); // 50%, the mortgage row's
    });

    it('reaches a shared account the user holds as the secondary owner, at the complementary share', function () {
        $position = $this->service->financialPosition($this->owner);

        // Looked up by `name`: these accounts carry no `account_name`, so the
        // institution is the name and the subtext is dropped rather than printed
        // twice.
        $secondary = collect($position['savings']['items'])
            ->firstWhere('name', 'SecondaryBank');

        expect($secondary)->not->toBeNull()
            ->and($secondary['value'])->toBe(7000.0)
            ->and($secondary['full_value'])->toBe(10000.0)
            ->and($secondary['subtext'])->toBe('');
    });

    it('keeps every non-mortgage debt, at its share, alongside the mortgages', function () {
        $position = $this->service->financialPosition($this->owner);
        $items = collect($position['liabilities']['items']);

        // An empty list and a fixed defect look identical, so the survival of the
        // rest of the section is asserted as well as the figure.
        expect($items)->toHaveCount(3)
            ->and($items->pluck('name')->all())->toContain('Everyday Credit Card')
            ->and($items->firstWhere('name', 'Everyday Credit Card')['value'])->toBe(4000.0)
            ->and($items->pluck('name')->all())->toContain('Chestnut Building Society');
    });
});

describe('the prose and the exported document state the same figures as the cards', function () {
    it('writes the user share into the property section instead of the whole property', function () {
        $letter = $this->service->getOrCreateLetter($this->owner);

        expect($letter->real_estate_info)->not->toBeNull()
            ->and($letter->real_estate_info)->toContain('Unit 12, Victoria Mill')
            ->and($letter->real_estate_info)->toContain('Your Share: £200,000.00 of £500,000.00')
            ->and($letter->real_estate_info)->not->toContain('Current Value: £500,000.00')
            // Their share of the borrowing, not the whole of it.
            ->and($letter->real_estate_info)->toContain('Outstanding Mortgage (your share): £88,000.00')
            // The whole of the borrowing appears nowhere in the section.
            ->and($letter->real_estate_info)->not->toContain('£220,000.00');
    });

    it('writes the user share into the liabilities section instead of the whole debt', function () {
        $letter = $this->service->getOrCreateLetter($this->owner);

        expect($letter->liabilities_info)->not->toBeNull()
            ->and($letter->liabilities_info)->toContain('Your Share: £88,000.00')
            ->and($letter->liabilities_info)->not->toContain('£220,000.00')
            // The individually held card is stated whole, and still present.
            ->and($letter->liabilities_info)->toContain('Everyday Credit Card')
            ->and($letter->liabilities_info)->toContain('Outstanding: £4,000.00');
    });

    it('states the whole balance for a joint account the survivor can draw on, and says which figure it is', function () {
        $letter = $this->service->getOrCreateLetter($this->owner);

        // The one figure in the letter deliberately not at the user's share: a
        // surviving joint holder reaches the whole account by survivorship.
        expect($letter->immediate_funds_access)->not->toBeNull()
            ->and($letter->immediate_funds_access)->toContain('SecondaryBank - £10,000.00 (full account balance)')
            ->and($letter->immediate_funds_access)->not->toContain('SoleBank');
    });
});

describe('the endpoint the page reads', function () {
    it('refuses an unauthenticated caller', function () {
        $this->getJson('/api/user/letter-to-spouse/financial-position')->assertUnauthorized();
    });

    it('serves the four sections at the caller\'s own share', function () {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/user/letter-to-spouse/financial-position')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'savings' => ['total', 'items'],
                    'investments' => ['total', 'items'],
                    'properties' => ['total', 'items'],
                    'liabilities' => ['total', 'items'],
                ],
            ]);

        $data = $response->json('data');

        // JSON has no float/int distinction, so the values are cast back before
        // comparison rather than the expectation being loosened to `toEqual`.
        expect((float) $data['properties']['total'])->toBe(480000.0)
            ->and((float) $data['liabilities']['total'])->toBe(162000.0);
    });

    it('answers the spouse with the spouse\'s share, not the primary owner\'s', function () {
        $data = $this->actingAs($this->spouse)
            ->getJson('/api/user/letter-to-spouse/financial-position')
            ->assertOk()
            ->json('data');

        // 30% of the £400,000 joint home, and nothing of the property held with
        // the off-platform co-owner. The two accounts must not agree — a 50/50
        // household would make them identical and prove nothing.
        expect((float) $data['properties']['total'])->toBe(120000.0)
            ->and((float) $data['savings']['total'])->toBe(3000.0)
            ->and((float) $data['properties']['total'])->not->toBe(480000.0);
    });
});

describe('a letter written before the fix does not stay wrong', function () {
    it('repairs a stored section that still holds the whole of a shared record', function () {
        // The prose is PERSISTED, so correcting the generator does not by itself
        // correct a letter produced last week. This is the row that already went
        // out with a stranger's money in it.
        LetterToSpouse::create([
            'user_id' => $this->owner->id,
            'auto_populated_fields' => ['real_estate_info', 'liabilities_info'],
            'real_estate_info' => "Property Ownership:\n\n• Unit 12, Victoria Mill\n  Current Value: £500,000.00\n  Use: Primary_residence\n",
            'liabilities_info' => "Outstanding Liabilities:\n\n• Mortgage - Stranger Mortgage Co\n  Outstanding: £220,000.00\n",
        ]);

        $letter = $this->service->getOrCreateLetter($this->owner->fresh());

        expect($letter->real_estate_info)->toContain('Your Share: £200,000.00 of £500,000.00')
            ->and($letter->real_estate_info)->not->toContain('Current Value: £500,000.00')
            ->and($letter->real_estate_info)->not->toContain('Use: Primary_residence')
            ->and($letter->liabilities_info)->toContain('Your Share: £88,000.00')
            ->and($letter->liabilities_info)->not->toContain('£220,000.00');

        // Repaired in the database, not only in the returned model — the next
        // reader gets the corrected text without this code path running again.
        expect(LetterToSpouse::where('user_id', $this->owner->id)->value('real_estate_info'))
            ->toContain('Your Share: £200,000.00 of £500,000.00');
    });

    it('leaves a section the user has taken ownership of, which is the hole this does NOT close', function () {
        // Editing a section drops it from `auto_populated_fields` for good, so
        // Fynla stops regenerating it (W-0022, deliberate — a letter to a
        // grieving partner is not the place to overwrite their words). The
        // consequence is that a section edited BEFORE this fix keeps the figures
        // Fynla generated at 100%, and nothing here changes that.
        //
        // Asserted rather than left implicit so the limitation is a recorded
        // decision instead of a silent gap. Zero live rows are in this state.
        $theirWords = "Property Ownership:\n\n• Unit 12 — ask Margaret about this one.\n  Current Value: £500,000.00\n";

        LetterToSpouse::create([
            'user_id' => $this->owner->id,
            'auto_populated_fields' => ['liabilities_info'],
            'real_estate_info' => $theirWords,
        ]);

        $letter = $this->service->getOrCreateLetter($this->owner->fresh());

        expect($letter->real_estate_info)->toBe($theirWords)
            // …while everything Fynla still owns is corrected around it.
            ->and($letter->liabilities_info)->toContain('Your Share: £88,000.00');
    });
});

describe('an account type is named the way a stranger reading the letter would understand it', function () {
    it('spells out the account types that are acronyms, and keeps ISA', function () {
        $letter = $this->service->getOrCreateLetter($this->owner);

        // `humanise()` alone produced "Gia", "Vct" and "Isa" — two meaningless
        // acronyms and one that is no longer the acronym (Rule 9 makes ISA the
        // single permitted abbreviation).
        expect($letter->investment_accounts_info)->toContain('Account Type: General Investment Account')
            ->and($letter->investment_accounts_info)->not->toContain('Account Type: Gia')
            ->and($letter->investment_accounts_info)->not->toContain('Account Type: Isa');
    });

    it('leaves the ordinary types humanised as they always were', function () {
        $letter = $this->service->getOrCreateLetter($this->owner);

        expect($letter->bank_accounts_info)->toContain('Account Type: ');
    });
});

describe('an investment ISA is marked as one', function () {
    it('reads the value the column actually holds', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->owner->id,
            'joint_owner_id' => null,
            'provider' => 'Isa Platform',
            // `isa`, not `stocks_and_shares_isa` — the latter is an `isa_type`
            // value, so the badge condition had never once been true.
            'account_type' => 'isa',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 20_000,
        ]);

        // Keyed on `account_type`, not on the display name: the factory supplies
        // an `account_name`, which wins over `provider` when the item is named.
        $items = collect($this->service->financialPosition($this->owner)['investments']['items']);

        expect($items->firstWhere('account_type', 'isa'))->not->toBeNull()
            ->and($items->firstWhere('account_type', 'isa')['is_isa'])->toBeTrue();

        // And the general investment account beside it is still not an ISA, so
        // the flag is discriminating rather than always true.
        expect($items->firstWhere('account_type', 'gia')['is_isa'])->toBeFalse();
    });
});

describe('the section total is the sum of the items above it', function () {
    it('adds each list of items to the figure printed beside its heading', function () {
        $position = $this->service->financialPosition($this->owner);

        foreach (['savings', 'investments', 'properties', 'liabilities'] as $section) {
            expect(collect($position[$section]['items'])->sum('value'))
                ->toBe($position[$section]['total'], "section {$section} does not add up");
        }
    });
});
