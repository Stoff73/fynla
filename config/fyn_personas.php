<?php

declare(strict_types=1);

use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\HandoffContract;
use App\Services\AI\Prompts\DataCapturePromptBuilder;

/*
|--------------------------------------------------------------------------
| Fyn Persona Registry
|--------------------------------------------------------------------------
| Each persona has:
|   - prompt_builder: fully-qualified class name used to build the system
|                     prompt for this persona's turns. Advice uses the
|                     10-layer AdvicePromptBuilder; data-capture uses the
|                     short DataCapturePromptBuilder.
|   - allowed_tools:  whitelist of tool names the persona can call. The
|                     FynPersonaInvoker filters getTools() output against
|                     this list via toolsListOverride. Preview-mode
|                     filtering is applied orthogonally inside
|                     AiToolDefinitions::getTools(bool $isPreviewMode).
|   - handoff_tools:  internal tool names that trigger a persona transition
|                     when emitted. Added to the tool list but stripped
|                     from the SSE stream by the invoker and interpreted
|                     by the orchestrator.
|
| Adding a future persona is one new array entry plus a new prompt builder
| class — no changes to the orchestrator or invoker.
*/

return [
    'advice' => [
        'prompt_builder' => AdvicePromptBuilder::class,
        'allowed_tools' => [
            // Read / analyse (every advice-side tool from AiToolDefinitions)
            'navigate_to_page',
            'list_goals',
            'list_life_events',
            'list_records',
            'get_module_analysis',
            'create_what_if_scenario',
            'get_recommendations',
            'get_tax_information',
            'generate_financial_plan',
        ],
        'handoff_tools' => [
            HandoffContract::DELEGATE_TO_CAPTURE,
        ],
    ],

    'data_capture' => [
        'prompt_builder' => DataCapturePromptBuilder::class,
        'allowed_tools' => [
            // Financial records
            'create_savings_account',
            'create_investment_account',
            'create_holding',
            'create_pension',
            'create_protection_policy',
            'create_property',
            'create_mortgage',
            'create_asset',
            'create_liability',
            'create_estate_gift',
            'create_trust',
            'create_chattel',
            'create_business_interest',
            // Goals and life events
            'create_goal',
            'create_life_event',
            // People
            'create_family_member',
            // Profile updates
            'update_profile',
            // Estate documents
            'create_will',
            'update_will',
            'create_power_of_attorney',
            'update_power_of_attorney',
            // Generic updates / deletes
            'update_record',
            'delete_record',
            // Expenditure
            'set_expenditure',
        ],
        'handoff_tools' => [
            HandoffContract::CAPTURE_COMPLETE,
        ],
    ],
];
