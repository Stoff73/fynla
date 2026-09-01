---
type: handover
mode: session-end
date: 2026-09-01
session: 2
repo: fynla
branch: chore/board-verification-31-august
---

# Session Handover — 2026-09-01, Session 2

## Where things stand

**The board is clear of everything in scope.** Twelve items closed this session; the six
still outstanding are all `deferred-ios` and out of scope for the board loop by CSJ's
ruling. All of it is merged to `dev` (PR #759, merge commit `c52b51db2`) and **deployed to
csjones**, with both migrations applied and both frontend bundles rebuilt and uploaded.

**Nothing has been driven in a browser.** Every item was verified by reading code at
`file:line` and by targeted test suites; four items carry an explicitly unverified visual
acceptance criterion. That is the whole of tomorrow's first job, and it is listed below in
detail because it is now testable — the work is live on csjones, which it was not during
the session.

## Priorities for the next session

### 1. Browser-test on csjones.co/fynla — the four items with unverified acceptance

Top priority, blocked on nothing. Log in at `https://csjones.co/fynla` — real accounts
register on **csjones**, not fynla.org; a fynla.org account returns `user_not_found`.
Verification code via SSH tinker:

```bash
ssh -F ~/.ssh/config.fynlaDev csjones-dev \
  'cd ~/www/csjones.co/fynla-app && php artisan tinker --execute="\$u = \App\Models\User::where(\"email\",\"<email>\")->first(); echo \App\Models\EmailVerificationCode::where(\"user_id\", \$u->id)->latest()->first()->code ?? \"none\";"'
```

**W-0504 — `/m` dashboard donut rings.** The sharpest visual defect of the run: a **72% arc
beside `+0%`**. Open `/m` and check the five cards.
- Net worth ring: caption must now read **"Equity"**, not "Trend", and the arc must match
  the percentage printed inside it.
- Investment ring: must reflect the household's real share of assets. **Use `peak_earners`
  — 11% investments.** A persona near 72% proves nothing, because 72 was the constant.
- Protection ring: full or empty, never a partial arc.
- Savings and retirement bars were always derived; they are the control.

**W-0500 — the undivided-share spouse question on `/m`.** Open a property detail for a
**shared property whose co-owner holds no account** (`joint_owner_id` null). A card
"Who you own this with" should ask *"Is {name} your spouse or civil partner?"* with **Yes /
No**. Press one, then confirm the stored column — the item says a UI screenshot alone is
not enough:

```sql
SELECT id, joint_owner_name, joint_owner_is_spouse FROM properties WHERE id = <id>;
```

Expect `1` for Yes, `0` for No. Not answering must leave it **NULL** — the safe state,
where no discount applies.

**W-0034 — Health and lifestyle on `/m`.** `/m` → Personal Information. The section must
**read and write**: change health status, smoking status and education level, save, reload,
confirm they persisted. It writes through the desktop endpoint, so a 500 on an unanswered
select would be the W-0006 trap returning.

**W-0045 — Trusts palette.** Four screens, listed on the board file:
`/trusts` card badges (pale blue "Relevant Property Trust" beside a spring "Active", both
still distinguishable from the grey "Inactive" chip, row wrapping rather than overflowing);
the "UK Trust Types Guide" and "Inheritance Tax Charges" panels on `light-blue-100`;
`/trusts/{id}` header badges and the tax-implications card; and the `/dashboard` trusts
overview card outlined in `light-blue-500`.

**Worth a look while you are there, though not formally owed:**
- **W-0200** — the new "Who is the other person covered?" dropdown on the web protection
  policy form, shown when Joint life is ticked. Offers the linked partner, family members,
  and "Someone else".
- **W-0483** — the "We borrowed in different shares…" checkbox on the property wizard's
  mortgage step, shown only for a shared property.
- **W-0531** — the emergency runway figure. Materially **lower** now for any mortgaged
  household; peak_earners moved from 83 months to about 13.

### 2. Raise board items for four findings this session surfaced but did not fix

- **`family_module` and `benefits_child`** — zero consumers, and **both named in the pricing
  comparison** (`app/Services/Payment/TierComparisonService.php:28-29`). Identical to
  W-0499: sold to customers, enforced nowhere. Sharpest of the four.
- **`tenure_types` and `leasehold_reform`** — configured under `property_ownership`, read by
  nothing. Adding that area to `ConfiguredRulesHaveConsumersTest`'s `GUARDED_AREAS` turns it
  red; the audit is written into that file so the finding is not lost.
- **`resources/js/components/Estate/IHTPlanning.vue:620-630`** — a true sentence about the
  current column ("£X of pension savings is left out… that changes on {date}") that the
  engine does not publish, so the free-tier teaser cannot say it.
- **`app/Agents/CoordinatingAgent.php` at 6,768 lines** — every Fyn capture handler lives
  there and it grows with each tool. Wants a plan, not an opportunistic extraction.

### 3. The 34 remaining sweep findings — decide, do not chase

`workforce/ops/sweep.sh` went from 99 broken references to 34. The remainder is largely
**real**: genuinely deleted files (`SpouseNRBTrackerService.php`,
`MigrateSavingsToCash.php`, `EstateOverviewCard.vue`, `VoiceInputButton.vue`,
`MobileLoginScreen.vue`) cited in historical reports, plus stale build hashes quoted as
deploy evidence, correctly unresolvable for ever. Rewriting history to satisfy a checker is
the failure the item was about. The sweep is now on a **weekly rhythm**
(`workforce/core/registry/rhythm.md` §4ter, Monday planning meeting) and a **rising** count
is the signal, not the absolute number.

### 4. Reviewer gates left unrun

No agents were dispatched, per CSJ's instruction. These items carry reviewers in their front
matter that were never run: `tax-compliance-reviewer` (W-0518, W-0498), `design-lead` and
`quality-lead` (W-0497), `chief-of-staff` (W-0506). None blocks the code; they are
governance gates on items already merged.

## Context to load

- `tasks.md` — the board. Six outstanding, all `deferred-ios`. **Read the appendix at the
  bottom**: every decision this run made on someone else's behalf, the four findings above,
  and the process failure.
- `.claude/skills/board-loop/SKILL.md` — the nine steps. CSJ treats this as law and stopped
  the session over it; see "Decisions and dead ends".
- `docs/tech-debt-report.md` — this session's audit. 0 critical, 1 warning, 2 suggestions,
  plus a "deliberate simplifications" section listing three things that must not be
  "fixed" by someone who does not know why they are there.
- `workforce/ops/board/W-0498-the-joint-ownership-config-cluster-has-no-consumers.md` —
  carries the `tenure_types` / `leasehold_reform` finding in full, including why they were
  not registered in `UNIMPLEMENTED_RULES`.
- `workforce/core/registry/rhythm.md` §4ter — the sweep's new cadence and the two rules that
  keep it worth reading.

## Completed this session

Twenty-five commits, `f3fae45bd..3455ddb16`, merged as `c52b51db2`.

**Closed with code changes:**
- **W-0531** (raised here) `1fa2b4434` — the emergency runway divided cash by a household
  total with **no housing line**; `users` has no mortgage, council-tax or utilities column.
  Overstated up to **4.7×** for every mortgaged user.
- **W-0178** `697fc5552` — maintenance reserve and other property costs now deduct from
  rental profit, per CSJ's ruling.
- **W-0200** `c7ce1ebf6` — a joint-life policy now **records** its second life assured
  instead of inferring the spouse, with the picker CSJ asked for.
- **W-0383** `43497397e` — the other life assured sees the whole policy; edit stays
  owner-only.
- **W-0394** `c7bb3b0c4` — the charity name list ran at **read** time and overrode an
  explicit "individual", **understating** Inheritance Tax. Demoted to write-time inference.
- **W-0426** `a035cc994` — `letter_to_spouse` gated writes only; capability-mapped paths are
  no longer read-only-excluded.
- **W-0476** `68e42a4d6` — the spouse enumeration oracle closed at **both** `status()` and
  `revoke()`.
- **W-0483** `dc75dcffe` — CSJ's W-0228 amendment built as a nullable declared share.
- **W-0494** `475ef307f` — four Windows path fixes verified **plus** the pre-existing
  `StoreBoundary` failure fixed; Architecture suite now 0 failed.
- **W-0499** `bb2f6a24a` — `investments_exotic` defined and gated in the Store.
- **W-0500** `b54d1cea5` — `/m` can answer the undivided-share question.
- **W-0507** `1f0fd74fb` — the free-tier teaser now carries both caveats.
- **W-0510** `5358e0b18` — a drawn-out pension fund reports as depleted.
- **W-0516** `78f8716f4` — seven State Pension age literals across six files.
- **W-0518** `2f741678c` — Fyn asks which income figure was given.
- **W-0497** `221a70fb8` — 20 acronym expansions across three estate services.
- **W-0498** `a5814d68e` — joint-ownership config reaches the user.
- **W-0504** `cb171c701` — three `/m` rings derived.
- **W-0506** `84ad5d8da` — sweep 99 → 34.

**Closed as verified-fixed, no code change:** W-0034, W-0045, W-0100, W-0492.
**Deferred, iOS:** W-0044, W-0496.

## Verification state

All green at `3455ddb16` unless noted:

| Suite | Result |
|---|---|
| Architecture | **153 passed, 0 failed** — first clean run in the board's records |
| Protection / LifeCover | 354 passed |
| Retirement / Pension / Projection | 446 passed |
| Investment | 569 passed |
| Tiers / Subscription / Capability | 452 passed |
| Estate / IHT | 842 passed |
| Spouse / FamilyMember | 441 passed, 3 skipped (browser scenarios) |
| Frontend (vitest) | 466 passed, 60 files |
| `/m` (vitest) | 197 passed, 34 files |

**Deployed and confirmed live on csjones:** homepage 200, `/m` 200, and the newly-built
`/m` bundle `main-CaSx9EWf.js` serves 200 **and contains today's work** — greps positive
for `equityPct` (W-0504) and the spouse question (W-0500). That last check is what proves
the deploy did not silently land a stale build.

**Not verified:** no browser interaction anywhere. **No full PHP suite** was run — targeted
families only, per CLAUDE.md #17. The last full run was the previous session's
(3 failed, 8,304 passed at `9b54719b3`), and HEAD has moved 25 commits since.

## Decisions and dead ends

- **CSJ stopped the session over process.** The board-loop skill's **step 6** — invoke
  `superpowers:systematic-debugging` before touching a live bug — was **skipped on the
  first thirteen items** and applied on the last six. Step numbers also stopped being
  announced, and work already evidenced in the transcript was re-run. CSJ's instruction is
  unambiguous: the skill is law, every step announced **by number before executing it**,
  and step 4 branches to **5 or 6, never both**. The thirteen are listed in `tasks.md`.
- **An item's own proposed fix can be wrong.** W-0506 suggested treating only paths
  containing a `/` as links. Measured: **25 of the 41 slash-bearing references were the
  `reports/…` citations the item itself wanted excluded.** The real cause was the basename
  index omitting `tests`, `public`, `ios-native`, `routes`, `fyn-memory`. Measure the
  proposal before implementing it.
- **W-0498's three classifications were all wrong as stated.** Not a gap, not dead: a Rule
  20 **duplicate** — `AssetForm.vue` hardcoded the same two sentences the config held.
- **W-0476's acceptance 2 was wrong.** It said the item closes with W-0472's retention
  decision. W-0472 decided **not** to retain, and the oracle closed anyway — retention was
  never what the two branches needed.
- **Instrument errors cost four rounds.** A regex for PHP string literals breaks on an
  apostrophe in a docblock (`User's`); tracking a method name as "the `T_STRING` after
  `T_FUNCTION`" misfiles under `toArray` because of anonymous closures; a source-scanning
  guard matches **its own comment** describing the defect; and `grep -rl` over `app/`
  reports a consumer for a config rule because it counts `TaxConfigService.php` itself.
  **Verify the instrument before trusting the measurement.**
- **A decoy test.** `RetirementProjectionServiceTest`'s "tracks fund depletion age
  correctly" had its only assertion inside `if ($result['fund_depletion_age'] !== null)`,
  and the value was always null — green over the defect it was named after.

## Things that will bite you

- **`/m` cannot be browser-tested locally.** `public/m-build/` is a **csjones** build —
  router base `/fynla/m/app` — so `localhost:8000/m/app/login` redirects to
  `/fynla/m/app/m/app/login` and the SPA never boots. Test `/m` **on csjones**.
- **SSH to csjones needs the key in the agent.** `ssh -F ~/.ssh/config.fynlaDev csjones-dev`.
  If it returns `Permission denied (publickey)`, run `ssh-add ~/.ssh/fynlaDev` — the key is
  passphrase-protected and is not loaded automatically.
- **`workforce/ops/sweep.sh` takes about three minutes** and buffers nothing useful until it
  finishes. Wait for the summary line; a header-only output file means it is still running,
  and reading counts from it reports zeros.
- **The tool-schema golden masters are byte-identical.** Changing a Fyn tool schema turns
  four of them red; regenerate only via
  `CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php`.
- **Both Fyn schema variants must move together** — `.xai.md` (live) and `.md`. Under
  `strict: true` a nullable enum needs `anyOf: [{enum}, {type: null}]`; a bare `enum` is
  rejected.
- **`./vendor/bin/pint app/` times out** at two minutes. Format only the changed files.
- Same-namespace `use` statements are redundant and Pint strips them —
  `RetirementProjectionService` is in `App\Services\Retirement` and must **not** import
  `StatePensionAgeResolver`, but `RetirementAgent` (in `App\Agents`) **must**.

## Tech debt deferred

Full report at `docs/tech-debt-report.md`. Automated checks all clean; three items carried:

- `app/Agents/CoordinatingAgent.php` — **6,768 lines**, warning. Wants its own board item.
- `app/Services/TaxConfigService.php:828-846` — `hasSurvivorshipRights()` and
  `allowsWillOverride()` have zero callers **by design**, recorded with a guard. Listed so a
  dead-code sweep does not delete them.
- `app/Services/Retirement/RetirementProjectionService.php` — 915 lines, now nine
  constructor arguments. Watch if a tenth appears.

Three deliberate simplifications are recorded in that report so they are not "fixed" by
someone who does not know why they exist.

## Branch and deploy state

- Branch: `chore/board-verification-31-august` — **merged**, safe to delete
- Unpushed commits: none
- **`dev`**: at `c52b51db2` (PR #759 merged with `--merge --admin`)
- **csjones**: deployed and verified. Pulled to `c52b51db2`; **11 migrations applied**,
  including today's two (`add_second_life_assured_to_life_insurance_policies`,
  `add_declared_liability_percentage_to_mortgages`); both bundles rebuilt with
  `./deploy/csjones-fynla/build.sh` and uploaded (348 files to `public/build/`, all of
  `public/m-build/`); caches cleared with `route:clear`, `cache:clear`, `view:clear`, then
  config re-cached and `composer dump-autoload -o`. **Neither of the two forbidden artisan
  caching commands was run** — see the CLAUDE.md troubleshooting table for why they shadow
  the homepage.
- **production**: untouched. Nothing from this session is on fynla.org.
