<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\MemoryRetrieverService;
use App\Services\AI\Prompts\UserContentSanitiser;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\TaxConfigService;

/**
 * Builds the dynamic <context>…</context> + <user_message>…</user_message>
 * block prepended in-memory to the current user turn. Bucket membership
 * comes from FynContextSelector; block content reuses the existing
 * AdvicePromptBuilder public builders verbatim (no behavioural drift).
 */
final class FynContextAssembler
{
    public function __construct(
        private readonly FynContextSelector $selector,
        private readonly AdvicePromptBuilder $advice,
        private readonly MemoryRetrieverService $memory,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function build(FynTurnContext $ctx): string
    {
        $buckets = $this->selector->buckets($ctx);
        $has = fn (ContextBucket $b): bool => in_array($b, $buckets, true);

        $firstName = $this->resolveFirstName($ctx->user);
        $taxYear = $this->taxConfig->getTaxYear() ?? '2026/27';

        $lines = [];
        $lines[] = '<context>';
        $lines[] = "Current tax year: {$taxYear}";
        $lines[] = 'You are speaking with: '.UserContentSanitiser::wrap($firstName);
        $lines[] = $ctx->isOnboarding()
            ? 'Situation: onboarding — focus: '.$this->focusLabel($ctx->onboardingFocus)
            : 'Situation: advice';

        // IDENTITY (always present in every bucket set)
        $lines[] = '<user_profile>'."\n".$this->advice->buildUserProfile($ctx->user)."\n".'</user_profile>';
        $lines[] = '<current_context>'."\n".$this->advice->moduleContextFor($ctx->currentRoute)."\n".'</current_context>';

        // Known facts — mode-independent, included whenever non-empty
        $known = $this->memory->renderKnownFactsBlock($ctx->user, $ctx->conversation);
        if ($known !== '') {
            $lines[] = $known;
        }

        if ($has(ContextBucket::POSITION)) {
            $fin = $this->advice->buildFinancialContext($ctx->user, null, $ctx->classification);
            $lines[] = "<financial_context>\n{$fin}\n</financial_context>";
            $rec = $this->advice->buildExistingRecordsSummary($ctx->user, $ctx->classification);
            $lines[] = "<existing_records>\n{$rec}\n</existing_records>";
        }

        if ($has(ContextBucket::READINESS)) {
            $lines[] = $this->advice->buildPrerequisiteStateContextWrapped($ctx->user);
        }

        if ($has(ContextBucket::CAPTURE)) {
            $focus = (string) $ctx->onboardingFocus;
            $lines[] = FynCaptureTurnInstructions::render(
                $this->focusLabel($focus),
                implode(', ', OnboardingPromptBuilder::toolsForFocus($focus)),
            );
        }

        if ($ctx->isPreview) {
            $lines[] = '<preview_mode>'."\n"
                .'The user is previewing Fynla without a real account. You cannot create, '
                .'update, or delete any records. If they ask you to save anything, tell them '
                .'warmly it will be captured when they sign up, then continue helping with '
                .'analysis and questions.'."\n".'</preview_mode>';
        }

        $lines[] = '</context>';
        $lines[] = '<user_message>';
        $lines[] = UserContentSanitiser::clean($ctx->message);
        $lines[] = '</user_message>';

        return implode("\n", $lines);
    }

    private function resolveFirstName(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));
        if ($first === '') {
            $parts = explode(' ', (string) $user->name);
            $first = $parts[0] !== '' ? $parts[0] : 'there';
        }

        return $first;
    }

    private function focusLabel(?string $focus): string
    {
        return match ($focus) {
            'savings', 'budgeting' => 'Cash & Savings',
            'investment' => 'Investments',
            'retirement' => 'Retirement',
            'protection' => 'Protection',
            'estate' => 'Estate Planning',
            'business' => 'Business',
            'goals' => 'Goals',
            'savetax' => 'SaveTax',
            default => (string) $focus,
        };
    }
}
