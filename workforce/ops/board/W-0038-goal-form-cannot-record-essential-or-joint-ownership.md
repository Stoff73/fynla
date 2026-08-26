---
id: W-0038
title: Goal form cannot record "essential" or joint ownership — a joint goal cannot be created, so no goal ever splits between spouses
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: review
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T12:40:00Z
claimed: 2026-08-26
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0013, W-0014, W-0025, W-0029]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, playbook preparation. Local `localhost:8000`,
premium. Accounts **David Jones (16)** and **Sarah Jones (17)**.

**Surface:** `/goals` → `GoalFormModal.vue`.

**Batch D's surface.** W-0029 (goals and events cannot be past-dated) and W-0028
(`/m` goals page shows no life events) are in flight on the same module. This is a
third gap in the same form.

### Expected

The persona specifies both attributes:

| Goal | Persona says |
|---|---|
| Early Retirement Fund | Priority **Critical**, **Essential: Yes**, **Ownership: Joint** |
| William's House Deposit Help | **Ownership: Joint** |
| Charlotte's Gap Year Fund | **Ownership: Joint** |
| Sarah's ISA | **Owner: Spouse** |

Three of the six goals are joint. A joint goal should behave like every other joint
record under Rule 6: ONE row, `ownership_type = 'joint'`, `joint_owner_id` set,
`ownership_percentage` held, and each spouse seeing their share of the target and the
progress.

`is_essential` marks a goal the household cannot go without, which is what should
separate it from a discretionary goal in any prioritisation or shortfall analysis.

### Actual

`GoalFormModal.vue` binds these and nothing else: `goal_name`, `goal_type`,
`custom_goal_type_name`, `description`, `target_amount`, `current_amount`,
`target_date`, `monthly_contribution`, `priority`, `show_in_projection`,
`show_in_household_view`, `is_first_time_buyer`, `estimated_property_price`,
`deposit_percentage`.

There is **no `is_essential` control and no ownership control**, although the `goals`
table carries `is_essential`, `ownership_type`, `joint_owner_id` and
`ownership_percentage`.

Consequences:

1. **No goal can be created as joint through the UI.** Every goal is individual to
   whoever entered it. The persona's three joint goals cannot be represented, and no
   goal ever splits between the spouses the way property, savings, investments and
   chattels do.
2. **`is_essential` is write-only from the UI's point of view but read for display.**
   `GoalDetailInline.vue:180` renders `{{ goal.is_essential ? 'Yes' : 'No' }}` — a
   field the user is shown but can never set, so it reads "No" for every goal
   regardless of the truth.

`show_in_household_view` exists and is bound, which is a *visibility* flag, not
ownership — a goal shown in the household view is still owned 100% by one spouse. The
two are easy to conflate; confirm which behaviour is actually intended before
building.

### Repro

1. Two linked spouse accounts, premium.
2. `/goals` → Add Goal → fill everything.
3. Search the form for an "essential" toggle or an ownership / joint-owner control —
   neither exists.
4. Save. `goals.ownership_type` is not joint, `joint_owner_id` is NULL,
   `is_essential` is false.
5. Open the goal's detail view — "Essential: No", for a goal the persona marks
   essential.
6. Log in as the spouse — the goal is either invisible or shows at 100%, never as a
   half share.

### Evidence

- `resources/js/components/Goals/GoalFormModal.vue` — the complete set of `v-model`
  bindings (676 lines; priority is a button group at :126-138, essential and
  ownership are absent entirely)
- `resources/js/components/Goals/GoalDetailInline.vue:180` — displays
  `is_essential`, which nothing can set
- `goals` schema — `is_essential`, `ownership_type`, `joint_owner_id`,
  `ownership_percentage` all present
- Persona lines: `tests/Persona/peak_earners.md` Goals 2, 3, 5 and 6

## Acceptance

- [ ] The goal form accepts an "essential" flag and persists `is_essential`; the
      persona's Early Retirement Fund saves as essential and its detail view says so.
- [ ] The goal form accepts joint ownership with a joint owner, persisting
      `ownership_type`, `joint_owner_id` and `ownership_percentage` — using the SAME
      shared mechanism as property, savings, investments and chattels, not a fourth
      copy (Rule 20; coordinate with W-0013 / W-0014 / W-0025, which are consolidating
      exactly this).
- [ ] Both spouses see a joint goal, each with their share of target and progress, from
      ONE row (Rule 6).
- [ ] The distinction between `show_in_household_view` (visibility) and
      `ownership_type` (who owns it) is decided deliberately and documented — they are
      not the same thing and the form currently offers only the former.
- [ ] Household goal roll-ups do not double-count a joint goal across both spouses.
- [ ] `/m` and iOS goal entry carry the same fields (Rule 19).
- [ ] Re-verified live in the browser by the persona run, both accounts.

---

## Built 2026-08-26 — and the item overstated the gap

**The backend was never the problem.** `StoreGoalRequest` has always validated
`is_essential`, `ownership_type`, `joint_owner_id` and `ownership_percentage`; `Goal`
already uses `HasJointOwnership`; and `GoalsController:64` already reads
`Goal::forUserOrJoint()`. A joint goal created directly was already visible to both
spouses. The gap was the form, and one piece of behaviour behind it.

**What was genuinely missing beyond the controls:** `Goal::isJoint()` had **zero
callers**, so nothing anywhere acted on a goal being shared.

### CSJ decision 2026-08-26 — a shared goal is ONE goal

Put with both answers costed. A couple saving £50,000 for a deposit have a single
£50,000 target; showing them £25,000 each would describe a household saving twice and
reaching neither figure. **Deliberately unlike a jointly-owned asset**, where each
spouse genuinely owns half the value.

So `ownership_percentage` carries no meaning on a goal, no surface asks for one, and
the Fyn tool deliberately does not expose one — a test asserts its absence, because
offering it would invite Fyn to halve a target nobody halved.

### Acceptance

| # | State |
|---|---|
| 1. Essential flag persists, detail view says so | **Done** — control added; `GoalDetailInline:180` had always displayed a field nothing could set |
| 2. Joint ownership via the shared mechanism | **Done** — no fourth copy; the form writes the same columns every other joint record uses, and the spouse is offered only where reciprocally linked |
| 3. Both spouses see it from ONE row | **Already true**, now pinned by test rather than assumed |
| 4. `show_in_household_view` vs `ownership_type` decided and documented | **Done** — in `Goal::isJoint()`'s docblock. Ownership is WHOSE goal it is; household view is whether it appears in the COMBINED projection. They are independent, and the form offers both |
| 5. Roll-ups do not double-count | **Verified, no change needed** — `GoalsProjectionService:551` is ONE query with `orWhere`, so a row matching several conditions returns once |
| 6. `/m` and iOS carry the same fields | **Done via Fyn.** `resources/mobile` has no goal form and no goal write call at all — `Goals.vue` and `GoalDetail.vue` are read surfaces — so those surfaces create goals through Fyn. Both `create_goal` schemas and `CoordinatingAgent`'s handler now carry the three fields, pinned by a test against the LIVE catalogue |
| 7. Re-verified live in the browser by the persona run | **NOT DONE** — see below |

### Not done

**Acceptance 7 is outstanding.** These changes are covered by test at the API, store,
model and tool-catalogue level, and the frontend suite is green, but nobody has
clicked through `/goals` as both spouses and watched a shared goal appear on each
side. That is the persona run's job and it has not been done.
