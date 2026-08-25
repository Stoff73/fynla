<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * 2026-07-23 live (user 292, msg 19833): the SaveTax campaign captures
 * savings BEFORE the spouse section links a spouse User, so a joint account
 * could never save mid-campaign. The web form (StoreSavingsAccountRequest)
 * treats joint with a nullable joint_owner_id as first-class; the Fyn
 * capture path must match. Attaching a REAL joint_owner_id still requires
 * the reciprocal spouse link — that security posture is unchanged.
 */
function inspectJointOwner(array $input, User $user): ?array
{
    $agent = app(CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, 'captureJointOwnerError');
    $m->setAccessible(true);

    return $m->invoke($agent, 'create_savings_account', $input, $user);
}

it('allows a joint record with no linked co-owner', function () {
    $user = User::factory()->create();

    $result = inspectJointOwner([
        'account_name' => 'Joint Savings Account',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => null,
    ], $user);

    expect($result)->toBeNull();
});

it('still rejects attaching a joint owner who is not the reciprocal spouse', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $result = inspectJointOwner([
        'account_name' => 'Joint Savings Account',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => $stranger->id,
    ], $user);

    expect($result)->not->toBeNull()
        ->and($result['error'])->toBeTrue()
        ->and($result['missing'])->toContain('joint_owner_id');
});

it('still rejects a joint owner on an individually owned record', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $result = inspectJointOwner([
        'account_name' => 'Savings Account',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'joint_owner_id' => $other->id,
    ], $user);

    expect($result)->not->toBeNull()
        ->and($result['error'])->toBeTrue()
        ->and($result['missing'])->toContain('ownership_type');
});
