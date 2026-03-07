<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\JourneyStateService;

describe('Journey API', function () {
    beforeEach(function () {
        $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
        $this->user = User::factory()->create();
    });

    it('saves journey selections', function () {
        $this->actingAs($this->user)
            ->postJson('/api/journeys/selections', ['journeys' => ['protection', 'retirement']])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->user->refresh();
        expect($this->user->journey_selections)->toBe(['protection', 'retirement']);
    });

    it('gets journey selections', function () {
        $this->user->update(['journey_selections' => ['budgeting']]);

        $this->actingAs($this->user)
            ->getJson('/api/journeys/selections')
            ->assertOk()
            ->assertJsonPath('data.selections', ['budgeting']);
    });

    it('gets preview with deduplicated fields', function () {
        $this->actingAs($this->user)
            ->getJson('/api/journeys/preview?journeys[]=protection&journeys[]=retirement')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['personal_count', 'financial_count', 'estimated_minutes']]);
    });

    it('starts a journey', function () {
        $this->actingAs($this->user)
            ->postJson('/api/journeys/protection/start')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->user->refresh();
        $states = $this->user->journey_states;
        expect($states['protection']['status'])->toBe('in_progress');
    });

    it('completes a journey', function () {
        $service = app(JourneyStateService::class);
        $service->startJourney($this->user, 'investment');

        $this->actingAs($this->user)
            ->postJson('/api/journeys/investment/complete')
            ->assertOk();

        $this->user->refresh();
        $states = $this->user->journey_states;
        expect($states['investment']['status'])->toBe('completed');
    });

    it('dismisses a prompt', function () {
        $this->actingAs($this->user)
            ->postJson('/api/journeys/dismiss-prompt', ['prompt_id' => 'post_journey_protection'])
            ->assertOk();

        $this->user->refresh();
        expect($this->user->dismissed_prompts)->toContain('post_journey_protection');
    });

    it('prevents unauthenticated access', function () {
        $this->getJson('/api/journeys/selections')->assertUnauthorized();
    });

    it('isolates data between users', function () {
        $otherUser = User::factory()->create();
        $otherUser->update(['journey_selections' => ['estate', 'goals']]);

        $this->actingAs($this->user)
            ->getJson('/api/journeys/selections')
            ->assertOk()
            ->assertJsonPath('data.selections', []);
    });

    it('validates journey names on selection', function () {
        $this->actingAs($this->user)
            ->postJson('/api/journeys/selections', ['journeys' => ['invalid_journey']])
            ->assertUnprocessable();
    });

    it('validates empty selections', function () {
        $this->actingAs($this->user)
            ->postJson('/api/journeys/selections', ['journeys' => []])
            ->assertUnprocessable();
    });

    it('returns journey steps', function () {
        $this->actingAs($this->user)
            ->getJson('/api/journeys/protection/steps')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['steps']]);
    });

    it('returns dashboard prompts', function () {
        $service = app(JourneyStateService::class);
        $service->setSelectedJourneys($this->user, ['budgeting']);

        $this->actingAs($this->user)
            ->getJson('/api/journeys/dashboard-prompts')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['prompts']]);
    });
});
