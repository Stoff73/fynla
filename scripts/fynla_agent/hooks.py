"""PreToolUse hooks for prerequisite enforcement."""
import requests

from .config import LARAVEL_API_URL, AGENT_SECRET

_HEADERS = {
    'X-Agent-Token': AGENT_SECRET,
    'Accept': 'application/json',
    'Content-Type': 'application/json',
}

_TIMEOUT = 10  # seconds


def prerequisite_hook(tool_name: str, tool_input: dict) -> dict:
    """
    Check prerequisites before executing a tool.

    Calls the Laravel PrerequisiteGateService to verify that the user
    has sufficient data for the requested operation.

    Returns:
        dict with keys:
            - can_proceed (bool): Whether the tool may run
            - missing (list): Data the user still needs to provide
            - guidance (str): Human-readable guidance message
            - required_actions (list): Actions the user should take
    """
    url = f"{LARAVEL_API_URL.rstrip('/')}/api/internal/agent/prerequisite-check"

    try:
        resp = requests.post(
            url,
            headers=_HEADERS,
            json={'tool_name': tool_name, 'tool_input': tool_input},
            timeout=_TIMEOUT,
        )
        resp.raise_for_status()
        return resp.json()
    except requests.RequestException as exc:
        # If the gate is unreachable, fail open so the agent can still
        # attempt the tool (Laravel will enforce its own gates anyway).
        return {
            'can_proceed': True,
            'missing': [],
            'guidance': f'Prerequisite check unavailable: {exc}',
            'required_actions': [],
        }
