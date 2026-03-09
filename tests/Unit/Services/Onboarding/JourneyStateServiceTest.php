<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\JourneyStateService;

beforeEach(function () {
    $this->service = new JourneyStateService;
});

describe('JourneyStateService', function () {
    it('returns all not_started states for new user', function () {
        $user = User::factory()->create();

        $states = $this->service->getJourneyStates($user);

        expect($states)->toHaveCount(8);

        foreach (JourneyStateService::JOURNEYS as $journey) {
            expect($states[$journey]['status'])->toBe('not_started')
                ->and($states[$journey]['started_at'])->toBeNull()
                ->and($states[$journey]['completed_at'])->toBeNull();
        }
    });

    it('starts a journey setting it to in_progress', function () {
        $user = User::factory()->create();

        $this->service->startJourney($user, 'protection');

        $user->refresh();
        $states = $this->service->getJourneyStates($user);

        expect($states['protection']['status'])->toBe('in_progress')
            ->and($states['protection']['started_at'])->not->toBeNull()
            ->and($states['protection']['completed_at'])->toBeNull();
    });

    it('completes a journey setting it to completed', function () {
        $user = User::factory()->create();

        $this->service->startJourney($user, 'retirement');
        $this->service->completeJourney($user, 'retirement');

        $user->refresh();
        $states = $this->service->getJourneyStates($user);

        expect($states['retirement']['status'])->toBe('completed')
            ->and($states['retirement']['started_at'])->not->toBeNull()
            ->and($states['retirement']['completed_at'])->not->toBeNull();
    });

    it('returns selected journeys', function () {
        $user = User::factory()->create();

        expect($this->service->getSelectedJourneys($user))->toBe([]);

        $this->service->setSelectedJourneys($user, ['protection', 'retirement']);

        $user->refresh();
        expect($this->service->getSelectedJourneys($user))->toBe(['protection', 'retirement']);
    });

    it('sets selected journeys and initialises states', function () {
        $user = User::factory()->create();

        $this->service->setSelectedJourneys($user, ['budgeting', 'estate']);

        $user->refresh();
        $states = $this->service->getJourneyStates($user);

        expect($states['budgeting']['status'])->toBe('not_started')
            ->and($states['estate']['status'])->toBe('not_started');
    });

    it('throws exception for invalid journey name on start', function () {
        $user = User::factory()->create();

        $this->service->startJourney($user, 'invalid_journey');
    })->throws(\InvalidArgumentException::class);

    it('throws exception for invalid journey name on complete', function () {
        $user = User::factory()->create();

        $this->service->completeJourney($user, 'invalid_journey');
    })->throws(\InvalidArgumentException::class);

    it('returns journey progress', function () {
        $user = User::factory()->create();

        // Not started
        $progress = $this->service->getJourneyProgress($user, 'protection');
        expect($progress['current_step'])->toBe(0)
            ->and($progress['total_steps'])->toBe(5)
            ->and($progress['percentage'])->toBe(0);

        // In progress
        $this->service->startJourney($user, 'protection');
        $user->refresh();
        $progress = $this->service->getJourneyProgress($user, 'protection');
        expect($progress['current_step'])->toBe(1)
            ->and($progress['total_steps'])->toBe(5)
            ->and($progress['percentage'])->toBe(20);

        // Completed
        $this->service->completeJourney($user, 'protection');
        $user->refresh();
        $progress = $this->service->getJourneyProgress($user, 'protection');
        expect($progress['current_step'])->toBe(5)
            ->and($progress['total_steps'])->toBe(5)
            ->and($progress['percentage'])->toBe(100);
    });
});
