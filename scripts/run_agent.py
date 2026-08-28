#!/usr/bin/env python3
"""CLI entry point for the Fynla Agent SDK sidecar."""
import argparse
import asyncio
import json
import sys


def main() -> int:
    parser = argparse.ArgumentParser(description='Run Fynla agent analysis')
    parser.add_argument('--input', required=True, help='JSON input payload')
    args = parser.parse_args()

    try:
        payload = json.loads(args.input)
    except json.JSONDecodeError as exc:
        print(json.dumps({'error': f'Invalid JSON input: {exc}'}), file=sys.stdout)
        return 1

    task = payload.get('task', 'holistic_plan')
    context = payload.get('user_context', {})
    user_id = payload.get('user_id', 0)
    api_key = payload.get('api_key', '')
    model = payload.get('model', 'claude-sonnet-4-6-20260320')
    max_tokens = payload.get('max_tokens', 8192)

    if not api_key:
        print(json.dumps({'error': 'No API key provided'}), file=sys.stdout)
        return 1

    from fynla_agent.agent import run_analysis

    try:
        result = asyncio.run(run_analysis(
            task=task,
            context=context,
            api_key=api_key,
            model=model,
            user_id=user_id,
            max_tokens=max_tokens,
        ))
    except Exception as exc:
        print(json.dumps({'error': str(exc)}), file=sys.stdout)
        return 1

    print(json.dumps(result, default=str), file=sys.stdout)
    return 0


if __name__ == '__main__':
    sys.exit(main())
