# Deferred-by-Design Register — for 2026-07-23

*Written 2026-07-22 evening. Every item here is KNOWN and INTENTIONALLY not done —
either explicitly deferred by CSJ, parked pending a decision, or noted as debt in a
handover. Nothing in this list is forgotten work; this register exists so it stays
that way. Scope note: we are working on dev only for the time being — nothing here
implies a prod deploy.*

---

## A. Fyn / backend

### A1. Mid-onboarding questions — POLICY DECIDED (CSJ, reconfirmed 2026-07-23); one defect open
**The decided behaviour (CSJ):** Fyn decides per question. A straightforward
definitional question ("What is salary sacrifice?") is answered inline and the
walk resumes; a longer question needing fuller information is acknowledged with
a promise and addressed once onboarding completes (deferred_questions → raised
at the completion terminal). This IS the implemented dispatcher behaviour — it
is NOT an open ruling. An earlier version of this register wrongly framed the
policy as undecided (over-read from the turn-intent spec's out-of-scope note);
that framing is retracted.
**The open DEFECT (live, 2026-07-23, Tessa E2E):** at a delegated capture step,
when the model's turn only re-asks the scripted question ("I still need your
gross annual income…"), the model-requested-clarification branch ends the turn
with no interruption dispatch — the user's straightforward question ("Does
gross income include employer pension contributions?") is neither answered
inline nor acknowledged/deferred. It vanishes. Under the decided policy this
is a bug, not a design question.
**Fix shape:** on that branch, when `userAskedQuestion`, run the existing
interruption dispatcher (which already implements "Fyn decides") before
parking the step; pin with a test on the exact Tessa shape. Small director
change; turn-intent stamps already truthful on these rows.

### A2. `ai_messages` forensic columns have no purge path
The forensic/debug columns on `ai_messages` (raw payloads captured for defect
investigation) accumulate indefinitely — PII bloat with no retention mechanism,
unlike the episodic blobs which have cold-archive + 6-year purge commands.
CSJ-deferred to a future DB-hygiene sweep (memory:
`project_ai_messages_forensic_columns_need_purge`).
**Done looks like:** a scheduled purge command (mirroring the `fyn:episodic:*`
family) with an agreed retention window.

### A3. Legacy `SubscriptionPlanSeeder` note
Backend engineering-internal cleanup flagged during the 2026-07-20/21 audit
remediation and consciously left (CSJTODO 2026-07-21: "Remaining deferred
(engineering-internal)"). Legacy seeder shape predates the pure-freemium
lifecycle; harmless but stale.
**Done looks like:** seeder aligned with the freemium plan model, one reseed
verified locally + csjones.

### A4. Composer Task 3.3 — `house_view` narratives (CSJ domain)
Open since 2026-06-16. The cross-module plan composer works without them; the
narrative copy layer is CSJ's domain and deliberately unstarted.

### A5. Composer Task 4.4 — episodic recall de-ranking
Open since 2026-06-16. Recall currently doesn't de-rank stale episodic memories;
deferred as non-blocking when the composer shipped.

---

## B. /m (mobile web)

### B1. /m milestone-banner fix awaits build + deploy
The fix is committed in the main repo (`2772831` on `codex/savetax-allowance-ctas`,
now on dev) but the /m **bundle** on csjones predates it — it needs a
`./deploy/csjones-fynla/build.sh` + `public/build/` upload, which is CSJ's call
(memory: warn before SPA rebuilds). Until then csjones /m shows the old
milestone-banner placement.
**Done looks like:** CSJ green-lights a bundle build + upload; visual check of the
level card / banner order on csjones /m.

### B2. csjones-only bug-report FAB deliberately NOT ported to native
The floating bug-report button on csjones /m is a dev-environment affordance. Its
absence from the native SwiftUI app is **by design** (parity ledger decision) —
do not "fix" it in any future parity sweep.

### B3. /m hardcoded-hex sweep — DONE except the style.css body (CSJ ruling needed)
Task 7 landed (`fce7ca3`): Login.vue + GamificationCelebration.vue hex-free,
confetti palettes in a `tokens.js` mirror, Dashboard check-stroke on
`currentColor`, three missing guide tokens added to `:root`. Two gradient-stop
nearest-picks (`#2c2466` → horizon-500, `#141a2e` → horizon-600) are listed in
the commit body; before/after login screenshots exist for CSJ's eye.
**Open decision:** the `style.css` BODY was deliberately NOT swept — the parity
ledger documents `#6B7280` as /m's *rendered* neutral-500 truth while the
`--neutral-500` variable says `#717171`. CSJ must rule which value neutral-500
IS before that sweep can be mechanical (a blanket literal→var sweep would
subtly restyle every /m label and contradict the ledger's live-rendering calls).

### B4. Deploy gotcha recorded: /m ships from `public/m-build/`
The /m SPA bundle lives at `public/m-build/` (built by the same
`deploy/csjones-fynla/build.sh`), separate from `public/build/`. A deploy that
rsyncs only `public/build/` leaves /m on the old bundle silently — this bit the
2026-07-23 verification (an apparent Dashboard dead-token bug was just the
stale bundle). Any /m deploy checklist must upload BOTH directories.

---

## C. Native iOS (SwiftUI)

### C1. Diagnostics wiring (audit P1-9) — design call needed
The native app has a diagnostics-export surface but the wiring (what gets
collected, where it goes, how the user triggers it) is an unresolved design
decision from the 2026-07-20 audit. Parked as "P1-9 design call" — needs CSJ's
choice before implementation.

### C2. `framed {}` hero-persistence helper duplicated 18×
The /m-parity sweep transcribed the hero-persistence wrapper per screen
(transcription-style, 18 copies) rather than abstracting. A shared component
would halve the code — consolidation only if CSJ wants it (handover 2026-07-21:
"if CSJ ever wants the consolidation").

### C3. `TolerantDecoding` gap — non-optional Int/String from string tokens
`TolerantDecoding.swift` (from the live E2E defect fix) covers `Decimal`, `Int?`,
`String?` arriving as string tokens / `{}`-null. **Non-optional** `Int`/`String`
from string tokens is uncovered — no current payload needs it, but a future
endpoint emitting a string-wrapped required int would fail decode.
**Done looks like:** extend the propertyWrapper family + unit rows when a payload
first needs it (or proactively in a hygiene pass).

### C4. Programme tech debt carried since Package 1
Three items restated in every package handover, still open:
- **Centralise ownership-share calculation** (duplicated logic vs the
  `CalculatesOwnershipShare` trait's single-source rule).
- **Simplify balance-history orchestration** (plus the audit's "balance-history
  magic values" note).
- **Split adviser-pack collection responsibilities** (one collector doing too
  many jobs).

### C5. 6 StoreKit hosted-config tests red LOCALLY only
Green in CI; the local reds are environmental (hosted StoreKit config vs local
Xcode). Standing instruction: **don't chase**. Listed so a future session doesn't
burn time on them.

### C6. TestFlight install report — CSJ action
Build 1.0(2) of Fynla Dev (staging backend) is uploaded and VALID; CSJ is invited
as internal tester (c.jones@csjones.co). Awaiting CSJ's install + report on a
physical iPhone.

---

## D. Ops / security

### D1. `fynla-dist` keychain password sits in `ios-native/TESTFLIGHT.md`
Acceptable for a local dev-machine keychain, but it must be **rotated/relocated
before any production release flow** (handover 2026-07-21 debt note). The file is
in the repo.
**Done looks like:** password rotated, TESTFLIGHT.md references a keychain lookup
or env var instead of the literal.

### D2. MFA code-relay script lives only in session scratchpads
The live-E2E code-relay pattern is documented (memory:
`reference_native_live_e2e_pattern`) but the script itself is recreated per
session. A committed `scripts/` version would make native live-E2E reruns one
command.

---

## E. Standing context (not tasks)

- **dev → main release pending** — prod (fynla.org) has none of the July waves
  (#638–#643, interruption intelligence, native backend endpoints). Deliberately
  held; we are dev-only for the time being per CSJ 2026-07-22.
- **csjones runs `codex/savetax-allowance-ctas`** (dev merged in), not `dev`
  proper — account for this in any deploy.
- **PR #249** (Python Agent SDK sidecar) — parked on purpose; never merge or
  auto-delete.
