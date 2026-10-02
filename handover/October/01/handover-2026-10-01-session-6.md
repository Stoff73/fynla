---
type: handover
mode: session-end
date: 2026-10-01
session: 6
repo: fynla
branch: feat/retirement-decumulation-and-care-costs
---

# Session Handover — 2026-10-01, Session 6

## Where things stand

CSJ asked for item 7 and every sub-item, including "a fix for all surfaces that do not get their figures from the server", with no subagents. Care costs: "FORGET CARE COSTS, TAKE IT OUT". CSJ also confirmed that ALL Fyn capture is through forms, never phrase matching.

Done this session (all on the branch, pushed, deployed on csjones at `78329f6e6` plus the TODO commit):

- Care costs taken out (`ae9a7cb6d`): the input, the Fyn tool fields and session 4's phrase-matching and focus-map patches reverted; `care_costs_not_modelled` disabled in `RetirementActionDefinitionSeeder`.
- Item 7a, every audit item where a surface worked out its own figure, now renders a server field on web, /m and iOS. The commit list is under item 7a in `todoCurrent/TODO.md`.
- The open DECISION (the drawer's dashboard card) was done to the rule: the card shows this year's income from the drawing view.
- Walked on csjones, web 1440 and /m 390. Evidence is under item 7a and in `walk-item7/*.png`.

## Priorities for the next session

1. **Merge the branch to `dev`** if this session did not (check `gh pr list --head feat/retirement-decumulation-and-care-costs`), then **stop for CSJ's release decision**. Release needs:
   - `db:seed --class=RetirementActionDefinitionSeeder --force` (care costs card disabled)
   - `ActionHowToSeeder` (retirement how-tos, from earlier sessions)
   - fyn-memory rsync (the `capture_retirement_goals` schema reverted)
   - both bundles, and app/
   - iOS ships with the next build; CI verifies it.
2. **Walks not yet done:** /m as a joint household (the Mitchell demo on /m); the web Inheritance Tax table's earlier/later columns (toggle them on); iOS (CI only).
3. **Rest of 7a (server-to-server duplicates):** audit 38/item 16 (ISA used, five server rules), 42, 43, 44, 50. See item 7a "Not done".
4. Item 7 how-to: the decumulation entry (`b9abf3645`) waits for CSJ's re-approval.

## Decisions and dead ends

- Care costs are out entirely. Do not rebuild any input, form or Fyn route for them unless CSJ asks.
- Fyn capture is forms only: no classifier phrases, no focus-map patches to make the model call a tool.
- Each surface keeps its approved tile wording ("£0 of headroom" web, "£0 available" /m and iOS); the STATE comes from the server (`tile_state`).
- The web Emergency Fund "Adjust Target" slider was removed: it worked out a second target in the browser. Tell CSJ if asked.
- The web Tax Strategy "£X of headroom" total across allowances was replaced by the count (allowances of different kinds cannot be added).
- Two dead IHT blocks (read a field nothing sets, £500,000 typed in) were deleted, not switched on.

## Things that will bite you

- The auto-mode classifier denied a verification command on csjones as "Production Deploy" (csjones is staging). Deploy commands themselves went through; do not loop on it.
- `config:cache` may not have run after the last clear on csjones; Laravel reads `.env` uncached, which works.
- Vitest: always `--exclude '.claude/**'` (three abandoned agent worktrees under `.claude/worktrees` hold stale copies).
- The test hook blocks Pest calls whose paths are shell variables: name files literally.
- Walk accounts: 459 / 460 (`item7-walk-a@` / `item7-walk-b@example.com`, `Password1!`); code via csjones ssh tinker.

## Branch and deploy state

- Branch `feat/retirement-decumulation-and-care-costs`, pushed. csjones runs it (backend and both bundles from `78329f6e6`). fynla.org unchanged (main `a80399d5a`).
- CSJ's own uncommitted files (excalidraw, workforce logs, September 30 handover edit) left alone.
