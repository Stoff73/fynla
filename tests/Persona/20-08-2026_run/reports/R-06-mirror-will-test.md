# R-06 — Mirror will test (CSJ interrupt, W-0019)

**Run:** `peak_earners` · **Environment:** local `http://localhost:8000`, premium
**Accounts:** David Jones (16) primary · Sarah Jones (17) mirror
**Ran:** 2026-08-21 09:00 – 09:20
**Trigger:** CSJ interrupt while watching the run drive the will process. Sweep
redirected from the Simple Will path (being removed for married users) to the mirror
path.

---

## Done

### 1. W-0019 confirmed live, not just in code

`WillBuilderIntroStep.vue:60-86` renders, for David (a married user with
`has_spouse = true`), **two equal side-by-side buttons** with no default and no
warning:

```
[ Simple Will  — A single will for you, distributing your estate as you wish. ]
[ Mirror Will  — Two matching wills — one for you and one for your spouse.    ]
```

**And I had already completed a Simple Will for him before the interrupt.**
`will_documents.id 5` was created with `will_type = "simple"` for a married user, with
his spouse named as executor — precisely the one-sided married will CSJ described.
That document is still on the system as live evidence. No duplicate raised; this is
recorded against W-0019.

### 2. Mirror path tested end to end — both wills generated and completed

| | David (doc 5) | Sarah (doc 6) |
|---|---|---|
| testator | David Jones | Sarah Jones ✓ correctly replaced |
| address / DOB / occupation | his | hers ✓ from her profile |
| residuary | 100% to **Sarah**, substitute: children in equal shares held in trust until 25 | 100% to **David**, same substitute ✓ **correctly swapped** |
| executors | Sarah Jones + Barclays Wealth | **Sarah Jones** + Barclays Wealth ✗ |
| specific gift | Cancer Research UK £10,000 | **Cancer Research UK £10,000** ✗ (copied) |
| status | complete | complete (after I completed it as Sarah) |
| `wills` row | id 11 | id 12 (created on completion) |
| bequests | **0** | **0** |

### 3. Answers to the three questions team-lead asked

**Can the mirror flow faithfully hold the persona's wills?**
**Partly — with one hard failure and one wrong default.**

- Residuary: **yes**, correctly swapped both ways.
- Differing charity bequests: **yes, but only if the spouse notices and edits.**
  `generateMirrorWill` copies `specific_gifts` verbatim (`WillDocumentService.php:311`),
  so Sarah's will was seeded with **David's** charity. The field **is editable** on her
  draft — I changed it to British Heart Foundation and the document regenerated
  correctly. So: **not impossible, but silently wrong by default.** That distinction
  matters and I only got it by testing rather than reading the code.
- Executors: **no.** Copied verbatim, so Sarah's will appoints **Sarah** as her own
  executor, describing her as "my Spouse". → **W-0024**, high.

**Do both sets of bequests persist with correct priority and conditions?**
**No — neither does.** After both wills completed, `bequests` is **0** for both wills.
W-0023 reproduces identically on the mirror path. Priority and conditions have nowhere
to live: the will builder's gift form offers beneficiary, type, amount and conditions,
but no priority field, and none of it reaches the `bequests` table.

**Does Sarah see her own will correctly from her own login?**
**Yes, mechanically.** Her builder opened directly on her draft, she could edit every
step, and completing it created her `Will` row. What she sees is *wrong in content*
(herself as executor, the wrong charity pre-filled), not inaccessible.

### 4. Third finding — the spouse gets no Guardians step

David's stepper: Intro, Personal, Executors, **Guardians**, Gifts, Residuary, Funeral,
Digital, Review, Signing — plus the warning "You have children under 18 but have not
appointed a guardian."

Sarah's stepper: Intro, Personal, Executors, Gifts, Residuary, Funeral, Digital,
Review, Signing — **no Guardians step, no warning.**

The children are `FamilyMember` rows on David's account; on Sarah's they appear only
as "shared from spouse". She is Charlotte's mother, and a guardian appointment only
bites when *both* parents are gone — so it belongs in both wills. Folded into W-0024.

---

## Not done, and why

- **Did not deep-test the Simple Will path further.** Per instruction — it is being
  removed for married users.
- **Did not test a permanently-draft mirror.** The spouse's document is created as
  `draft` and only she can complete it. What the app does if she never engages is
  untested — see Needs.
- **Did not re-test bequest priority/conditions via the separate "Add Bequest" control**
  on the Will Planning summary. That is the proper `bequests` API path and may work
  where the builder does not. **I COULD NOT TEST THIS** within this interrupt.
- **`/m` and iOS mirror rendering** — not tested.

---

## Assumptions

- That in a mirror pair each spouse names the other as executor. This is the persona's
  arrangement (`peak_earners.md:534, :552`) and the standard instrument; W-0024 is
  written on that basis.
- That doc 5's `funeral_preference` reading NULL after my mirror re-walk is **my**
  doing — I aborted a wizard pass midway and it likely PUT partial data. **Not raised**,
  because I disturbed it and cannot cleanly attribute it.

---

## Needs

**Evidence for CSJ's two open questions on W-0019** — offered, not answered:

1. *"A spouse who will not engage."* The mirror generator creates the spouse's document
   as `status: 'draft'` with `will_id: NULL`, and only the spouse can complete it from
   her own login. Today the pair can sit indefinitely as one complete will plus one
   orphan draft, and **neither party is shown that state**. If mirror-only becomes
   mandatory for married users, this becomes the default failure mode rather than an
   edge case.
2. *"Existing one-sided wills."* There is now a concrete instance on the system:
   `will_documents.id 5` was created `will_type = "simple"` for a married user and was
   later converted in place to `will_type = "mirror"` when I re-entered the builder and
   changed the type — the same row, not a new one. So conversion of an existing
   one-sided will is at least mechanically possible without data loss. Whether that is
   the desired migration is CSJ's call.

---

## Noticed

- The mirror generator also nulls `funeral_wishes_notes` and
  `digital_assets_instructions` for the spouse (`WillDocumentService.php:314, :317`)
  while copying `funeral_preference`. Defensible — those are personal — but it means
  the spouse's will can carry a bare "cremation" with the reasoning silently dropped.
  Not raised separately; noted in W-0024's acceptance discussion.
- `swapResiduaryForMirror` is the proof that the right pattern exists in this file.
  Executors need the same treatment, not a new mechanism.
