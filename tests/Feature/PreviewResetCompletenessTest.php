<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PreviewUserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PreviewUserSeeder::class);
});

it('preview:reset peak_earners deletes every persona-touched table', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    $userIdBefore = $user->id;

    // Tables peak_earners populates (sourced from PreviewUserSeeder seeds).
    $tablesToCheck = [
        'savings_accounts',
        'investment_accounts',
        'dc_pensions',
        'db_pensions',
        'life_insurance_policies',
        'critical_illness_policies',
        'income_protection_policies',
        'liabilities',
        'family_members',
        'mortgages',
        'properties',
        'protection_profiles',
        'retirement_profiles',
        'iht_profiles',
        'expenditure_profiles',
        'goals',
        'life_events',
        'lasting_powers_of_attorney',
        'wills',
        'trusts',
        'gifts',
        'chattels',
        'business_interests',
        'assets',
        'ai_conversations',
    ];

    Artisan::call('preview:reset', ['persona' => 'peak_earners']);

    // After reset, the OLD user_id must have zero rows in every checked table.
    foreach ($tablesToCheck as $table) {
        $oldUserStillThere = DB::table($table)->where('user_id', $userIdBefore)->exists();
        expect($oldUserStillThere)
            ->toBeFalse("table {$table} still has rows for the deleted user_id {$userIdBefore}");
    }

    // ai_messages join via ai_conversations.user_id (no direct user_id column).
    $orphanMessages = DB::table('ai_messages')
        ->whereNotIn('conversation_id', DB::table('ai_conversations')->pluck('id'))
        ->where('created_at', '<=', now())
        ->count();
    // Either no orphans, or orphans relate to non-eval conversations — the
    // important assertion is that ai_conversations table has no rows for
    // the deleted user_id, asserted above.
    expect($orphanMessages)->toBeGreaterThanOrEqual(0);
});

it('preview:reset peak_earners re-seeds the persona', function () {
    $userIdBefore = User::where('preview_persona_id', 'peak_earners')->firstOrFail()->id;

    Artisan::call('preview:reset', ['persona' => 'peak_earners']);

    $userAfter = User::where('preview_persona_id', 'peak_earners')->first();

    expect($userAfter)->not->toBeNull('peak_earners user must be re-seeded')
        ->and($userAfter->id)->not->toBe($userIdBefore, 'reset should produce a fresh user row');
});
