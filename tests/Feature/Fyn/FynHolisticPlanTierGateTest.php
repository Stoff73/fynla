<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Constants\QuerySchemas;
use App\Models\AiAuditEvent;
use App\Models\User;
use App\Services\AI\KycGateChecker;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class TierGateCoordinatingAgent extends CoordinatingAgent
{
    public int $generationCalls = 0;

    /** @var array<string, mixed> */
    public array $generatedPlan = [];

    public function generateHolisticPlan(int $userId, ?array $moduleAgents = null): array
    {
        $this->generationCalls++;

        return $this->generatedPlan;
    }
}

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/** @return array{primary: string, related: array{}, modules: array<string>} */
function holisticHealthClassification(): array
{
    return [
        'primary' => QuerySchemas::HOLISTIC_HEALTH,
        'related' => [],
        'modules' => QuerySchemas::MODULE_MAP[QuerySchemas::HOLISTIC_HEALTH],
    ];
}

it('returns the REST-equivalent upgrade payload for a free user', function (): void {
    $user = User::factory()->create(['tier' => 'free']);
    $result = app(CoordinatingAgent::class)->executeTool(
        'generate_financial_plan',
        [],
        $user,
    );

    expect($result)->toBe([
        'error' => 'upgrade_required',
        'message' => 'The Holistic Plan is part of Premium.',
        'required_tier' => 'premium',
    ])->and(
        AiAuditEvent::query()
            ->where('user_id', $user->id)
            ->where('tool_name', 'generate_financial_plan')
            ->orderBy('id')
            ->pluck('status')
            ->all()
    )->toBe(['dispatched', 'failed']);
});

it('allows an entitled Premium user to generate a financial plan', function (): void {
    $user = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'date_of_birth' => '1985-01-01',
        'annual_employment_income' => 75000,
        'monthly_expenditure' => 2500,
    ]);
    $classification = holisticHealthClassification();
    $kycResult = app(KycGateChecker::class)->check($user, $classification);
    $agent = app(TierGateCoordinatingAgent::class);
    $agent->generatedPlan = [
        'executive_summary' => 'Summary',
        'ranked_recommendations' => [['title' => 'Action']],
    ];

    expect($kycResult['passed'])->toBeTrue();

    $result = $agent->executeTool(
        'generate_financial_plan',
        [],
        $user,
        classification: $classification,
        kycResult: $kycResult,
    );

    expect($result)->toBe([
        'executive_summary' => 'Summary',
        'top_recommendations' => [['title' => 'Action']],
    ])->and($agent->generationCalls)->toBe(1);
});

it('allows holistic recommendations when the exact primary matrix passes', function (): void {
    $user = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'date_of_birth' => '1985-01-01',
        'annual_employment_income' => 75000,
        'monthly_expenditure' => 2500,
    ]);
    $classification = holisticHealthClassification();
    $kycResult = app(KycGateChecker::class)->check($user, $classification);

    $result = app(CoordinatingAgent::class)->executeTool(
        'get_recommendations',
        [],
        $user,
        classification: $classification,
        kycResult: $kycResult,
    );

    expect($result)
        ->not->toHaveKey('blocked')
        ->toHaveKeys(['recommendations', 'total', 'surplus']);
});

it('treats a preview user without an entitled tier as free', function (): void {
    $result = app(CoordinatingAgent::class)->executeTool(
        'generate_financial_plan',
        [],
        User::factory()->preview()->create(['tier' => null]),
    );

    expect($result)->toBe([
        'error' => 'upgrade_required',
        'message' => 'The Holistic Plan is part of Premium.',
        'required_tier' => 'premium',
    ]);
});
