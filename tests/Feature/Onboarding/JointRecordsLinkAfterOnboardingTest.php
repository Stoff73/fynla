<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T27 (Q23, RC8): a joint record added through Fyn after onboarding was never
 * linked to the partner (production property 533: joint, no joint_owner_id),
 * because the executor remembered joint records only while onboarding_completed
 * was false (CoordinatingAgent.php:1318).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'marital_status' => 'married', 'tier' => 'premium']);
    $this->spouse = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'marital_status' => 'married', 'spouse_id' => $this->user->id]);
    $this->user->forceFill(['spouse_id' => $this->spouse->id])->save();
});

it('links a joint account added after onboarding to the linked partner', function () {
    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Nationwide joint saver', 'institution' => 'Nationwide', 'account_type' => 'easy_access',
        'current_balance' => 24000, 'interest_rate' => 4, 'ownership_type' => 'joint', 'ownership_percentage' => 50,
    ], $this->user);

    expect(SavingsAccount::find($result['entity_id'])->joint_owner_id)->toBe($this->spouse->id);
});

it('links joint records saved before this fix, once, after a dry run', function () {
    $joint = SavingsAccount::factory()->create([
        'user_id' => $this->user->id, 'account_type' => 'easy_access', 'ownership_type' => 'joint',
        'ownership_percentage' => 50, 'joint_owner_id' => null, 'joint_owner_name' => null,
    ]);
    $someoneElse = SavingsAccount::factory()->create([
        'user_id' => $this->user->id, 'account_type' => 'easy_access', 'ownership_type' => 'joint',
        'ownership_percentage' => 50, 'joint_owner_id' => null, 'joint_owner_name' => 'Mum',
    ]);

    $this->artisan('couples:link-joint-records', ['--dry-run' => true])
        ->expectsOutputToContain('Would link 1 joint record(s)')
        ->assertSuccessful();
    expect($joint->fresh()->joint_owner_id)->toBeNull();

    $this->artisan('couples:link-joint-records')->assertSuccessful();
    expect($joint->fresh()->joint_owner_id)->toBe($this->spouse->id)
        ->and($someoneElse->fresh()->joint_owner_id)->toBeNull();
});
