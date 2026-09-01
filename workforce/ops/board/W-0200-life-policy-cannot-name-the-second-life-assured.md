---
id: W-0200
title: A joint-life policy records that it covers two lives but never records whose — the second life assured can only be inferred from users.spouse_id
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: gated
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T02:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0042]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Raised by `cycle2-ownership` while fixing **W-0186**. Not a defect in itself — a
column that does not exist, and the reason W-0186's fix has to infer something every
other module reads off the record.

### The gap

`life_insurance_policies` carries `joint_life` (a boolean) and a free-text
`beneficiaries` string. It has **no `joint_owner_id`, no `ownership_type` and no
`ownership_percentage`**. Every other shared record in the application names its
counterparty:

| Table | Names the counterparty |
|---|---|
| `properties`, `mortgages`, `savings_accounts`, `investment_accounts`, `chattels`, `liabilities`, `business_interests` | yes — `joint_owner_id` (+ type and percentage) |
| `life_insurance_policies` | **no** — only "this covers two lives" |

So "who is the other life assured" has one available answer: `users.spouse_id`.
W-0186's fix uses it, deliberately and in one place
(`app/Services/Protection/LifeCoverReach.php`).

### Why it matters

1. **A joint-life policy with a non-spouse second life cannot be expressed.** Business
   partners on a key-person or shareholder-protection policy, an unmarried couple, a
   parent and adult child. The application will silently attribute the second life to
   the spouse, or to nobody if there is no linked spouse.
2. **It is the same shape as W-0042** — an off-platform co-owner on savings and
   investments, which is blocked on the same class of decision.
3. **The inference is invisible.** Nothing tells the user the app assumed their spouse
   is the other life assured.

### Acceptance

1. A decision from CSJ on whether a second life assured is a first-class field or the
   spouse inference stays. **This is a product call, not an engineering one** — it is
   the same call as W-0042 and should probably be taken with it.
2. If first-class: a nullable `joint_life_with_user_id` plus a `joint_life_with_name`
   free-text fallback (mirroring the `joint_owner_id` / `joint_owner_name` pair the
   chattel counterparty rule uses), and `LifeCoverReach` composed from it instead of
   `spouse_id`.
3. If the inference stays: it is stated in the interface, not silent.

---

## 2026-09-01 — acceptance 3 done, acceptance 1 and 2 gated

**Acceptance 1 is explicitly a CSJ product call** ("this is a product call, not an
engineering one"), and the item says it should probably be taken with **W-0042**. It is
not taken here. The schema in acceptance 2 waits on it.

**Acceptance 3 is done, and it is right under either answer.** If the inference stays it
is now stated; if a second life assured becomes a first-class field, the source flips to
`recorded` and the qualifying wording disappears with it.

### What the user saw before

`LifeInsurancePolicyResource` published `joint_life_with` — a name inferred from
`users.spouse_id`, because the table has no field for a second life assured — and every
surface presented it as though the user had entered it:

| Surface | Was |
|---|---|
| `PolicyCard.vue:118` | "Joint life with Sarah" |
| `/m` `Protection.vue:168` | "Joint life with Sarah" |
| `/m` `ProtectionPolicy.vue:83` | "Yes, with Sarah" |

### What it says now

`joint_life_with_source` is published beside the name — the same shape as
`income_source` (W-0035) and life expectancy's `source` (W-0198), so a surface can
qualify the statement rather than each one deciding for itself whether to. It is
`inferred_from_spouse` when a name was derived and **null when there is none**, so no
source is invented for a policy that names nobody.

All three surfaces now say the app assumed it:
`PolicyCard.vue:117-128`, `Protection.vue:166-171`, `ProtectionPolicy.vue:203-211`.

**Rule 19: web and `/m` both carry it.** iOS is out of scope for the board loop; its
`joint_life_with` field is unchanged and still reads as fact — recorded here rather
than implied to be done.

### Tests

`tests/Feature/Protection/JointLifeInferenceIsStatedTest.php` — 2 tests: the source is
published with the inferred name, and no source is invented when nothing is named.

**Regression:** 190 protection tests, 239 frontend component specs.

### The decision still outstanding, in one line

*Should a joint-life policy name its second life assured as a first-class field
(`joint_life_with_user_id` plus a `joint_life_with_name` fallback), so a business
partner or unmarried partner can be recorded — or does the spouse inference stay?*
Same call as W-0042.
