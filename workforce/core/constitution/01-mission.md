# 01 — Mission and Who We Serve

**Status:** Ratified in part by CSJ, 2026-08-13, session 3. §4 open.
**Owner:** CSJ. Amendments gated.

---

## 1. Vision

**Adopted verbatim** from `April/April19Updates/marketing/04-product-strategy.md:12`:

> **Every UK household should plan its money the way the wealthiest families do —
> seeing business, pension, property, and estate as one living picture — and pay
> £20/month for it, not £20,000/year.**
>
> Fyn, the AI companion, is the thing that makes that price point possible: the
> financial reasoning of a £500/hour advisor, always on, never scolding, never
> selling you a product.

**Why adopted whole:** it is testable, which is rare in a vision. "One living
picture", "£20/month not £20,000/year" and "never selling you a product" are each
things a piece of work can be measured against. The last is already load-bearing —
it is the same logic that rules out advertising, AUM fees and product
recommendations.

---

## 2. Income is not an indicator of client

**Ratified 2026-08-13. This overturns the segmentation in
`04-product-strategy.md` §2.**

The strategy document segmented by income and wealth bands — founder-directors at
£120k–£300k revenue, couples at £800k–£2m, "sub-£30k earners" excluded. **CSJ's
ruling: those thresholds are arbitrary and meaningless as targeting criteria. The
level of income does not indicate whether someone is our client.**

**What does:** having a financial life that needs organising. That is a function of
**life stage and situation**, not of how much money passes through it. A student
with a Lifetime ISA and a student loan has a real planning problem. So does a widow
with a transferable nil-rate band. Neither is defined by an income band.

This is also the reading that makes the vision coherent. "Every UK household"
cannot sit above a strategy that excludes households for earning too little.

---

## 3. Who we serve — the personas are the answer

**Ratified 2026-08-13: the seeded personas represent the clients we are after.**
They are not demonstration breadth. They are the definition.

| Persona | Situation |
|---|---|
| `student` — Janice Taylor | Lifetime ISA, student loan, early career |
| `young_saver` — John Morgan | Emergency fund, first-time saving |
| `young_family` — Emily & James Carter | Mortgage, workplace pensions |
| `entrepreneur` — Alex Chen | SIPP, business interests |
| `peak_earners` — David & Sarah Mitchell | Multiple properties, SIPP + NHS pension |
| `retired_couple` — Patricia & Harold Bennett | Decumulation, estate planning |

**Six**, plus three spouse-view variants (`young_family_spouse`,
`peak_earners_spouse`, `retired_couple_spouse`). Read together they are not wealth
tiers; they are a sequence of life stages, which is precisely the point of §2.

**`widow` was removed on 17 March 2026** — commit `54b396a89`, *"Remove widow
persona (Margaret Thompson) from all systems"*. It is not a client segment and must
not be reintroduced by anything that finds its residue in the codebase.

### 3.1 Canonical source

**`PreviewController::VALID_PERSONAS` / `PERSONA_METADATA` is canonical** — it is
what `GET /api/preview/personas` returns, and therefore what a user actually sees.
`PreviewUserSeeder::PERSONAS` (the `private const`, not the file) agrees with it.

Everything else describes: `appMapping/personaData/`, `fynlaBrain/Personas/`,
`Persona Data.md`, `CLAUDE.md`'s table.

**Corrected 2026-08-13.** An earlier version of this section claimed seven personas
including `widow`, and blamed `CLAUDE.md` for being stale. **`CLAUDE.md` was right.**
The error came from grepping the seeder *file* for `'widow'` and hitting **dead
code** — unreachable branches (`createWidowLpas`, `createWidowWillDocument`, a
journey-state arm) left behind by the March removal — instead of reading
`private const PERSONAS`, the list the seeder actually iterates. See
`registry/capabilities.md` §2 for the rule this produced.

### 3.2 Persona changes are mission changes

**Ratified.** Because the personas define who we serve, **any persona added,
deleted, or materially changed is an amendment to this file.**

Enforced by the consistency sweep against `PreviewController::VALID_PERSONAS`: a
diff changing that constant raises a trunk amendment proposal. Gated like any trunk
change — the workforce proposes, a founder ratifies.

### 3.3 Outstanding cleanup

`widow` residue survives on the current branch, `origin/dev` **and** `origin/main`.
Unreachable, but it misleads anyone who greps.

**Code:** `PreviewUserSeeder.php` (~lines 1493, 1715, 1819, 1929, 2585) ·
`AdvisorClientSeeder.php:62` · `PersonaSelector.vue` · `PersonaSelectionModal.vue` ·
`PersonaIntroModal.vue` · `public/mockup-persona-modal.html`.

**Docs:** `Persona Data.md:13` · `appMapping/personaData/widow.{md,pdf}` ·
`appMapping/v083/05-FRONTEND-ARCHITECTURE.md:242` (*"Seven personas"* — the
document that caused this error) · `appMapping/v07/05-FRONTEND-ARCHITECTURE.md:204`
(says "six", lists widow, omits student — doubly wrong) · several
`appMapping/currentState/` files · vault `Personas/widow.md` and `Personas Index.md`.

**Never touch:** `widowed` as a marital status, or "Scottish Widows" as a provider.
Both are legitimate and unrelated — `OnboardingStateMachine.php`,
`IHTCalculationService.php`, factories, migrations, tests.

### 3.2 Persona changes are mission changes

**Ratified.** Because the personas define who we serve, **any persona added,
deleted, or materially changed is an amendment to this file.**

| Trigger | Consequence |
|---|---|
| Persona added | `01-mission.md` §3 amended; Cartographer updates the map; propagate to all describing docs |
| Persona deleted | Same, plus: check whether any exclusion in §4 now needs revisiting |
| Persona materially changed | Same — a change of situation is a change of who we serve |

Enforced by the consistency sweep: a diff to `PreviewUserSeeder.php` that changes
the persona set raises a trunk amendment proposal automatically. It is gated like
any trunk change — the workforce proposes, a founder ratifies.

---

## 4. Exclusions — capability, never income

**Principle ratified; the list itself is open.**

**Fynla excludes on capability, never on income.** If the product genuinely cannot
serve someone well, say so. If it merely finds them less lucrative, that is a
go-to-market question, not a statement about who we are for.

Applying that to `04-product-strategy.md`'s original list:

| Original exclusion | Basis | Status |
|---|---|---|
| Sub-£30k earners with no assets | **Income** | **Removed.** Contradicts `student` and `young_saver`, who are clients. |
| Non-UK residents | **Capability** — the tax engine is irreducibly UK-specific | **Retained** |
| Business-only customers | **Capability** — the edge is the personal–business bridge | **Retained** |
| Under-18s | **Capability / legal** — Lifetime ISA floor is 18 | **Retained** |
| Day-traders and crypto-native | Not modelled | **Removed, session 4.** CSJ: "have not built for" — a roadmap gap, not an exclusion. Silent-incompleteness consequence carried to `05-perimeter.md`. |
| True HNW >£5m | **Wealth band** with a positioning argument | **Removed, session 4.** CSJ: "HNW included". Positioning is not capability, and §2 forbids wealth as a criterion. |

**Three exclusions remain, all structural.** Full list and reasoning in
`03-hard-nos.md` §3.

## 5. Open

1. **Go-to-market sequencing — closed here, owned by Azlan.** §3 says who we serve;
   it deliberately says nothing about who to *acquire first*. **CSJ, 2026-08-13:
   the marketing sequence is Azlan's and will be defined by him.** The old P0–P4
   ladder is void — it was built on income bands (§2) — and nothing replaces it in
   this file. Acquisition focus is Azlan's to set (`registry/people.md` §3.2), and
   the BPR-wedge question travels with it.

   **The boundary matters:** who we serve is doctrine and lives here. Who we
   approach first is strategy and lives with Azlan. The Chief of Staff judges
   goal fit against §3, never against a marketing sequence.
