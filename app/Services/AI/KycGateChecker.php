<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Constants\GateRoutes;
use App\Constants\QuerySchemas;
use App\Events\Eval\GateChecked;
use App\Models\User;
use App\Services\PrerequisiteGateService;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;
use LogicException;

/**
 * Checks only the data required to answer the primary classified question.
 * Related classifications may enrich context, but never block the turn.
 */
class KycGateChecker
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly PrerequisiteGateService $prerequisiteGate,
    ) {}

    /**
     * @return array{passed: bool, missing: string[], prompt_text: string}
     */
    public function check(User $user, array $classification): array
    {
        $primary = $classification['primary'];

        if (! QuerySchemas::isAdviceType($primary)) {
            return $this->pass();
        }

        $requirements = QuerySchemas::REQUIRED_DATA[$primary]
            ?? throw new LogicException("Missing required-data definition for advice type: {$primary}");

        $missing = [];
        foreach ($requirements as $requirement) {
            $missingRequirement = $this->checkRequirement($user, $requirement);

            event(new GateChecked(
                gate: 'kyc',
                module: $primary,
                passed: $missingRequirement === null,
                context: [
                    'requirement' => $requirement,
                    'missing' => $missingRequirement['label'] ?? null,
                    'user_id' => $user->id,
                ],
                atMicrotime: microtime(true),
            ));

            if ($missingRequirement !== null) {
                $missing[] = $missingRequirement;
            }
        }

        if ($missing === []) {
            return $this->passWithSummary();
        }

        return $this->blocked($missing);
    }

    /**
     * @return array{label: string, destination: string}|null
     */
    private function checkRequirement(User $user, string $requirement): ?array
    {
        return match ($requirement) {
            QuerySchemas::REQUIREMENT_DATE_OF_BIRTH => $user->date_of_birth
                ? null
                : ['label' => 'Date of birth', 'destination' => GateRoutes::PERSONAL_DETAILS],
            QuerySchemas::REQUIREMENT_INCOME => $this->resolveGrossAnnualIncome($user) > 0
                ? null
                : ['label' => 'Annual income', 'destination' => GateRoutes::INCOME],
            QuerySchemas::REQUIREMENT_EXPENDITURE => $this->resolveMonthlyExpenditure($user)['amount'] > 0
                ? null
                : ['label' => 'Monthly expenditure', 'destination' => GateRoutes::EXPENDITURE],
            QuerySchemas::REQUIREMENT_PROTECTION => $this->requiredModuleData(
                $user,
                $requirement,
                'Protection details',
                GateRoutes::PROTECTION,
            ),
            QuerySchemas::REQUIREMENT_SAVINGS => $this->requiredModuleData(
                $user,
                $requirement,
                'Savings accounts',
                GateRoutes::SAVINGS,
            ),
            QuerySchemas::REQUIREMENT_LIABILITIES => $this->requiredModuleData(
                $user,
                $requirement,
                'Debts and liabilities',
                GateRoutes::LIABILITIES,
            ),
            QuerySchemas::REQUIREMENT_RETIREMENT => $this->requiredModuleData(
                $user,
                $requirement,
                'Pension details',
                GateRoutes::RETIREMENT,
            ),
            QuerySchemas::REQUIREMENT_INVESTMENT => $this->requiredModuleData(
                $user,
                $requirement,
                'Investment accounts',
                GateRoutes::INVESTMENT,
            ),
            QuerySchemas::REQUIREMENT_ESTATE => $this->requiredModuleData(
                $user,
                $requirement,
                'Estate information',
                GateRoutes::ESTATE,
            ),
            QuerySchemas::REQUIREMENT_GOALS => $this->requiredModuleData(
                $user,
                $requirement,
                'Goals',
                GateRoutes::GOALS,
            ),
            QuerySchemas::REQUIREMENT_PROPERTY => $this->requiredModuleData(
                $user,
                $requirement,
                'Property details',
                GateRoutes::PROPERTY,
            ),
            default => throw new LogicException("Unknown KYC data requirement: {$requirement}"),
        };
    }

    /** @return array{label: string, destination: string}|null */
    private function requiredModuleData(
        User $user,
        string $requirement,
        string $label,
        string $destination,
    ): ?array {
        return $this->prerequisiteGate->hasDataForRequirement($requirement, $user)
            ? null
            : ['label' => $label, 'destination' => $destination];
    }

    /** @return array{passed: true, missing: array{}, prompt_text: string} */
    private function passWithSummary(): array
    {
        return [
            'passed' => true,
            'missing' => [],
            'prompt_text' => "<kyc_status>\nKYC CHECK: PASSED. Sufficient data is available for this question. Proceed with the FCA 6-step process.\n</kyc_status>",
        ];
    }

    /**
     * @param  array<int, array{label: string, destination: string}>  $missing
     * @return array{passed: false, missing: string[], prompt_text: string}
     */
    private function blocked(array $missing): array
    {
        $missingLines = array_map(function (array $item): string {
            $pageLabel = GateRoutes::resolve($item['destination'])['label'];

            return "- {$item['label']} - open {$pageLabel}";
        }, $missing);

        $missingList = implode("\n", $missingLines);
        $promptText = <<<PROMPT
<kyc_status>
KYC CHECK: BLOCKED. The following data is missing and must be provided before you can give advice:

{$missingList}

MANDATORY INSTRUCTIONS - follow these exactly:
1. Do NOT give advice, estimates, or general guidance on this topic
2. Explain clearly what data is missing and why it is needed for personalised guidance
3. Offer to help the user enter the data conversationally
4. Signpost the exact page label shown above in plain text. Do not output an internal route and do not call a navigation or write tool on the advice surface
</kyc_status>
PROMPT;

        return [
            'passed' => false,
            'missing' => array_column($missing, 'label'),
            'prompt_text' => $promptText,
        ];
    }

    /** @return array{passed: true, missing: array{}, prompt_text: string} */
    private function pass(): array
    {
        return [
            'passed' => true,
            'missing' => [],
            'prompt_text' => '',
        ];
    }
}
