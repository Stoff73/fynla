<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->builder = app(AdvicePromptBuilder::class);
});

/**
 * S0.13 — INV-2.3.3.
 *
 * Recommendation-mode responses (anything in QuerySchemas::ADVICE_TYPES)
 * end with the canonical FCA signposting string. Factual / bypass /
 * unclassified turns must not carry the suffix.
 */
const FCA_SIGNPOSTING_SENTENCE = 'For regulated advice personal to your circumstances, speak to a qualified financial adviser.';

describe('FCA signposting suffix (S0.13)', function () {
    it('appends the signposting block on every advice-type classification', function () {
        $user = User::factory()->create(['first_name' => 'Alex', 'surname' => 'Carter']);

        foreach (QuerySchemas::ADVICE_TYPES as $type) {
            $prompt = $this->builder->build(
                user: $user,
                classification: ['primary' => $type, 'related' => []],
            );

            expect($prompt)->toContain('<fca_signposting>')
                ->and($prompt)->toContain(FCA_SIGNPOSTING_SENTENCE);
        }
    });

    it('does not append the signposting block on factual-mode (general) classification', function () {
        $user = User::factory()->create(['first_name' => 'Alex', 'surname' => 'Carter']);

        $prompt = $this->builder->build(
            user: $user,
            classification: ['primary' => QuerySchemas::GENERAL, 'related' => []],
        );

        expect($prompt)->not->toContain('<fca_signposting>')
            ->and($prompt)->not->toContain(FCA_SIGNPOSTING_SENTENCE);
    });

    it('does not append the signposting block on bypass classifications (data_entry, navigation)', function () {
        $user = User::factory()->create(['first_name' => 'Alex', 'surname' => 'Carter']);

        foreach (QuerySchemas::BYPASS_TYPES as $type) {
            $prompt = $this->builder->build(
                user: $user,
                classification: ['primary' => $type, 'related' => []],
            );

            expect($prompt)->not->toContain('<fca_signposting>')
                ->and($prompt)->not->toContain(FCA_SIGNPOSTING_SENTENCE);
        }
    });

    it('does not append the signposting block when classification is missing', function () {
        $user = User::factory()->create(['first_name' => 'Alex', 'surname' => 'Carter']);

        $prompt = $this->builder->build(user: $user);

        expect($prompt)->not->toContain('<fca_signposting>')
            ->and($prompt)->not->toContain(FCA_SIGNPOSTING_SENTENCE);
    });

    it('does not append the signposting block when primary is unknown', function () {
        $user = User::factory()->create(['first_name' => 'Alex', 'surname' => 'Carter']);

        $prompt = $this->builder->build(
            user: $user,
            classification: ['primary' => 'made_up_type', 'related' => []],
        );

        expect($prompt)->not->toContain('<fca_signposting>')
            ->and($prompt)->not->toContain(FCA_SIGNPOSTING_SENTENCE);
    });
});
