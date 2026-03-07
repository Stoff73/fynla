<?php

declare(strict_types=1);

use App\Models\User;

describe('Journey Flow', function () {
    beforeEach(function () {
        $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
        $this->user = User::factory()->create();
    });

    it('completes a full single-journey flow', function () {
        // Save selections
        $this->actingAs($this->user)
            ->postJson('/api/journeys/selections', ['journeys' => ['protection']])
            ->assertCreated();

        // Start journey
        $this->actingAs($this->user)
            ->postJson('/api/journeys/protection/start')
            ->assertOk();

        // Get steps
        $this->actingAs($this->user)
            ->getJson('/api/journeys/protection/steps')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['steps']]);

        // Complete journey
        $this->actingAs($this->user)
            ->postJson('/api/journeys/protection/complete')
            ->assertOk();

        // Verify state
        $this->user->refresh();
        $states = $this->user->journey_states;
        expect($states['protection']['status'])->toBe('completed');

        // Dashboard prompts should include post-journey prompt
        $this->actingAs($this->user)
            ->getJson('/api/journeys/dashboard-prompts')
            ->assertOk()
            ->assertJsonFragment(['journey' => 'protection']);
    });

    it('handles multi-journey deduplication', function () {
        $this->actingAs($this->user)
            ->postJson('/api/journeys/selections', ['journeys' => ['protection', 'retirement']])
            ->assertCreated();

        // Steps should be merged
        $response = $this->actingAs($this->user)
            ->getJson('/api/journeys/protection/steps')
            ->assertOk();

        $steps = $response->json('data.steps');

        // Should have merged personal step (fields from both journeys)
        $personalSteps = array_filter($steps, fn ($s) => $s['component'] === 'JourneyPersonalStep');
        expect(count($personalSteps))->toBe(1);
    });

    it('resumes journey after pause', function () {
        // Start journey
        $this->actingAs($this->user)
            ->postJson('/api/journeys/selections', ['journeys' => ['investment']])
            ->assertCreated();
        $this->actingAs($this->user)
            ->postJson('/api/journeys/investment/start')
            ->assertOk();

        // Verify in_progress state persists
        $this->user->refresh();
        expect($this->user->journey_states['investment']['status'])->toBe('in_progress');

        // Steps should still be available after pause
        $this->actingAs($this->user)
            ->getJson('/api/journeys/investment/steps')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['steps']]);
    });
});
