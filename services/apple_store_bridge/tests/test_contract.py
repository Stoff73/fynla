import io
import json
import unittest

from services.apple_store_bridge.contract import failure, read_request, success
from services.apple_store_bridge.errors import BridgeError


class ContractTest(unittest.TestCase):
    def assert_request_error(self, payload: str, code: str) -> BridgeError:
        with self.assertRaises(BridgeError) as caught:
            read_request(io.StringIO(payload))

        error = caught.exception
        self.assertEqual(code, error.code)
        self.assertFalse(error.retryable)
        self.assertEqual(code, str(error))
        return error

    def test_accepts_valid_version_one_transaction_request(self):
        request = read_request(
            io.StringIO(
                '{"version":1,"operation":"verify_transaction",'
                '"signed_data":"a.b.c"}'
            )
        )

        self.assertIsInstance(request, dict)
        self.assertEqual("verify_transaction", request["operation"])
        self.assertEqual("a.b.c", request["signed_data"])

    def test_accepts_every_known_operation(self):
        for operation in (
            "health",
            "verify_transaction",
            "verify_notification",
            "verify_renewal",
            "reconcile",
        ):
            with self.subTest(operation=operation):
                request = read_request(
                    io.StringIO(
                        json.dumps({"version": 1, "operation": operation})
                    )
                )
                self.assertEqual(operation, request["operation"])

    def test_rejects_every_non_exact_version_one_as_unsupported_contract(self):
        payloads = {
            "version_two": '{"version":2,"operation":"health"}',
            "null": '{"version":null,"operation":"health"}',
            "missing": '{"operation":"health"}',
            "boolean": '{"version":true,"operation":"health"}',
            "float": '{"version":1.0,"operation":"health"}',
            "string": '{"version":"1","operation":"health"}',
        }

        for case, payload in payloads.items():
            with self.subTest(case=case):
                self.assert_request_error(payload, "unsupported_contract")

    def test_rejects_unknown_or_wrong_type_operations_with_stable_error(self):
        payloads = {
            "unknown": '{"version":1,"operation":"unknown"}',
            "missing": '{"version":1}',
            "null": '{"version":1,"operation":null}',
            "numeric": '{"version":1,"operation":1}',
            "object": '{"version":1,"operation":{"name":"health"}}',
            "list": '{"version":1,"operation":["health"]}',
        }

        for case, payload in payloads.items():
            with self.subTest(case=case):
                self.assert_request_error(payload, "unsupported_operation")

    def test_rejects_malformed_trailing_and_non_object_json(self):
        payloads = {
            "malformed": '{"version":1,',
            "trailing": '{"version":1,"operation":"health"} trailing',
            "array": '[{"version":1,"operation":"health"}]',
            "string": '"request"',
            "number": "1",
            "null": "null",
            "boolean": "true",
        }

        for case, payload in payloads.items():
            with self.subTest(case=case):
                self.assert_request_error(payload, "malformed_request")

    def test_rejects_duplicate_security_critical_keys(self):
        payloads = {
            "version": '{"version":1,"version":1,"operation":"health"}',
            "operation": (
                '{"version":1,"operation":"health",'
                '"operation":"verify_transaction"}'
            ),
        }

        for key, payload in payloads.items():
            with self.subTest(key=key):
                self.assert_request_error(payload, "malformed_request")

    def test_returns_exact_success_envelope(self):
        data = {"id": "1"}

        self.assertEqual(
            {"version": 1, "ok": True, "data": data},
            success(data),
        )

    def test_returns_exact_failure_envelopes_without_source_details(self):
        non_retryable = BridgeError("invalid_signature", retryable=False)
        self.assertEqual(
            {
                "version": 1,
                "ok": False,
                "error": {"code": "invalid_signature", "retryable": False},
            },
            failure(non_retryable),
        )
        self.assertEqual(
            {"code": "invalid_signature", "retryable": False},
            non_retryable.__dict__,
        )

        try:
            try:
                raise ValueError("sensitive source exception")
            except ValueError as source:
                raise BridgeError("verifier_unavailable", retryable=True) from source
        except BridgeError as retryable:
            caught_retryable = retryable
            envelope = failure(retryable)

        self.assertEqual("verifier_unavailable", str(caught_retryable))
        self.assertNotIn("sensitive source exception", str(caught_retryable))
        self.assertEqual(
            {
                "version": 1,
                "ok": False,
                "error": {"code": "verifier_unavailable", "retryable": True},
            },
            envelope,
        )
        serialized = json.dumps(envelope)
        self.assertNotIn("sensitive source exception", serialized)
        self.assertNotIn("message", serialized)
        self.assertNotIn("cause", serialized)


if __name__ == "__main__":
    unittest.main()
