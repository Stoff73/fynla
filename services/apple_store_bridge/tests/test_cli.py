import io
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from services.apple_store_bridge.errors import BridgeError
from services.apple_store_bridge.cli import main


class CliTest(unittest.TestCase):
    def invoke(self, payload):
        stdout = io.StringIO()
        stderr = io.StringIO()
        with patch("services.apple_store_bridge.cli.sys.stderr", stderr):
            exit_code = main(io.StringIO(payload), stdout)
        return exit_code, stdout.getvalue(), stderr.getvalue()

    def test_health_has_exact_safe_single_line_output(self):
        exit_code, stdout, stderr = self.invoke(
            '{"version":1,"operation":"health"}'
        )

        self.assertEqual(0, exit_code)
        self.assertEqual(
            '{"version":1,"ok":true,"data":{"service":"apple-store-bridge",'
            '"contract":1,"library":"3.1.2"}}\n',
            stdout,
        )
        self.assertEqual("", stderr)
        self.assertEqual(1, len(stdout.splitlines()))

    def test_configured_cli_path_runs_directly_with_a_minimal_environment(self):
        cli_path = Path(__file__).parents[1] / "cli.py"
        with tempfile.TemporaryDirectory() as working_directory:
            completed = subprocess.run(
                [sys.executable, str(cli_path)],
                input='{"version":1,"operation":"health"}',
                text=True,
                capture_output=True,
                cwd=working_directory,
                env={"PYTHONDONTWRITEBYTECODE": "1"},
                timeout=10,
                check=False,
            )

        self.assertEqual(0, completed.returncode)
        self.assertEqual(
            '{"version":1,"ok":true,"data":{"service":"apple-store-bridge",'
            '"contract":1,"library":"3.1.2"}}\n',
            completed.stdout,
        )
        self.assertEqual("", completed.stderr)

    def test_dispatches_each_verification_operation_with_the_full_request(self):
        operations = {
            "verify_transaction": "verify_transaction",
            "verify_notification": "verify_notification",
            "verify_renewal": "verify_renewal",
        }

        for operation, method_name in operations.items():
            with self.subTest(operation=operation):
                request = {
                    "version": 1,
                    "operation": operation,
                    "signed_data": "private.signed.value",
                }
                with patch(
                    "services.apple_store_bridge.cli.AppleSignedDataService"
                ) as service_class:
                    method = getattr(service_class.return_value, method_name)
                    method.return_value = {"safe": operation}
                    exit_code, stdout, stderr = self.invoke(json.dumps(request))

                self.assertEqual(0, exit_code)
                self.assertEqual(
                    {
                        "version": 1,
                        "ok": True,
                        "data": {"safe": operation},
                    },
                    json.loads(stdout),
                )
                self.assertNotIn("private.signed.value", stdout)
                self.assertEqual("", stderr)
                method.assert_called_once_with(request)

    def test_dispatches_reconcile_without_returning_server_configuration(self):
        request = {
            "version": 1,
            "operation": "reconcile",
            "original_transaction_id": "apple-original-1",
            "private_key_path": "/private/server/AuthKey.p8",
        }
        safe_result = {
            "original_transaction_id": "apple-original-1",
            "transactions": [],
            "renewals": [],
        }
        with patch(
            "services.apple_store_bridge.cli.AppleServerReconciler"
        ) as reconciler_class:
            reconciler_class.return_value.reconcile.return_value = safe_result
            exit_code, stdout, stderr = self.invoke(json.dumps(request))

        self.assertEqual(0, exit_code)
        self.assertEqual(
            {"version": 1, "ok": True, "data": safe_result},
            json.loads(stdout),
        )
        self.assertNotIn("AuthKey", stdout)
        self.assertNotIn("/private/server", stdout)
        self.assertEqual("", stderr)
        reconciler_class.return_value.reconcile.assert_called_once_with(request)

    def test_handled_bridge_error_returns_only_its_stable_envelope(self):
        payload = (
            '{"version":1,"operation":"verify_transaction",'
            '"signed_data":"private.signed.value"}'
        )
        with patch(
            "services.apple_store_bridge.cli.AppleSignedDataService"
        ) as service_class:
            source = VerificationFailureWithSensitiveMessage()
            service_class.return_value.verify_transaction.side_effect = source
            exit_code, stdout, stderr = self.invoke(payload)

        self.assertEqual(2, exit_code)
        self.assertEqual(
            {
                "version": 1,
                "ok": False,
                "error": {
                    "code": "retryable_verification_failure",
                    "retryable": True,
                },
            },
            json.loads(stdout),
        )
        self.assertNotIn("private.signed.value", stdout)
        self.assertNotIn("sensitive", stdout)
        self.assertEqual("", stderr)

    def test_malformed_request_is_handled_without_stderr_or_source_detail(self):
        exit_code, stdout, stderr = self.invoke(
            '{"version":1,"operation":"health","payload":NaN}'
        )

        self.assertEqual(2, exit_code)
        self.assertEqual(
            {
                "version": 1,
                "ok": False,
                "error": {"code": "malformed_request", "retryable": False},
            },
            json.loads(stdout),
        )
        self.assertEqual("", stderr)

    def test_unexpected_failure_becomes_retryable_unavailable_without_leakage(self):
        payload = (
            '{"version":1,"operation":"verify_transaction",'
            '"signed_data":"private.signed.value"}'
        )
        with patch(
            "services.apple_store_bridge.cli.AppleSignedDataService"
        ) as service_class:
            service_class.return_value.verify_transaction.side_effect = ValueError(
                "sensitive source exception private.signed.value"
            )
            exit_code, stdout, stderr = self.invoke(payload)

        self.assertEqual(2, exit_code)
        self.assertEqual(
            {
                "version": 1,
                "ok": False,
                "error": {"code": "verifier_unavailable", "retryable": True},
            },
            json.loads(stdout),
        )
        self.assertEqual("verifier_unavailable\n", stderr)
        self.assertNotIn("sensitive", stdout + stderr)
        self.assertNotIn("private.signed.value", stdout + stderr)
        self.assertEqual(1, len(stdout.splitlines()))

    def test_unserializable_success_is_replaced_before_any_partial_output(self):
        payload = '{"version":1,"operation":"verify_transaction"}'
        with patch(
            "services.apple_store_bridge.cli.AppleSignedDataService"
        ) as service_class:
            service_class.return_value.verify_transaction.return_value = {
                "not_json": {1, 2}
            }
            exit_code, stdout, stderr = self.invoke(payload)

        self.assertEqual(2, exit_code)
        self.assertEqual("verifier_unavailable", json.loads(stdout)["error"]["code"])
        self.assertEqual("verifier_unavailable\n", stderr)
        self.assertEqual(1, len(stdout.splitlines()))


class VerificationFailureWithSensitiveMessage(BridgeError):
    def __init__(self):
        super().__init__("retryable_verification_failure", retryable=True)
        self.__cause__ = ValueError("sensitive certificate detail")


if __name__ == "__main__":
    unittest.main()
