<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;

/**
 * W-0473 — this file's mock used to return `['modules' => [...]]`, a shape
 * `CoordinatingAgent::analyze()` has never produced. Every reader missed, the
 * controller fell through to its generic catch-all insight, and the suite stayed
 * green because it asserted only that *a* non-empty string came back. Every mock
 * below is now the real shape: `module_analysis`, each module being the
 * coordinator's flat map with the agent's own payload under `full_analysis`.
 * Assertions name the insight text, so a reader drifting a level again fails here.
 */
function mockAnalysis(array $moduleAnalysis): void
{
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('analyze')->andReturn(['module_analysis' => $moduleAnalysis]);
    app()->instance(CoordinatingAgent::class, $mock);
}

beforeEach(function () {
    mockAnalysis([
        'savings' => [
            'total_savings' => 12000,
            'emergency_fund_months' => 3.5,
            'full_analysis' => [
                'emergency_fund' => ['runway_months' => 3.5],
                'isa_allowance' => ['remaining' => 12000.00],
            ],
        ],
    ]);
});

afterEach(function () {
    Mockery::close();
});

describe('Daily Insights API', function () {
    it('returns daily insight for authenticated user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/mobile/insights/daily');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'insight',
                    'category',
                    'cached_at',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        expect($data['insight'])->toBeString()->not->toBeEmpty();
        expect($data['category'])->toBeIn([
            'savings', 'protection', 'investment', 'retirement', 'estate', 'goals', 'tax',
        ]);
    });

    // The rotation at DailyInsightService:252 is `$insights[(int) now()->format('z')
    // % count($insights)]` — day of year. The shared beforeEach mock supplies BOTH
    // savings keys, so `compose()` builds TWO insights and the rotation alternates
    // by the parity of the day: this file asserted the ISA text while the mock could
    // serve the emergency-fund one, and the test therefore failed on every EVEN
    // day-of-year. Measured: 2026-08-24 z=235 ISA, 2026-08-25 z=236 emergency fund,
    // 2026-08-26 z=237 ISA, 2026-08-27 z=238 emergency fund. It went red in CI on
    // the 25th and green locally on the 26th — same code, a day apart.
    //
    // Each key produces its own insight independently (`:118` and `:129`), so one
    // key per test makes `count($insights)` exactly 1 and the rotation a genuine
    // no-op — which is what the old comment claimed and the mock did not deliver.
    // Both readers stay covered; neither depends on the calendar.
    it('reads the ISA allowance out of the agent payload', function () {
        mockAnalysis([
            'savings' => [
                'total_savings' => 12000,
                'full_analysis' => [
                    'isa_allowance' => ['remaining' => 12000.00],
                ],
            ],
        ]);

        $insight = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/mobile/insights/daily')
            ->assertOk()
            ->json('data.insight');

        expect($insight)->toContain('12,000.00');
    });

    it('reads the emergency-fund runway out of the agent payload', function () {
        mockAnalysis([
            'savings' => [
                'emergency_fund_months' => 3.5,
                'full_analysis' => [
                    'emergency_fund' => ['runway_months' => 3.5],
                ],
            ],
        ]);

        $insight = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/mobile/insights/daily')
            ->assertOk()
            ->json('data.insight');

        expect($insight)->toContain('3.5 months');
    });

    it('reads the pension Annual Allowance under its own key', function () {
        mockAnalysis([
            'retirement' => [
                'total_pension_value' => 50000,
                // `remaining_allowance`, not `remaining` — the reader named the
                // wrong one, so this branch was dead twice over.
                'full_analysis' => ['annual_allowance' => ['remaining_allowance' => 55600]],
            ],
        ]);

        $insight = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/mobile/insights/daily')
            ->assertOk()
            ->json('data.insight');

        expect($insight)->toContain('55,600.00');
        expect($insight)->toContain('Annual Allowance');
    });

    it('carries the unmodelled relief caveat alongside the Inheritance Tax figure', function () {
        mockAnalysis([
            'estate' => [
                'iht_liability' => 58500,
                'full_analysis' => [
                    'summary' => [
                        'iht_liability' => 58500,
                        'unmodelled_relief_caveat' => 'This figure does not include Agricultural Property Relief.',
                    ],
                ],
            ],
        ]);

        $insight = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/mobile/insights/daily')
            ->assertOk()
            ->json('data.insight');

        expect($insight)->toContain('58,500.00');
        expect($insight)->toContain('Agricultural Property Relief');
    });

    it('does not claim a protection gap when every figure in the gaps is zero', function () {
        mockAnalysis([
            'protection' => [
                'coverage_gap' => 0,
                // The structure is present for every analysed household — its
                // existence is not a gap.
                'full_analysis' => [
                    'gaps' => [
                        'total_gap' => 0,
                        'gaps_by_category' => ['human_capital_gap' => 0, 'income_protection_gap' => 0],
                    ],
                ],
            ],
        ]);

        $insight = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/mobile/insights/daily')
            ->assertOk()
            ->json('data.insight');

        expect($insight)->not->toContain('gaps in your protection coverage');
    });

    it('claims a protection gap when a category is above zero', function () {
        mockAnalysis([
            'protection' => [
                'coverage_gap' => 0,
                'full_analysis' => [
                    'gaps' => [
                        'total_gap' => 0,
                        'gaps_by_category' => ['human_capital_gap' => 0, 'income_protection_gap' => 21000],
                    ],
                ],
            ],
        ]);

        $insight = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/mobile/insights/daily')
            ->assertOk()
            ->json('data.insight');

        expect($insight)->toContain('gaps in your protection coverage');
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/mobile/insights/daily')
            ->assertUnauthorized();
    });

    it('includes ETag header in response', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/mobile/insights/daily');

        $response->assertOk();
        expect($response->headers->has('ETag'))->toBeTrue();
    });

    it('returns fallback insight when analysis fails', function () {
        $failingMock = Mockery::mock(CoordinatingAgent::class);
        $failingMock->shouldReceive('analyze')->andThrow(new RuntimeException('Analysis failed'));
        $this->app->instance(CoordinatingAgent::class, $failingMock);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/mobile/insights/daily');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'insight',
                    'category',
                    'cached_at',
                ],
            ]);

        $data = $response->json('data');
        expect($data['insight'])->toBeString()->not->toBeEmpty();
    });

    it('returns 304 when ETag matches If-None-Match header', function () {
        $user = User::factory()->create();

        // First request to get ETag
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/mobile/insights/daily');
        $response->assertOk();
        $etag = $response->headers->get('ETag');

        expect($etag)->not->toBeNull();

        // Second request with matching If-None-Match
        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('If-None-Match', $etag)
            ->getJson('/api/v1/mobile/insights/daily');

        $response->assertStatus(304);
    });
});
