<?php

declare(strict_types=1);

namespace App\Traits;

use Anthropic\Client as AnthropicClient;
use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawContentBlockStartEvent;
use Anthropic\Messages\RawContentBlockStopEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ToolUseBlock;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Property;
use App\Models\User;
use App\Constants\TaxDefaults;
use App\Services\PrerequisiteGateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Provides AI chat capabilities: streaming completion, prompt building,
 * tool call loop, and message persistence.
 *
 * Expects the using class to have:
 * - HasAiGuardrails trait
 * - $this->anthropicClient (AnthropicClient)
 * - $this->prerequisiteGate (PrerequisiteGateService)
 * - $this->taxConfig (TaxConfigService)
 * - Module agent properties (protectionAgent, savingsAgent, etc.)
 * - $this->toolDefinitions (AiToolDefinitions)
 */
trait HasAiChat
{
    private const MAX_TOOL_CALLS_PER_TURN = 5;

    private const MAX_HISTORY_MESSAGES = 20;

    /**
     * Send a message and yield SSE chunks.
     *
     * @return \Generator yields SSE event arrays
     */
    public function chat(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null
    ): \Generator {
        // Save user message
        $userMessage = $this->saveMessage($conversation, 'user', $message);

        // Check token budget
        if (! $this->hasTokenBudget($user)) {
            yield ['type' => 'error', 'message' => "You've reached your daily message limit. Your allowance resets tomorrow."];

            return;
        }

        // Build context
        $systemPrompt = $this->buildSystemPrompt($user, $currentRoute);
        $messageHistory = $this->buildMessageHistory($conversation);

        // Model selection
        $complexity = $this->classifyComplexity($message, $conversation->message_count);
        $model = $this->getAiModel($user, $complexity);
        $maxTokens = $this->getAiMaxTokens($user);
        $tools = $this->toolDefinitions->getTools($user->is_preview_user);

        // Auto-generate title from first message
        if ($conversation->message_count === 0) {
            $title = $this->generateTitle($message);
            $conversation->update(['title' => $title]);
            yield ['type' => 'title', 'title' => $title];
        }

        // API call loop — handles tool calls and text responses
        $fullResponse = '';
        $toolCallCount = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $toolCallsSummary = [];

        $messages = $messageHistory;

        while (true) {
            $currentTextBlock = '';
            $currentToolUseBlock = null;
            $accumulatedToolJson = '';
            $contentBlocks = [];
            $toolUseBlocks = [];
            $stopReason = 'end_turn';
            $streamError = null;

            try {
                $stream = $this->anthropicClient->messages->createStream(
                    maxTokens: $maxTokens,
                    messages: $messages,
                    model: $model,
                    system: [
                        [
                            'type' => 'text',
                            'text' => $systemPrompt,
                            'cache_control' => ['type' => 'ephemeral'],
                        ],
                    ],
                    tools: ! empty($tools) ? $tools : null,
                    toolChoice: ! empty($tools) ? ['type' => 'auto'] : null,
                );

                foreach ($stream as $event) {
                    if ($event instanceof RawMessageStartEvent) {
                        $totalInputTokens += $event->message->usage->inputTokens ?? 0;
                    } elseif ($event instanceof RawContentBlockStartEvent) {
                        if ($event->contentBlock instanceof TextBlock) {
                            $currentTextBlock = '';
                        } elseif ($event->contentBlock instanceof ToolUseBlock) {
                            $currentToolUseBlock = [
                                'type' => 'tool_use',
                                'id' => $event->contentBlock->id,
                                'name' => $event->contentBlock->name,
                                'input' => [],
                            ];
                            $accumulatedToolJson = '';
                        }
                    } elseif ($event instanceof RawContentBlockDeltaEvent) {
                        if ($event->delta instanceof TextDelta) {
                            $text = $event->delta->text ?? '';
                            if ($text !== '') {
                                $currentTextBlock .= $text;
                                $fullResponse .= $text;
                                yield ['type' => 'content', 'text' => $text];
                            }
                        } elseif ($event->delta instanceof InputJSONDelta) {
                            $accumulatedToolJson .= $event->delta->partialJSON ?? '';
                        }
                    } elseif ($event instanceof RawContentBlockStopEvent) {
                        if ($currentToolUseBlock !== null) {
                            if ($accumulatedToolJson !== '') {
                                $parsed = json_decode($accumulatedToolJson, true);
                                $currentToolUseBlock['input'] = is_array($parsed) ? $parsed : [];
                            }
                            $contentBlocks[] = $currentToolUseBlock;
                            $toolUseBlocks[] = $currentToolUseBlock;
                            $currentToolUseBlock = null;
                            $accumulatedToolJson = '';
                        } elseif ($currentTextBlock !== '') {
                            $contentBlocks[] = [
                                'type' => 'text',
                                'text' => $currentTextBlock,
                            ];
                            $currentTextBlock = '';
                        }
                    } elseif ($event instanceof RawMessageDeltaEvent) {
                        $stopReason = $event->delta->stopReason ?? $stopReason;
                        $totalOutputTokens += $event->usage->outputTokens ?? 0;
                    }
                }
            } catch (\Exception $e) {
                Log::error('[CoordinatingAgent] Anthropic API streaming failed', [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                $hint = $this->categoriseApiError($e->getMessage(), null, null);
                yield ['type' => 'error', 'message' => $hint];

                return;
            }

            // Handle tool calls
            $hasToolCalls = ! empty($toolUseBlocks);

            if ($hasToolCalls) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $contentBlocks,
                ];

                $toolResultBlocks = [];

                foreach ($toolUseBlocks as $toolUseBlock) {
                    $toolCallCount++;
                    $functionName = $toolUseBlock['name'];
                    $functionArgs = $toolUseBlock['input'] ?? [];

                    yield [
                        'type' => 'tool_use',
                        'tool' => $functionName,
                        'status' => 'running',
                    ];

                    // Execute the tool with prerequisite gate enforcement
                    $toolResult = $this->executeTool($functionName, $functionArgs, $user);

                    // Handle navigation results
                    if (isset($toolResult['action']) && $toolResult['action'] === 'navigate') {
                        yield [
                            'type' => 'navigation',
                            'route_path' => $toolResult['route_path'],
                            'description' => $toolResult['description'] ?? '',
                        ];
                    }

                    // Handle entity creation results
                    if (isset($toolResult['created']) && $toolResult['created'] === true) {
                        yield [
                            'type' => 'entity_created',
                            'entity_type' => $toolResult['entity_type'],
                            'entity_id' => $toolResult['entity_id'],
                            'name' => $toolResult['name'] ?? '',
                        ];
                    }

                    $toolCallsSummary[] = [
                        'tool' => $functionName,
                        'input' => $this->summariseToolInput($functionArgs),
                        'result_summary' => $this->summariseToolResult($toolResult),
                    ];

                    $toolResultBlocks[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $toolUseBlock['id'],
                        'content' => json_encode($toolResult),
                    ];

                    yield [
                        'type' => 'tool_use',
                        'tool' => $functionName,
                        'status' => 'complete',
                    ];
                }

                $messages[] = [
                    'role' => 'user',
                    'content' => $toolResultBlocks,
                ];
            }

            if ($hasToolCalls && $stopReason === 'tool_use' && $toolCallCount < self::MAX_TOOL_CALLS_PER_TURN) {
                continue;
            }

            break;
        }

        // Build metadata with tool call summary
        $messageMetadata = [];
        if (! empty($toolCallsSummary)) {
            $messageMetadata['tool_calls'] = $toolCallsSummary;
        }

        // Save assistant message
        $assistantMessage = $this->saveMessage($conversation, 'assistant', $fullResponse, array_merge([
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
            'model_used' => $model,
        ], ! empty($messageMetadata) ? ['metadata' => $messageMetadata] : []));

        // Update conversation token usage
        $conversation->incrementTokenUsage($totalInputTokens, $totalOutputTokens);
        $conversation->update(['model_used' => $model]);

        // Invalidate daily usage cache
        $this->invalidateDailyUsageCache($user);

        yield [
            'type' => 'done',
            'message_id' => $assistantMessage->id,
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }

    // ─── Prompt Building ─────────────────────────────────────────────

    /**
     * Build the complete system prompt for the AI assistant.
     */
    protected function buildSystemPrompt(User $user, ?string $currentRoute = null): string
    {
        $profile = $this->buildUserProfile($user);
        $financialContext = $this->buildFinancialContext($user);
        $prerequisiteState = $this->buildPrerequisiteStateContext($user);
        $moduleContext = $this->getModuleContext($currentRoute);
        $isPreview = $user->is_preview_user;

        $nameParts = explode(' ', $user->name);
        $firstName = $nameParts[0] ?? 'there';

        $prompt = <<<PROMPT
<identity>
You are Fynla Assistant, a professional financial planning assistant built into the Fynla application. You help users understand and improve their financial position using their actual, real data held in the application. You are not a generic chatbot — you have access to this user's specific financial data and you use it in every response.
</identity>

<instructions>
- Always use British English spelling and vocabulary (e.g. "personalised", "optimise", "analyse", "whilst", "behaviour")
- Never use acronyms in your responses — always spell them out in full. Write "Inheritance Tax" not "IHT", "Individual Savings Account" not "ISA", "Defined Contribution" not "DC", "Defined Benefit" not "DB", "Annual Allowance" not "AA", "Money Purchase Annual Allowance" not "MPAA". The only permitted abbreviation is "ISA" itself, which may remain abbreviated.
- Format all currency values in GBP with commas and two decimal places (e.g. £1,250.00). For large round numbers you may abbreviate (e.g. £250,000)
- When discussing the user's data, always reference their specific numbers — never speak in generalities when you have real figures available
- If you do not have sufficient data to answer a question accurately, say so honestly and explain what data would help
- Never speculate about data you do not have. If a module shows no data, say that rather than guessing
</instructions>

<regulatory_compliance>
1. Hedging language is mandatory. Frame all guidance as "you may want to consider", "it could be worth exploring", "one option might be", or "it is worth discussing with a regulated adviser". Never use directive language such as "you should", "you must", or "I recommend you do X".
2. No product recommendations. Never name specific financial products, providers, funds, or platforms. You can describe product types (e.g. "a Stocks and Shares Individual Savings Account") but never recommend a specific provider or product.
3. Signpost regulated advice. Whenever a question touches on complex tax planning, specific investment decisions, pension transfers, protection underwriting, or estate planning structures, acknowledge the limits of the application and suggest the user speaks with a regulated financial adviser or specialist solicitor.
4. Risk warnings. When discussing investments or pensions, include an appropriate caveat that the value of investments can go down as well as up, and past performance is not a reliable indicator of future results.
5. Tax caveats. Tax rules are based on current UK legislation and the 2025/26 tax year. Tax treatment depends on individual circumstances and may change. Always caveat tax-related guidance accordingly.
6. No market timing. Never suggest that now is a good or bad time to invest, buy, or sell based on market conditions.
7. Tax data accuracy. NEVER state tax rates, thresholds, allowances, or financial product details from memory. ALWAYS use the get_tax_information tool to retrieve current values from the centralised tax configuration before quoting any figures. This applies to income tax bands, National Insurance rates, Capital Gains Tax rates, Inheritance Tax thresholds, ISA allowances, pension limits, Stamp Duty Land Tax bands, benefits rates, and all investment product tax treatment (Individual Savings Accounts, General Investment Accounts, onshore/offshore bonds, Venture Capital Trusts, Enterprise Investment Schemes, Seed Enterprise Investment Schemes).
</regulatory_compliance>

<user_profile>
{$profile}
</user_profile>

<financial_context>
{$financialContext}
</financial_context>

<data_completeness>
The following shows which modules have sufficient data for analysis:
{$prerequisiteState}

CRITICAL RULES FOR BLOCKED MODULES:
1. When a user asks about a BLOCKED module, you MUST respond with a clear, friendly explanation of what specific data is missing and why it is needed for that analysis.
2. Do NOT attempt to give advice, estimates, or general guidance on blocked modules. You do not have the data to do so accurately.
3. List each missing item as a bullet point so the user can see exactly what to add.
4. ALWAYS use the navigate_to_page tool to take the user to the correct page where they can add the missing information. This is mandatory — never just tell the user to go somewhere without navigating them.
5. End with an encouraging note that once the data is added, you will be able to provide a full analysis.

If a tool call returns a "blocked" result, follow the instruction field in that result — explain the missing data to the user and navigate them to the right page.
</data_completeness>
PROMPT;

        if ($moduleContext) {
            $prompt .= "\n\n<current_context>\n{$moduleContext}\n</current_context>";
        }

        $prompt .= <<<'SCOPE'


<scope>
You are a personal financial planning assistant. You only discuss topics directly related to the user's personal financial planning: budgeting, savings, investments, pensions, protection, estate planning, tax planning, goals, and financial wellbeing.

If a user asks about something outside this scope — such as general knowledge questions, news, cooking, travel, technology, or any non-financial topic — politely explain that you are only able to help with their personal financial planning, and offer to redirect them to something useful within the application.
</scope>
SCOPE;

        $prompt .= <<<RESPONSE_FORMAT


<response_format>
- Keep responses concise and focused. Avoid long preambles — get to the point quickly
- Use **bold** for key figures, amounts, and important terms
- Use numbered lists when presenting a sequence of recommendations or steps
- Use bullet points for summaries, comparisons, or multiple related items
- Always end your response with a natural follow-up question to continue the conversation
- Never start a response with "Certainly!", "Of course!", "Great question!", "Absolutely!" or similar filler phrases
- When referencing the user informally, you may occasionally use their first name ({$firstName}) to make the conversation feel personal — but do not overdo it
</response_format>
RESPONSE_FORMAT;

        $prompt .= <<<'PERSONALITY'


<personality>
- Warm, encouraging, and clear — like a knowledgeable friend who understands financial planning deeply
- Celebrate progress: when the user has done something well, acknowledge it genuinely before discussing gaps
- Be honest about gaps or risks without being alarming. Frame challenges as opportunities
- Use plain language and avoid jargon. When a technical term is necessary, explain it briefly
- Be empathetic to the emotional weight of financial decisions
- Never be condescending or make the user feel bad about their financial position
</personality>
PERSONALITY;

        $prompt .= <<<'AVAILABLE_ACTIONS'


<available_actions>
Use your tools proactively to serve the user — do not wait to be asked to look something up or navigate somewhere.

- Navigate the user to a relevant page when the conversation naturally leads there
- Fetch detailed module analysis when the user asks about a specific financial area
- Run what-if scenarios when the user wants to understand the impact of a change
- Look up current UK tax information when needed
- Generate a holistic financial plan when the user wants a comprehensive overview
</available_actions>
AVAILABLE_ACTIONS;

        if ($isPreview) {
            $prompt .= <<<'PREVIEW_MODE'


<preview_mode>
This user is exploring Fynla in preview mode using a demonstration persona. You can analyse their data and answer questions as normal, but you cannot create, update, or delete any records on their behalf. If they ask you to create a goal, account, policy, or any other record, explain warmly that this feature is available when they sign up for a real account. You may still run analysis, answer questions, and navigate them around the application.
</preview_mode>
PREVIEW_MODE;
        }

        if (! $isPreview) {
            $prompt .= <<<'DATA_CREATION_GUIDANCE'


<data_creation_guidance>
When the user tells you about a financial product they hold, create it immediately using the appropriate tool — do not simply acknowledge what they said.

- Individual Savings Accounts must always have ownership_type set to "individual" — UK legal requirement
- Default ownership to "individual" unless the user specifically mentions joint ownership
- Set sensible defaults for any fields the user does not mention
- After creating a record, briefly confirm what was created then suggest the natural next step
- If the user mentions a property with a mortgage, use the create_property tool with the outstanding_mortgage field
- If the user mentions a pension without specifying the type, ask: "Is this a workplace pension where your employer contributes, or a personal pension you manage yourself?"
</data_creation_guidance>
DATA_CREATION_GUIDANCE;
        }

        return $prompt;
    }

    /**
     * Build user profile section.
     */
    private function buildUserProfile(User $user): string
    {
        $lines = [];
        $lines[] = "- Name: {$user->name}";

        if ($user->date_of_birth) {
            $lines[] = "- Age: {$user->date_of_birth->age}";
        }

        if ($user->employment_status) {
            $lines[] = "- Employment: {$user->employment_status}";
        }

        if ($user->marital_status) {
            $lines[] = "- Marital status: {$user->marital_status}";
        }

        $totalIncome = $this->calculateTotalUserIncome($user);
        if ($totalIncome > 0) {
            $formatted = number_format($totalIncome, 2);
            $lines[] = "- Total annual income: £{$formatted}";

            $taxBand = $this->estimateTaxBand($totalIncome);
            $lines[] = "- Estimated income tax band: {$taxBand}";
        }

        $totalExpenditure = $this->calculateTotalExpenditure($user);
        if ($totalExpenditure > 0) {
            $formatted = number_format($totalExpenditure, 2);
            $lines[] = "- Monthly expenditure: £{$formatted}";
        }

        if ($user->retirement_date) {
            $lines[] = "- Target retirement date: {$user->retirement_date->format('j F Y')}";
        } elseif ($user->target_retirement_age) {
            $lines[] = "- Target retirement age: {$user->target_retirement_age}";
        } elseif ($user->retirementProfile && $user->retirementProfile->target_retirement_age) {
            $lines[] = "- Target retirement age: {$user->retirementProfile->target_retirement_age}";
        }

        $children = $user->familyMembers()->where('relationship', 'child')->count();
        if ($children > 0) {
            $lines[] = "- Children: {$children}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build financial context using FULL orchestrateAnalysis() output.
     * Includes all module metrics, ranked recommendations with decision traces,
     * cashflow allocation, shortfall analysis, conflicts, and cross-module strategies.
     */
    private function buildFinancialContext(User $user): string
    {
        return Cache::remember("ai_financial_context_{$user->id}", 120, function () use ($user) {
            try {
                $analysis = $this->orchestrateAnalysis($user->id);
            } catch (\Exception $e) {
                Log::warning('[CoordinatingAgent] Failed to build financial context', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return 'Financial context unavailable — analysis could not be completed.';
            }

            $lines = [];
            $modules = $analysis['module_analysis'] ?? [];

            // Available surplus
            $surplus = $analysis['available_surplus'] ?? 0;
            if ($surplus !== 0) {
                $formatted = number_format(abs($surplus), 2);
                $label = $surplus >= 0 ? 'Monthly surplus' : 'Monthly shortfall';
                $lines[] = "- {$label}: £{$formatted}";
            }

            // Savings
            if (isset($modules['savings'])) {
                $s = $modules['savings'];
                if (($s['total_savings'] ?? 0) > 0) {
                    $lines[] = '- Total savings: £'.number_format($s['total_savings'], 2);
                }
                if (isset($s['emergency_fund_months'])) {
                    $lines[] = "- Emergency fund: {$s['emergency_fund_months']} months of cover";
                }
            }

            // Investments
            if (isset($modules['investment'])) {
                $inv = $modules['investment'];
                if (($inv['total_portfolio_value'] ?? 0) > 0) {
                    $lines[] = '- Investment portfolio: £'.number_format($inv['total_portfolio_value'], 0);
                }
            }

            // Retirement
            if (isset($modules['retirement'])) {
                $ret = $modules['retirement'];
                if (($ret['total_pension_value'] ?? 0) > 0) {
                    $lines[] = '- Total pension value: £'.number_format($ret['total_pension_value'], 0);
                }
                if (($ret['projected_annual_income'] ?? 0) > 0) {
                    $lines[] = '- Projected retirement income: £'.number_format($ret['projected_annual_income'], 0).' per year';
                }
                if (($ret['income_gap'] ?? 0) > 0) {
                    $lines[] = '- Retirement income gap: £'.number_format($ret['income_gap'], 0).' per year';
                }
            }

            // Protection
            if (isset($modules['protection'])) {
                $prot = $modules['protection'];
                if (($prot['full_analysis']['total_cover'] ?? 0) > 0) {
                    $lines[] = '- Total life cover: £'.number_format($prot['full_analysis']['total_cover'], 0);
                }
                if (($prot['coverage_gap'] ?? 0) > 0) {
                    $lines[] = '- Coverage gap: £'.number_format($prot['coverage_gap'], 0);
                }
            }

            // Property
            $ownsProperty = Property::where('user_id', $user->id)->exists();
            $lines[] = '- Property owner: '.($ownsProperty ? 'Yes' : 'No');

            // Estate
            if (isset($modules['estate'])) {
                $est = $modules['estate'];
                if (($est['iht_liability'] ?? 0) > 0) {
                    $lines[] = '- Estimated Inheritance Tax liability: £'.number_format($est['iht_liability'], 0);
                }
                if (($est['net_worth'] ?? 0) > 0) {
                    $lines[] = '- Net estate value: £'.number_format($est['net_worth'], 0);
                }
            }

            // Ranked recommendations with decision traces
            $recommendations = $analysis['ranked_recommendations'] ?? [];
            if (! empty($recommendations)) {
                $top = array_slice($recommendations, 0, 5);
                $lines[] = '';
                $lines[] = 'Top ranked recommendations:';
                foreach ($top as $i => $rec) {
                    $title = $rec['title'] ?? $rec['recommendation'] ?? 'Recommendation';
                    $urgency = isset($rec['urgency_score']) ? " (urgency: {$rec['urgency_score']}/100)" : '';
                    $module = isset($rec['module']) ? " [{$rec['module']}]" : '';
                    $num = $i + 1;
                    $lines[] = "{$num}. {$title}{$module}{$urgency}";

                    // Include decision trace if available
                    if (isset($rec['decision_trace'])) {
                        $trace = $rec['decision_trace'];
                        $trigger = $trace['trigger'] ?? $trace['definition_key'] ?? null;
                        if ($trigger) {
                            $lines[] = "   Triggered by: {$trigger}";
                        }
                    }
                }
            }

            // Cashflow allocation
            $cashflow = $analysis['cashflow_allocation'] ?? [];
            if (! empty($cashflow) && isset($cashflow['total_demand'])) {
                $lines[] = '';
                $totalDemand = number_format($cashflow['total_demand'], 2);
                $lines[] = "Cashflow: Total monthly demand £{$totalDemand} vs surplus £".number_format(abs($surplus), 2);
            }

            // Shortfall analysis
            $shortfall = $analysis['shortfall_analysis'] ?? [];
            if ($shortfall['has_shortfall'] ?? false) {
                $lines[] = 'Cashflow shortfall detected — not all recommendations can be fully funded';
            }

            // Conflicts
            $conflicts = $analysis['conflicts'] ?? [];
            if (! empty($conflicts)) {
                $lines[] = '';
                $lines[] = 'Active conflicts:';
                foreach (array_slice($conflicts, 0, 3) as $conflict) {
                    $type = $conflict['type'] ?? 'Unknown';
                    $lines[] = "- {$type}";
                }
            }

            // Cross-module strategies
            $strategies = $analysis['cross_module_strategies'] ?? [];
            if (! empty($strategies)) {
                $lines[] = '';
                $lines[] = 'Cross-module strategies:';
                foreach (array_slice($strategies, 0, 3) as $strategy) {
                    $title = $strategy['title'] ?? $strategy['strategy'] ?? '';
                    if ($title) {
                        $lines[] = "- {$title}";
                    }
                }
            }

            return ! empty($lines) ? implode("\n", $lines) : 'No financial data recorded yet.';
        });
    }

    /**
     * Build prerequisite state context for the AI prompt.
     */
    private function buildPrerequisiteStateContext(User $user): string
    {
        return $this->prerequisiteGate->buildCompletenessContext($user);
    }

    /**
     * Get context about the current page/module the user is viewing.
     */
    private function getModuleContext(?string $currentRoute): ?string
    {
        if (! $currentRoute) {
            return null;
        }

        $contexts = [
            '/dashboard' => 'The user is on their Dashboard — the main overview of their financial position.',
            '/profile' => 'The user is viewing their User Profile — personal details, date of birth, marital status, retirement date, employment status.',
            '/net-worth/wealth-summary' => 'The user is viewing their Net Worth summary across all asset categories.',
            '/net-worth/property' => 'The user is viewing their property portfolio, including property values, equity positions, and mortgage balances.',
            '/net-worth/investments' => 'The user is viewing their investment accounts — including Stocks and Shares ISAs and general investment accounts.',
            '/net-worth/retirement' => 'The user is viewing their pension holdings — Defined Contribution, Defined Benefit, and State Pension.',
            '/net-worth/cash' => 'The user is viewing their cash and savings accounts.',
            '/net-worth/chattels' => 'The user is viewing their valuable possessions (chattels).',
            '/net-worth/business' => 'The user is viewing their business interests.',
            '/net-worth/liabilities' => 'The user is viewing their liabilities and debts.',
            '/valuable-info?section=income' => 'The user is viewing their Income section — employment income, self-employment, rental, dividends, interest, and other income sources.',
            '/valuable-info?section=expenditure' => 'The user is viewing their Expenditure section — monthly and annual spending breakdown.',
            '/valuable-info?section=letter' => 'The user is viewing their Expression of Wishes — a letter to their spouse or family.',
            '/protection' => 'The user is on the Protection module — covering life insurance, income protection, and critical illness cover.',
            '/estate' => 'The user is on the Estate Planning module — covering Inheritance Tax, wills, trusts, gifting strategies, and Lasting Powers of Attorney.',
            '/estate/will-builder' => 'The user is viewing the Will Builder — creating or editing their will.',
            '/estate/power-of-attorney' => 'The user is viewing Lasting Powers of Attorney.',
            '/goals' => 'The user is on the Goals and Life Events module — tracking financial goals and planned life events.',
            '/holistic-plan' => 'The user is viewing their Holistic Financial Plan — a comprehensive cross-module summary.',
            '/trusts' => 'The user is viewing their Trusts within the Estate Planning module.',
            '/risk-profile' => 'The user is viewing their Risk Profile — their assessed attitude to investment risk.',
            '/plans' => 'The user is viewing their Financial Plans dashboard.',
            '/actions' => 'The user is viewing their Actions dashboard — recommended next steps.',
            '/planning/what-if' => 'The user is viewing What-If Scenarios — exploring how changes affect their financial position.',
        ];

        return $contexts[$currentRoute] ?? null;
    }

    // ─── Message Persistence ─────────────────────────────────────────

    /**
     * Save a message to the database.
     */
    private function saveMessage(
        AiConversation $conversation,
        string $role,
        string $content,
        array $extra = []
    ): AiMessage {
        return $conversation->messages()->create(array_merge([
            'role' => $role,
            'content' => $content,
        ], $extra));
    }

    /**
     * Build message history from conversation.
     */
    private function buildMessageHistory(AiConversation $conversation): array
    {
        $dbMessages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        $messages = [];

        foreach ($dbMessages as $msg) {
            $content = $msg->content;

            if ($msg->role === 'assistant' && ! empty($msg->metadata['tool_calls'])) {
                $toolContext = $this->buildToolCallContext($msg->metadata['tool_calls']);
                if ($toolContext !== '') {
                    $content .= "\n\n".$toolContext;
                }
            }

            $messages[] = [
                'role' => $msg->role,
                'content' => $content,
            ];
        }

        return $messages;
    }

    /**
     * Generate a short conversation title from the first message.
     */
    private function generateTitle(string $message): string
    {
        $title = mb_substr(trim($message), 0, 80);

        if (mb_strlen($message) > 80) {
            $title .= '...';
        }

        return $title;
    }

    /**
     * Build context from tool call metadata.
     */
    private function buildToolCallContext(array $toolCalls): string
    {
        if (empty($toolCalls)) {
            return '';
        }

        $parts = [];
        foreach ($toolCalls as $call) {
            $tool = $call['tool'] ?? 'unknown';
            $summary = $call['result_summary'] ?? '';
            $parts[] = "- {$tool}: {$summary}";
        }

        return "[Context: This response used the following data lookups]\n".implode("\n", $parts);
    }

    /**
     * Summarise tool input.
     */
    private function summariseToolInput(array $input): array
    {
        if (empty($input)) {
            return [];
        }

        $summary = [];
        $count = 0;

        foreach ($input as $key => $value) {
            if ($count >= 5) {
                break;
            }

            if (is_string($value)) {
                $summary[$key] = mb_strlen($value) > 80 ? mb_substr($value, 0, 80).'...' : $value;
            } elseif (is_numeric($value) || is_bool($value)) {
                $summary[$key] = $value;
            } elseif (is_array($value)) {
                $summary[$key] = '[array: '.count($value).' items]';
            } else {
                $summary[$key] = (string) $value;
            }

            $count++;
        }

        return $summary;
    }

    /**
     * Summarise a tool result.
     */
    private function summariseToolResult(array $result): string
    {
        if (empty($result)) {
            return 'empty result';
        }

        $parts = [];
        $count = 0;

        foreach ($result as $key => $value) {
            if ($count >= 5) {
                break;
            }

            if (is_string($value)) {
                $truncated = mb_strlen($value) > 60 ? mb_substr($value, 0, 60).'...' : $value;
                $parts[] = "{$key}: {$truncated}";
            } elseif (is_numeric($value)) {
                $parts[] = "{$key}: {$value}";
            } elseif (is_bool($value)) {
                $parts[] = "{$key}: ".($value ? 'true' : 'false');
            } elseif (is_array($value)) {
                $parts[] = "{$key}: [".count($value).' items]';
            }

            $count++;
        }

        return implode(', ', $parts) ?: 'processed';
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Calculate total annual income from all sources.
     */
    private function calculateTotalUserIncome(User $user): float
    {
        return (float) $user->annual_employment_income
            + (float) $user->annual_self_employment_income
            + (float) $user->annual_rental_income
            + (float) $user->annual_dividend_income
            + (float) $user->annual_interest_income
            + (float) $user->annual_other_income
            + (float) $user->annual_trust_income;
    }

    /**
     * Calculate total monthly expenditure.
     */
    private function calculateTotalExpenditure(User $user): float
    {
        if ($user->monthly_expenditure && $user->monthly_expenditure > 0) {
            return (float) $user->monthly_expenditure;
        }

        if ($user->annual_expenditure && $user->annual_expenditure > 0) {
            return (float) $user->annual_expenditure / 12;
        }

        return 0;
    }

    /**
     * Estimate income tax band.
     */
    private function estimateTaxBand(float $totalIncome): string
    {
        try {
            $incomeTax = $this->taxConfig->getIncomeTax();
            $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? TaxDefaults::PERSONAL_ALLOWANCE);
            $basicRateLimit = $personalAllowance + (float) ($incomeTax['bands'][0]['max'] ?? TaxDefaults::BASIC_RATE_BAND);
            $additionalRateLimit = (float) ($incomeTax['additional_rate_threshold'] ?? TaxDefaults::ADDITIONAL_RATE_THRESHOLD);
        } catch (\Exception) {
            $personalAllowance = (float) TaxDefaults::PERSONAL_ALLOWANCE;
            $basicRateLimit = (float) TaxDefaults::HIGHER_RATE_THRESHOLD;
            $additionalRateLimit = (float) TaxDefaults::ADDITIONAL_RATE_THRESHOLD;
        }

        if ($totalIncome <= $personalAllowance) {
            return 'No tax (below Personal Allowance)';
        }

        if ($totalIncome <= $basicRateLimit) {
            return 'Basic rate (20%)';
        }

        if ($totalIncome <= $additionalRateLimit) {
            return 'Higher rate (40%)';
        }

        return 'Additional rate (45%)';
    }
}
