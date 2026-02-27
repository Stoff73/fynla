<?php

declare(strict_types=1);

namespace App\Services\AI;

class AiToolDefinitions
{
    /**
     * Get all tool definitions for the Anthropic Messages API.
     */
    public function getTools(bool $isPreviewMode = false): array
    {
        $tools = [
            ...$this->navigationTools(),
            ...$this->analysisTools(),
            ...$this->taxTools(),
        ];

        if (! $isPreviewMode) {
            $tools = array_merge($tools, $this->dataCreationTools());
        }

        return $tools;
    }

    private function navigationTools(): array
    {
        return [
            [
                'name' => 'navigate_to_page',
                'description' => 'Navigate the user to a specific page in the application. Use this when the user asks to go somewhere or when showing them relevant information would be helpful.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'route_path' => [
                            'type' => 'string',
                            'description' => 'The application route path. Valid routes include: /dashboard, /net-worth, /net-worth/property, /net-worth/investments, /net-worth/pensions, /net-worth/savings, /net-worth/cash, /net-worth/chattels, /net-worth/business-interests, /net-worth/liabilities, /retirement, /savings, /investment, /protection, /estate, /goals, /settings, /profile',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Brief explanation of why navigating here is helpful',
                        ],
                    ],
                    'required' => ['route_path', 'description'],
                ],
            ],
        ];
    }

    private function analysisTools(): array
    {
        return [
            [
                'name' => 'get_module_analysis',
                'description' => 'Get detailed financial analysis for a specific module. Returns personalised analysis based on the user\'s actual financial data.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'module' => [
                            'type' => 'string',
                            'enum' => ['protection', 'savings', 'investment', 'retirement', 'estate', 'goals', 'holistic'],
                            'description' => 'The financial planning module to analyse',
                        ],
                    ],
                    'required' => ['module'],
                ],
            ],
            [
                'name' => 'run_what_if_scenario',
                'description' => 'Run a what-if scenario to show the user how changes would affect their financial plan. For example: "What if I increase pension contributions by £200/month?" or "What if I retire at 60 instead of 67?"',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'module' => [
                            'type' => 'string',
                            'enum' => ['protection', 'savings', 'investment', 'retirement'],
                            'description' => 'The module to run the scenario against',
                        ],
                        'parameters' => [
                            'type' => 'object',
                            'description' => 'Scenario parameters. For retirement: additional_contribution (number), later_retirement_age (number), lower_target_income (number). For protection: new_coverage (object with type and amount).',
                            'properties' => (object) [],
                        ],
                    ],
                    'required' => ['module', 'parameters'],
                ],
            ],
            [
                'name' => 'get_recommendations',
                'description' => 'Get the user\'s personalised financial recommendations ranked by priority across all modules.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }

    private function taxTools(): array
    {
        return [
            [
                'name' => 'get_tax_information',
                'description' => 'Get current UK tax year information for a specific topic. Use this when the user asks about tax thresholds, allowances, or rates.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => [
                            'type' => 'string',
                            'enum' => ['income_tax', 'capital_gains', 'inheritance_tax', 'isa_allowances', 'pension_allowances'],
                            'description' => 'The tax topic to retrieve information for',
                        ],
                    ],
                    'required' => ['topic'],
                ],
            ],
        ];
    }

    private function dataCreationTools(): array
    {
        return [
            [
                'name' => 'create_goal',
                'description' => 'Create a new financial goal for the user. Use this when the user says they want to save for something specific.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name of the goal (e.g., "Holiday Fund", "House Deposit")',
                        ],
                        'target_amount' => [
                            'type' => 'number',
                            'description' => 'Target amount in pounds',
                        ],
                        'target_date' => [
                            'type' => 'string',
                            'description' => 'Target date in YYYY-MM-DD format',
                        ],
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['critical', 'high', 'medium', 'low'],
                            'description' => 'Priority level of the goal',
                        ],
                        'goal_type' => [
                            'type' => 'string',
                            'enum' => ['emergency_fund', 'house_deposit', 'holiday', 'education', 'wedding', 'car', 'retirement_supplement', 'other'],
                            'description' => 'Type of goal',
                        ],
                    ],
                    'required' => ['name', 'target_amount', 'target_date', 'priority', 'goal_type'],
                ],
            ],
            [
                'name' => 'create_life_event',
                'description' => 'Create a future life event that may impact the user\'s financial plan.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_type' => [
                            'type' => 'string',
                            'description' => 'Type of life event (e.g., "marriage", "graduation", "career_change", "property_purchase", "retirement")',
                        ],
                        'event_date' => [
                            'type' => 'string',
                            'description' => 'Expected date in YYYY-MM-DD format',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Description of the event',
                        ],
                        'estimated_cost' => [
                            'type' => 'number',
                            'description' => 'Estimated cost in pounds (if applicable)',
                        ],
                    ],
                    'required' => ['event_type', 'event_date', 'description'],
                ],
            ],
        ];
    }
}
