<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\User;
use App\Traits\StructuredLogging;

class JourneyStateService
{
    use StructuredLogging;

    public const JOURNEYS = [
        'budgeting',
        'protection',
        'investment',
        'retirement',
        'estate',
        'family',
        'business',
        'goals',
    ];

    public const STATES = [
        'not_started',
        'in_progress',
        'completed',
    ];

    private const DEFAULT_STEP_COUNTS = [
        'budgeting' => 3,
        'protection' => 5,
        'investment' => 3,
        'retirement' => 5,
        'estate' => 5,
        'family' => 3,
        'business' => 2,
        'goals' => 3,
    ];

    public function getJourneyStates(User $user): array
    {
        if ($user->journey_states !== null) {
            return $user->journey_states;
        }

        $states = [];
        foreach (self::JOURNEYS as $journey) {
            $states[$journey] = [
                'status' => 'not_started',
                'started_at' => null,
                'completed_at' => null,
            ];
        }

        return $states;
    }

    public function startJourney(User $user, string $journey): void
    {
        $this->validateJourneyName($journey);

        $states = $this->getJourneyStates($user);
        $states[$journey] = [
            'status' => 'in_progress',
            'started_at' => now()->toIso8601String(),
            'completed_at' => null,
        ];

        $user->update([
            'journey_states' => $states,
            'life_stage' => $journey,
        ]);

        $this->logInfo('Journey started', [
            'user_id' => $user->id,
            'journey' => $journey,
        ]);
    }

    public function completeJourney(User $user, string $journey): void
    {
        $this->validateJourneyName($journey);

        $states = $this->getJourneyStates($user);
        $states[$journey] = [
            'status' => 'completed',
            'started_at' => $states[$journey]['started_at'] ?? now()->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
        ];

        $user->update(['journey_states' => $states]);

        $this->logInfo('Journey completed', [
            'user_id' => $user->id,
            'journey' => $journey,
        ]);
    }

    public function getSelectedJourneys(User $user): array
    {
        return $user->journey_selections ?? [];
    }

    public function setSelectedJourneys(User $user, array $journeys): void
    {
        foreach ($journeys as $journey) {
            $this->validateJourneyName($journey);
        }

        $states = $this->getJourneyStates($user);

        foreach ($journeys as $journey) {
            if (! isset($states[$journey]) || $states[$journey]['status'] === 'not_started') {
                $states[$journey] = [
                    'status' => 'not_started',
                    'started_at' => null,
                    'completed_at' => null,
                ];
            }
        }

        $user->update([
            'journey_selections' => $journeys,
            'journey_states' => $states,
        ]);

        $this->logInfo('Journey selections saved', [
            'user_id' => $user->id,
            'journeys' => $journeys,
        ]);
    }

    public function getJourneyProgress(User $user, string $journey): array
    {
        $this->validateJourneyName($journey);

        $states = $this->getJourneyStates($user);
        $state = $states[$journey] ?? ['status' => 'not_started'];

        $totalSteps = self::DEFAULT_STEP_COUNTS[$journey];

        if ($state['status'] === 'completed') {
            return [
                'current_step' => $totalSteps,
                'total_steps' => $totalSteps,
                'percentage' => 100,
            ];
        }

        if ($state['status'] === 'not_started') {
            return [
                'current_step' => 0,
                'total_steps' => $totalSteps,
                'percentage' => 0,
            ];
        }

        // For in_progress, default to step 1 (exact step tracking will be handled by the frontend)
        return [
            'current_step' => 1,
            'total_steps' => $totalSteps,
            'percentage' => (int) round((1 / $totalSteps) * 100),
        ];
    }

    private function validateJourneyName(string $journey): void
    {
        if (! in_array($journey, self::JOURNEYS, true)) {
            throw new \InvalidArgumentException(
                "Invalid journey name: '{$journey}'. Valid journeys: ".implode(', ', self::JOURNEYS)
            );
        }
    }
}
