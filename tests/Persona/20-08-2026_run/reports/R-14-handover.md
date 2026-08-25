# R-14 — Handover (written at ~705k, deliberately early)

**Written:** 2026-08-21 · **Reason:** team-lead asked for the handover at two-thirds
rather than at the 900k buffer, because a long browser transcript is the most expensive
context to reconstruct. **I am not blocked and not stopping** — this is a snapshot.
**From:** persona-tester (replacement, seeded from R-08).

Read this, then `PASS-PLAYBOOK.md`. You should not need R-09 … R-13 to resume.

---

## 1. The one thing that is mid-flight, and it is time-sensitive

**The cap-lift test is at step 4 of a 7-step protocol, with the browser session
deliberately frozen.**

| Step | Owner | State |
|---|---|---|
| 1 Register a free throwaway | me | **DONE** — `users.id 31`, `pt.throwaway.cap+0821@example.com` (Tomas Weber) |
| 2 Capture the free-tier baseline | me | **DONE** — screenshot `40-web-FREE-tier-life-event-cap-baseline-user31.png` |
| 3 Stop, touch nothing | me | **HOLDING — do not navigate, reload, click or authenticate in context [2]** |
| 4 Tell team-lead | me | **DONE** (message `36f16d3f`) |
| 5 Provision premium, verify in a **clean process** | team-lead | **DONE 2026-08-21 ~14:05** — `resolve() → premium`, `allows(life_events) → true` (was false), `life_event` rows still 1 |
| 6 Confirm to me | team-lead | **DONE** |
| 7 Capped action, **no reload** → … | me | **DONE — GREEN, lifted live at step 1.** See R-15. Steps 2–3 not needed. |

**Verified baseline for `users.id 31`:**
```
tier=free  plan=free  TierResolver::resolve() → free  subscriptions=0
life_events = 1   (free count_cap = 1 — exactly AT the cap)
TeaserGate mode(life_events)=limited   allows()=false
UI at the cap: "Add Life Event" visible + enabled, real click does NOT open the form,
               an "Upgrade" affordance appears  → gated BEFORE entry
```

**Why the subject is `life_event` and not property:** cap of 1, reachable in one step,
and the persona carries **ten** life events against it — the most commercially glaring
of the caps.

**If it is still capped at step 7, establish WHERE the staleness lives** — client-held
capability state vs a server-side per-request memo — before calling it. team-lead hit a
phantom `free` earlier today by reading `TierResolver` in the same process it wrote to
(`PremiumEntitlementResolver` memoises per user id). That distinction is the finding
either way. If it needs a re-login, that is a **commercial defect** — raise from the
block.

---

## 2. Browser state — three isolated contexts, do not mix them

`browser.contexts()` by index:

| # | Contents | Rule |
|---|---|---|
| **[0]** | The shared/original profile. Held a dead token for a **deleted** user (Batch B's `wb.` pair, since removed). Batch B left tab 0 at **430×930**, so it is mobile-width. | Not mine. Resize deliberately before any desktop check. `fynla-state` is shared across tabs in a context. |
| **[1]** | **Priya Raman, `users.id 20`, PREMIUM**, married, spouse `users.id 30`, child Meera. Has a completed mirror will (`will_documents.14` / `wills.23`), 2 properties. | The estate/premium subject. |
| **[2]** | **Tomas Weber, `users.id 31`, FREE**, 1 life event. | **FROZEN for the cap-lift test.** |

Re-acquire a context inside `browser_run_code_unsafe` — `globalThis` does **not**
persist between calls:
```js
const browser = page.context().browser();
const mine = browser.contexts()[1];      // or [2]
const p = mine.pages()[0];
```

**Why isolated contexts at all:** `/register` redirected to a dashboard because the
shared profile held someone else's live session. Opening a separate context instead of
signing them out was the right call — revoking another agent's session mid-run is
unrecoverable if you are wrong.

---

## 3. Defects I raised (block W-0050 – W-0059)

| Item | Sev | Summary |
|---|---|---|
| **W-0050** | high | Cannot create an account without consenting to Google Analytics + Awin. `/register` renders **zero** form elements after declining. Copy claims the cookies "keep you securely signed in" — untrue; `XSRF-TOKEN` was present throughout. Clicking Accept fires `googletagmanager.com/gtag/js?id=G-3Y8DL3QB09`, the **hardcoded production** ID, from localhost. Split: team-lead raised **W-0047** for the GA fallback; the cookie-wall ruling is with CSJ. |
| **W-0051** | high | Onboarding creates a spouse with **no email field**, so no link — then labels it "Linked account" and removes Edit/Delete. Linking properly afterwards leaves **two undeletable spouse cards**, both captioned "Account Linked", only one real. |
| **W-0052** | **critical** | **Regression of W-0008.** Creating any investment account 500s — `advisor_fee_percent cannot be null`. A `nullable` rule added for a `NOT NULL` column; `AccountForm.vue:971` sends explicit `null`. Assigned to `fix-batch-A` as urgent. |
| **W-0053** | high | Completing a mirror will strands the user — "Generate Spouse's Will" exists only pre-completion, `mirror_document_id` stays NULL. Routed to `fix-batch-B` as a defect. |

Also: an **addendum on W-0006** — health/smoking/education persist correctly via
onboarding step 1, so two mechanisms write those columns and only `/settings/health`
fails. The fix should converge on the onboarding path (Rule 20).

---

## 4. Verified GREEN — do not re-test unless a fix touches them

- **Spouse linking via `/settings/family`** — reciprocal `spouse_id`, reciprocal
  `FamilyMember`, `SpousePermission` **auto-accepted both ways**. No manual accept step
  when the app *creates* the spouse account.
- **Registration + email verification** — `pending_registrations` holds the account
  until verified, so an unverified account cannot exist.
- **The executor-is-testator gate, both ways** — error + `Complete & Finalise` disabled
  (opacity 0.5) when the testator names themselves; error cleared + button enabled when
  corrected; `wills.executor_name` regenerated, not inherited.
- **W-0014 does NOT reproduce** — the Joint Owner select is present, populated and
  hit-testable on the **create** modal. Explicit non-reproduction, with DOM evidence.
- **Free-tier `life_event` cap is gated before entry**, not after submit.

---

## 5. Traps and cleared false positives — do not re-walk these

**Six things that looked like defects and were not.** Each cost real time:

1. **Dates are not stored a day early.** `tinker` renders a `date` cast as
   `…T23:00:00Z` under Europe/London. The raw column is correct.
2. **Onboarding does not dead-end at Assets & Wealth.** A correct "Skip Assets &
   Wealth?" confirmation modal was blocking clicks.
3. **A blank `/onboarding` was my own `context.route()` interceptor**, still attached
   and failing. `unrouteAll()` restored it. **Never install a broad `**/*` route
   handler** — use narrow per-host patterns.
4. **This app hydrates slowly.** Wait **9–11s** on estate routes before believing a
   blank page or an empty read. Several "blank pages" and "missing buttons" were simply
   early reads.
5. **`TeaserGate::allows()` is true only for `full`** — so `false` means "not
   unlimited", **not** "cannot add".
6. **`capabilityFor()` returns `'none'` for any key absent from the matrix** — a bogus
   key returns `none` too. My `savings` / `retirement` probe looked like a premium
   defect until I found the real keys are `savings_account` / `pension_account`.

**And one of my own results I voided:** the first cap-lift attempt. Premium landed at
13:03:59, **before** the baseline I thought I was capturing at 13:09, and my own
`page.goto()` destroyed the in-session question independently. Both screenshots renamed
`33/34-web-VOID-premium-not-free-*`. **A mislabelled artefact is worse than a missing
one.**

**Told to me, do not raise:**
- The **£85** holdings movement — units are authoritative under W-0039; 333 × £255 =
  £84,915, and £85,000 was never a typed figure.
- **Sarah Mitchell (preview `users.id 24`, NOT our Sarah Jones 17)** — a `REVIEW` row
  from the bequest backfill, £10,000 → £20,000, a preserved artefact of the pre-fix
  W-0024 mirror bug. Deliberately not merged.
- David (16) and Sarah (17) gaining `Bequest` rows — that is the backfill landing.

---

## 6. Next actions, in order

1. ~~Step 7 of the cap-lift protocol~~ — **COMPLETE, GREEN** (R-15). Context [2] is now
   free to use; `users.id 31` is premium with 2 life events.
2. **Batch B regression checks**, on Priya (premium, context [1]):
   - **The Inheritance Tax cache** — `iht_calculations` was keyed on asset/liability
     hashes only, so a charitable legacy could qualify for 36% and the user keep being
     served 40% from cache. **Change a bequest and confirm the displayed rate moves with
     no other edit.** That is the check that proves the cache, not the calculation.
   - **`WillController::deleteBequest()`** deletes the row then 500s. Delete a bequest,
     confirm success is reported as success **and** the row is actually gone.
   - **The gift → `Bequest` sync with gifts present** — build a will with at least one
     gift. (Zero gifts producing zero rows is verified-by-absence, not a gap.)
   - **W-0053's fix** — the mirror must stay generatable after completion, and
     `will_documents.14` (already stranded) must be rescued, not just guarded.
3. **W-0037** — bequest priority. **Expect a failure to preserve order**, not a pass:
   the gift form has no priority field, so `syncBequests` assigns `priority_order` from
   array order.
4. **Arjun's side** (`users.id 30`) — never logged into. The spouse half of the
   household is untested.
5. **`/m`** — **ask team-lead for a rebuild first** and check the served asset hash
   (last known **`main-Df3Ab1_w.js`**). `/m` has **no HMR**; the bundle must be rebuilt
   after any `resources/mobile/` change or you are testing history. **Never rebuild it
   yourself.**

---

## 7. Standing rules that cost me something to learn

- **Real pointer clicks only.** Playwright's actionability log is diagnostic: it told me
  a `fixed inset-0 bg-black/50` overlay was intercepting a "visible, enabled and stable"
  button — a dispatched DOM click would have fired the handler and reported success.
- **Screenshots:** `animations: 'disabled'` with a 15–25s timeout. Batch B lost two
  attempts to animation timeouts. If two elements must appear together, check the pixel
  gap first — the Review error and its button are **910px apart in a 673px viewport**;
  resizing to 1440×1500 put both in one frame.
- **Verify a gate both ways** — an error shown is not proof until the gate also opens.
- **Check the tier at the moment it matters**, not once at the start.
- **Never provision, rebuild bundles, or touch test data.** Those are team-lead's.
- Exact-match locators bite: the property wizard's final button is **"Save Property"**,
  not "Save".

---

## 8. Context position

**~705k.** Handover written early by instruction, not at the buffer. Still working.
