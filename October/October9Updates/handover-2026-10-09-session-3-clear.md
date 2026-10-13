---
type: handover
mode: context-clear
date: 2026-10-09
session: 3
branch: dev
trigger: context-handover skill (context budget)
---

# Context Clear Handover — 2026-10-09, Session 3

## Immediate state

Walk D was finished and its fixes (R38, R39) retested on csjones. The walk file and TODO are up to date. Walk E (edits) is next.

## The thread

- **Before item 18:** CSJ asked for full regression walks. Rule: every anomaly is noted, fixed and retested. The walk file is `October/October9Updates/regression-walk-2026-10-09.md`; see its "Where the walk stopped" section.
- **Retested on fynla.org from release ab:** R1, R2 (web + /m), R4–R8, R10, R12, R13 (web), R14–R18. R13 on /m is still to see.
- **Walked on fynla.org:**
  - B6–B8 as Sam (809) and Morgan (808).
  - C1–C4 and D1–D4 as Casey (810, normal registration, free).
- **Found and fixed on dev, NOT released.** All retested on csjones (Drew 508, Pat 506, Rory 504, Alex 507):
  - R21: retirement target leaves out RAS payments.
  - R22: invited partner's spending is prefilled.
  - R23: recap's "tax saved" sentence.
  - R24: a joint owner's records count for completeness.
  - R25: 40% pension slice is pay only (tax-compliance-reviewed; IncomeBandStrategy's taper sentence suppressed when interest or dividends sit above the threshold).
  - R26: one share format, "50%".
  - R27: Fyn writes whole pounds; one constant `ComplianceRules::CURRENCY_FORMAT`.
  - R28: Fyn lists, `<ol>` and loose `<ul>`, one helper `utils/fynListItems.js` for web and /m.
  - R29: History hides empty conversations.
  - R31: joint hint.
  - R32: Cash ISA type filled by `SavingsStore`, with a migration.
  - R33: web "Bank Accounts" card label.
  - R34: gender "other" wording.
  - R35/R36: completeness banner uses one module map and refreshes on `fyn-screen-refresh`.
  - R37: web Cash ISA add is gated on the investment cap, using server counts `isa_count`/`isa_limit`.
  - R38: a restated record fills the add form.
  - R39: the duplicate question at the cap offers only "The same one".
  - R20: caption no longer claims a line (part only).
- **Not defects (ruled out):**
  - R30: both alternatives in /m top actions; the card carries the note.
  - Sam's web tab dropping to /login: desktop inactivity sign-out (memory updated).
  - Retirement age 67 is the documented assumption (W-0196).
  - Pat asked employment: nothing had given it.
  - "78% of target" is the Retirement card's bar.
- **Dead end:** the memory said `mScaffoldBridge` copies /m's bearer to desktop. That is stale and has been corrected in `reference_m_desktop_auth_bridge.md`.

## Files touched this session

PRs #1187–#1207 merged to dev (see each PR):
- **Server:**
  - `ModuleDataRequirementsService`, `OnboardingChatDirector`, `RequiredCapitalCalculator`, `UserProfileService`, `WalkFormPrefill`
  - `TaxStrategyMath`, `PensionTaxReliefStrategy`, `IncomeBandStrategy`
  - `ConversationHistoryService`, `ComplianceRules`, `FynSystemPrompt` (+ snapshot), `CaptureForms`
  - `SavingsStore` (+ migration `2026_10_09_140000`), `SavingsController`
- **Web:**
  - `utils/ownership.js` (+16 components), `utils/fynListItems.js`, `AiMessageContent.vue`
  - `GamifiedDashboard.vue`, `router/index.js`, `ModuleStatusBar.vue`
  - `CashOverview.vue`, `store/modules/savings.js`, `utils/projectionCaption.js`
- **/m:** `utils/fynText.js`, `views/dashboard.css`, and four /m module views (share format).
- **Docs:** the walk file, shots, `todoCurrent/TODO.md`, this handover.

## WIP commit

- Docs-only snapshot (walk file, shots, TODO, handover) on `docs/context-handover-2026-10-09-s3`, merged to dev by PR. The SHA is in that PR.

## Open decisions

- **R9 (DECISION, CSJ):** which figure is "your pot at retirement"? Planning projection £561,689 vs Monte Carlo median £463,839 for Rory. It now also covers R20's remaining card questions:
  - the heading "(using high probability of 80% of achieving 6.5% returns)" describes nothing on the chart, which draws the 75–90% bands only;
  - whether the Portfolio Projection card should show its assumed £143 a month contribution (it is shown only on the Projections tab).
  - Default recommendation: the planning projection leads; the Monte Carlo card shows its range.
- **R11 (DECISION, CSJ):** /m top-action titles get about 87px of a 271px row.

## Pick up from here (auto-continue contract)

Current item: todoCurrent/TODO.md **18** — see its "Where we stopped (2026-10-09, session 3, context clear)".

1. **Walk E (edits) on fynla.org as Casey (810)**, web 1440 then /m 390:
   - E1: web savings edit form with a read-back.
   - E2: a Fyn edit, "change my Barclays balance to £11,000" (the form opens with the change filled in).
   - E3: an unchanged save says "Already on file".
   - E4: an /m edit.
   - E5: a joint owner edit (Sam or Morgan, joint Nationwide id 1640).
2. **Then F (actions; check R3 there: holistic plan reads an unknown emergency fund as 0 months), G (how-tos), H (logs + model; purge accounts at the end).**
3. **Then one release of dev to main:**
   - It needs migration `2026_10_09_140000_fill_isa_type_from_savings_account_type`, run one file.
   - It needs both bundles (fynla-org `build.sh`).
   - Write the script like `release-prod-2026-10-09-ab.sh` and give CSJ the `! bash` line (auto mode blocks main and prod).
   - Then retest every fixed flow on fynla.org: R19–R39, the C4 recap wording (R23), D1 (R37), R13 /m.
   - Update the patch notes (`October/October8Updates/patch-notes-2026-10-08.md`) and purge all walk accounts.
4. **Before walking csjones again:** rebuild both bundles from dev in `scratchpad/build` (session 07808929) and rsync. The web bundle is at `7d5805727` (lacks #1204); /m is at `8cb4b25f3`.

## What the next Claude needs to know

- **Hooks:**
  - A Bash command that contains "fynla.org" plus `npm run build` is blocked. Keep PR bodies that mention production out of build commands.
  - Variable file lists in pest are blocked; name the files literally.
- **Playwright:**
  - Fyn's chat collapses after a check-step navigation. Open it with `getByRole('button', {name: /Chat with Fyn/})`.
  - `button:has-text("Save")` also matches chips like "…save £4,280 in tax". Use `getByRole('button', {name: 'Save', exact: true})`.
  - /m: `button.md-fyn-dock--bar` opens Fyn; the input is `#md-fyn-input`; send with `button.md-fyn__send`; close with `button.md-fyn__close`.
  - /m logs in at `/m/app/login`. To switch /m user, clear localStorage `m_scaffold_token` first.
  - Tabs can vanish between calls; list them first.
- **Codes:**
  - Registration codes are in `PendingRegistration.verification_code` (by email).
  - Sign-in codes are in `EmailVerificationCode` (by user_id).
  - csjones: `php zz_code.php <id>`. prod: the ssh-fynla MCP tinker.
- **Tests:**
  - `writeFormRecords` errors now carry `entity_type`.
  - Use the real form path in tests (R39 lesson: a hand-built error hid the bug).
- **Releases:** web and /m bundles are separate. Server-only fixes need just `git pull` on csjones.

## Branch / deploy state

- Branch: dev (clean after the docs PR).
- fynla.org: main `f45bc67fd` (release ab).
- csjones: dev `ea11f2898` server; web bundle `7d5805727`; /m bundle `8cb4b25f3`.
- On dev, not on main: R19–R39 (#1185, #1187–#1207) and docs.
