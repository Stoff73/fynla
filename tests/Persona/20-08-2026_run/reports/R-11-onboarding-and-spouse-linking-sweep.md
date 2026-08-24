# R-11 — Registration, verification, onboarding and spouse-linking sweep

**When:** 2026-08-21 13:45–15:10 · **Surface:** desktop web, `localhost:8000`
**Driver:** **Playwright MCP, real pointer clicks**, visible Chrome, in a dedicated
isolated browser context. **Accounts:** throwaway only — `users.id 20` Priya Raman
(`pt.throwaway.primary+0821@example.com`) and `users.id 30` Arjun Raman
(`pt.throwaway.spouse+0821@example.com`). **No persona data touched. David (16) and
Sarah (17) untouched.**

---

## Done

### Isolation, so nobody else's run was disturbed

`/register` redirected to `/dashboard` — the shared Chrome profile held a live session
as **Adam Hall (`users.id 19`)**, one of a pair (18/19, `wb.` prefix) created 20
minutes before I started by another agent. Signing out would have revoked their token.

Instead I opened a **second isolated browser context** on the same visible Chrome
(`browser.newContext()`), with its own cookie and storage jar. It landed on `/register`
without redirecting, confirming zero auth state. That agent's session was never touched.

### The journey, end to end

| Stage | Result |
|---|---|
| Cookie banner | **Defect — W-0050** (below) |
| Registration | `POST /api/auth/register` → **201**. Held in `pending_registrations`, not `users` — the account cannot exist unverified. Good design. |
| Email verification | Code `222922` read from the DB myself. Entered six boxes one at a time with 300 ms gaps. `POST /api/auth/verify-code` → **200**, user created as `users.id 20`, pending row consumed, landed on `/onboarding?newUser=1`. **GREEN** |
| Onboarding step 1, About You | All 12 fields + Health & Lifestyle persisted correctly. **GREEN** — see the W-0006 note below |
| Onboarding step 2, Family | **Defect — W-0051** |
| Onboarding steps 3–6 | Assets (4 sub-tabs), Debts, Income, Spending walked. Skip-confirmation modals behave correctly. |
| Spouse linking via `/settings/family` | **GREEN, completely** — see below |

### Spouse linking is correct — the one thing I most expected to break

`POST /api/user/family-members` → 201, "Spouse account created successfully."

```
users.id 20  spouse_id = 30        users.id 30  spouse_id = 20     reciprocal
family_members 46 (user 20) linked_user_id = 30                    forward
family_members 47 (user 30) linked_user_id = 20                    reciprocal
SpousePermission 20 -> 30  accepted
SpousePermission 30 -> 20  accepted                                auto-accepted both ways
```

Worth recording for the playbook: when the app **creates** the spouse account,
`SpousePermission` is accepted in both directions automatically — no manual acceptance
step is needed. Playbook §1.0 step 12 assumes a manual accept; that applies to linking
an account that already exists, not to this path.

The email field also reveals correctly and is fully hit-testable when Spouse is
selected — `pointerEvents: auto`, not disabled, 624×42, and `document.elementFromPoint`
at its centre resolves to the field itself. That is the same DOM-reveal evidence
pattern the coordinator asked for on W-0014, applied here and passing.

### Defects raised

**W-0050 — you cannot create an account without consenting to Google Analytics and
Awin affiliate tracking (high, `compliance-lead`, handoff `build-lead`).**
Taking the privacy-preserving option my own rules require — Decline, then Continue
Without Cookies — leaves `/register` with **zero `<form>` and zero `<input>` elements**.
The page reads "Cookies Required — Cookies are required to create an account. They
allow us to keep you securely signed in", with **Accept Cookies & Continue** as the only
control. The justification is untrue: `acceptCookies()` (`cookieConsent.js:31-45`) sets
the flag and loads **Google Analytics and the Awin MasterTag** — nothing else. Session
cookies never depended on it; `XSRF-TOKEN` was present in `document.cookie` throughout.
Proven live — clicking Accept immediately fired
`https://www.googletagmanager.com/gtag/js?id=G-3Y8DL3QB09`, which I blocked at the
network layer. That is a **hardcoded production measurement id** (`cookieConsent.js:6`),
so any local or test run that accepts sends hits to the live property.

**W-0051 — onboarding creates a spouse family member with no link, calls it a "Linked
account", and removes Edit and Delete (high, `build-lead`).**
The onboarding family form has **no email field**, so there is nothing to link by.
`family_members.id 25` saved with `linked_user_id = NULL` and `users.spouse_id = NULL`,
yet renders as "Linked account — edit or delete by logging into the spouse's account"
with **zero buttons**, while the child added in the same session has Edit and Delete.
Both `FamilyInfoStep.vue:61` and `FamilyMembers.vue:96` branch on
`relationship === 'spouse'` instead of `linked_user_id`.

**It compounds.** Linking the spouse properly afterwards does not reconcile the orphan —
the household ends with **two** "Arjun Raman / Spouse" cards, both captioned "Account
Linked", both carrying the linked-account notice, and **neither deletable**, though only
one has a real link. Reached by following the product's own happy path, with no route
back on any surface.

### W-0006 strengthened with a cross-path finding

Health, smoking and education **persist correctly through onboarding step 1**
(`health_status='yes'`, `smoking_status='never'`, `education_level='postgraduate'` on
`users.id 20`, first attempt). W-0006 says the same three fields never persist via
`/settings/health`. So two mechanisms write the same columns and only one works —
the fix should **converge on the onboarding path's behaviour** rather than add a third
implementation (Rule 20). Addendum written onto W-0006.

---

## Not done, and why

- **The coordinator's two folded-in checks.** The cap-lift-without-re-login test and the
  W-0014 Joint Owner select reproduction are **not started** — the sweep produced two
  defects that needed proper diagnosis and write-up first. Both are next.
- **Onboarding steps 7 (Estate) and 8 (Goals)** not reached; steps 3–6 were walked and
  skipped rather than filled, since their fields duplicate the module forms the playbook
  already covers in depth.
- **Nothing verified from the spouse's side.** Arjun (`users.id 30`) was created but
  never logged into. His side of the household is **untested**.
- **`/m` and iOS not touched** on this journey.

---

## Three false positives I chased and cleared — do not re-raise

1. **Dates were NOT stored a day early.** `tinker` rendered `1977-06-02` as
   `1977-06-01T23:00:00.000000Z` — that is Eloquent's `date` cast serialising to UTC
   under `Europe/London`. The raw column holds `1977-06-02`. I checked the raw value
   before writing anything up.
2. **Onboarding does NOT dead-end at Assets & Wealth.** Six Continue clicks appeared to
   do nothing; the cause was a correct "Skip Assets & Wealth?" confirmation modal whose
   backdrop was properly intercepting clicks. Answering it advanced normally.
3. **The blank `/onboarding` page was MY OWN doing.** `#app` emptied and the console
   showed `net::ERR_FAILED`. Cause: the `context.route()` interceptor I had installed to
   block the analytics beacon was still attached and failing. `unrouteAll()` restored the
   page immediately (`appLen: 16770`). **Not an app defect.** Recorded so nobody chases it.

---

## The method change is already earning its keep

Rule 14 diagnosis on the "dead end" surfaced this from Playwright's own click log:

```
element is visible, enabled and stable
<div class="fixed inset-0 bg-black/50 transition-opacity"> ... intercepts pointer events
```

The button was visible, enabled and stable — **a dispatched DOM click would have fired
its handler and reported success.** The real pointer click correctly refused. That is
exactly the blind spot the re-run was commissioned to close, demonstrated on the first
journey it was pointed at.

---

## Assumptions

1. Registering a throwaway account and accepting a first-party cookie banner on
   **localhost**, with synthetic data, is within the assigned task. I blocked the
   third-party beacon rather than let a test hit reach the production Google Analytics
   property, and recorded the acceptance in W-0050 so the run's own evidence is not
   mistaken for a user freely consenting.
2. `email_verified_at` being NULL on `users.id 20` is **not** a defect: `MustVerifyEmail`
   is commented out on the User model and the column is NULL for every user including
   seeded ones. The pending-registration design makes an unverified account impossible.
   **Latent trap worth knowing:** if anyone ever adds the `verified` middleware or
   enables `MustVerifyEmail`, every existing user is locked out.

---

## Needs

1. **Teardown list:** `users.id 20` (`pt.throwaway.primary+0821@example.com`) and
   `users.id 30` (`pt.throwaway.spouse+0821@example.com`), plus `family_members`
   25, 26, 46, 47 and both `SpousePermission` rows. **Note `users.id 30` was created by
   the app, not by me** — the spouse-link flow creates the account, so teardown must
   cover it.
2. **W-0050 needs a compliance ruling, not just a code fix** — whether consent already
   collected through the wall is valid, and what that means for analytics gathered on
   its basis. That is a CSJ decision.
3. **Whose are Adam and Beth Hall (18/19)?** I worked around their session rather than
   disturb it. If they are abandoned, they are cluttering the shared browser profile.

---

## Noticed

- **Playbook §1.0 step 12 needs a correction**, which I will make: `SpousePermission` is
  auto-accepted both ways when the app creates the spouse account. The manual
  accept-from-both-sides step applies only when linking a pre-existing account.
- The `/settings/family` page also carries a **Charitable Bequest** prompt ("Leaving 10%
  or more to charity can reduce your Inheritance Tax rate from 40% to 36%") — a fourth
  surface touching charitable bequests, alongside the will builder, the Estate module
  and `/m`. Worth checking it reads the same source as W-0020/W-0023/W-0037 rather than
  being a fifth mechanism (Rule 20). Not tested.

---

## Context position

Roughly **490k**. Inside the Rule 22 buffer, with room for the two folded-in checks.
