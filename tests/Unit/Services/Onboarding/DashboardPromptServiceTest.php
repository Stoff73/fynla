<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\DashboardPromptService;
use App\Services\Onboarding\JourneyStateService;

beforeEach(function () {
    $this->journeyStateService = new JourneyStateService;
    $this->service = new DashboardPromptService($this->journeyStateService);
});

describe('DashboardPromptService', function () {
    it('returns correct prompt for completed journey', function () {
        $user = User::factory()->create();

        $this->journeyStateService->startJourney($user, 'protection');
        $this->journeyStateService->completeJourney($user, 'protection');

        $user->refresh();
        $prompts = $this->service->getDashboardPrompts($user);

        $protectionPrompt = collect($prompts)->firstWhere('journey', 'protection');

        expect($protectionPrompt)->not->toBeNull()
            ->and($protectionPrompt['id'])->toBe('post_journey_protection')
            ->and($protectionPrompt['message'])->toContain('coverage gaps')
            ->and($protectionPrompt['cta_link'])->toBe('/protection');
    });

    it('excludes dismissed prompts', function () {
        $user = User::factory()->create();

        $this->journeyStateService->startJourney($user, 'retirement');
        $this->journeyStateService->completeJourney($user, 'retirement');
        $user->refresh();

        $this->service->dismissPrompt($user, 'post_journey_retirement');
        $user->refresh();

        $prompts = $this->service->getDashboardPrompts($user);

        $retirementPrompt = collect($prompts)->firstWhere('id', 'post_journey_retirement');
        expect($retirementPrompt)->toBeNull();
    });

    it('returns empty for no completed journeys', function () {
        $user = User::factory()->create();

        $prompts = $this->service->getDashboardPrompts($user);

        // No selected journeys and no completed/in-progress, so no prompts
        expect($prompts)->toBeEmpty();
    });

    it('returns continue prompt for in-progress journey', function () {
        $user = User::factory()->create();

        $this->journeyStateService->startJourney($user, 'investment');
        $user->refresh();

        $prompts = $this->service->getDashboardPrompts($user);

        $continuePrompt = collect($prompts)->firstWhere('id', 'continue_investment');
        expect($continuePrompt)->not->toBeNull()
            ->and($continuePrompt['message'])->toContain('unfinished')
            ->and($continuePrompt['cta_text'])->toBe('Continue');
    });

    it('returns start prompt for not-started selected journey', function () {
        $user = User::factory()->create();

        $this->journeyStateService->setSelectedJourneys($user, ['estate']);
        $user->refresh();

        $prompts = $this->service->getDashboardPrompts($user);

        $startPrompt = collect($prompts)->firstWhere('id', 'start_estate');
        expect($startPrompt)->not->toBeNull()
            ->and($startPrompt['message'])->toContain('Ready to start')
            ->and($startPrompt['cta_text'])->toBe('Start');
    });

    it('dismisses a prompt', function () {
        $user = User::factory()->create();

        $this->service->dismissPrompt($user, 'post_journey_protection');

        $user->refresh();
        expect($user->dismissed_prompts)->toContain('post_journey_protection');
    });
});
