# F-0005 — design-lead: palette breach (W-0045) + education acronyms (W-0080)

**Written under a power-loss stop order, 2026-08-21 ~14:30. Not tidied.**

Agent: design-lead (`design-palette-fix`). Branch: `dev`. Nothing committed, no PR,
no deploy, no browser, no `public/m-build/` rebuild.

---

## 1. THE ONE THING THAT MATTERS TO INHERIT

### `resources/css/app.css` — NOT TOUCHED. ZERO BYTES CHANGED.

`app.css:323` (`.badge-vct, .badge-eis { @apply bg-pink-100 text-pink-800 }` →
`light-pink-*`) was **authorised and never started.** The authorisation arrived in
the same message batch as the stop order, so there was no window between them.

**There is no half-applied change on the shared file.** `app.css` is exactly as the
other four agents left it. Nobody needs to inspect it, revert it, or reconcile it.
Whoever picks this up starts from clean.

The tester does **not** need warning about Investment surfaces — the VCT/EIS badges
are unchanged and still non-palette pink.

---

## 2. STATE OF EACH TASK

### W-0045 — trust palette breach: **COMPLETE, ON DISK, ACCEPTED**

Five files, 39 occurrences, whole Trusts module clean. All saved:

- `resources/js/components/Trusts/TrustCard.vue`
- `resources/js/components/Trusts/TrustsOverviewCard.vue` ← the fourth surface the item did not name
- `resources/js/components/Trusts/TrustFormModal.vue`
- `resources/js/views/Trusts/TrustDetailView.vue`
- `resources/js/views/Trusts/TrustsDashboard.vue`

Board item rewritten with the full mapping and reasoning:
`workforce/ops/board/W-0045-trust-badges-use-non-palette-colours.md`

Outstanding: **visual confirmation only** (4 screens, listed in the item, includes
the W-0021 badge-wrap regression check). Queued with the tester.

### W-0080 — education level acronyms: **COMPLETE, ON DISK, TESTS GREEN**

All six files written and saved. 25 vitest tests green across three specs, and the
new specs were **proven to fail** by injecting a divergent `/m` label before
restoring it — a green run on a test that cannot fail is not evidence.

- `app/Constants/ProfileEnums.php` — new `EDUCATION_LEVEL_LABELS`, the single home
- `app/Services/Protection/ComprehensiveProtectionPlanService.php` — private `match` deleted, reads the constant
- `resources/js/constants/profileOptions.js`
- `resources/mobile/constants/profileOptions.js`
- `resources/js/components/__tests__/UserProfile/ProfileOptionsParity.spec.js`
- `resources/mobile/__tests__/profileOptionsParity.spec.js`

Copy: `secondary` → **"Secondary School"**, `a_level` → **"Advanced Level or
Vocational"**. Four unchanged. Pint + ESLint clean on my paths.

**The real finding**, recorded in the item: there was no "one place" to change. Four
renderers existed and the parity spec bound only two —
`ComprehensiveProtectionPlanService` held a private `match` nothing compared
against, and would have kept rendering "Secondary (GCSE/O-Levels)" with no test
going red. The single home was created, not assumed.

**Borderline call flagged for design, independently revertible:** expanding
`A-Level` → `Advanced Level`. "A" means "Advanced" so Rule 9 applies on the same
reading that caught "RPT", but it is arguably a proper name.

---

## 3. OUTSTANDING — PICK THESE UP

1. **`app.css:323`** — authorised, not started. See §1. One line. Tell team-lead
   the moment it lands so the tester can be warned; it changes Investment surfaces.
2. **`tests/Unit/Database/ProfileEnumColumnsTest.php` NEVER RAN.** `laravel_testing_e`
   does not exist (`_a`/`_b`/`_c`/`_d` do). team-lead offered to create it; it was
   never created. Reasoning says the change is unaffected — it pins value lists with
   `->toBe()` on named constants and I only added a constant — but **that is
   reasoning, not a green run. I COULD NOT TEST THIS.**
3. **Visual confirmation** for both items — tester owns the browser.

---

## 4. BOARD ITEMS WRITTEN — ALL ON DISK

- `W-0045-trust-badges-use-non-palette-colours.md` — rewritten, sign-off framing retracted
- `W-0080-education-level-labels-carry-acronyms.md` — new
- `W-0081-m-stylesheet-hardcodes-non-palette-hex.md` — new, **queued not started**, as instructed
- `W-0082-tailwind-safelist-licenses-non-palette-colours.md` — new

### ⚠ W-0082 DUPLICATES team-lead's W-0048 — MERGE THEM

I wrote W-0082 covering the safelist root cause and the 807/916 ledger before
learning team-lead had raised the same ground as **W-0048**, owned by me. **Do not
work both.** W-0048 is the canonical item; fold W-0082's content into it and close
W-0082, or the reverse — but one of them must go.

W-0082 carries three things worth preserving whichever survives:
- `app.css:323` is the highest-leverage single line in the sweep.
- **The Risk module's 276 occurrences are a design decision for Azlan, not a token
  swap** — the palette has no five-step sequential ramp, so remapping
  green→teal→blue→red cannot be done mechanically.
- **Safelist entries must be removed LAST per cluster, not first**, or pages break
  before they are migrated.

### W-0081 — the `/m` item team-lead asked me to raise: **RAISED, NOT STARTED**

`resources/mobile/style.css` — nine non-palette hex values. The two worst are
`--neutral-400: #9CA3AF` and `--neutral-600: #4B5563`: they **invent shades of a
palette token**, so they read as Fynla tokens at every call site while being
Tailwind greys wearing a Fynla name. The palette defines `neutral-500` (`#717171`)
and nothing else in that ramp.

Full table of all nine with line numbers is in the item. Correctly left unstarted —
it is global `/m` chrome and moves every mobile screen, so it needs sequencing
against the tester's `/m` pass.

---

## 5. FINDINGS THAT OUTLIVE THIS SESSION

1. **The design guide contains an unbuildable clause.** §Badges "Info" specifies
   `bg-light-blue-100 text-light-blue-700`, but `tailwind.config.js:106-109` defines
   `light-blue` at **100 and 500 only** — `@apply text-light-blue-700` is a build
   error, not a near-miss. The nearest defined pair is **2.9:1**, below the AA floor
   the guide itself mandates. Repaired in W-0045 by pairing the specified background
   with `horizon-500` (10.9:1). **CSJ owns `fynlaDesignGuide.md` and it is flagged to
   them** — an agent quietly compensating is how the clause stays broken.
2. **Rule 11 has no enforcement behind it.** The safelist is why. See W-0048.
3. **House-style finding, deliberately not acted on:** health and smoking labels are
   sentence case; education is Title Case — the outlier in its own file. Not a Rule 9
   matter, touches four non-defective labels, and would have invalidated the tester's
   playbook strings mid-run. Raised in W-0080, not done.

---

## 6. NOT MINE — DO NOT ATTRIBUTE

Other agents' uncommitted work is in this tree (`resources/mobile/views/*`,
`resources/js/components/{Investment,NetWorth,Retirement,...}`, `tests/Persona/`).
My footprint is exactly the 11 files listed in §2 plus the four board items in §4
plus this document. David (16) and Sarah (17) untouched.

Master handover: `tests/Persona/20-08-2026_run/COORDINATOR-HANDOVER.md`.
