<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T70 (csjones walk, Robin 509, 2026-10-14): "Add my son Leo's Junior ISA …"
 * opened the Junior ISA form, but Fyn asked Leo's date of birth though Leo is
 * on the record, and the saved account named "Leo" without being his
 * (beneficiary_id null), where the web form links the child. A Junior ISA
 * belongs to the child (gov.uk/junior-individual-savings-accounts).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->leo = FamilyMember::factory()->create([
        'user_id' => $this->user->id, 'first_name' => 'Leo', 'last_name' => 'Walk',
        'relationship' => 'child', 'date_of_birth' => '2022-05-03',
    ]);
});

function juniorIsaFor(User $user, string $name, ?string $dob): SavingsAccount
{
    return app(SavingsStore::class)->create(array_filter([
        'account_name' => 'Hargreaves Lansdown Junior ISA for '.$name,
        'account_type' => 'junior_isa',
        'is_isa' => true,
        'institution' => 'Hargreaves Lansdown',
        'current_balance' => 1200,
        'ownership_type' => 'individual',
        'beneficiary_name' => $name,
        'beneficiary_dob' => $dob,
    ], fn ($v) => $v !== null), $user, IngestSource::FYN_AI);
}

it('links a Junior ISA to the one child on the record with that first name', function () {
    expect(juniorIsaFor($this->user, 'leo', null)->beneficiary_id)->toBe($this->leo->id);
    expect(juniorIsaFor($this->user, 'Leo', '2022-05-03')->beneficiary_id)->toBe($this->leo->id);
});

it('does not link when the date of birth given is a different child\'s', function () {
    expect(juniorIsaFor($this->user, 'Leo', '2019-01-01')->beneficiary_id)->toBeNull();
});

it('does not link when two children share the name', function () {
    FamilyMember::factory()->create([
        'user_id' => $this->user->id, 'first_name' => 'Leo', 'relationship' => 'child', 'date_of_birth' => '2018-02-02',
    ]);

    expect(juniorIsaFor($this->user, 'Leo', null)->beneficiary_id)->toBeNull();
});

it('fills the form\'s date of birth from the child on the record', function () {
    $form = ['schema' => [], 'answers' => ['junior_isa' => ['provider' => 'Hargreaves Lansdown', 'current_value' => 1200.0, 'child_first_name' => 'Leo']], 'filled' => ['provider', 'current_value', 'child_first_name']];

    $filled = CaptureForms::withKnownChild($this->user, $form);

    expect($filled['answers']['junior_isa']['child_date_of_birth'])->toBe('2022-05-03')
        ->and($filled['filled'])->toContain('child_date_of_birth');
});

it('leaves the form alone for a child not on the record', function () {
    $form = ['schema' => [], 'answers' => ['junior_isa' => ['child_first_name' => 'Maya']], 'filled' => ['child_first_name']];

    expect(CaptureForms::withKnownChild($this->user, $form))->toBe($form);
});
