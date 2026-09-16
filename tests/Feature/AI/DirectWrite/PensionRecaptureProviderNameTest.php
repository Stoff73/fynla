<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * csjones user 405, 2026-09-16: the pension form saved "Aviva personal pension
 * or SIPP"; at the next question the model re-recorded it as "Personal pension
 * or SIPP" with provider Aviva and a duplicate £160,000 row landed. The
 * recapture guard now ignores the provider's name inside the record name.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

afterEach(function (): void {
    Mockery::close();
});

it('treats a re-record whose name lacks the provider prefix as the same pension, not a second one', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1962-03-30', 'employment_status' => 'retired']);
    $agent = app(CoordinatingAgent::class);

    $first = $agent->executeTool('create_pension', [
        'pension_category' => 'dc', 'scheme_name' => 'Aviva personal pension or SIPP', 'scheme_type' => 'personal', 'provider' => 'Aviva', 'current_fund_value' => 160000,
    ], $user);
    expect($first['success'] ?? false)->toBeTrue();

    $second = $agent->executeTool('create_pension', [
        'pension_category' => 'dc', 'scheme_name' => 'Personal pension or SIPP', 'scheme_type' => 'personal_pension', 'provider' => 'Aviva', 'current_fund_value' => 160000,
    ], $user);

    expect(DCPension::where('user_id', $user->id)->count())->toBe(1)
        ->and($second['created'] ?? false)->toBeFalse();
});
