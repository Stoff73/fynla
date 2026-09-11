"""Tool functions that call back to the Laravel internal API."""
import requests

from .config import LARAVEL_API_URL, AGENT_SECRET

_HEADERS = {
    'X-Agent-Token': AGENT_SECRET,
    'Accept': 'application/json',
    'Content-Type': 'application/json',
}

_TIMEOUT = 30  # seconds


def _url(path: str) -> str:
    return f"{LARAVEL_API_URL.rstrip('/')}/api/internal/agent{path}"


def _handle_response(resp: requests.Response) -> dict:
    """Parse JSON response or return an error dict."""
    try:
        resp.raise_for_status()
        return resp.json()
    except requests.HTTPError as exc:
        return {'error': True, 'status': resp.status_code, 'message': str(exc)}
    except ValueError:
        return {'error': True, 'message': 'Invalid JSON in response'}


def get_module_analysis(module: str) -> dict:
    """Fetch analysis for a specific financial module."""
    resp = requests.get(
        _url(f'/analysis/{module}'),
        headers=_HEADERS,
        timeout=_TIMEOUT,
    )
    return _handle_response(resp)


def get_tax_information(topic: str) -> dict:
    """Fetch UK tax configuration data for a topic."""
    resp = requests.get(
        _url(f'/tax/{topic}'),
        headers=_HEADERS,
        timeout=_TIMEOUT,
    )
    return _handle_response(resp)


def run_what_if_scenario(module: str, parameters: dict) -> dict:
    """Run a what-if scenario against a module."""
    resp = requests.post(
        _url('/scenario'),
        headers=_HEADERS,
        json={'module': module, 'parameters': parameters},
        timeout=_TIMEOUT,
    )
    return _handle_response(resp)


def get_recommendations() -> dict:
    """Fetch current recommendations from the coordinating agent."""
    resp = requests.get(
        _url('/recommendations'),
        headers=_HEADERS,
        timeout=_TIMEOUT,
    )
    return _handle_response(resp)


def get_user_context(user_id: int) -> dict:
    """Fetch full orchestrated analysis context for a user."""
    resp = requests.get(
        _url(f'/user-context/{user_id}'),
        headers=_HEADERS,
        timeout=_TIMEOUT,
    )
    return _handle_response(resp)
