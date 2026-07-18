import json
import sys
from importlib.metadata import version
from pathlib import Path
from typing import IO, Any, Dict

if __package__ in (None, ""):
    sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from services.apple_store_bridge.contract import (  # noqa: E402
    CONTRACT_VERSION,
    failure,
    read_request,
    success,
)
from services.apple_store_bridge.errors import BridgeError  # noqa: E402
from services.apple_store_bridge.api_client import (  # noqa: E402
    AppleServerReconciler,
)
from services.apple_store_bridge.verifier import (  # noqa: E402
    AppleSignedDataService,
)

SERVICE_NAME = "apple-store-bridge"
LIBRARY_DISTRIBUTION = "app-store-server-library"


def _dispatch(request: dict) -> Dict[str, Any]:
    operation = request["operation"]
    if operation == "health":
        return {
            "service": SERVICE_NAME,
            "contract": CONTRACT_VERSION,
            "library": version(LIBRARY_DISTRIBUTION),
        }
    if operation == "reconcile":
        return AppleServerReconciler().reconcile(request)

    service = AppleSignedDataService()
    if operation == "verify_transaction":
        return service.verify_transaction(request)
    if operation == "verify_notification":
        return service.verify_notification(request)
    if operation == "verify_renewal":
        return service.verify_renewal(request)
    raise BridgeError("unsupported_operation")


def _serialize(envelope: Dict[str, Any]) -> str:
    return json.dumps(
        envelope,
        ensure_ascii=True,
        allow_nan=False,
        separators=(",", ":"),
    ) + "\n"


def main(stdin: IO[str] = sys.stdin, stdout: IO[str] = sys.stdout) -> int:
    try:
        request = read_request(stdin)
        output = _serialize(success(_dispatch(request)))
    except BridgeError as error:
        output = _serialize(failure(error))
        exit_code = 2
    except Exception:
        unavailable = BridgeError("verifier_unavailable", retryable=True)
        output = _serialize(failure(unavailable))
        sys.stderr.write("verifier_unavailable\n")
        exit_code = 2
    else:
        exit_code = 0

    stdout.write(output)
    return exit_code


if __name__ == "__main__":
    raise SystemExit(main())
