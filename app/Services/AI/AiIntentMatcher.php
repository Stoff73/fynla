<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Str;

class AiIntentMatcher
{
    /**
     * Navigation route mappings from keywords to frontend paths.
     */
    private const NAVIGATION_ROUTES = [
        'dashboard' => '/dashboard',
        'home' => '/dashboard',
        'net worth' => '/net-worth/wealth-summary',
        'wealth summary' => '/net-worth/wealth-summary',
        'wealth' => '/net-worth/wealth-summary',
        'savings' => '/net-worth/cash',
        'cash' => '/net-worth/cash',
        'emergency fund' => '/net-worth/cash',
        'property' => '/net-worth/property',
        'properties' => '/net-worth/property',
        'investments' => '/net-worth/investments',
        'investment' => '/net-worth/investments',
        'portfolio' => '/net-worth/investments',
        'stocks' => '/net-worth/investments',
        'shares' => '/net-worth/investments',
        'pensions' => '/net-worth/retirement',
        'pension' => '/net-worth/retirement',
        'retirement' => '/net-worth/retirement',
        'business' => '/net-worth/business',
        'chattels' => '/net-worth/chattels',
        'liabilities' => '/net-worth/liabilities',
        'protection' => '/protection',
        'life insurance' => '/protection',
        'income protection' => '/protection',
        'critical illness' => '/protection',
        'cover' => '/protection',
        'protection plan' => '/plans/protection',
        'estate' => '/estate',
        'estate planning' => '/estate',
        'inheritance' => '/estate',
        'will' => '/estate',
        'estate plan' => '/plans/estate',
        'goals' => '/goals',
        'targets' => '/goals',
        'holistic plan' => '/holistic-plan',
        'financial plan' => '/holistic-plan',
        'risk profile' => '/risk-profile',
        'risk' => '/risk-profile',
        'settings' => '/settings',
        'account' => '/settings',
        'tax efficiency' => '/net-worth/tax-efficiency',
        'fees' => '/net-worth/fees-detail',
        'holdings' => '/net-worth/holdings-detail',
    ];

    /**
     * Module keywords mapped to agent module names.
     */
    private const MODULE_KEYWORDS = [
        'protection' => ['protection', 'cover', 'life insurance', 'income protection', 'critical illness', 'insurance', 'policies'],
        'savings' => ['savings', 'cash', 'emergency fund', 'bank account', 'isa', 'deposit'],
        'investment' => ['investment', 'portfolio', 'stocks', 'shares', 'fund', 'equities', 'bonds'],
        'retirement' => ['retirement', 'pension', 'sipp', 'annuity', 'drawdown', 'workplace pension'],
        'estate' => ['estate', 'inheritance', 'will', 'trust', 'probate', 'iht', 'gift'],
        'goals' => ['goals', 'targets', 'milestones', 'aspirations', 'objectives'],
    ];

    /**
     * Tax topic keywords mapped to TaxConfigService method identifiers.
     */
    private const TAX_KEYWORDS = [
        'income_tax' => ['income tax', 'personal allowance', 'tax band', 'tax rate', 'higher rate', 'additional rate', 'basic rate'],
        'capital_gains' => ['capital gains', 'cgt', 'capital gains tax'],
        'inheritance_tax' => ['inheritance tax', 'iht', 'nil rate band', 'rnrb', 'estate tax'],
        'isa_allowances' => ['isa allowance', 'isa limit', 'individual savings account'],
        'pension_allowances' => ['pension allowance', 'annual allowance', 'lifetime allowance', 'money purchase annual allowance', 'pension limit', 'pension contribution'],
    ];

    /**
     * Match a user message to an intent.
     *
     * @return array{intent: string, params: array}
     */
    public function match(string $message): array
    {
        $lower = Str::lower(trim($message));

        // 1. Greeting
        if ($this->matchesGreeting($lower)) {
            return ['intent' => 'greeting', 'params' => []];
        }

        // 2. Help
        if ($this->matchesHelp($lower)) {
            return ['intent' => 'help', 'params' => []];
        }

        // 3. Navigation
        $navResult = $this->matchesNavigation($lower);
        if ($navResult) {
            return ['intent' => 'navigation', 'params' => $navResult];
        }

        // 4. Financial plan generation
        if ($this->matchesFinancialPlan($lower)) {
            return ['intent' => 'financial_plan', 'params' => []];
        }

        // 5. Recommendations
        if ($this->matchesRecommendations($lower)) {
            return ['intent' => 'recommendations', 'params' => []];
        }

        // 6. What-if scenarios
        $whatIfResult = $this->matchesWhatIf($lower);
        if ($whatIfResult) {
            return ['intent' => 'what_if', 'params' => $whatIfResult];
        }

        // 7. Tax information
        $taxResult = $this->matchesTax($lower);
        if ($taxResult) {
            return ['intent' => 'tax_info', 'params' => $taxResult];
        }

        // 8. Create blocked (preview mode)
        if ($this->matchesCreate($lower)) {
            return ['intent' => 'create_blocked', 'params' => ['action' => $this->extractCreateAction($lower)]];
        }

        // 9. Module analysis
        $moduleResult = $this->matchesModule($lower);
        if ($moduleResult) {
            return ['intent' => 'module_analysis', 'params' => $moduleResult];
        }

        // 10. Net worth / balance queries
        if ($this->matchesNetWorth($lower)) {
            return ['intent' => 'net_worth', 'params' => []];
        }

        // 11. Unknown
        return ['intent' => 'unknown', 'params' => []];
    }

    private function matchesGreeting(string $message): bool
    {
        $greetings = ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'howdy', 'greetings'];

        foreach ($greetings as $greeting) {
            if ($message === $greeting || Str::startsWith($message, $greeting.' ') || Str::startsWith($message, $greeting.',') || Str::startsWith($message, $greeting.'!')) {
                return true;
            }
        }

        return false;
    }

    private function matchesHelp(string $message): bool
    {
        $patterns = ['help', 'what can you do', 'how does this work', 'what are you', 'what do you do', 'capabilities', 'how can you help'];

        foreach ($patterns as $pattern) {
            if (Str::contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesNavigation(string $message): ?array
    {
        $navTriggers = ['take me to', 'show me', 'go to', 'navigate to', 'open my', 'open the', 'open '];

        $isNavRequest = false;
        foreach ($navTriggers as $trigger) {
            if (Str::contains($message, $trigger)) {
                $isNavRequest = true;
                break;
            }
        }

        if (! $isNavRequest) {
            return null;
        }

        // Find best matching route
        $bestMatch = null;
        $bestLength = 0;

        foreach (self::NAVIGATION_ROUTES as $keyword => $path) {
            if (Str::contains($message, $keyword) && strlen($keyword) > $bestLength) {
                $bestMatch = ['route_path' => $path, 'description' => ucfirst($keyword)];
                $bestLength = strlen($keyword);
            }
        }

        return $bestMatch;
    }

    private function matchesFinancialPlan(string $message): bool
    {
        $patterns = ['financial plan', 'holistic plan', 'generate a plan', 'create a plan', 'overall plan', 'comprehensive plan', 'full plan'];

        foreach ($patterns as $pattern) {
            if (Str::contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesRecommendations(string $message): bool
    {
        $patterns = ['what should i do', 'what should i focus', 'recommendations', 'advice', 'priorities', 'suggest', 'next steps', 'where should i start', 'what do you recommend'];

        foreach ($patterns as $pattern) {
            if (Str::contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesWhatIf(string $message): ?array
    {
        $patterns = ['what if', 'what would happen', 'scenario', 'if i', 'hypothetically'];

        $isWhatIf = false;
        foreach ($patterns as $pattern) {
            if (Str::contains($message, $pattern)) {
                $isWhatIf = true;
                break;
            }
        }

        if (! $isWhatIf) {
            return null;
        }

        // Try to detect which module the scenario relates to
        $module = $this->detectModule($message);

        return ['module' => $module, 'query' => $message];
    }

    private function matchesTax(string $message): ?array
    {
        foreach (self::TAX_KEYWORDS as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($message, $keyword)) {
                    return ['topic' => $topic];
                }
            }
        }

        // Generic "tax" without specific topic
        if (Str::contains($message, 'tax')) {
            return ['topic' => 'income_tax'];
        }

        return null;
    }

    private function matchesCreate(string $message): bool
    {
        $patterns = ['create a', 'add a', 'set up a', 'set up an', 'open an account', 'open a', 'new account', 'add an'];

        foreach ($patterns as $pattern) {
            if (Str::contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesModule(string $message): ?array
    {
        $module = $this->detectModule($message);

        if ($module) {
            return ['module' => $module];
        }

        return null;
    }

    private function matchesNetWorth(string $message): bool
    {
        $patterns = ['net worth', 'how much do i have', 'how much am i worth', 'total worth', 'balance', 'surplus', 'how much have i', 'what is my worth', 'financial position', 'financial summary', 'overview of my finances'];

        foreach ($patterns as $pattern) {
            if (Str::contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect which financial module a message relates to.
     */
    private function detectModule(string $message): ?string
    {
        foreach (self::MODULE_KEYWORDS as $module => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($message, $keyword)) {
                    return $module;
                }
            }
        }

        return null;
    }

    /**
     * Extract what the user wants to create from their message.
     */
    private function extractCreateAction(string $message): string
    {
        $entities = [
            'savings account' => 'savings account',
            'investment account' => 'investment account',
            'pension' => 'pension',
            'property' => 'property',
            'mortgage' => 'mortgage',
            'policy' => 'protection policy',
            'life insurance' => 'life insurance policy',
            'goal' => 'goal',
            'life event' => 'life event',
            'account' => 'account',
        ];

        foreach ($entities as $keyword => $label) {
            if (Str::contains($message, $keyword)) {
                return $label;
            }
        }

        return 'item';
    }
}
