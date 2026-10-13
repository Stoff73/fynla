<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\UserProfile\ModuleDataRequirementsService;
use Illuminate\Database\Eloquent\Model;

/**
 * Walk R24 (2026-10-09): Sam, the joint owner of Morgan's Nationwide account,
 * opened it and the panel above it listed "Your savings accounts" as
 * outstanding. A joint record is one row on the primary owner (Rule 6), so a
 * requirement read through `user_id` alone never counts the joint owner's.
 */
beforeEach(function () {
    $this->modelEventDispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();

    $this->service = app(ModuleDataRequirementsService::class);
});

afterEach(function () {
    Model::setEventDispatcher($this->modelEventDispatcher);
});

/**
 * @return list<string>
 */
function jointRecordLabels(array $requirements): array
{
    return array_map(static fn (array $r): string => $r['label'], $requirements);
}

it('counts a savings account the user holds jointly', function () {
    $morgan = User::factory()->create();
    $sam = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $morgan->id,
        'joint_owner_id' => $sam->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    $result = $this->service->getRequirementsForModule($sam, 'budgeting');

    expect(jointRecordLabels($result['filled']))->toContain('Your savings accounts')
        ->and(jointRecordLabels($result['missing']))->not->toContain('Your savings accounts');
});

it('counts an investment account the user holds jointly', function () {
    $morgan = User::factory()->create();
    $sam = User::factory()->create();
    InvestmentAccount::factory()->create([
        'user_id' => $morgan->id,
        'joint_owner_id' => $sam->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    $result = $this->service->getRequirementsForModule($sam, 'investment');

    expect(jointRecordLabels($result['missing']))->not->toContain('Your investment accounts');
});

it('still lists the requirement for someone with no account of either kind', function () {
    $sam = User::factory()->create();

    $result = $this->service->getRequirementsForModule($sam, 'budgeting');

    expect(jointRecordLabels($result['missing']))->toContain('Your savings accounts');
});
