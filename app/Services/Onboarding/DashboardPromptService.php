<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\User;
use App\Traits\StructuredLogging;

class DashboardPromptService
{
    use StructuredLogging;

    private const POST_JOURNEY_PROMPTS = [
        'budgeting' => [
            'message' => 'Your savings rate is ready. See how your emergency fund is tracking.',
            'cta_text' => 'View Savings',
            'cta_link' => '/savings',
        ],
        'protection' => [
            'message' => 'Check your coverage gaps — we have identified areas where you may be underinsured.',
            'cta_text' => 'View Protection',
            'cta_link' => '/protection',
        ],
        'investment' => [
            'message' => 'Your portfolio is set up. Review your asset allocation and fee analysis.',
            'cta_text' => 'View Investments',
            'cta_link' => '/investment',
        ],
        'retirement' => [
            'message' => 'See your retirement projection — find out when you could afford to retire.',
            'cta_text' => 'View Retirement',
            'cta_link' => '/retirement',
        ],
        'estate' => [
            'message' => 'Your estimated Inheritance Tax position is ready. Review your estate plan.',
            'cta_text' => 'View Estate',
            'cta_link' => '/estate',
        ],
        'family' => [
            'message' => 'Your family details enhance protection and estate calculations. Explore household planning.',
            'cta_text' => 'View Profile',
            'cta_link' => '/profile',
        ],
        'business' => [
            'message' => 'Your business interests are now included in your net worth. Review your financial position.',
            'cta_text' => 'View Net Worth',
            'cta_link' => '/net-worth',
        ],
        'goals' => [
            'message' => 'Your goal is live! Track your progress and see your affordability analysis.',
            'cta_text' => 'View Goals',
            'cta_link' => '/goals',
        ],
    ];

    public function __construct(
        private readonly JourneyStateService $journeyStateService
    ) {}

    public function getPostJourneyPrompt(string $journey): array
    {
        $prompt = self::POST_JOURNEY_PROMPTS[$journey] ?? null;

        if ($prompt === null) {
            throw new \InvalidArgumentException(
                "Invalid journey name: '{$journey}'. Valid journeys: ".implode(', ', JourneyStateService::JOURNEYS)
            );
        }

        return [
            'id' => "post_journey_{$journey}",
            'journey' => $journey,
            'message' => $prompt['message'],
            'cta_text' => $prompt['cta_text'],
            'cta_link' => $prompt['cta_link'],
        ];
    }

    public function getDashboardPrompts(User $user): array
    {
        $states = $this->journeyStateService->getJourneyStates($user);
        $selectedJourneys = $this->journeyStateService->getSelectedJourneys($user);
        $dismissedPrompts = $user->dismissed_prompts ?? [];
        $prompts = [];

        foreach ($states as $journey => $state) {
            $status = $state['status'] ?? 'not_started';

            if ($status === 'completed') {
                $promptId = "post_journey_{$journey}";
                if (! in_array($promptId, $dismissedPrompts, true)) {
                    $prompts[] = $this->getPostJourneyPrompt($journey);
                }
            }

            if ($status === 'in_progress') {
                $promptId = "continue_{$journey}";
                if (! in_array($promptId, $dismissedPrompts, true)) {
                    $prompts[] = [
                        'id' => $promptId,
                        'journey' => $journey,
                        'message' => 'You have an unfinished '.$journey.' journey. Pick up where you left off.',
                        'cta_text' => 'Continue',
                        'cta_link' => '/onboarding/journey/'.$journey,
                    ];
                }
            }

            if ($status === 'not_started' && in_array($journey, $selectedJourneys, true)) {
                $promptId = "start_{$journey}";
                if (! in_array($promptId, $dismissedPrompts, true)) {
                    $prompts[] = [
                        'id' => $promptId,
                        'journey' => $journey,
                        'message' => 'Ready to start your '.$journey.' journey? It only takes a few minutes.',
                        'cta_text' => 'Start',
                        'cta_link' => '/onboarding/journey/'.$journey,
                    ];
                }
            }
        }

        return $prompts;
    }

    public function dismissPrompt(User $user, string $promptId): void
    {
        $dismissedPrompts = $user->dismissed_prompts ?? [];

        if (! in_array($promptId, $dismissedPrompts, true)) {
            $dismissedPrompts[] = $promptId;
            $user->update(['dismissed_prompts' => $dismissedPrompts]);

            $this->logInfo('Dashboard prompt dismissed', [
                'user_id' => $user->id,
                'prompt_id' => $promptId,
            ]);
        }
    }
}
