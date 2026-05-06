# Sprint 0 Browser test harness

This directory holds the Playwright-MCP browser-testing harness for the
Sprint 0 BS-NN scenario matrix. **20 scenarios** required for Sprint 0:

| # | Slug | Invariants |
|---|------|-----------|
| BS-01 | `onboarding-path-choice-to-done` | INV-2.2.1 |
| BS-02 | `base-spouse-direct-write` | INV-2.2.2 |
| BS-04 | `resume-after-disconnect` | INV-2.2.4 |
| BS-05 | `journey-map-by-entry-source` | INV-2.2.5 |
| BS-06 | `parked-facts-flush` | INV-2.2.6 |
| BS-07 | `dispatch-flips-after-onboarding` | INV-2.1.1, INV-2.1.3 |
| BS-10 | `out-of-remit-refusal` | INV-2.3.4 |
| BS-11 | `handoff-invisibility` | INV-2.4.1, INV-2.4.2 |
| BS-12 | `capture-complete-styling` | INV-2.4.3 |
| BS-13 | `token-limit-system-message` | INV-2.4.4 |
| BS-14 | `direct-write-savings-account` | INV-2.5.1 |
| BS-15 | `hash-chain-audit-admin-view` | INV-2.5.4, INV-2.10.2 |
| BS-16 | `billing-where-is-my-invoice` | INV-2.7.2 |
| BS-17 | `multi-entity-persist` | INV-2.8.1 |
| BS-18 | `sse-abort-keep-writes` | INV-2.9.2 |
| BS-19 | `gap-fill-dedup-on-retry` | INV-2.9.5 |
| BS-20 | `generate-title-sanitation` | INV-2.9.6 |
| BS-21 | `coreidentity-tone` | INV-2.10.1 |
| BS-22 | `consent-required-mid-session` | INV-2.10.3 |
| BS-23 | `prompt-injection-sanitisation` | INV-2.10.4 |

Sprint 1 scenarios (BS-03, BS-08, BS-09, BS-24) are authored under Sprint 1
Task 1.9. BS-25 (failover) is Sprint 4. BS-17 batch-tool variants are
Sprint 2.

## Why this isn't an automated Pest suite

The Playwright MCP tools (`browser_navigate`, `browser_click`,
`browser_fill_form`, etc.) are invoked by Claude during an interactive
session — they are NOT callable from `vendor/bin/pest`. Each BS-NN file
is therefore **both**:

1. A Pest test that `markTestSkipped()`s at runtime (so CI is clean and
   `vendor/bin/pest --testsuite=Browser` reports the suite without
   exploding) — the skip message points back at this README.
2. An executable script that Claude reads in a chat session and walks
   through step by step, capturing screenshots into
   `docs/sprint-0-verification/BS-NN/`.

If you want CI-grade browser tests in a future sprint, switch the
harness to Laravel Dusk or a JS-Playwright runner — but that's a Sprint 4+
discussion, not a Sprint 0 problem.

## Running a scenario interactively (S0.16b)

```bash
./dev.sh                   # Laravel on :8000, Vite on :5173
php artisan db:seed        # tax config + preview personas + factories
```

Then in a Claude session, hand Claude the BS-NN file path and say
"execute this scenario". Claude:

1. Reads the file's step list.
2. Drives Playwright via the MCP browser_* tools, step by step.
3. Calls `Login::latestVerificationCode($email)` (via `php artisan tinker`)
   when an MFA prompt appears in local dev.
4. Captures `browser_take_screenshot` per assertion checkpoint into
   `docs/sprint-0-verification/BS-NN/<step>.png`.
5. Pins the assertions via `AssertSseEvents` + DB queries / Eloquent
   model checks.

**Browser-testing law (root CLAUDE.md + memory):** "Browser tested" means
Claude clicked, filled, submitted, and verified the result in Playwright.
Reading the diff is NOT a browser test. Snapshotting without interaction
is NOT a test.

## Helpers

- `tests/Browser/TestCase.php` — Pest base for every scenario;
  `markPendingInteractiveRun()` is the canonical skip path;
  `browserHealthcheck()` pings `:8000` so the script can fail fast if
  `./dev.sh` isn't running.
- `tests/Browser/Helpers/Login.php` — login flow doc + DB plumbing for
  the MFA-code lookup. The actual `browser_*` calls live in the BS-NN
  scripts; this file documents the canonical sequence.
- `tests/Browser/Helpers/AssertSseEvents.php` — pure PHP SSE event
  parsing + assertions. Takes the array `browser_network_requests`
  returns, decodes the chat-stream body, and exposes
  `assertNoEventType`, `assertEventTypeCount`, `assertEventTypeEmitted`,
  and `windowBetween` helpers.

## Screenshot output

Every scenario commits screenshots to a sibling directory under
`docs/sprint-0-verification/BS-NN/`. Naming convention:

```
docs/sprint-0-verification/BS-NN/01-rest-state.png
docs/sprint-0-verification/BS-NN/02-after-submit.png
docs/sprint-0-verification/BS-NN/03-final-assertion.png
```

Two-digit prefix matches the step number in the script so reviewers can
correlate visual evidence to spec line.

## Out of scope for this harness

- Real CI execution (would need Laravel Dusk or external Playwright).
- Cross-browser tests (Chromium-only via the MCP).
- Mobile / Capacitor tests (out of Sprint 0; covered in
  `April/spec` Sprint 4 mobile track).

## Sprint 0 sign-off

Run all 20 scenarios, capture screenshots, then update
`docs/sprint-0-verification/rubric-a-score.md` with the dimension-by-
dimension Rubric-A re-score (S0.17). The browser matrix is part of the
Sprint 0 verification rollup; do not claim "Browser 20/20 PASS" until
every screenshot exists.
