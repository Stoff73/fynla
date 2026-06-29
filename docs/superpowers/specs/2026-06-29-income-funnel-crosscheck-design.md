# Design — SaveTax funnel income cross-check & challenge

**Date:** 2026-06-29
**Status:** Approved (brainstorm)
**Branch:** `savetax-income-only` (off `dev`)
**Surfaces:** web SPA + `/m` (backend-only change, reaches both)

## Problem

A user answers an income **band** on the public SaveTax funnel (e.g.
"£100,001–£125,140"), then types a contradicting exact figure in the Fyn
onboarding chat (e.g. "£50,000"). The funnel's headline tax-saving estimate
was computed from the band, so a contradiction silently invalidates the
expectation Fyn set. Fyn currently accepts the typed figure without comment.

## Goal

When the income captured in chat falls **outside** the band the user picked on
the funnel, Fyn challenges it before moving on: names what they said earlier,
states it changes the tax-saving calculation, and offers **Continue** (keep the
typed figure) / **Change** (re-enter) bubbles. Deterministic — the challenge
must always fire on a real mismatch and never on a match.

## Scope

In scope (CSJ decision): **user income** and **spouse income**.
Out of scope: employment status, spouse yes/no, and assets cross-checks (the
same mechanism can extend to them later; not built now — YAGNI).

## Band → range map

The live funnel (`public/pages/savetax.php`) stores both the user's `income`
and the `spouseIncome` using one key scheme (`spouseIncome` adds `zero`):

| Funnel key | Inclusive range (£) | Challenge label |
|---|---|---|
| `zero` (spouse only) | exactly 0 | "no income" |
| `upto_50270` | 0 – 50,270 | "up to £50,270" |
| `50271_100000` | 50,271 – 100,000 | "£50,271–£100,000" |
| `100001_125140` | 100,001 – 125,140 | "£100,001–£125,140" |
| `over_125140` | 125,141 – ∞ | "over £125,140" |

**Mismatch** = the saved figure is outside the picked band's range. For `zero`,
any figure > 0 is a mismatch. Boundaries are inclusive (a figure exactly on a
band edge is in-band, no challenge).

A single pure helper owns this map and exposes: given a band key + a numeric
figure, is it in-band? plus the human label for a band key. Reused for both
user and spouse so the rule lives in one place.

## Trigger points (deterministic, in `OnboardingChatDirector`)

Run the check **after the figure is saved**, before the flow advances:

- **User income** — after `CoordinatingAgent::handleCaptureWorkDetails` writes
  `annual_employment_income` / `annual_self_employment_income` at the
  `base_work` state. Compare vs `users.funnel_answers['income']`.
- **Spouse income** — after the spouse income is captured at the `base_spouse`
  state (written to the spouse `FamilyMember.annual_income`). Compare vs
  `users.funnel_answers['spouseIncome']`.

**Skip the check** (no challenge) when:
- the user has no `funnel_answers` (non-campaign onboarding), or
- the relevant band key is absent/unknown (e.g. `spouse='no'` → no
  `spouseIncome`), or
- the saved figure is null/zero for the user-income case (nothing captured yet —
  the existing income-required retry handles that).

## Challenge flow (context flag — no workflow-table/corpus change)

On mismatch, the director:
1. Parks `users.onboarding_fyn_context['pending_income_challenge'] =
   { field: 'self' | 'spouse', band: <key>, entered: <figure> }`.
2. Emits the challenge text + two quick-reply bubbles
   `[{id:'continue', label:'Continue'}, {id:'change', label:'Change'}]`.
3. **Stays on the current state** (does not advance) so the next turn re-enters
   the director with the flag set.

Next turn, the director checks `pending_income_challenge` **first**:
- reply `continue` → clear the flag, advance as normal (the figure is already
  saved, so nothing else to do).
- reply `change` → clear the flag, re-emit the income question for that field
  (re-ask). The re-entered figure re-runs capture and re-checks — if still out
  of band, it challenges again; **Continue always ends it**, so no infinite
  loop.
- free-typed figure instead of tapping a bubble → treat as a re-capture of that
  field, then re-check.

This mirrors the existing bubble-branch handling (the resume "Continue /
Something else" prompt) and the `onboarding_fyn_context['verify_section']`
pattern already in the director — no new transition-table state, no corpus
`.md` edit, no golden-master change.

## Wording

Plain text, no icons (Rule #15), British spelling. Example (user income):

> "Earlier you told us your income was £100,001–£125,140, but you've entered
> £50,000. That changes your tax-saving estimate — is £50,000 right?"

Spouse variant names the spouse and the spouse band. No tax-saving figure is
recomputed in the message; the engine already uses the actual saved figure
downstream, so this is purely a confirm + warning. Bubbles: **Continue** /
**Change**.

## Surfaces

All logic is backend (`OnboardingChatDirector` + the pure band helper), so it
reaches the web SPA and `/m` through the shared chat endpoint. The challenge
renders via the existing quick-replies handling on both surfaces (web
`AiChatPanel` / `/m` dock). **No frontend change** — Rule #19 parity is
satisfied by the shared backend.

## Components

- **`FunnelIncomeBand`** (new, pure) — band key → `[min, max]` range, `inBand(key, figure): bool`, `label(key): string`. No deps; fully unit-testable.
- **`OnboardingChatDirector`** — post-capture mismatch check at `base_work` and `base_spouse`; emit-challenge + park-flag; flag-first handling at turn start with the `continue` / `change` / free-text branches.
- **`users.onboarding_fyn_context`** — reuse the existing JSON context column for `pending_income_challenge`.

## Testing

- **Unit (`FunnelIncomeBand`)** — in-band / out-of-band for every band, boundary values (50,270 vs 50,271), `zero` with 0 and with a positive figure, unknown key.
- **Director tests** — mismatch parks the flag + emits the challenge bubbles and does not advance; `continue` advances; `change` re-asks; matching figure advances with no challenge; non-campaign user (no `funnel_answers`) is never challenged; spouse-income mismatch path.
- **Browser (csjones, web + `/m`)** — register a campaign user with band X, type a contradicting income, confirm the challenge + Continue/Change render; exercise both Continue (keeps figure, advances) and Change (re-asks). Repeat for spouse income.

## Out of scope / non-goals

- Cross-checks for employment status, spouse yes/no, assets.
- Recomputing or re-displaying the funnel's headline saving figure in the
  challenge.
- Any new workflow-table state or corpus `.md` change.
