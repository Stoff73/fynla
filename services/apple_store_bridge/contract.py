import json
from typing import IO, Any, Dict, List, Tuple

from .errors import BridgeError

CONTRACT_VERSION = 1
OPERATIONS = frozenset(
    {
        "health",
        "verify_transaction",
        "verify_notification",
        "verify_renewal",
        "reconcile",
    }
)

_SECURITY_CRITICAL_KEYS = frozenset({"version", "operation"})
_MALFORMED = object()


class _DuplicateSecurityCriticalKey(ValueError):
    pass


def _object_without_critical_duplicates(
    pairs: List[Tuple[str, Any]],
) -> Dict[str, Any]:
    result: Dict[str, Any] = {}
    seen_critical_keys = set()

    for key, value in pairs:
        if key in _SECURITY_CRITICAL_KEYS:
            if key in seen_critical_keys:
                raise _DuplicateSecurityCriticalKey
            seen_critical_keys.add(key)
        result[key] = value

    return result


def _load_json(stream: IO[str]) -> Any:
    try:
        return json.load(
            stream,
            object_pairs_hook=_object_without_critical_duplicates,
        )
    except (json.JSONDecodeError, UnicodeError, _DuplicateSecurityCriticalKey):
        return _MALFORMED


def read_request(stream: IO[str]) -> Dict[str, Any]:
    request = _load_json(stream)

    if request is _MALFORMED or not isinstance(request, dict):
        raise BridgeError("malformed_request")

    if type(request.get("version")) is not int or request["version"] != CONTRACT_VERSION:
        raise BridgeError("unsupported_contract")

    operation = request.get("operation")
    if type(operation) is not str or operation not in OPERATIONS:
        raise BridgeError("unsupported_operation")

    return request


def success(data: Dict[str, Any]) -> Dict[str, Any]:
    return {"version": CONTRACT_VERSION, "ok": True, "data": data}


def failure(error: BridgeError) -> Dict[str, Any]:
    return {
        "version": CONTRACT_VERSION,
        "ok": False,
        "error": {"code": error.code, "retryable": error.retryable},
    }
