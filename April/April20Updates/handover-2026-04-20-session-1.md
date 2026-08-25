---
type: handover
mode: end-of-day
date: 2026-04-20
session: 1
branch: feature/csj/excalidraw
previous_session: 2026-04-19 session 2 (end-of-day)
---

# Handover — 2026-04-20, Session 1

## Where we left off

End of 19 April. Last session (session 2, 19 Apr) was a **product-management skill run with no code changes** — Chris used `/plugin` to pull in the PM skills, then ran `market-segments → beachhead-segment → plan-launch → strategy` in sequence to produce marketing and strategy reports for Fynla. Output lives in `April/April19Updates/marketing/` (5 files, gitignored, mirrored to vault). Branch unchanged: `feature/csj/excalidraw` still 4 commits ahead of `dev`, pushed, not PR'd. Working tree clean.

## What shipped today (19 April, both sessions combined)

- Session 1 (morning, context-clear): vault orphan sweep (86 → 0), PR #220 merge to `dev`, `.claude/skills/` refactor for Fynla UK with session-start port-stacking fix, new `excalidraw` skill + 3 seed diagrams, vault-sync orphan-scanner fix (`3197805`). 6 commits.
- Session 2 (afternoon, end-of-day): PM skill suite run → 5 marketing/strategy markdown reports. **No commits.**

## What's in flight (NOT done)

- [ ] **Open PR `feature/csj/excalidraw` → `dev`** — carried from session 1. Branch is clean + pushed; nothing blocking.
- [ ] **Merge `onboardingFyn` → `dev`** — Fyn AI testing outstanding since session 58. Required before any further dev deploy (csjones.co/fynla currently runs `onboardingFyn` build).
- [ ] **Deploy dev → production (`main`)** — PR #220 (tech-debt) is in `dev`, not in `main`. Standard release PR required.
- [ ] **Test Fyn chat fixes on dev** — carried from session 58.
- [ ] **Re-enable branch protection on `dev`** — carried from session 57.
- [ ] **Add `Current State/Insights.md` to the vault** — flagged in session 62.
- [ ] **`AutoRiskCalculatorTest` enum truncation** — pre-existing, not Insights-related.
- [ ] **Marketing strategy follow-ups** (optional, from session 2):
  - Run `pm-product-strategy:business-model` (Lean Canvas pressure-test of the GTM economics)
  - Run `pm-go-to-market:growth-strategy` (design the specific growth loops the strategy hand-waves)
  - Ship a free **BPR Exposure Calculator** at `fynla.org/bpr` as beachhead funnel top (Week 1 of the GTM timeline)
  - Mirror the marketing folder into `fynlaBrain/` under a `Strategy/` tree for Obsidian wikilinking (currently nested under `April/April19Updates/marketing/`)

## Deploy status

**Nothing to deploy — all session-2 work was docs (PM reports) in a gitignored folder.**

- **Production (`fynla.org`)**: Running PR #219 (Admin Insights CMS) + `062c7c7` (tooling audit). No change.
- **Dev (`csjones.co/fynla`)**: Still serving `onboardingFyn` build (Fyn AI testing). Do NOT rebuild from `dev` until onboardingFyn is merged in first.
- **Pending**: PR #220 tech-debt work in `dev`, not yet released to `main`. Excalidraw branch sitting ahead of `dev`.

## Tech debt found this session

None. Session 2 touched no tracked code. Session 1's findings are in the session-1 handover.

## Known issues / blockers

- Don't deploy from `dev` — `csjones.co/fynla` is running `onboardingFyn`. Rebuild + deploy happens once after `onboardingFyn → dev` merge (per session 1 handover).
- `AutoRiskCalculatorTest` enum truncation — pre-existing, unrelated to today's work.

## Rules reinforced this session

None new from session 2. Session 1's "ask before killing a port-holder", "interact like a real user in browser tests", and "never `./deploy/csjones-fynla/build.sh` from `dev` while onboardingFyn is live on csjones.co/fynla" still stand.

## Next session should

1. **Ask Chris what direction**: continue the strategy work (Lean Canvas + growth-loops), ship the beachhead landing-page BPR calculator, open the excalidraw PR, or prep the onboardingFyn merge.
2. **If it's the BPR calculator**: the calc logic already exists in `app/Services/Estate/` (27 services include NRB/RNRB/BPR/gift-taper). Build a no-auth public route + controller + Vue page. See `04-product-strategy.md` Section 7 for the acquisition rationale.
3. **If it's the excalidraw PR**: `gh pr create --base dev --head feature/csj/excalidraw` — branch is clean and pushed.
4. **If it's the strategy follow-ups**: `pm-product-strategy:business-model` takes the GTM plan as input and returns a Lean Canvas. Write output to `April/April20Updates/marketing/05-lean-canvas.md` (or similar). Follow the same naming pattern as 01–04.

## Context hints

- Active branch type: mixed (skill work + docs)
- Behind origin/main by: measured from `dev` — branch is 4 commits ahead of `dev`; `main` is older than `dev` by PR #220's merge-ahead
- Uncommitted: none, working tree clean
- Last commit: `3197805 fix(skills): vault-sync orphan scanner handles escaped pipes; remove disable-model-invocation flag`

## Session 2 output pointers

The PM skill run produced 5 markdown files. They're gitignored (April/ is in .gitignore) but synced to the fynlaBrain vault:

- `April/April19Updates/marketing/README.md` — index + headline conclusions
- `April/April19Updates/marketing/01-market-segments.md` — two niches (Ltd Co Directors + IHT-anxious couples)
- `April/April19Updates/marketing/02-beachhead-analysis.md` — scorecard, beachhead pick, 90-day sketch
- `April/April19Updates/marketing/03-gtm-plan.md` — full GTM plan, 8-week runway to mid-June 2026 launch
- `April/April19Updates/marketing/04-product-strategy.md` — 9-section Product Strategy Canvas

**Headline**: beachhead = UK single-director Ltd Cos (£120k–£300k revenue), wedge = Oct 2024 Budget's £1m BPR cap, north star = Paid Active Households, biggest risk = FCA reclassifying Fyn AI outputs as regulated advice.
