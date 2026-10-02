<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * INV-2.6.2 — handleRecommendations returns the user's actions list with no
 * summarisation. Since audit item 50 (CSJ 2026-10-01, one figure) the list is
 * NextActionsService::buildAll, the one the dashboard, /m and the action cards
 * show, not orchestrateAnalysis's own ranking. Every field of every item and
 * of its card (category, timeline, personalised_context, figures, etc.)
 * round-trips to the model; only the clients' tap routing (`action`) is left out.
 *
 * The test stubs the actions list and orchestrateAnalysis (still read for the
 * surplus), calls the handler, and asserts every field arrives unchanged.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

/** @param list<array<string, mixed>> $items */
function stubActionsList(array $items): void
{
    $actions = Mockery::mock(NextActionsService::class);
    $actions->shouldReceive('buildAll')->andReturn($items);
    app()->instance(NextActionsService::class, $actions);
}

function callHandleRecommendations(CoordinatingAgent $agent, User $user): array
{
    $method = (new ReflectionClass($agent))->getMethod('handleRecommendations');
    $method->setAccessible(true);

    return $method->invoke($agent, $user);
}

/**
 * Build a CoordinatingAgent subclass that returns a fixed
 * orchestrateAnalysis payload, bypassing the engine and the audit-chain
 * pipeline so the test focuses on the handler's read-completeness
 * contract.
 */
function buildAgentWithFixedAnalysis(array $analysis): CoordinatingAgent
{
    $stub = new class($analysis) extends CoordinatingAgent
    {
        public function __construct(private array $stubAnalysis)
        {
            // The parent constructor must run (handleRecommendations reads
            // promoted readonly properties, e.g. $composedTaxPlans). Resolve
            // its dependencies from the container so the stub stays honest
            // and never breaks when the constructor signature changes.
            parent::__construct(...array_map(
                static fn (ReflectionParameter $p): object => app((string) $p->getType()),
                (new ReflectionMethod(CoordinatingAgent::class, '__construct'))->getParameters(),
            ));
        }

        public function orchestrateAnalysis(int $userId, ?array $moduleAgents = null): array
        {
            return $this->stubAnalysis;
        }
    };

    return $stub;
}

it('returns the full actions list verbatim with all metadata fields', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = [
        [
            'id' => 'protection_life_cover_gap',
            'type' => 'recommendation',
            'module' => 'protection',
            'title' => 'Increase your life cover to £500,000',
            'detail' => 'Your current cover leaves a £200,000 gap against your dependants and mortgage.',
            'meta' => 'Cover',
            'value' => 95.0,
            'done' => false,
            'module_label' => 'Protection',
            'action' => ['kind' => 'navigate', 'route' => '/protection'],
            'card' => [
                'category' => 'protection',
                'timeline' => 'immediate',
                'personalised_context' => ['You have 2 dependants.'],
                'conflict_note' => null,
                'alternatives_note' => null,
                'potential_benefit' => null,
                'requires_advice' => false,
                'definition_key' => 'life_cover_gap',
                'figures' => ['gap' => 200000, 'dependants' => 2],
            ],
        ],
        [
            'id' => 'savings_emergency_fund_low',
            'type' => 'recommendation',
            'module' => 'savings',
            'title' => 'Build your emergency fund',
            'detail' => 'Your cash covers 2 months of spending; the target is 6.',
            'meta' => 'You could save £19,200',
            'value' => 80.0,
            'done' => false,
            'module_label' => 'Savings',
            'action' => ['kind' => 'navigate', 'route' => '/savings'],
            'card' => [
                'category' => 'emergency_fund',
                'timeline' => 'short_term',
                'personalised_context' => [],
                'conflict_note' => 'Do this before investing.',
                'alternatives_note' => 'This is one choice with the ISA top-up.',
                'potential_benefit' => 19200.0,
                'requires_advice' => false,
                'definition_key' => 'emergency_fund_low',
                'figures' => ['months' => 2, 'target_months' => 6],
            ],
        ],
    ];
    stubActionsList($items);

    $result = callHandleRecommendations(buildAgentWithFixedAnalysis(['available_surplus' => 1500.0]), $user);

    expect($result['total'] ?? null)->toBe(2)
        ->and($result['surplus'] ?? null)->toBe(1500.0)
        ->and($result['recommendations'] ?? [])->toHaveCount(2);

    // Every field of every item and of its card round-trips byte-for-byte.
    foreach ($items as $idx => $item) {
        $actual = $result['recommendations'][$idx];
        $expected = array_merge(array_diff_key($item, ['card' => true, 'action' => true]), $item['card']);
        foreach ($expected as $field => $value) {
            expect($actual)->toHaveKey($field);
            expect($actual[$field])->toBe($value, "Field `{$field}` did not round-trip on item #{$idx}");
        }
        expect($actual)->not->toHaveKey('action')
            ->and($actual['recommendation_id'])->toBe($item['id'])
            ->and($actual['description'])->toBe($item['detail']);
    }
});

it('passes through nested personalised_context and figures without flattening or truncating', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $deeplyNested = [
        'breakdowns' => [
            ['label' => 'Pension contribution headroom', 'value' => 24000.0],
            ['label' => 'ISA allowance remaining', 'value' => 12500.0],
            ['label' => 'Capital gains allowance', 'value' => 3000.0],
        ],
        'modelled_outcomes' => [
            'low' => 12000,
            'central' => 18000,
            'high' => 25000,
        ],
        'tags' => ['tax_efficient', 'pre_retirement', 'higher_rate'],
    ];

    stubActionsList([[
        'id' => 'tax_pension_relief',
        'type' => 'recommendation',
        'module' => 'tax',
        'title' => 'Pay more into your pension before the tax year ends',
        'detail' => 'You have unused annual allowance and are inside the higher-rate band.',
        'card' => ['figures' => $deeplyNested, 'personalised_context' => ['You pay 40% on the slice above £50,270.']],
    ]]);

    $result = callHandleRecommendations(buildAgentWithFixedAnalysis(['available_surplus' => 0]), $user);

    expect($result['recommendations'][0]['figures'])->toBe($deeplyNested)
        ->and($result['recommendations'][0]['personalised_context'])->toBe(['You pay 40% on the slice above £50,270.']);
});

it('returns an empty list when the actions list is empty', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    stubActionsList([]);

    $result = callHandleRecommendations(buildAgentWithFixedAnalysis(['available_surplus' => 0]), $user);

    expect($result['total'])->toBe(0);
    expect($result['recommendations'])->toBe([]);
});
