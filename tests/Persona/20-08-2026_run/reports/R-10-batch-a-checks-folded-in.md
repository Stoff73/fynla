# R-10 — Batch A independent-confirmation checks folded into the playbook

**When:** 2026-08-21 13:00–13:35 · **Surface:** none (static verification + playbook)
**Agent:** persona-tester (replacement) · **Branch:** `dev`, local.

---

## Done

Folded the coordinator's three checks into `PASS-PLAYBOOK.md` as a new **§4.1,
Independent confirmation of Batch A** (playbook now 1,386 lines):

- **§4.1.b** — the joint-share consolidation, both accounts, web and `/m`, with the
  £47,500 + £47,500 = £95,000 arithmetic and four further joint records to cross-check.
- **§4.1.c** — the W-0014 create-modal non-reproduction, with an explicit instruction to
  settle it either way and to record a non-reproduction rather than leave it as
  folklore. Written around the real risk: a select present in the DOM but not
  hit-testable is exactly what the previous run's dispatched clicks could not see.
- **§4.1.d** — whether an upgraded user's cap lifts without re-login, split into the
  fresh-login case and the in-session-upgrade case, with the commercial reason stated.
- **§4.1.e** — Batch A's own four stated evidence gaps, recorded as open rather than
  passed.

**W-0039** (holding form has no units input) and **W-0040** (deliberate 100/0 split)
added to the §4 regression table and, for W-0039, to the §7.1 no-home-in-the-UI list.
§8 now opens with W-0039 as a **hard blocker on holdings entry**.

### Independent static verification of Batch A — done before writing the checks

The coordinator's point was that a fix agent verifying itself is not verification. Four
claims were checkable by reading the code, so the re-run can skip them:

| Claim | Result |
|---|---|
| `app/Support/SharedOwnership.php` is the single write rule | **Holds.** 20 files read it — four Store normalisers, both savings and both chattel FormRequests, `MortgageService`, `CrossModuleAssetAggregator`, `CalculatesOwnershipShare`, five controllers, three tax strategies. |
| `resources/js/utils/ownership.js` is the single display rule | **Holds.** 6 web components + the investment store + **5 `/m` views**. |
| The `100 → 50` read-side fallback is removed from `CalculatesOwnershipShare.php` | **Holds.** Removed, with a comment naming W-0014 and W-0015 as the cause. |
| Existing 100/0 rows are repaired | **Holds — and I had assumed otherwise.** `database/migrations/2026_08_21_000000_normalise_shared_ownership_percentage.php` backfills shared rows stored at 100 to 50 across five tables, deliberately excluding `business_interests`. It has run: `investment_accounts.id 14` now reads `ownership_percentage = 50.00`, where R-08 recorded 100. |

**The assumption I got wrong, and why it mattered.** I expected the backfill to be
missing, because removing the read-side rewrite without repairing stored rows would
turn the £190,000 double-count into a *disappearance* — primary owner at 100%, spouse
at £0. Batch A had already handled it. Recorded in §4.1.a so a tester who meets a stale
figure does not misdiagnose it, and so nobody re-derives the concern.

---

## Not done, and why

- **Nothing was tested live.** All four confirmations above are static. The three
  coordinator checks are written for the re-run, not executed — Batch A's surfaces are
  freshly landed and the other three batches are still in flight. **I COULD NOT TEST
  THESE IN THE BROWSER.**
- **`/m` remains genuinely untested**, by Batch A and by me. Local `/m` needs
  `public/m-build/` rebuilt, and per the `verify-m` skill `/m` is verified on csjones.
  The five `/m` views importing `ownership.js` are unproven at runtime. This is the
  single largest open gap in Batch A's evidence.

---

## Assumptions

1. **W-0039 blocks only holdings entry**, not the rest of Pass A. Everything else in
   §1 can proceed; §2.8 cannot be verified until it lands.
2. **W-0040 is not to be tested until product-lead decides.** Recorded as blocked, not
   failed. The persona is unaffected either way — its joint assets are all 50/50 or
   tenants-in-common at 40%.

---

## Needs

1. **The ID collision recurred and has already self-resolved — please confirm you are
   holding the new numbers.** Batch A and I both wrote W-0035 and W-0036 at nearly the
   same moment. Batch A's two have since been renumbered to **W-0039** (holding units)
   and **W-0040** (100/0 split); mine kept **W-0035** (target retirement income) and
   **W-0036** (Defined Benefit counted as income in payment). Your message referred to
   the Batch A pair as W-0035/W-0036 — that is now stale. Next free is **W-0041**.
2. **A `/m` rebuild or a dev leg**, so `/m` stops being the untested half of Batch A.
3. **W-0039 assigned**, since a faithful Pass A cannot enter a single holding until it
   lands.

---

## Noticed

- **The joint savings account on the repro household is owned the other way round.**
  `savings_accounts.id 29` has **Sarah (17) as primary owner** and David (16) as
  `joint_owner_id`, where playbook §1.2 enters it as David's. Not a defect — primary
  owner is whoever entered it — but it makes a useful second test case, because it
  exercises the joint-owner side of the arithmetic from David's login rather than
  Sarah's. Noted in §4.1.b rather than "corrected".
- **`SharedOwnership::primaryOwnerPercentage()` coerces an explicit 100 to 50 on a
  shared type**, and the backfill migration did the same to stored rows. That is
  precisely W-0040's subject, so it is already raised and owned by product-lead — not
  raised again here, only recorded in the W-0040 regression row so the tester knows the
  current behaviour before the decision lands.
- The migration's `down()` is deliberately a no-op with its reasoning stated: a
  deliberate 50/50 is indistinguishable from a corrected 100/0, and restoring 100/0
  would reinstate the double-count. Correct call; flagged only so nobody expects a
  rollback path.

---

## Context position

Roughly **300k** of budget consumed. Comfortably inside the Rule 22 buffer.
