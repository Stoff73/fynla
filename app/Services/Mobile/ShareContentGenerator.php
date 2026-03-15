<?php

declare(strict_types=1);

namespace App\Services\Mobile;

class ShareContentGenerator
{
    public function generate(string $type, ?int $id = null, ?int $userId = null): array
    {
        return match ($type) {
            'goal_milestone' => $this->goalMilestone($id, $userId),
            'net_worth_milestone' => $this->netWorthMilestone(),
            'fyn_insight' => $this->fynInsight(),
            'app_referral' => $this->appReferral(),
            default => throw new \InvalidArgumentException("Unknown share type: {$type}"),
        };
    }

    private function goalMilestone(?int $goalId, ?int $userId): array
    {
        return [
            'title' => 'Financial Goal Progress',
            'text' => "I'm making great progress on my financial goals with Fynla! Setting clear targets and tracking progress really works.",
            'url' => 'https://fynla.org',
        ];
    }

    private function netWorthMilestone(): array
    {
        return [
            'title' => 'Financial Milestone',
            'text' => 'Just hit a financial milestone! Tracking my net worth with Fynla has been a game-changer for staying motivated.',
            'url' => 'https://fynla.org',
        ];
    }

    private function fynInsight(): array
    {
        return [
            'title' => 'Financial Insight from Fyn',
            'text' => 'Got a great financial insight from Fyn, my AI financial advisor on Fynla. Really helpful for making better financial decisions.',
            'url' => 'https://fynla.org',
        ];
    }

    private function appReferral(): array
    {
        return [
            'title' => 'Try Fynla',
            'text' => "I've been using Fynla to manage my finances — it covers everything from savings to investments to estate planning. Worth a look!",
            'url' => 'https://fynla.org',
        ];
    }
}
