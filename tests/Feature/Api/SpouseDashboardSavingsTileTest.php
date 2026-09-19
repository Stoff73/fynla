<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// 2026-09-19: an invitee who had just registered saw net worth £6,000 (their share of
// the joint account) beside a Savings card reading £0 — the savings analysis was gated
// on date of birth, income and expenditure, and the card read the gated summary.
it('the spouse of a joint account sees their share on the savings card before the readiness gate opens', function (): void {
    $a = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    $b = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => false, 'spouse_id' => $a->id]);
    $a->spouse_id = $b->id;
    $a->save();
    SavingsAccount::factory()->create([
        'user_id' => $a->id, 'joint_owner_id' => $b->id, 'ownership_type' => 'joint', 'ownership_percentage' => 50,
        'current_balance' => 12000, 'account_type' => 'easy_access', 'is_isa' => false,
    ]);

    Sanctum::actingAs($b);
    $res = $this->getJson('/api/v1/mobile/dashboard')->assertOk()->json();
    $data = $res['data'] ?? $res;
    $modules = $data['modules'] ?? [];
    $sav = $modules['savings'] ?? [];
    expect((float) ($sav['total_savings'] ?? 0))->toBe(6000.0)
        ->and((int) ($sav['total_accounts'] ?? 0))->toBe(1)
        ->and((float) data_get($data, 'net_worth.breakdown.assets.savings'))->toBe(6000.0);
});
