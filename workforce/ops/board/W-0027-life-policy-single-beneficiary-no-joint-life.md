---
id: W-0027
title: Life policy takes one beneficiary from a list that excludes the children, and has no joint-life flag
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0006-batch-d-protection-goals.md
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T10:00:00Z
claimed: 2026-08-21T11:00:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0026, LifeInsurancePolicy.joint_life column, LifeCoverCalculator, ProtectionActionDefinitionService, PolicyFormModal.loadFamilyMembers]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **`/m` discovery sweep** (entry phase), local
`localhost:8000`, premium. Account **David Jones (16)**.

**Surface:** desktop web, `/protection` → Add Protection → Life Insurance.
Separate from W-0026 (which is about dates); this is about who the policy pays.

### Expected

Persona `tests/Persona/peak_earners.md:412-426` — Joint Level Term, Vitality:

| Field | Value |
|---|---|
| **Joint Life** | **Yes** |
| **Beneficiaries** | **Sarah Jones, William Jones, Charlotte Jones** |
| In Trust | Yes |

### Actual

```
life_insurance_policies.id 7
  beneficiaries = 'Sarah Jones: 100%'     <-- persona names three
  in_trust      = true                     ok
```

**Fault 1 — one beneficiary only, and the children are not offered.** The Beneficiary
control is a single `<select>` whose entire option list is:

```
Select beneficiary...
Sarah Jones (Spouse - Linked Account)
Add Beneficiary
```

William Jones and Charlotte Jones are **`FamilyMember` records on this very account**
— they are offered as beneficiaries elsewhere (the Junior ISA beneficiary picker
sources from family members, and the will builder listed Charlotte correctly as a
minor child). Here they are absent. Only the linked spouse appears.

The persona's three-beneficiary split cannot be recorded. I put the full list in
Additional Notes as free text so the information is not lost, which is exactly the
workaround a real user would be forced into.

**Fault 2 — no joint-life flag.** Searching the rendered form for "joint life"
returns nothing. The persona's policy is explicitly Joint Life, and that materially
changes the instrument: a joint-life-first-death policy pays once, on the first death,
whereas two single-life policies pay twice. The protection gap analysis cannot model
that distinction if it cannot be recorded.

### Why it matters

- A life policy written in trust with the wrong beneficiaries pays the wrong people —
  and `in_trust = true` here means it sits outside the estate, so the beneficiary
  nomination *is* the distribution.
- The Estate module reads life cover for Inheritance Tax mitigation. A £500,000
  joint-life policy and a £500,000 single-life policy are not interchangeable in that
  calculation.

### Evidence

Rendered option list and the absence of any joint-life control were read from the live
DOM; the stored row is quoted from tinker.

**No screenshot** — the finding is an absence (missing options, missing field), which
a screenshot evidences poorly. The DOM enumeration above is stronger.

Report: `reports/R-07-m-sweep.md`.

### Repro

1. Account with a linked spouse **and** children recorded as family members.
2. `/protection` → Add Protection → Policy Type "Life Insurance".
3. Open the Beneficiary select — only the spouse and "Add Beneficiary" are listed; the
   children are missing.
4. There is no way to select more than one beneficiary, and no joint-life control
   anywhere on the form.

## Acceptance

- [ ] The beneficiary picker sources from **all** eligible family members, not just the
      linked spouse — matching the pattern already used by the Junior ISA beneficiary
      picker.
- [ ] Multiple beneficiaries can be recorded with shares, and they persist
      (`beneficiaries` already stores a "Name: %" shape, so the column can hold it).
- [ ] A joint-life flag exists on life policies and is respected by the protection gap
      analysis and by the Estate module's life-cover aggregation.
- [ ] Entering the persona's policy records all three beneficiaries and joint-life =
      yes.
- [ ] Check what "Add Beneficiary" does — it may already be the intended route for
      non-spouse beneficiaries, in which case this is a discoverability problem rather
      than a missing capability. **I did not test that path** and it should be
      established before choosing the fix.
- [ ] `/m` and iOS protection entry checked (Rule 19).
- [ ] Re-verified live in the browser by the persona run.

## Working notes

(append-only)

- 2026-08-21 persona-tester: found while entering the persona's protection policies.
  Not fixed by me — routed to build-lead.
- **Explicit gap in my own testing:** I did not click "Add Beneficiary" to see whether
  it opens a picker that includes the children or a free-text add. That single check
  would settle whether this is a missing feature or a discoverability issue, and it
  should be done before any code is written. Recorded rather than assumed.
- What is right: `in_trust` persisted correctly, and the trust flag matters for the
  Inheritance Tax treatment the persona depends on.

- 2026-08-21 build-lead: **fixed**, both faults.

  **The "Add Beneficiary" question you left open, answered:** it is a free-text
  name field, not a picker — it never included the children. So this was a missing
  capability, not discoverability. The children were already being fetched:
  `PolicyFormModal.loadFamilyMembers()` populated `this.familyMembers` on mount and
  the template never read it, offering only `userProfile/spouse`. Two sources for
  one list, one of them dead.

  **Fault 1 — beneficiaries.** One `beneficiaryOptions` computed now builds the list
  from the family members plus the linked spouse (deduplicated by name), and the
  single select is a repeatable row list — add, remove, share per row, running
  "Shares total X%". Serialisation lives in two module-level functions,
  `parseBeneficiaries` / `serialiseBeneficiaries`, the only place the
  `"Name: 60%, Name: 40%"` column shape is read or written; free text that was never
  a named share round-trips unchanged as a name-only row. No schema change: the
  `beneficiaries` text column already held that shape, and `/m`
  (`ProtectionPolicy.vue:120`) and the web detail view render it as stored.

  **Fault 2 — joint life.** `life_insurance_policies.joint_life` has existed since
  `2026_03_14_100004`, is fillable, and is already respected by
  `LifeCoverCalculator.php:466` and `ProtectionActionDefinitionService.php:1059-1060`
  — nothing could set it. Added: a checkbox on life policies, `joint_life` in
  `StoreLifePolicyRequest`/`UpdateLifePolicyRequest`, the field in
  `LifeInsurancePolicyResource` (so the edit form round-trips it), and a display row
  on the web detail view and `/m`.

  **Copy change worth noting:** the select option "Add Beneficiary" is now "Someone
  else" — with an "Add another beneficiary" button below it, two controls could not
  both be called "Add Beneficiary".

  **Live verification (localhost:8000, David Jones 16):** the beneficiary select on
  life policy 7 lists "Sarah Jones (Spouse - Linked Account), William Jones (child),
  Charlotte Jones (child), Someone else". Entered all three plus joint life; DB:
  `joint=Y trust=Y beneficiaries = "Sarah Jones: 34%, William Jones: 33%,
  Charlotte Jones: 33%"`. Web detail shows "Joint Life: Yes" and the three names;
  `/m/app/protection/policy/life/7` shows "Joint life Yes" and the same three.

  **Assumption stated:** the persona names three beneficiaries with no shares, so I
  used an equal split (34/33/33). If CSJ wants specific shares, the persona file
  needs them — I did not invent a spouse-weighted split.

  **Tests:** `PolicyDatesPersistTest.php` (3 joint-life/beneficiary cases) and
  `tests/frontend/components/Protection/PolicyFormModal.test.js` (10 cases,
  including the parse/serialise round trip and the option list).

  **Not done / flagged:** Fyn cannot set `joint_life` or `beneficiaries` on any
  surface. `CoordinatingAgent.php:3747` already accepts `joint_life` as input, but
  no tool schema exposes either field and neither is in `UpdateRecordAllowlist`, so
  `/m` and native (Fyn-only protection entry) still cannot record them. Closing that
  needs a corpus version bump plus regeneration of the Phase-4b golden fixtures — a
  reviewed tool-catalogue change, left to the lead.
