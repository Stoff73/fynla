<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;
use App\Constants\TaxDefaults;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;

class AiSimulatedResponseBuilder
{
    use FormatsCurrency;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {}

    /**
     * Build a response for the given intent with real agent data.
     *
     * @return array{text: string, navigation: ?array}
     */
    public function build(string $intent, array $params, array $agentData, User $user): array
    {
        return match ($intent) {
            'greeting' => $this->buildGreeting($user),
            'help' => $this->buildHelp($user),
            'navigation' => $this->buildNavigation($params),
            'financial_plan' => $this->buildFinancialPlan($agentData, $user),
            'recommendations' => $this->buildRecommendations($agentData, $user),
            'what_if' => $this->buildWhatIf($params, $agentData),
            'tax_info' => $this->buildTaxInfo($params),
            'create_blocked' => $this->buildCreateBlocked($params),
            'module_analysis' => $this->buildModuleAnalysis($params, $agentData, $user),
            'net_worth' => $this->buildNetWorth($agentData, $user),
            default => $this->buildUnknown(),
        };
    }

    /**
     * Split text into chunks for simulated streaming.
     *
     * @return string[]
     */
    public function chunkForStreaming(string $text): array
    {
        // Split on sentence boundaries (., !, ?) followed by space or newline
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            // If a sentence is short enough, keep as one chunk
            if (mb_strlen($sentence) <= 80) {
                $chunks[] = $sentence.' ';

                continue;
            }

            // Split longer sentences on commas, colons, semicolons, or em-dashes
            $parts = preg_split('/(?<=[,;:\-])\s+/', $sentence, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                $chunks[] = trim($part).' ';
            }
        }

        // Handle markdown list items and headers that got merged
        $refined = [];
        foreach ($chunks as $chunk) {
            // Split on newlines to preserve markdown structure
            $lines = explode("\n", $chunk);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $refined[] = $line."\n";
                }
            }
        }

        return $refined ?: [$text];
    }

    private function buildGreeting(User $user): array
    {
        $firstName = $user->first_name ?? 'there';

        $text = "Hello, {$firstName}! I'm your financial planning assistant. I can help you with:\n\n"
            ."- **Analysing** your protection, savings, investments, pensions, estate, and goals\n"
            ."- **Navigating** to any section of your financial dashboard\n"
            ."- **Generating** a holistic financial plan with personalised recommendations\n"
            ."- **Exploring** tax allowances and rates for the current tax year\n"
            ."- **Running** what-if scenarios to see how changes affect your finances\n\n"
            .'What would you like to explore?';

        return ['text' => $text, 'navigation' => null];
    }

    private function buildHelp(User $user): array
    {
        $text = "Here's what I can help you with:\n\n"
            ."**Analysis & Insights**\n"
            ."- \"How is my protection?\" - Analyse any financial module\n"
            ."- \"What is my net worth?\" - View your overall financial position\n"
            ."- \"Generate a financial plan\" - Get a comprehensive holistic plan\n\n"
            ."**Recommendations**\n"
            ."- \"What should I focus on?\" - Get prioritised recommendations\n"
            ."- \"What if I increased my pension contributions?\" - Run scenarios\n\n"
            ."**Navigation**\n"
            ."- \"Take me to my investments\" - Navigate to any page\n"
            ."- \"Show me my pensions\" - Quick access to any module\n\n"
            ."**Tax Information**\n"
            ."- \"What is my ISA allowance?\" - Current tax year rates and limits\n"
            ."- \"Tell me about inheritance tax\" - Tax rules and thresholds\n\n"
            .'Just ask me anything about your finances!';

        return ['text' => $text, 'navigation' => null];
    }

    private function buildNavigation(array $params): array
    {
        $description = $params['description'] ?? 'the requested page';

        $text = "Taking you to {$description} now.";

        return [
            'text' => $text,
            'navigation' => [
                'route_path' => $params['route_path'],
                'description' => $description,
            ],
        ];
    }

    private function buildFinancialPlan(array $agentData, User $user): array
    {
        $firstName = $user->first_name ?? 'you';

        $text = "Here's your holistic financial plan, {$firstName}.\n\n";

        // Executive summary (nested object with overview, key_strengths, overall_score)
        $summary = $agentData['executive_summary'] ?? [];
        if (is_array($summary)) {
            if (isset($summary['overall_score'])) {
                $score = round((float) $summary['overall_score'], 1);
                $text .= "**Overall Financial Health Score: {$score}/100**\n\n";
            }
            if (isset($summary['overview'])) {
                $text .= "{$summary['overview']}\n\n";
            }
            if (! empty($summary['key_strengths'])) {
                $text .= "**Key Strengths**\n";
                foreach ($summary['key_strengths'] as $strength) {
                    $area = $strength['area'] ?? '';
                    $desc = $strength['description'] ?? '';
                    $text .= "- **{$area}:** {$desc}\n";
                }
                $text .= "\n";
            }
            if (! empty($summary['key_vulnerabilities'])) {
                $text .= "**Areas for Improvement**\n";
                foreach ($summary['key_vulnerabilities'] as $vuln) {
                    $area = $vuln['area'] ?? '';
                    $desc = $vuln['description'] ?? '';
                    $text .= "- **{$area}:** {$desc}\n";
                }
                $text .= "\n";
            }
            if (! empty($summary['top_priorities'])) {
                $text .= "**Top Priorities**\n";
                foreach (array_slice($summary['top_priorities'], 0, 5) as $i => $priority) {
                    $num = $i + 1;
                    $title = $priority['action'] ?? $priority['title'] ?? $priority['description'] ?? 'Priority';
                    $text .= "{$num}. {$title}\n";
                }
                $text .= "\n";
            }
        }

        // Top recommendations (fallback if summary didn't have priorities)
        $recommendations = $agentData['top_recommendations'] ?? [];
        if (! empty($recommendations) && empty($summary['top_priorities'] ?? [])) {
            $text .= "**Top Priorities**\n";
            foreach (array_slice($recommendations, 0, 5) as $i => $rec) {
                $num = $i + 1;
                $title = $rec['action'] ?? $rec['title'] ?? $rec['recommendation'] ?? $rec['description'] ?? 'Recommendation';
                $desc = $rec['rationale'] ?? $rec['description'] ?? $rec['reason'] ?? '';
                $text .= "{$num}. **{$title}**";
                if ($desc && $desc !== $title) {
                    $text .= " - {$desc}";
                }
                $text .= "\n";
            }
            $text .= "\n";
        }

        // Monthly surplus from suggested_allocation
        $allocation = $agentData['suggested_allocation'] ?? [];
        $surplus = $allocation['available_surplus'] ?? $agentData['monthly_surplus'] ?? 0;
        if ($surplus > 0) {
            $text .= "**Available Monthly Surplus:** {$this->formatCurrency((float) $surplus)}\n\n";
        }

        // Suggested allocation items
        $allocationItems = $allocation['allocation'] ?? [];
        if (! empty($allocationItems)) {
            $text .= "**Suggested Surplus Allocation**\n";
            foreach ($allocationItems as $item) {
                if (is_array($item)) {
                    $label = ucfirst(str_replace('_', ' ', $item['category'] ?? $item['name'] ?? 'Other'));
                    $amount = $item['amount'] ?? $item['value'] ?? 0;
                    $text .= "- {$label}: {$this->formatCurrency((float) $amount)}/month\n";
                }
            }
            $text .= "\n";
        }

        $text .= 'Would you like me to dive deeper into any of these areas?';

        return [
            'text' => $text,
            'navigation' => null,
        ];
    }

    private function buildRecommendations(array $agentData, User $user): array
    {
        $firstName = $user->first_name ?? 'you';

        $text = "Here are your prioritised recommendations, {$firstName}.\n\n";

        $recommendations = $agentData['recommendations'] ?? [];
        $surplus = $agentData['surplus'] ?? 0;

        if (! empty($recommendations)) {
            foreach (array_slice($recommendations, 0, 7) as $i => $rec) {
                $num = $i + 1;
                $title = $rec['action'] ?? $rec['title'] ?? $rec['recommendation'] ?? $rec['description'] ?? 'Recommendation';
                $category = $rec['category'] ?? '';
                $impact = $rec['impact'] ?? '';
                $rationale = $rec['rationale'] ?? $rec['description'] ?? $rec['reason'] ?? '';

                $text .= "**{$num}. {$title}**";
                if ($category) {
                    $text .= " _({$category})_";
                }
                if ($impact) {
                    $text .= " - {$impact} impact";
                }
                $text .= "\n";
                if ($rationale && $rationale !== $title) {
                    $text .= "   {$rationale}\n";
                }
                $text .= "\n";
            }
        } else {
            $text .= "Your finances are looking well-managed. No urgent recommendations at this time.\n\n";
        }

        if ($surplus > 0) {
            $formatted = $this->formatCurrency((float) $surplus);
            $text .= "**Monthly Surplus Available:** {$formatted}\n\n";
        }

        $text .= 'Would you like me to explain any of these in more detail, or run a scenario?';

        return ['text' => $text, 'navigation' => null];
    }

    private function buildWhatIf(array $params, array $agentData): array
    {
        $module = $params['module'] ?? null;

        if (! $module) {
            $text = "I can run what-if scenarios for your finances. Could you be a bit more specific? For example:\n\n"
                ."- \"What if I increased my pension contributions by 2%?\"\n"
                ."- \"What if I saved an extra 200 per month?\"\n"
                ."- \"What if my investment returns were 7% instead of 5%?\"\n"
                ."- \"What if I retired at 60 instead of 67?\"\n\n"
                .'Which area would you like to explore?';

            return ['text' => $text, 'navigation' => null];
        }

        $moduleName = ucfirst($module);
        $text = "Here are the scenario results for **{$moduleName}**.\n\n";

        if (isset($agentData['error'])) {
            $text = "I wasn't able to run that scenario right now. Could you try rephrasing your question? For example:\n\n"
                ."- \"What if I increased my pension contributions by 2%?\"\n"
                ."- \"What if I saved an extra 200 per month?\"\n";

            return ['text' => $text, 'navigation' => null];
        }

        // Format scenario data
        if (isset($agentData['scenarios'])) {
            foreach ($agentData['scenarios'] as $scenario) {
                $name = $scenario['name'] ?? 'Scenario';
                $text .= "**{$name}**\n";
                if (isset($scenario['description'])) {
                    $text .= "{$scenario['description']}\n";
                }
                if (isset($scenario['impact'])) {
                    $text .= "Impact: {$scenario['impact']}\n";
                }
                $text .= "\n";
            }
        } else {
            // Raw data output
            foreach ($agentData as $key => $value) {
                if (is_scalar($value)) {
                    $label = ucfirst(str_replace('_', ' ', $key));
                    $display = is_numeric($value) && $value > 100
                        ? $this->formatCurrency((float) $value)
                        : (string) $value;
                    $text .= "- **{$label}:** {$display}\n";
                }
            }
            $text .= "\n";
        }

        $text .= 'Would you like to explore a different scenario?';

        return ['text' => $text, 'navigation' => null];
    }

    private function buildTaxInfo(array $params): array
    {
        $topic = $params['topic'] ?? 'income_tax';
        $taxYear = $this->taxConfig->getTaxYear();

        $data = match ($topic) {
            'income_tax' => $this->taxConfig->getIncomeTax(),
            'capital_gains' => $this->taxConfig->getCapitalGainsTax(),
            'inheritance_tax' => $this->taxConfig->getInheritanceTax(),
            'isa_allowances' => $this->taxConfig->getISAAllowances(),
            'pension_allowances' => $this->taxConfig->getPensionAllowances(),
            default => $this->taxConfig->getIncomeTax(),
        };

        $topicLabel = match ($topic) {
            'income_tax' => 'Income Tax',
            'capital_gains' => 'Capital Gains Tax',
            'inheritance_tax' => 'Inheritance Tax',
            'isa_allowances' => 'ISA Allowances',
            'pension_allowances' => 'Pension Allowances',
            default => 'Tax Information',
        };

        $text = "Here are the current **{$topicLabel}** rates for the **{$taxYear}** tax year.\n\n";

        $text .= $this->formatTaxData($topic, $data);

        $text .= "\nWould you like to know about any other tax allowances or rates?";

        return ['text' => $text, 'navigation' => null];
    }

    private function formatTaxData(string $topic, array $data): string
    {
        $text = '';

        switch ($topic) {
            case 'income_tax':
                if (isset($data['personal_allowance'])) {
                    $text .= "- **Personal Allowance:** {$this->formatCurrency((float) $data['personal_allowance'])}\n";
                }
                if (isset($data['bands']) && is_array($data['bands'])) {
                    $text .= "\n**Tax Bands**\n";
                    foreach ($data['bands'] as $band) {
                        $name = $band['name'] ?? $band['band'] ?? 'Band';
                        $rate = ($band['rate'] ?? 0) * 100;
                        $text .= "- {$name}: {$rate}%";
                        if (isset($band['lower']) && isset($band['upper'])) {
                            $lower = $this->formatCurrency((float) $band['lower']);
                            $upper = $band['upper'] === null ? '+' : ' - '.$this->formatCurrency((float) $band['upper']);
                            $text .= " ({$lower}{$upper})";
                        }
                        $text .= "\n";
                    }
                }
                break;

            case 'capital_gains':
                if (isset($data['annual_exempt_amount'])) {
                    $text .= "- **Annual Exempt Amount:** {$this->formatCurrency((float) $data['annual_exempt_amount'])}\n";
                }
                if (isset($data['rates']) && is_array($data['rates'])) {
                    $text .= "\n**Rates**\n";
                    foreach ($data['rates'] as $key => $rate) {
                        $label = ucfirst(str_replace('_', ' ', $key));
                        if (is_array($rate)) {
                            foreach ($rate as $subKey => $subRate) {
                                $subLabel = ucfirst(str_replace('_', ' ', $subKey));
                                $pct = is_numeric($subRate) ? ($subRate * 100).'%' : (string) $subRate;
                                $text .= "- {$label} ({$subLabel}): {$pct}\n";
                            }
                        } else {
                            $pct = is_numeric($rate) ? ($rate * 100).'%' : (string) $rate;
                            $text .= "- {$label}: {$pct}\n";
                        }
                    }
                }
                break;

            case 'inheritance_tax':
                if (isset($data['nil_rate_band'])) {
                    $text .= "- **Nil Rate Band:** {$this->formatCurrency((float) $data['nil_rate_band'])}\n";
                }
                if (isset($data['residence_nil_rate_band'])) {
                    $text .= "- **Residence Nil Rate Band:** {$this->formatCurrency((float) $data['residence_nil_rate_band'])}\n";
                }
                if (isset($data['rate'])) {
                    $rate = $data['rate'] * 100;
                    $text .= "- **Standard Rate:** {$rate}%\n";
                }
                if (isset($data['taper_threshold'])) {
                    $text .= "- **Taper Threshold:** {$this->formatCurrency((float) $data['taper_threshold'])}\n";
                }
                $nrb = $data['nil_rate_band'] ?? TaxDefaults::NRB;
                $rnrb = $data['residence_nil_rate_band'] ?? TaxDefaults::RNRB;
                $combined = $nrb + $rnrb;
                $text .= "\nFor a married couple or civil partners, the combined allowance can be up to {$this->formatCurrency((float) ($combined * 2))}.\n";
                break;

            case 'isa_allowances':
                if (isset($data['annual_allowance'])) {
                    $text .= "- **Annual ISA Allowance:** {$this->formatCurrency((float) $data['annual_allowance'])}\n";
                }
                if (isset($data['lifetime_isa']['annual_allowance'])) {
                    $text .= "- **Lifetime ISA Limit:** {$this->formatCurrency((float) $data['lifetime_isa']['annual_allowance'])}/year\n";
                    if (isset($data['lifetime_isa']['government_bonus_rate'])) {
                        $bonus = $data['lifetime_isa']['government_bonus_rate'] * 100;
                        $text .= "  - Government bonus: {$bonus}% (up to {$this->formatCurrency((float) ($data['lifetime_isa']['annual_allowance'] * $data['lifetime_isa']['government_bonus_rate']))}/year)\n";
                    }
                }
                if (isset($data['junior_isa']['annual_allowance'])) {
                    $text .= "- **Junior ISA Limit:** {$this->formatCurrency((float) $data['junior_isa']['annual_allowance'])}/year\n";
                }
                $text .= "\nYou can split your ISA allowance across Cash ISA, Stocks & Shares ISA, Innovative Finance ISA, and Lifetime ISA.\n";
                break;

            case 'pension_allowances':
                if (isset($data['annual_allowance'])) {
                    $text .= "- **Annual Allowance:** {$this->formatCurrency((float) $data['annual_allowance'])}\n";
                }
                if (isset($data['money_purchase_annual_allowance'])) {
                    $text .= "- **Money Purchase Annual Allowance:** {$this->formatCurrency((float) $data['money_purchase_annual_allowance'])}\n";
                }
                if (isset($data['tapered_allowance']['threshold_income'])) {
                    $text .= "- **Tapered Allowance Threshold:** {$this->formatCurrency((float) $data['tapered_allowance']['threshold_income'])}\n";
                }
                if (isset($data['state_pension']['full_weekly_amount'])) {
                    $weekly = $this->formatCurrencyWithPence((float) $data['state_pension']['full_weekly_amount']);
                    $annual = $this->formatCurrency((float) ($data['state_pension']['full_weekly_amount'] * 52));
                    $text .= "- **Full New State Pension:** {$weekly}/week ({$annual}/year)\n";
                }
                break;

            default:
                foreach ($data as $key => $value) {
                    $label = ucfirst(str_replace('_', ' ', $key));
                    if (is_numeric($value) && $value > 100) {
                        $text .= "- **{$label}:** {$this->formatCurrency((float) $value)}\n";
                    } elseif (is_scalar($value)) {
                        $text .= "- **{$label}:** {$value}\n";
                    }
                }
        }

        return $text;
    }

    private function buildCreateBlocked(array $params): array
    {
        $action = $params['action'] ?? 'item';

        $text = "I appreciate you wanting to create a {$action}, but this isn't available in preview mode. "
            ."Preview mode lets you explore the app's features using sample data, but creating or modifying data is restricted.\n\n"
            ."To create and save your own financial data, you can **sign up for a free account**.\n\n"
            ."In the meantime, I can still help you:\n"
            ."- Analyse the existing sample data\n"
            ."- Show you how the {$action} feature works\n"
            ."- Navigate to the relevant section\n\n"
            .'What would you like to do instead?';

        return ['text' => $text, 'navigation' => null];
    }

    private function buildModuleAnalysis(array $params, array $agentData, User $user): array
    {
        $module = $params['module'] ?? 'protection';
        $moduleName = ucfirst($module);
        $firstName = $user->first_name ?? 'you';

        $text = "Here's your **{$moduleName}** analysis, {$firstName}.\n\n";

        // Extract metrics
        $metrics = $agentData['metrics'] ?? $agentData['data'] ?? $agentData;
        $recommendations = $agentData['recommendations'] ?? [];

        if (isset($agentData['error'])) {
            $text .= "I wasn't able to retrieve your {$module} data right now. This might mean you don't have any {$module} data set up yet. ";
            $text .= "Would you like me to take you to the {$moduleName} section to get started?";

            return ['text' => $text, 'navigation' => null];
        }

        // Module-specific formatting
        $text .= $this->formatModuleMetrics($module, $metrics);

        // Recommendations
        if (! empty($recommendations)) {
            $text .= "\n**Key Recommendations**\n";
            foreach (array_slice($recommendations, 0, 3) as $i => $rec) {
                $num = $i + 1;
                $title = $rec['action'] ?? $rec['title'] ?? $rec['recommendation'] ?? $rec['description'] ?? 'Recommendation';
                $rationale = $rec['rationale'] ?? $rec['reason'] ?? '';
                $text .= "{$num}. **{$title}**";
                if ($rationale) {
                    $text .= " - {$rationale}";
                }
                $text .= "\n";
            }
            $text .= "\n";
        }

        $text .= "Would you like me to go deeper into any aspect, or navigate you to the {$moduleName} section?";

        return ['text' => $text, 'navigation' => null];
    }

    private function formatModuleMetrics(string $module, array $metrics): string
    {
        $text = '';

        switch ($module) {
            case 'protection':
                if (isset($metrics['total_cover'])) {
                    $text .= "- **Total Life Cover:** {$this->formatCurrency((float) $metrics['total_cover'])}\n";
                }
                if (isset($metrics['coverage_gaps']) && is_array($metrics['coverage_gaps'])) {
                    $gapCount = count($metrics['coverage_gaps']);
                    $text .= "- **Coverage Gaps:** {$gapCount} identified\n";
                    foreach (array_slice($metrics['coverage_gaps'], 0, 3) as $gap) {
                        $name = $gap['type'] ?? $gap['name'] ?? 'Gap';
                        $text .= "  - {$name}";
                        if (isset($gap['shortfall'])) {
                            $text .= ": shortfall of {$this->formatCurrency((float) $gap['shortfall'])}";
                        }
                        $text .= "\n";
                    }
                }
                if (isset($metrics['risk_score'])) {
                    $text .= "- **Risk Score:** {$metrics['risk_score']}/100\n";
                }
                break;

            case 'savings':
                if (isset($metrics['total_savings']) || isset($metrics['total_value'])) {
                    $total = $metrics['total_savings'] ?? $metrics['total_value'] ?? 0;
                    $text .= "- **Total Savings:** {$this->formatCurrency((float) $total)}\n";
                }
                if (isset($metrics['total_accounts'])) {
                    $text .= "- **Accounts:** {$metrics['total_accounts']}\n";
                }
                if (isset($metrics['emergency_fund_months'])) {
                    $text .= "- **Emergency Fund:** {$metrics['emergency_fund_months']} months of expenses covered\n";
                }
                if (isset($metrics['monthly_expenditure'])) {
                    $text .= "- **Monthly Expenditure:** {$this->formatCurrency((float) $metrics['monthly_expenditure'])}\n";
                }
                if (isset($metrics['monthly_surplus'])) {
                    $text .= "- **Monthly Surplus:** {$this->formatCurrency((float) $metrics['monthly_surplus'])}\n";
                }
                break;

            case 'investment':
                if (isset($metrics['total_value']) || isset($metrics['total_investments'])) {
                    $total = $metrics['total_investments'] ?? $metrics['total_value'] ?? 0;
                    $text .= "- **Total Portfolio Value:** {$this->formatCurrency((float) $total)}\n";
                }
                if (isset($metrics['asset_allocation']) && is_array($metrics['asset_allocation'])) {
                    $text .= "- **Asset Allocation:**\n";
                    foreach ($metrics['asset_allocation'] as $asset => $pct) {
                        $label = ucfirst(str_replace('_', ' ', $asset));
                        $formatted = is_numeric($pct) ? round($pct * 100, 1).'%' : (string) $pct;
                        $text .= "  - {$label}: {$formatted}\n";
                    }
                }
                break;

            case 'retirement':
                if (isset($metrics['pension_projection'])) {
                    $text .= "- **Projected Pension Pot:** {$this->formatCurrency((float) $metrics['pension_projection'])}\n";
                }
                if (isset($metrics['retirement_income'])) {
                    $text .= "- **Estimated Retirement Income:** {$this->formatCurrency((float) $metrics['retirement_income'])}/year\n";
                }
                if (isset($metrics['target_income'])) {
                    $text .= "- **Target Retirement Income:** {$this->formatCurrency((float) $metrics['target_income'])}/year\n";
                }
                if (isset($metrics['shortfall'])) {
                    $shortfall = (float) $metrics['shortfall'];
                    if ($shortfall > 0) {
                        $text .= "- **Income Shortfall:** {$this->formatCurrency($shortfall)}/year\n";
                    } else {
                        $text .= "- **Status:** On track to meet your target income\n";
                    }
                }
                break;

            case 'estate':
                if (isset($metrics['net_worth'])) {
                    $text .= "- **Net Estate Value:** {$this->formatCurrency((float) $metrics['net_worth'])}\n";
                }
                if (isset($metrics['iht_liability'])) {
                    $iht = (float) $metrics['iht_liability'];
                    $text .= "- **Estimated Inheritance Tax Liability:** {$this->formatCurrency($iht)}\n";
                }
                break;

            case 'goals':
                if (isset($metrics['progress_percentage'])) {
                    $text .= "- **Overall Goal Progress:** {$metrics['progress_percentage']}%\n";
                }
                break;

            default:
                foreach ($metrics as $key => $value) {
                    if (is_scalar($value) && ! in_array($key, ['module', 'error', 'success', 'message', 'timestamp'])) {
                        $label = ucfirst(str_replace('_', ' ', $key));
                        $display = is_numeric($value) && abs((float) $value) > 100
                            ? $this->formatCurrency((float) $value)
                            : (string) $value;
                        $text .= "- **{$label}:** {$display}\n";
                    }
                }
        }

        return $text;
    }

    private function buildNetWorth(array $agentData, User $user): array
    {
        $firstName = $user->first_name ?? 'you';

        $text = "Here's your financial overview, {$firstName}.\n\n";

        $metrics = $agentData['metrics'] ?? $agentData['summary'] ?? $agentData;

        if (isset($metrics['net_worth'])) {
            $text .= "**Net Worth: {$this->formatCurrency((float) $metrics['net_worth'])}**\n\n";
        }

        // Assets and liabilities summary
        if (isset($metrics['total_assets']) || isset($agentData['total_assets'])) {
            $assets = $metrics['total_assets'] ?? $agentData['total_assets'] ?? 0;
            $liabilities = $metrics['total_liabilities'] ?? $agentData['total_liabilities'] ?? 0;
            $text .= "- **Total Assets:** {$this->formatCurrency((float) $assets)}\n";
            $text .= "- **Total Liabilities:** {$this->formatCurrency((float) $liabilities)}\n\n";
        }

        // Break down key figures (cashflow_surplus preferred over monthly_surplus)
        $breakdownFields = [
            'total_savings' => 'Total Savings',
            'total_investments' => 'Total Investments',
            'pension_projection' => 'Pension Projection',
            'cashflow_surplus' => 'Monthly Surplus',
            'emergency_fund_months' => 'Emergency Fund',
        ];

        $hasBreakdown = false;
        foreach ($breakdownFields as $field => $label) {
            if (isset($metrics[$field])) {
                if (! $hasBreakdown) {
                    $text .= "**Breakdown**\n";
                    $hasBreakdown = true;
                }

                if ($field === 'emergency_fund_months') {
                    $text .= "- {$label}: {$metrics[$field]} months\n";
                } else {
                    $text .= "- {$label}: {$this->formatCurrency((float) $metrics[$field])}\n";
                }
            }
        }

        if ($hasBreakdown) {
            $text .= "\n";
        }

        // Recommendations
        $recommendations = $agentData['recommendations'] ?? $agentData['ranked_recommendations'] ?? [];
        if (! empty($recommendations)) {
            $text .= "**Top Priorities**\n";
            foreach (array_slice($recommendations, 0, 3) as $i => $rec) {
                $num = $i + 1;
                $title = $rec['action'] ?? $rec['title'] ?? $rec['recommendation'] ?? $rec['description'] ?? 'Recommendation';
                $text .= "{$num}. {$title}\n";
            }
            $text .= "\n";
        }

        // Show surplus if available
        $surplus = $agentData['available_surplus'] ?? 0;
        if ($surplus > 0) {
            $text .= "**Monthly Surplus:** {$this->formatCurrency((float) $surplus)}\n\n";
        }

        $text .= 'Would you like me to dive into a specific area?';

        return ['text' => $text, 'navigation' => null];
    }

    private function buildUnknown(): array
    {
        $text = "I'm not quite sure what you're asking about. Here are some things I can help with:\n\n"
            ."- **\"How is my protection?\"** - Analyse any module\n"
            ."- **\"What is my net worth?\"** - Financial overview\n"
            ."- **\"Take me to my savings\"** - Navigate to any page\n"
            ."- **\"What should I focus on?\"** - Get recommendations\n"
            ."- **\"Generate a financial plan\"** - Holistic plan\n"
            ."- **\"What is my ISA allowance?\"** - Tax information\n\n"
            .'Could you rephrase your question?';

        return ['text' => $text, 'navigation' => null];
    }
}
