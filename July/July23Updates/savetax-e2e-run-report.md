# SaveTax Campaign E2E — Test-Loop Run Report (2026-07-23 evening)

**Brief (CSJ):** run the full SaveTax campaign as a real user — web `/m` funnel → register → full Fyn onboarding → tax strategy — logging EVERY deviation, fixing errors, restarting, looping until right. Then verify in the native iOS app and run LiveJourneyTests.

**Branch:** `codex/savetax-e2e-capture-fixes` (off dev tip `2f9beffa`), deployed to csjones throughout the loop. 8 fix commits, each TDD'd (failing test → fix → green), targeted families run per commit (largest sweep: 953 tests green, zero collateral).

## Outcome

- **Web `/m` journey: COMPLETE and GREEN end-to-end** on round 2 + in-flight fixes. Fresh user `priya-e2e-0723b@example.com` (user 293): funnel (5 questions) → plan page (maths verified) → register → verify code → campaign sections (income £85k → ISA £8k → savings joint £12k @ 4.2% 50/50 + current £2.5k → GIA £15k → DOB → workplace pension 5%/3% → no personal pensions → spouse £32k/ISA £4k/pension 4% → expenditure £3,400) → synthesis (£1,072/yr, maths verified) → Tax Strategy page (allowance figures verified against entered data) → dashboard net worth £31,500 (share-adjusted, correct). `onboarding_completed=true`, step nulled.
- **iOS native: COULD NOT TEST.** The simulator runtime wedged repeatedly at CoreSimulator level (frozen sim clock 25+ min, hung `simctl launch` on the existing device AND a freshly-created one, hung install during the first run; CoreSimulator service restart did not help). The same suite ran fine on this machine at 18:33 the same day — environment regression, most likely needs a host reboot. Everything is staged for a re-run (see below).

## Fixes shipped (all on `codex/savetax-e2e-capture-fixes`, live on csjones)

| Commit | Fix |
|---|---|
| `d2afc70` | Joint account with no linked co-owner allowed at agent + gate (matches web-form semantics; reciprocal-spouse authorization kept for attaching a real id); latest-turn-wins evidence binding (kills the permanent ownership re-ask deadlock); em-dash-severed "owned 50/50 with my husband …" recognised as share evidence; `stripEchoedFailureCopy` no longer truncates mid-decimal ("2%." fragment); `UserOnboardingStepObserver` busts `mobile_dashboard_{id}` on step change |
| `d4b625b` | Fresh campaign registrants count as mid-walk before the first turn stamps the step (first-paint unlock-suppression race) |
| `4344d6a` | `SavingsStore` accepts joint with null co-owner; ONE reciprocal-spouse rule: `User::hasReciprocalSpouseLink` (**flag: deliberately relaxes the 2026-07-13 hardening for the null-owner case — CSJ to confirm**). Structural note: the savetax campaign never creates a spouse User (household inputs only), so the old gate could never pass in-campaign |
| `02883fc` | Dedupe skip ("already exists") is never a failed write — `ToolResults::isDuplicateSkip`, consumed by the `landed` signal + both director consumers (killed the fabricated-success blend AND the "couldn't record anything new" line above a landed save) |
| `3fd8dac` | Completion declarations ("that's all my savings", "done", "no more") close a zero-output capture turn like negative declarations |
| `8ce60e7` | Ownership evidence survives the intervening detail sentence the prompt itself asks for (inert same-turn detail stepped over; corrections naming another account still break the walk) |
| `d613376` | `TaxConfigService` import in `OnboardingChatDirector` — the spouse-advice fallback fatally resolved namespace-relative ("An unexpected error occurred" after spouse verify) |
| `ac31e2b` | `salary_sacrifice=false` survives `PensionNormaliser` — a stated "not salary sacrifice" persisted NULL |

## Open items (logged, not shipped — see `savetax-e2e-issue-log.md` for full detail)

1. **[HIGH — tax modelling]** PSA usage attributes the joint account's FULL £504 interest to the primary owner (shows "Fully used £500"); HMRC 50/50 split says £252 each. Spouse's savings allowance ignores the known share entirely. Net worth DOES share-adjust — cross-surface inconsistency. Needs `CalculatesOwnershipShare` in the tax-strategy interest calc + tax-compliance review.
2. **[Medium]** `/m` savings page rows labelled "Unknown" — `resources/mobile/views/modules/Savings.vue:41,67,155` leads with `provider||institution`, never `account_name`; capture defaults institution to literal "Unknown". Needs /m rebuild to ship.
3. **[Medium]** "Total cash £14,500" on the savings page sums full joint balance (vs. share) — convention ruling needed (issue 15).
4. **[Medium]** ISA question asks "is it owned by you individually?" — ISAs have exactly one legal owner; gate already auto-confirms. Copy change (CSJ wording call).
5. **[Medium]** Verify-page "Continue" injects a fabricated user bubble "Yes, that's right" — words the user never typed (design call).
6. **[Low]** Stale-session registration edge: registering via the funnel with another user's `/m` token in localStorage lands in the OLD account, verification dangling (shared-device edge; funnel could clear tokens on register success).
7. **[Low]** `/m` shell requests domain-root favicon (404 each load); cookie dialog Privacy Policy link ignores the `/fynla` base path.
8. **[Low]** Confirmation-voice inconsistency ("Recorded — …" / "Saved to your records" / "Saved X.", double confirmations); spouse-advice line was skipped on the post-fatal retry; completion phrases still round-trip the LLM (~£0.04 + refusal risk each) before the guard rescues — a deterministic pre-LLM shortcut would be cheaper.
9. **[Observation]** SaveTax never asks current pension pot value → retirement projections anchor at £0 pot (defensible for a tax campaign — flagging only). "Unlock ISA info — enter your ISA details" action shows for a user with a complete ISA row — verify the gate's missing-field list.

## iOS re-run — staged and ready (after host reboot)

1. Build products exist: `~/Library/Developer/Xcode/DerivedData/Fynla-cezjombadwdacdeqxksztysjjahv/Build/Products/Fynla-Staging_iphonesimulator26.2-x86_64.xctestrun` (worktree `fynla-ios-package7`, branch `codex/ios-package7-platform-release` @ `d4e8a2b`).
2. Relay: `scratchpad/code-relay.sh <code-file> priya-e2e-0723b@example.com` (polls csjones `EmailVerificationCode`, 30-min window).
3. Test: `xcodebuild test-without-building -xctestrun <above> -destination 'platform=iOS Simulator,name=Fynla iPhone 11' -only-testing:FynlaUITests/LiveJourneyTests` with `TEST_RUNNER_FYNLA_LIVE_EMAIL=priya-e2e-0723b@example.com`, `TEST_RUNNER_FYNLA_LIVE_PASSWORD=Password1!`, `TEST_RUNNER_FYNLA_LIVE_CODE_FILE=<code-file>`.
4. Verify screenshots against: net worth £31,500, savings £14,500 (2 accts), investments £23,000 (ISA £8k + GIA £15k), pension 5%/3%, tax plan £1,072/yr.

Priya-b's account on csjones is fully populated and completed — the iOS pull-through test needs no web-side re-work.
