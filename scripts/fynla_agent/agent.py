"""Main agent entry point using the Anthropic Python SDK."""
import json

import anthropic

from .hooks import prerequisite_hook
from .schemas import DeepRecommendationOutput, HolisticPlanOutput, ScenarioOutput
from .tools import (
    get_module_analysis,
    get_recommendations,
    get_tax_information,
    get_user_context,
    run_what_if_scenario,
)

# Tool definitions exposed to the model
TOOL_DEFINITIONS = [
    {
        'name': 'get_module_analysis',
        'description': 'Fetch analysis for a specific financial module (protection, savings, investment, retirement, estate, goals, tax_optimisation).',
        'input_schema': {
            'type': 'object',
            'properties': {'module': {'type': 'string', 'description': 'Module name'}},
            'required': ['module'],
        },
    },
    {
        'name': 'get_tax_information',
        'description': 'Fetch UK tax configuration data for a topic (income_tax, inheritance_tax, isa, pension, capital_gains_tax, national_insurance).',
        'input_schema': {
            'type': 'object',
            'properties': {'topic': {'type': 'string', 'description': 'Tax topic'}},
            'required': ['topic'],
        },
    },
    {
        'name': 'run_what_if_scenario',
        'description': 'Run a what-if scenario against a module with changed parameters.',
        'input_schema': {
            'type': 'object',
            'properties': {
                'module': {'type': 'string', 'description': 'Module name'},
                'parameters': {'type': 'object', 'description': 'Scenario parameters'},
            },
            'required': ['module', 'parameters'],
        },
    },
    {
        'name': 'get_recommendations',
        'description': 'Fetch current ranked recommendations from the coordinating agent.',
        'input_schema': {'type': 'object', 'properties': {}},
    },
    {
        'name': 'get_user_context',
        'description': 'Fetch the full orchestrated financial analysis context for a user.',
        'input_schema': {
            'type': 'object',
            'properties': {'user_id': {'type': 'integer', 'description': 'User ID'}},
            'required': ['user_id'],
        },
    },
]

# Map tool names to callable functions
TOOL_DISPATCH = {
    'get_module_analysis': lambda inp: get_module_analysis(inp['module']),
    'get_tax_information': lambda inp: get_tax_information(inp['topic']),
    'run_what_if_scenario': lambda inp: run_what_if_scenario(inp['module'], inp['parameters']),
    'get_recommendations': lambda _inp: get_recommendations(),
    'get_user_context': lambda inp: get_user_context(inp['user_id']),
}

# Task type to prompt and schema mapping
TASK_CONFIG = {
    'holistic_plan': {
        'schema': HolisticPlanOutput,
        'system': (
            'You are Fynla, a UK financial planning analyst. Analyse the user\'s complete '
            'financial position across protection, savings, investment, retirement, estate '
            'planning, goals, and tax. Produce a holistic plan with an executive summary, '
            'ranked recommendations, a step-by-step action plan, cross-module conflicts, '
            'and strategies. Use British spelling. All monetary values in GBP.'
        ),
    },
    'scenario': {
        'schema': ScenarioOutput,
        'system': (
            'You are Fynla, a UK financial planning analyst. Run the requested what-if '
            'scenario, compare the current state with the projected state, provide an '
            'impact analysis, and assess feasibility. Use British spelling. GBP values.'
        ),
    },
    'deep_recommendations': {
        'schema': DeepRecommendationOutput,
        'system': (
            'You are Fynla, a UK financial planning analyst. Produce deep recommendations '
            'with full reasoning traces, cost-benefit analysis, and decision traces for '
            'each recommendation. Use British spelling. All monetary values in GBP.'
        ),
    },
}


async def run_analysis(
    task: str,
    context: dict,
    api_key: str,
    model: str,
    user_id: int,
    max_tokens: int = 8192,
) -> dict:
    """
    Run an agent analysis loop.

    Uses the Anthropic Messages API with tool use. Implements a manual
    tool-use loop: send messages, check for tool_use blocks, execute
    tools (with prerequisite hooks), feed results back, and repeat
    until the model produces a final text response or MAX_TURNS is hit.
    """
    from .config import MAX_TURNS

    config = TASK_CONFIG.get(task)
    if not config:
        return {'error': f'Unknown task type: {task}'}

    client = anthropic.Anthropic(api_key=api_key)

    # Build the user message from context
    user_message = (
        f"Task: {task}\n"
        f"User ID: {user_id}\n"
        f"Financial context:\n{json.dumps(context, indent=2, default=str)}"
    )

    messages = [{'role': 'user', 'content': user_message}]

    for _turn in range(MAX_TURNS):
        response = client.messages.create(
            model=model,
            max_tokens=max_tokens,
            system=config['system'],
            tools=TOOL_DEFINITIONS,
            messages=messages,
        )

        # If the model stopped without requesting tools, extract the final text
        if response.stop_reason != 'tool_use':
            text_parts = [
                block.text for block in response.content if block.type == 'text'
            ]
            raw_text = '\n'.join(text_parts)

            # Attempt to parse as the expected schema
            try:
                parsed = json.loads(raw_text)
                validated = config['schema'](**parsed)
                return validated.model_dump()
            except (json.JSONDecodeError, Exception):
                # Return raw text wrapped in a result key
                return {'result': raw_text}

        # Process tool use blocks
        assistant_content = response.content
        messages.append({'role': 'assistant', 'content': assistant_content})

        tool_results = []
        for block in assistant_content:
            if block.type != 'tool_use':
                continue

            # Prerequisite check
            gate = prerequisite_hook(block.name, block.input)
            if not gate.get('can_proceed', True):
                tool_results.append({
                    'type': 'tool_result',
                    'tool_use_id': block.id,
                    'content': json.dumps({
                        'error': 'prerequisite_not_met',
                        'missing': gate.get('missing', []),
                        'guidance': gate.get('guidance', ''),
                    }),
                })
                continue

            # Execute the tool
            handler = TOOL_DISPATCH.get(block.name)
            if handler:
                try:
                    result = handler(block.input)
                except Exception as exc:
                    result = {'error': str(exc)}
            else:
                result = {'error': f'Unknown tool: {block.name}'}

            tool_results.append({
                'type': 'tool_result',
                'tool_use_id': block.id,
                'content': json.dumps(result, default=str),
            })

        messages.append({'role': 'user', 'content': tool_results})

    return {'error': 'Max turns exceeded without final response'}
