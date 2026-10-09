<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\Fyn\RecaptureGuard;
use App\Services\Onboarding\RecordEditForms;

/*
 * Full Luna run 2026-10-08: Jordan, joint owner of Sam's Santander account,
 * opened it with Edit and saved it unchanged. Fyn said "Updated — Santander easy
 * access savings, balance £24,000, 4% interest, individual." Nothing was written
 * (SPEC-crud-handler-contract C5: never claim a write that did not happen) and
 * the account is joint: the edit form carries no ownership fields, so the
 * read-back took the form default.
 */
function jointSantander(User $owner, User $partner): SavingsAccount
{
    // Linked both ways, as an accepted invitation leaves them: the store refuses
    // a joint owner who is not (User::hasReciprocalSpouseLink).
    $owner->update(['spouse_id' => $partner->id]);
    $partner->update(['spouse_id' => $owner->id]);

    return SavingsAccount::factory()->create([
        'user_id' => $owner->id, 'joint_owner_id' => $partner->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
        'account_name' => 'Santander easy access savings', 'institution' => 'Santander',
        'account_type' => 'easy_access', 'current_balance' => 24000, 'interest_rate' => 4,
    ]);
}

it('tells the joint owner nothing changed, and calls the account joint, when they save it unchanged', function () {
    $sam = User::factory()->create();
    $jordan = User::factory()->create();
    $account = jointSantander($sam, $jordan);
    $forms = app(RecordEditForms::class);

    $result = $forms->update($jordan, $forms->formFor($jordan, 'savings_account', $account->id), 0);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->not->toStartWith('Updated')
        ->and($result['message'])->toContain('joint')
        ->and($result['message'])->not->toContain('individual');
});

it('tells the owner nothing changed when they save unchanged', function () {
    $sam = User::factory()->create();
    $account = jointSantander($sam, User::factory()->create());
    $forms = app(RecordEditForms::class);

    $result = $forms->update($sam, $forms->formFor($sam, 'savings_account', $account->id), 0);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->not->toStartWith('Updated');
});

it('reads back a joint investment with the partner\'s own share', function () {
    $sam = User::factory()->create();
    $jordan = User::factory()->create();
    $sam->update(['spouse_id' => $jordan->id]);
    $jordan->update(['spouse_id' => $sam->id]);
    $account = InvestmentAccount::factory()->create([
        'user_id' => $sam->id, 'joint_owner_id' => $jordan->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 70,
        'account_type' => 'gia', 'provider' => 'Vanguard', 'account_name' => 'Vanguard general investment account',
        'current_value' => 40000, 'isa_type' => null,
    ]);
    $forms = app(RecordEditForms::class);

    $result = $forms->update($jordan, $forms->formFor($jordan, 'investment_account', $account->id), 0);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->not->toStartWith('Updated')
        ->and($result['message'])->toContain('joint')
        ->and($result['message'])->toContain('my share 30%');
});

it('does not say Updated when a pension is saved unchanged', function () {
    $sam = User::factory()->create();
    $pension = DCPension::factory()->create([
        'user_id' => $sam->id, 'provider' => 'Nest', 'scheme_name' => 'Nest',
        'current_fund_value' => 48000,
    ]);
    $forms = app(RecordEditForms::class);

    $result = $forms->update($sam, $forms->formFor($sam, 'dc_pension', $pension->id), 0);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->not->toStartWith('Updated');
});

it('says Updated, with the stored ownership, when the owner changes a value', function () {
    $sam = User::factory()->create();
    $account = jointSantander($sam, User::factory()->create());
    $forms = app(RecordEditForms::class);
    $form = $forms->formFor($sam, 'savings_account', $account->id);
    $kind = array_key_first($form['answers']);
    $form['answers'][$kind]['current_value'] = 25000;

    $result = $forms->update($sam, $form, 0);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toStartWith('Updated')
        ->and($result['message'])->toContain('£25,000')
        ->and($result['message'])->toContain('joint')
        ->and((float) $account->fresh()->current_balance)->toBe(25000.0);
});

it('keeps the name and type the user gave when only the balance changes', function () {
    $sam = User::factory()->create();
    $account = SavingsAccount::factory()->create([
        'user_id' => $sam->id, 'ownership_type' => 'individual',
        'account_name' => 'Premium Bonds', 'institution' => 'NS&I',
        'account_type' => 'premium_bonds', 'current_balance' => 10000, 'interest_rate' => 3.6,
    ]);
    $forms = app(RecordEditForms::class);
    $form = $forms->formFor($sam, 'savings_account', $account->id);
    $kind = array_key_first($form['answers']);
    $form['answers'][$kind]['current_value'] = 12000;

    $result = $forms->update($sam, $form, 0);

    $fresh = $account->fresh();
    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toStartWith('Updated')
        ->and((float) $fresh->current_balance)->toBe(12000.0)
        ->and($fresh->account_name)->toBe('Premium Bonds')
        ->and($fresh->account_type)->toBe('premium_bonds');
});

it('keeps a pension\'s own name on an unchanged save', function () {
    $sam = User::factory()->create();
    $pension = DCPension::factory()->create([
        'user_id' => $sam->id, 'provider' => 'AJ Bell', 'scheme_name' => "David's SIPP",
        'current_fund_value' => 90000,
    ]);
    $forms = app(RecordEditForms::class);

    $forms->update($sam, $forms->formFor($sam, 'dc_pension', $pension->id), 0);

    expect($pension->fresh()->scheme_name)->toBe("David's SIPP");
});

it('writes a Stocks and Shares ISA\'s paid-in figure where the ISA allowance reads it', function () {
    $sam = User::factory()->create();
    $account = InvestmentAccount::factory()->create([
        'user_id' => $sam->id, 'ownership_type' => 'individual', 'joint_owner_id' => null,
        'account_type' => 'isa', 'isa_type' => 'stocks_and_shares', 'provider' => 'Vanguard',
        'account_name' => 'Vanguard Stocks and Shares ISA', 'current_value' => 30000,
        'isa_subscription_current_year' => 2000, 'contributions_ytd' => 0,
    ]);
    $forms = app(RecordEditForms::class);
    $form = $forms->formFor($sam, 'investment_account', $account->id);
    $kind = array_key_first($form['answers']);
    $form['answers'][$kind]['paid_in_this_year'] = 5000;

    $result = $forms->update($sam, $form, 0);

    expect($result['success'])->toBeTrue()
        ->and((float) $account->fresh()->isa_subscription_current_year)->toBe(5000.0);
});

it('reports no change from update_record when a pension value already matches', function () {
    $sam = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $sam->id, 'current_fund_value' => 48000]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'dc_pension', 'entity_id' => $pension->id, 'fields' => ['current_fund_value' => 48000],
    ], $sam, 0);

    expect($result['success'])->toBeTrue()
        ->and($result['updated'])->toBeFalse();
});

it('counts a first answer over nothing stored as a change', function () {
    expect(RecaptureGuard::differs(null, 0))->toBeTrue()
        ->and(RecaptureGuard::differs(null, false))->toBeTrue()
        ->and(RecaptureGuard::differs('45000.00', 45000))->toBeFalse()
        ->and(RecaptureGuard::differs(null, null))->toBeFalse();
});

it("writes the joint owner's own line with the account's real ownership", function () {
    $sam = User::factory()->create();
    $jordan = User::factory()->create();
    $account = jointSantander($sam, $jordan);
    $forms = app(RecordEditForms::class);

    $line = $forms->transcriptLine($jordan, $forms->formFor($jordan, 'savings_account', $account->id));

    expect($line)->toBe('Santander easy access savings, balance £24,000, 4% interest, joint.');
});

// Both owners of a joint account own it (CSJ 2026-10-08).
it('lets the joint owner change the joint account through Fyn, and it stays the owner\'s record', function () {
    $sam = User::factory()->create();
    $jordan = User::factory()->create();
    $account = jointSantander($sam, $jordan);
    $forms = app(RecordEditForms::class);
    $form = $forms->formFor($jordan, 'savings_account', $account->id);
    $kind = array_key_first($form['answers']);
    $form['answers'][$kind]['current_value'] = 25000;

    $result = $forms->update($jordan, $form, 0);

    $fresh = $account->fresh();
    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toStartWith('Updated')
        ->and((float) $fresh->current_balance)->toBe(25000.0)
        ->and($fresh->user_id)->toBe($sam->id)
        ->and($fresh->joint_owner_id)->toBe($jordan->id);
});

it('lets the joint owner remove the joint account through Fyn', function () {
    $sam = User::factory()->create();
    $jordan = User::factory()->create();
    $account = jointSantander($sam, $jordan);

    $result = app(RecordEditForms::class)->delete($jordan, 'savings_account', $account->id, 0);

    expect($result['success'])->toBeTrue()
        ->and(SavingsAccount::find($account->id))->toBeNull();
});
