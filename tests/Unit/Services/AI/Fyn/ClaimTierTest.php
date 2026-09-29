<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\ClaimTier;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\File;

/*
 * claim_tier reaches Fyn only as plain words it may say (2026-09-26 walk: a user
 * was told about a "mechanical-tier strategy"). One vocabulary, ClaimTier, used
 * by the tool-result boundary, the live-data blocks, the voicing rules and the
 * corpus (Rule 20).
 */

it('replaces claim_tier at any depth with the plain basis, and drops an unknown tier', function (): void {
    $out = ClaimTier::forModel([
        'recommendations' => [['title' => 'A', 'claim_tier' => 'mechanical']],
        'composed_tax_plan' => ['items' => [['title' => 'B', 'claim_tier' => 'judgement'], ['title' => 'C', 'claim_tier' => 'other']]],
    ]);

    expect($out['recommendations'][0])->toBe(['title' => 'A', 'basis' => ClaimTier::BASIS['mechanical']])
        ->and($out['composed_tax_plan']['items'][0])->toBe(['title' => 'B', 'basis' => ClaimTier::BASIS['judgement']])
        ->and($out['composed_tax_plan']['items'][1])->toBe(['title' => 'C'])
        ->and(json_encode($out))->not->toContain('claim_tier')->not->toContain('mechanical');
});

it('rewrites a fetched JSON block and leaves other text alone', function (): void {
    $json = (string) json_encode(['composed_tax_plan' => ['items' => [['claim_tier' => 'mechanical']]]]);

    expect(ClaimTier::forModelJson($json))->toContain('Worked from your figures')->not->toContain('claim_tier')
        ->and(ClaimTier::forModelJson('plain text'))->toBe('plain text');
});

it('builds the voicing rules from the same words the model sees in the data', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $out = app(FynContextAssembler::class)->build(FynTurnContext::make(
        user: User::factory()->create(), message: 'Should I pay more into my pension?', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => 'retirement_contribution'],
    ));

    expect($out)->toContain('whose basis begins "'.ClaimTier::label('mechanical').'"')
        ->toContain('whose basis begins "'.ClaimTier::label('judgement').'"')
        ->toContain('Never say how certain, sure or confident you are')
        ->not->toContain('claim_tier')
        ->not->toMatch('/mechanical/i');
});

it('keeps tier jargon out of every Fyn corpus file', function (): void {
    $offenders = collect(File::allFiles(base_path('fyn-memory')))
        ->filter(fn ($f): bool => preg_match('/\bmechanical\b|mechanical-tier|judgement-tier|claim[\s_]tiers?|\b(mechanical|judgement) tier\b/i', $f->getContents()) === 1)
        ->map(fn ($f): string => $f->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
