# Agent Browser Acceptance Runbook

This runbook governs independent browser acceptance on local, csjones, and production environments. A run is evidence of observed behaviour at one exact release commit; it is not permission to deploy or mutate production.

## Contract files

- Write each scenario as a manifest under `tests/Browser/acceptance/` and validate it before opening a browser.
- Record the run as JSON matching `tests/Browser/results/schema.json`.
- Bind every result to the full requested release commit SHA. A result from another commit is invalid.
- Keep evidence paths relative to the result JSON directory and list every captured artifact in the result. Every inventoried path must exist as a regular, non-symbolic-link file beneath that directory when the result is validated.
- Diagnostic notes are optional. When present, mark every note with `redacted: true` after removing credentials, codes, tokens, personal data, and raw financial values that are not necessary to prove the assertion.

Validate a manifest:

```bash
node scripts/quality/validate-acceptance.mjs tests/Browser/acceptance/release-smoke.yaml
```

Validate a manifest and result together:

```bash
node scripts/quality/validate-acceptance.mjs tests/Browser/acceptance/release-smoke.yaml \
  --result docs/online-readiness/evidence/RELEASE_SHA/results.json \
  --release-sha RELEASE_SHA
```

For the whole manifest directory, the validator ignores `schema.json` and validates every YAML or JSON manifest.

The JSON Schemas enforce document-local shape constraints. The executable validator additionally enforces invariants that require both documents or the file system: exact ordered step matching, manifest evidence-path retention, environment-origin binding, release-commit equality, evidence inventory coverage, and evidence-file containment and existence. A schema-only pass is not acceptance validation.

## Interaction rules

Use normal accessible browser interactions only:

1. Start from the manifest URL and capture the accessible page state.
2. Click visible links, buttons, and controls by accessible role or label.
3. Fill visible fields and submit through the real form control.
4. Wait for the documented response, navigation, stream event, or loading state.
5. Observe the rendered outcome, relevant network result, and required evidence.

For an `inspect-frame` step, enter the same-origin frame through the browser's frame locator and observe its accessible main region. It is not permission to inject code into the frame.

The following do not count as acceptance:

- Direct document-object-model JavaScript clicks or invoking component methods.
- Injecting authentication, application, Vuex, browser-storage, database, or server-sent event state to manufacture a starting point or outcome.
- Snapshot-only inspection with no required click, fill, submit, wait, or observed response.
- Reusing an older screenshot or result as evidence for the current release commit.
- Claiming an outcome that was not observed. Missing evidence is a failed or blocked step, never a fabricated pass.

## Identities and verification codes

Manifests contain environment-variable references such as `CSJONES_QA_EMAIL`, never an email address, password, verification code, token, recovery code, or other credential. Environment URLs must not include user information or credential-like query parameters. Supply credentials through the approved operator-controlled secret channel and do not write them to browser traces, screenshots, results, terminal logs, or committed files.

### Local and isolated end-to-end runs

- Use only seeded users, preview personas, or namespaced disposable users documented by the manifest.
- In the isolated `e2e` application environment, retrieve the active verification code through the protected `__e2e/verification-code` test-support route used by the browser suite.
- In ordinary local development, use `Tests\Browser\Helpers\Login::latestVerificationCode($email)` or an equivalently scoped database lookup for the named test user.
- Test-support routes must remain unavailable outside the `e2e` environment.

### csjones

- Use the standing quality-assurance account for read-safe release smoke tests.
- For state-changing scenarios, create an explicitly namespaced release/run test user, record it in the run inventory, and scope every query to that identity.
- csjones is staging. Never use a real customer identity or production credentials there.
- Retrieve a code only through the approved staging operator path. Do not capture it as evidence or retain it after entry.

### Production

- Production smoke is read-safe unless CSJ explicitly authorises a bounded write check.
- Use the dedicated production quality-assurance account only.
- When the verification screen appears, ask CSJ for the code. Do not query the production database, mail logs, or caches for it.
- Enter the code in the visible form and do not repeat it in notes, screenshots, traces, or result JSON.

## Desktop and mobile evidence

Every user-visible manifest covers both the desktop surface and the `/m` pathway. On mobile, verify the `/m` host or funnel frame and the real `/m/app` mobile application route required by the scenario. A desktop-only pass is not a release pass.

For each step, capture evidence after the interaction and after the asserted state is visible. The result contains exactly one entry for every manifest step in manifest order; surface, action, target, and assertion must match, and the result step retains the manifest's expected evidence path. Evidence can include a screenshot, a redacted network record, a scoped database record, server-sent event metadata, or an audit record. A screenshot alone does not prove persistence, streaming, or cross-surface agreement.

Database evidence must:

- Query only the explicit test identity and expected record type.
- Record the scoped row identifier, relevant field names, and outcome without dumping unrelated personal or financial data.
- Support, not replace, the visible user-interface assertion.

Server-sent event evidence must:

- Record the expected event names and order for the tested turn.
- Redact message bodies, tokens, credentials, personal data, and unrelated payload fields.
- Compare the stream with the visible final state and, for writes, the scoped database or audit result.

For every surface, also observe the manifest's negative assertions: console errors, unexpected client or server responses, false success, duplicate writes, and desktop/mobile divergence.

## Completion and cleanup

1. Mark each step and negative assertion as passed, failed, or blocked from actual evidence. Overall status can be passed only when every child result is passed.
2. Record browser name and version, viewport, environment URL, start and finish timestamps, and the exact release commit SHA.
3. Redact diagnostic notes before setting `redacted: true`.
4. Remove disposable users, records, uploads, conversations, and files created by the run, unless the manifest explicitly requires retained audit evidence.
5. Preserve the standing quality-assurance account. Do not delete or repurpose it.
6. Recheck the scoped database and storage locations to confirm cleanup.
7. Validate the result against the same manifest and requested release commit SHA.

If any required assertion is red, diagnose the root cause, fix it, redeploy the same bounded candidate flow, and rerun the affected manifest. Do not report partial success as green.
